<?php
session_start();
header('Content-Type: application/json');

include_once '../config/config.php';

// Accept POST json
$data = json_decode(file_get_contents("php://input"));

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data provided.']);
    exit;
}

// In a real app we would get prescriber_id from the session token.
// For now, we will assume passing it or leaving it null to represent the doctor tracking it.
$prescriber_id = isset($data->prescriber_id) ? $data->prescriber_id : null; 
$patient_id = $data->patient_id ?? '';
$name = $data->name ?? '';
$dosage = $data->dosage ?? '';
$type = $data->type ?? '';
$quantity = $data->quantity ?? 0;
$frequency = $data->frequency ?? '';
$instructions = $data->instructions ?? '';

if (empty($patient_id) || empty($name) || empty($dosage) || empty($frequency)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO medicines (patient_id, prescriber_id, name, dosage, type, quantity, frequency, instructions) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $patient_id,
        $prescriber_id,
        $name,
        $dosage,
        $type,
        $quantity,
        $frequency,
        $instructions
    ]);

    echo json_encode(['success' => true, 'message' => 'Prescription scheduled successfully!']);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
        echo json_encode(['success' => false, 'message' => 'Invalid Patient ID. User might not exist.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
