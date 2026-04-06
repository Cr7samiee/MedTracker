<?php
session_start();
header('Content-Type: application/json');

include_once '../config/config.php';
require_once 'schedule_utils.php';
require_once 'assignment_utils.php';
require_once 'notification_utils.php';

// Accept POST json or form data
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput);

if (!$data && !empty($_POST)) {
    $data = (object) $_POST;
}

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data provided.']);
    exit;
}

$sessionRole = medtracker_normalize_role($_SESSION['role'] ?? null);
$sessionUserId = $_SESSION['user_id'] ?? null;

$patient_id = $data->patient_id ?? '';
$isPatientSelfAdd = $sessionRole === 'User';

$patient_id = $isPatientSelfAdd ? $sessionUserId : $patient_id;
$prescriber_id = isset($data->prescriber_id) && $data->prescriber_id !== ''
    ? $data->prescriber_id
    : (($sessionRole === 'Health Worker') ? $sessionUserId : null);
$name = $data->name ?? '';
$dosage = $data->dosage ?? '';
$type = $data->type ?? '';
$quantity = intval($data->quantity ?? 0);
$frequency = $data->frequency ?? '';
$instructions = $data->instructions ?? '';

if (!$sessionUserId) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

if (empty($patient_id) || empty($name) || empty($dosage) || empty($frequency)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

try {
    medtracker_ensure_schedule_schema($pdo);
    medtracker_ensure_assignment_schema($pdo);
    medtracker_ensure_notification_schema($pdo);

    $startDate = $data->start_date ?? date('Y-m-d');
    $startDateObject = date_create($startDate) ?: date_create(date('Y-m-d'));
    $startDate = $startDateObject->format('Y-m-d');
    $durationDays = max(1, min((int) ($data->duration_days ?? 7), 365));
    $endDate = medtracker_calculate_end_date($startDate, $durationDays);

    $pdo->beginTransaction();

    if ($sessionRole === 'Health Worker') {
        $assignmentList = medtracker_get_assigned_workers($pdo, $patient_id);
        $currentWorkerLinked = medtracker_worker_can_access_patient($pdo, $sessionUserId, $patient_id);

        if ($assignmentList && !$currentWorkerLinked) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'This patient has not linked your doctor code yet. Ask the patient to add your code in Settings first.']);
            exit;
        }

        if (!$assignmentList) {
            $assignmentResult = medtracker_assign_patient_to_worker($pdo, $patient_id, $sessionUserId);
            if (!$assignmentResult['success']) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $assignmentResult['message']]);
                exit;
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO medicines (patient_id, prescriber_id, name, dosage, type, quantity, frequency, start_date, duration_days, end_date, instructions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $patient_id,
        $prescriber_id,
        $name,
        $dosage,
        $type,
        $quantity,
        $frequency,
        $startDate,
        $durationDays,
        $endDate,
        $instructions
    ]);

    $medicineId = intval($pdo->lastInsertId());
    medtracker_create_schedule_logs($pdo, [
        'id' => $medicineId,
        'patient_id' => $patient_id,
        'frequency' => $frequency,
        'start_date' => $startDate,
        'duration_days' => $durationDays,
        'end_date' => $endDate,
    ]);

    $pdo->commit();

    $notificationSummary = null;
    if ($sessionRole === 'Health Worker') {
        $patient = medtracker_fetch_user_contact($pdo, $patient_id);
        $prescriber = medtracker_fetch_user_contact($pdo, $sessionUserId);

        if ($patient) {
            $eventKey = 'NEW_PRESCRIPTION|' . $medicineId . '|' . $patient['id'];
            $subject = 'MedTracker: new medicine schedule assigned';
            $emailHtml = sprintf(
                'Hello %s,<br><br>%s has assigned a new medicine schedule for <b>%s</b> (%s).<br><br>Frequency: <b>%s</b><br>Treatment window: <b>%s to %s</b><br>Instructions: %s<br><br>You will also receive reminder alerts 30 minutes before the dose and a missed reminder if the dose stays pending for 30 minutes after the schedule.',
                htmlspecialchars($patient['name'] ?? 'User', ENT_QUOTES),
                htmlspecialchars($prescriber['name'] ?? 'Your health worker', ENT_QUOTES),
                htmlspecialchars($name, ENT_QUOTES),
                htmlspecialchars($dosage, ENT_QUOTES),
                htmlspecialchars($frequency, ENT_QUOTES),
                htmlspecialchars($startDate, ENT_QUOTES),
                htmlspecialchars($endDate, ENT_QUOTES),
                htmlspecialchars($instructions ?: 'Follow the prescribed guidance.', ENT_QUOTES)
            );
            $emailText = sprintf(
                'Hello %s, %s assigned a new medicine schedule for %s (%s). Frequency: %s. Treatment window: %s to %s. Instructions: %s. You will also receive a reminder 30 minutes before the dose and a missed reminder if it remains pending.',
                $patient['name'] ?? 'User',
                $prescriber['name'] ?? 'Your health worker',
                $name,
                $dosage,
                $frequency,
                $startDate,
                $endDate,
                $instructions ?: 'Follow the prescribed guidance.'
            );
            $smsBody = sprintf(
                'MedTracker: %s (%s) has been scheduled for you by %s. Starts %s. Frequency: %s.',
                $name,
                $dosage,
                $prescriber['name'] ?? 'your health worker',
                $startDate,
                $frequency
            );

            $notificationResults = medtracker_send_user_notifications($pdo, $patient, [
                'channels' => ['email', 'sms'],
                'notification_type' => 'NEW_PRESCRIPTION',
                'event_key' => $eventKey,
                'medicine_id' => $medicineId,
                'email_subject' => $subject,
                'email_html' => $emailHtml,
                'email_text' => $emailText,
                'sms_body' => $smsBody,
            ]);
            $notificationSummary = medtracker_build_notification_summary($notificationResults);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $isPatientSelfAdd ? 'Medicine added successfully!' : 'Prescription scheduled successfully!',
        'medicine_id' => $medicineId,
        'duration_days' => $durationDays,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'notification_summary' => $notificationSummary,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
        echo json_encode(['success' => false, 'message' => 'Invalid Patient ID. User might not exist.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
