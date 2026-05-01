<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'schedule_utils.php';
require_once 'audit_utils.php';

$sessionUserId = $_SESSION['user_id'] ?? '';
$sessionRole = medtracker_normalize_role($_SESSION['role'] ?? '');

if (!$sessionUserId || $sessionRole !== 'User') {
    echo json_encode(['success' => false, 'message' => 'Patient session not found. Please log in again.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput);

if (!$data && !empty($_POST)) {
    $data = (object) $_POST;
}

$medicineId = (int) ($data->medicine_id ?? 0);
$newDosage = trim((string) ($data->dosage ?? ''));

if ($medicineId <= 0 || $newDosage === '') {
    echo json_encode(['success' => false, 'message' => 'Provide a valid medicine and dosage.']);
    exit;
}

try {
    medtracker_ensure_audit_schema($pdo);

    $lookupStmt = $pdo->prepare(
        "SELECT id, prescriber_id
         FROM medicines
         WHERE id = ? AND patient_id = ?
         LIMIT 1"
    );
    $lookupStmt->execute([$medicineId, $sessionUserId]);
    $medicine = $lookupStmt->fetch();

    if (!$medicine) {
        echo json_encode(['success' => false, 'message' => 'Medicine not found for this patient.']);
        exit;
    }

    if (!empty($medicine['prescriber_id'])) {
        echo json_encode(['success' => false, 'message' => 'Doctor-assigned prescriptions cannot be edited from the patient side.']);
        exit;
    }

    $updateStmt = $pdo->prepare("UPDATE medicines SET dosage = ? WHERE id = ? AND patient_id = ?");
    $updateStmt->execute([$newDosage, $medicineId, $sessionUserId]);

    medtracker_log_audit_event(
        $pdo,
        $sessionUserId,
        $sessionRole,
        'patient_update_dosage',
        'medicine',
        (string) $medicineId,
        $sessionUserId,
        ['dosage' => $newDosage]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Dosage updated successfully.',
        'dosage' => $newDosage,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
