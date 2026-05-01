<?php
session_start();
header('Content-Type: application/json');
include_once '../config/config.php';
require_once 'notification_utils.php';
require_once 'schedule_utils.php';
require_once 'audit_utils.php';

$data = json_decode(file_get_contents("php://input"));
$medicine_id = $data->medicine_id ?? null;

if (!$medicine_id) {
    echo json_encode(['success' => false, 'message' => 'No medicine_id provided.']);
    exit;
}

try {
    $role = medtracker_normalize_role($_SESSION['role'] ?? '');
    if ($role !== 'Admin') {
        echo json_encode(['success' => false, 'message' => 'Admin session not found. Please log in again.']);
        exit;
    }

    medtracker_ensure_notification_schema($pdo);

    $medicineStmt = $pdo->prepare(
        "SELECT m.id, m.name, m.dosage, m.patient_id, m.prescriber_id, u.name AS patient_name, u.email AS patient_email, p.name AS prescriber_name, p.email AS prescriber_email
         FROM medicines m
         LEFT JOIN users u ON u.id = m.patient_id
         LEFT JOIN users p ON p.id = m.prescriber_id
         WHERE m.id = ?
         LIMIT 1"
    );
    $medicineStmt->execute([$medicine_id]);
    $medicine = $medicineStmt->fetch();

    if (!$medicine) {
        echo json_encode(['success' => false, 'message' => 'Medicine not found.']);
        exit;
    }

    $adminUser = medtracker_fetch_user_contact($pdo, $_SESSION['user_id'] ?? '');

    // Delete from medicines (intake_logs should cascade if schema has ON DELETE CASCADE)
    $stmt = $pdo->prepare("DELETE FROM medicines WHERE id = ?");
    $stmt->execute([$medicine_id]);

    if ($stmt->rowCount() > 0) {
        $patientUser = $medicine['patient_email']
            ? ['id' => $medicine['patient_id'], 'name' => $medicine['patient_name'], 'email' => $medicine['patient_email'], 'phone' => null]
            : null;
        $prescriberUser = $medicine['prescriber_id'] && $medicine['prescriber_email']
            ? ['id' => $medicine['prescriber_id'], 'name' => $medicine['prescriber_name'], 'email' => $medicine['prescriber_email'], 'phone' => null]
            : null;

        if ($patientUser) {
            medtracker_send_user_notifications($pdo, $patientUser, [
                'channels' => ['email'],
                'notification_type' => 'ADMIN_MEDICINE_DELETE_PATIENT',
                'event_key' => 'ADMIN_MED_DELETE_PATIENT|' . $medicine_id . '|' . date('YmdHis'),
                'medicine_id' => (int) $medicine_id,
                'email_subject' => 'MedTracker prescription update',
                'email_html' => sprintf(
                    'Hello %s,<br><br>The medicine <b>%s</b> (%s) was removed from your MedTracker plan by an administrator.',
                    htmlspecialchars($patientUser['name'] ?? 'User', ENT_QUOTES),
                    htmlspecialchars($medicine['name'], ENT_QUOTES),
                    htmlspecialchars($medicine['dosage'], ENT_QUOTES)
                ),
                'email_text' => sprintf(
                    'Hello %s, the medicine %s (%s) was removed from your MedTracker plan by an administrator.',
                    $patientUser['name'] ?? 'User',
                    $medicine['name'],
                    $medicine['dosage']
                ),
            ]);
        }

        if ($prescriberUser) {
            medtracker_send_user_notifications($pdo, $prescriberUser, [
                'channels' => ['email'],
                'notification_type' => 'ADMIN_MEDICINE_DELETE_PRESCRIBER',
                'event_key' => 'ADMIN_MED_DELETE_PRESCRIBER|' . $medicine_id . '|' . date('YmdHis'),
                'medicine_id' => (int) $medicine_id,
                'email_subject' => 'MedTracker prescription removed by admin',
                'email_html' => sprintf(
                    'Hello %s,<br><br>The prescription <b>%s</b> (%s) for patient <b>%s</b> was removed by an administrator.',
                    htmlspecialchars($prescriberUser['name'] ?? 'Health Worker', ENT_QUOTES),
                    htmlspecialchars($medicine['name'], ENT_QUOTES),
                    htmlspecialchars($medicine['dosage'], ENT_QUOTES),
                    htmlspecialchars($medicine['patient_name'] ?? $medicine['patient_id'], ENT_QUOTES)
                ),
                'email_text' => sprintf(
                    'Hello %s, the prescription %s (%s) for patient %s was removed by an administrator.',
                    $prescriberUser['name'] ?? 'Health Worker',
                    $medicine['name'],
                    $medicine['dosage'],
                    $medicine['patient_name'] ?? $medicine['patient_id']
                ),
            ]);
        }

        if ($adminUser) {
            medtracker_send_user_notifications($pdo, $adminUser, [
                'channels' => ['email'],
                'notification_type' => 'ADMIN_MEDICINE_DELETE_AUDIT',
                'event_key' => 'ADMIN_MED_DELETE_AUDIT|' . $medicine_id . '|' . date('YmdHis'),
                'medicine_id' => (int) $medicine_id,
                'email_subject' => 'MedTracker admin prescription deletion confirmation',
                'email_html' => sprintf(
                    'Hello %s,<br><br>You removed the prescription <b>%s</b> (%s) from the system.',
                    htmlspecialchars($adminUser['name'] ?? 'Admin', ENT_QUOTES),
                    htmlspecialchars($medicine['name'], ENT_QUOTES),
                    htmlspecialchars($medicine['dosage'], ENT_QUOTES)
                ),
                'email_text' => sprintf(
                    'You removed the prescription %s (%s) from the system.',
                    $medicine['name'],
                    $medicine['dosage']
                ),
            ]);
        }

        medtracker_log_audit_event(
            $pdo,
            $_SESSION['user_id'] ?? null,
            $_SESSION['role'] ?? null,
            'admin_delete_medicine',
            'medicine',
            (string) $medicine_id,
            $medicine['patient_id'] ?? null,
            [
                'medicine_name' => $medicine['name'] ?? '',
                'dosage' => $medicine['dosage'] ?? '',
                'prescriber_id' => $medicine['prescriber_id'] ?? null,
            ]
        );

        echo json_encode(['success' => true, 'message' => 'Medicine deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Medicine not found.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
