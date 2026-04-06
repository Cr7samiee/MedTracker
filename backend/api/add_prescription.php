<?php
session_start();
header('Content-Type: application/json');

include_once '../config/config.php';
require_once 'schedule_utils.php';

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

    $startDate = $data->start_date ?? date('Y-m-d');
    $startDateObject = date_create($startDate) ?: date_create(date('Y-m-d'));
    $startDate = $startDateObject->format('Y-m-d');
    $durationDays = max(1, min((int) ($data->duration_days ?? 7), 365));
    $endDate = medtracker_calculate_end_date($startDate, $durationDays);

    $pdo->beginTransaction();

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

    echo json_encode([
        'success' => true,
        'message' => $isPatientSelfAdd ? 'Medicine added successfully!' : 'Prescription scheduled successfully!',
        'medicine_id' => $medicineId,
        'duration_days' => $durationDays,
        'start_date' => $startDate,
        'end_date' => $endDate,
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
