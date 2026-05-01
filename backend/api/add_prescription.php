<?php
session_start();
header('Content-Type: application/json');

include_once '../config/config.php';
require_once 'schedule_utils.php';
require_once 'assignment_utils.php';
require_once 'notification_utils.php';
require_once 'audit_utils.php';

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
$type = trim((string) ($data->type ?? 'Oral Tablet'));
$quantity = medtracker_uses_stock_tracking($type)
    ? max(0, (int) ($data->quantity ?? 0))
    : null;
$frequency = $data->frequency ?? '';
$instructions = $data->instructions ?? '';
$customTimes = is_array($data->custom_times ?? null) ? $data->custom_times : [];

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
    medtracker_ensure_audit_schema($pdo);

    $treatmentMode = medtracker_normalize_treatment_mode($data->treatment_mode ?? null);
    $startDate = $data->start_date ?? date('Y-m-d');
    $startDateObject = date_create($startDate) ?: date_create(date('Y-m-d'));
    $startDate = $startDateObject->format('Y-m-d');
    $expectedDoseCount = medtracker_dose_count_from_frequency($frequency);
    $normalizedCustomTimes = $isPatientSelfAdd
        ? medtracker_parse_custom_times($customTimes, $expectedDoseCount)
        : [];
    $normalizedDoctorSlots = $sessionRole === 'Health Worker'
        ? medtracker_parse_custom_times($customTimes, $expectedDoseCount)
        : [];
    if ($isPatientSelfAdd && count($normalizedCustomTimes) !== $expectedDoseCount) {
        throw new RuntimeException(
            'Please provide exactly ' . $expectedDoseCount . ' reminder time' . ($expectedDoseCount === 1 ? '' : 's') . ' for this medicine.'
        );
    }
    if ($sessionRole === 'Health Worker' && count($normalizedDoctorSlots) !== $expectedDoseCount) {
        throw new RuntimeException('Choose exactly ' . $expectedDoseCount . ' doctor dose window' . ($expectedDoseCount === 1 ? '' : 's') . ' before saving this prescription.');
    }
    $customTimesJson = medtracker_encode_custom_times($normalizedCustomTimes);
    $doctorSlotsJson = medtracker_encode_custom_times($normalizedDoctorSlots);
    $customTimesEffectiveAt = $customTimesJson ? date('Y-m-d H:i:s') : null;
    $durationDays = $treatmentMode === 'ongoing'
        ? null
        : max(1, min((int) ($data->duration_days ?? 7), 365));
    $endDate = $treatmentMode === 'ongoing'
        ? null
        : medtracker_calculate_end_date($startDate, (int) $durationDays);
    $doctorFacingTimes = $sessionRole === 'Health Worker' ? $normalizedDoctorSlots : $normalizedCustomTimes;
    $timeWindowCopy = $doctorFacingTimes
        ? 'Doctor-selected dose windows: ' . implode(', ', array_map(
            static fn($time) => date('g:i A', strtotime($time)),
            $doctorFacingTimes
        )) . '.'
        : ($sessionRole === 'Health Worker'
            ? 'Open MedTracker to set your own reminder times for this medicine based on your real meal routine. No reminder time will run until the patient sets those times.'
            : 'Reminder times are set by the patient inside MedTracker.');
    $courseCopy = $treatmentMode === 'ongoing'
        ? 'This medicine is marked as an ongoing chronic care plan and will continue until the doctor changes or stops it.'
        : 'Treatment window runs from ' . $startDate . ' to ' . $endDate . '.';
    $reminderRuleCopy = $timeWindowCopy . ' Reminder alerts go out 5 minutes before each dose, another alert goes out at the exact medicine time, and a missed dose is auto-updated if it stays pending for 15 minutes after the scheduled time. The patient can still log it late afterward if they forgot to mark it. ' . $courseCopy;

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

    $stmt = $pdo->prepare("INSERT INTO medicines (patient_id, prescriber_id, name, dosage, type, quantity, frequency, custom_times_json, custom_times_effective_at, doctor_slots_json, treatment_mode, start_date, duration_days, end_date, instructions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $patient_id,
        $prescriber_id,
        $name,
        $dosage,
        $type,
        $quantity,
        $frequency,
        $customTimesJson,
        $customTimesEffectiveAt,
        $doctorSlotsJson,
        $treatmentMode,
        $startDate,
        $durationDays,
        $endDate,
        $instructions
    ]);

    $medicineId = intval($pdo->lastInsertId());
    medtracker_create_schedule_logs($pdo, [
        'id' => $medicineId,
        'patient_id' => $patient_id,
        'prescriber_id' => $prescriber_id,
        'frequency' => $frequency,
        'custom_times_json' => $customTimesJson,
        'custom_times_effective_at' => $customTimesEffectiveAt,
        'doctor_slots_json' => $doctorSlotsJson,
        'treatment_mode' => $treatmentMode,
        'start_date' => $startDate,
        'duration_days' => $durationDays ?? 1,
        'end_date' => $endDate,
    ]);

    medtracker_log_audit_event(
        $pdo,
        $sessionUserId,
        $sessionRole,
        $isPatientSelfAdd ? 'patient_add_medicine' : 'worker_add_prescription',
        'medicine',
        (string) $medicineId,
        $patient_id,
        [
            'medicine_name' => $name,
            'dosage' => $dosage,
            'frequency' => $frequency,
            'treatment_mode' => $treatmentMode,
        ]
    );

    $pdo->commit();

    $notificationSummary = null;
    if ($sessionRole === 'Health Worker') {
        $patient = medtracker_fetch_user_contact($pdo, $patient_id);
        $prescriber = medtracker_fetch_user_contact($pdo, $sessionUserId);

        if ($patient) {
            $eventKey = 'NEW_PRESCRIPTION|' . $medicineId . '|' . $patient['id'];
            $subject = 'MedTracker: new medicine schedule assigned';
            $emailHtml = sprintf(
                'Hello %s,<br><br>%s has assigned a new medicine schedule for <b>%s</b> (%s).<br><br>Frequency: <b>%s</b><br>Plan type: <b>%s</b><br>Treatment window: <b>%s</b><br>Instructions: %s<br><br>%s',
                htmlspecialchars($patient['name'] ?? 'User', ENT_QUOTES),
                htmlspecialchars($prescriber['name'] ?? 'Your health worker', ENT_QUOTES),
                htmlspecialchars($name, ENT_QUOTES),
                htmlspecialchars($dosage, ENT_QUOTES),
                htmlspecialchars($frequency, ENT_QUOTES),
                htmlspecialchars($treatmentMode === 'ongoing' ? 'Ongoing chronic medicine' : 'Fixed course', ENT_QUOTES),
                htmlspecialchars($treatmentMode === 'ongoing' ? ('Starts ' . $startDate . ' and continues until updated') : ($startDate . ' to ' . $endDate), ENT_QUOTES),
                htmlspecialchars($instructions ?: 'Follow the prescribed guidance.', ENT_QUOTES),
                htmlspecialchars($reminderRuleCopy, ENT_QUOTES)
            );
            $emailText = sprintf(
                'Hello %s, %s assigned a new medicine schedule for %s (%s). Frequency: %s. Plan type: %s. Treatment window: %s. Instructions: %s. %s',
                $patient['name'] ?? 'User',
                $prescriber['name'] ?? 'Your health worker',
                $name,
                $dosage,
                $frequency,
                $treatmentMode === 'ongoing' ? 'Ongoing chronic medicine' : 'Fixed course',
                $treatmentMode === 'ongoing' ? ('Starts ' . $startDate . ' and continues until updated') : ($startDate . ' to ' . $endDate),
                $instructions ?: 'Follow the prescribed guidance.',
                $reminderRuleCopy
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
        'treatment_mode' => $treatmentMode,
        'custom_times' => $normalizedCustomTimes,
        'notification_summary' => $notificationSummary,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
        echo json_encode(['success' => false, 'message' => 'Invalid Patient ID. User might not exist.']);
    } elseif ($e instanceof PDOException) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } else {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
