<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'worker_prescription_utils.php';
require_once 'audit_utils.php';
require_once 'notification_utils.php';

$sessionUserId = $_SESSION['user_id'] ?? '';
$sessionRole = medtracker_normalize_role($_SESSION['role'] ?? '');

if (!$sessionUserId || $sessionRole !== 'Health Worker') {
    echo json_encode(['success' => false, 'message' => 'Health worker session not found. Please log in again.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput);
if (!$data && !empty($_POST)) {
    $data = (object) $_POST;
}

$medicineId = (int) ($data->medicine_id ?? 0);
$action = strtolower(trim((string) ($data->action ?? '')));
$reason = trim((string) ($data->reason ?? ''));

if ($medicineId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Choose a valid prescription first.']);
    exit;
}

if (!in_array($action, ['pause', 'resume', 'stop'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid prescription action.']);
    exit;
}

try {
    medtracker_ensure_schedule_schema($pdo);
    medtracker_ensure_assignment_schema($pdo);
    medtracker_ensure_audit_schema($pdo);
    medtracker_ensure_notification_schema($pdo);

    $medicine = medtracker_worker_fetch_prescription($pdo, $medicineId, $sessionUserId);
    if (!$medicine) {
        echo json_encode(['success' => false, 'message' => 'Prescription not found or access denied.']);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $pdo->beginTransaction();

    if ($action === 'pause') {
        $updateStmt = $pdo->prepare(
            "UPDATE medicines
             SET prescription_status = 'paused',
                 paused_at = ?,
                 stopped_at = NULL
             WHERE id = ?
               AND prescriber_id = ?"
        );
        $updateStmt->execute([$now, $medicineId, $sessionUserId]);
        $affectedLogs = medtracker_delete_pending_logs_from($pdo, $medicineId, $now);
        $message = 'Prescription paused successfully.';
        $patientNoticeSubject = 'MedTracker: your medicine has been paused';
        $patientNoticeHtml = sprintf(
            'Your doctor has paused <b>%s</b> (%s).<br><br>Reason: %s<br><br>You will not receive new reminders for this medicine until the doctor resumes it.',
            htmlspecialchars((string) ($medicine['name'] ?? 'Medicine'), ENT_QUOTES),
            htmlspecialchars((string) ($medicine['dosage'] ?? ''), ENT_QUOTES),
            htmlspecialchars($reason !== '' ? $reason : 'Paused by health worker', ENT_QUOTES)
        );
        $patientNoticeText = sprintf(
            'Your doctor has paused %s (%s). Reason: %s. You will not receive new reminders for this medicine until the doctor resumes it.',
            (string) ($medicine['name'] ?? 'Medicine'),
            (string) ($medicine['dosage'] ?? ''),
            $reason !== '' ? $reason : 'Paused by health worker'
        );
        $patientNoticeSms = sprintf(
            'MedTracker: %s has been paused by your doctor. Reason: %s.',
            (string) ($medicine['name'] ?? 'Medicine'),
            $reason !== '' ? $reason : 'Paused by health worker'
        );
    } elseif ($action === 'resume') {
        $updateStmt = $pdo->prepare(
            "UPDATE medicines
             SET prescription_status = 'active',
                 paused_at = NULL,
                 stopped_at = NULL,
                 stop_reason = NULL
             WHERE id = ?
               AND prescriber_id = ?"
        );
        $updateStmt->execute([$medicineId, $sessionUserId]);
        $medicine['prescription_status'] = 'active';
        $medicine['paused_at'] = null;
        $medicine['stopped_at'] = null;
        $medicine['stop_reason'] = null;
        $affectedLogs = medtracker_resync_single_medicine_schedule($pdo, $medicine);
        $message = 'Prescription resumed successfully.';
        $patientNoticeSubject = 'MedTracker: your medicine has been resumed';
        $patientNoticeHtml = sprintf(
            'Your doctor has resumed <b>%s</b> (%s).<br><br>The reminder schedule is active again. Please open MedTracker to review the next dose time.',
            htmlspecialchars((string) ($medicine['name'] ?? 'Medicine'), ENT_QUOTES),
            htmlspecialchars((string) ($medicine['dosage'] ?? ''), ENT_QUOTES)
        );
        $patientNoticeText = sprintf(
            'Your doctor has resumed %s (%s). The reminder schedule is active again. Please open MedTracker to review the next dose time.',
            (string) ($medicine['name'] ?? 'Medicine'),
            (string) ($medicine['dosage'] ?? '')
        );
        $patientNoticeSms = sprintf(
            'MedTracker: %s has been resumed by your doctor. Check the app for the updated next dose time.',
            (string) ($medicine['name'] ?? 'Medicine')
        );
    } else {
        $resolvedReason = $reason !== '' ? $reason : 'Stopped by health worker';
        $updateStmt = $pdo->prepare(
            "UPDATE medicines
             SET prescription_status = 'stopped',
                 paused_at = NULL,
                 stopped_at = ?,
                 stop_reason = ?
             WHERE id = ?
               AND prescriber_id = ?"
        );
        $updateStmt->execute([$now, $resolvedReason, $medicineId, $sessionUserId]);
        $affectedLogs = medtracker_delete_pending_logs_from($pdo, $medicineId, $now);
        $message = 'Prescription stopped successfully.';
        $patientNoticeSubject = 'MedTracker: your doctor stopped a medicine';
        $patientNoticeHtml = sprintf(
            'Your doctor has stopped <b>%s</b> (%s).<br><br>Reason: %s<br><br>You should no longer take this medicine from the current MedTracker plan unless your doctor tells you otherwise.',
            htmlspecialchars((string) ($medicine['name'] ?? 'Medicine'), ENT_QUOTES),
            htmlspecialchars((string) ($medicine['dosage'] ?? ''), ENT_QUOTES),
            htmlspecialchars($resolvedReason, ENT_QUOTES)
        );
        $patientNoticeText = sprintf(
            'Your doctor has stopped %s (%s). Reason: %s. You should no longer take this medicine from the current MedTracker plan unless your doctor tells you otherwise.',
            (string) ($medicine['name'] ?? 'Medicine'),
            (string) ($medicine['dosage'] ?? ''),
            $resolvedReason
        );
        $patientNoticeSms = sprintf(
            'MedTracker: your doctor stopped %s. Reason: %s.',
            (string) ($medicine['name'] ?? 'Medicine'),
            $resolvedReason
        );
    }

    medtracker_log_audit_event(
        $pdo,
        $sessionUserId,
        $sessionRole,
        'prescription_' . $action,
        'medicine',
        (string) $medicineId,
        $medicine['patient_id'] ?? null,
        [
            'medicine_name' => $medicine['name'] ?? '',
            'reason' => $reason,
            'affected_logs' => $affectedLogs,
        ]
    );

    $patient = medtracker_fetch_user_contact($pdo, (string) ($medicine['patient_id'] ?? ''));
    if ($patient) {
        $notificationResults = medtracker_send_user_notifications($pdo, $patient, [
            'channels' => ['email', 'sms'],
            'notification_type' => 'PRESCRIPTION_' . strtoupper($action),
            'event_key' => sprintf('PRESCRIPTION_%s|%d|%s', strtoupper($action), $medicineId, date('YmdHis', strtotime($now))),
            'medicine_id' => $medicineId,
            'email_subject' => $patientNoticeSubject,
            'email_html' => $patientNoticeHtml,
            'email_text' => $patientNoticeText,
            'sms_body' => $patientNoticeSms,
        ]);

        medtracker_log_audit_event(
            $pdo,
            $sessionUserId,
            $sessionRole,
            'prescription_' . $action . '_notification',
            'medicine',
            (string) $medicineId,
            $medicine['patient_id'] ?? null,
            [
                'channels' => medtracker_build_notification_summary($notificationResults),
            ]
        );
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $message,
        'affected_logs' => $affectedLogs,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode(['success' => false, 'message' => $e instanceof PDOException ? 'Database error: ' . $e->getMessage() : $e->getMessage()]);
}
?>
