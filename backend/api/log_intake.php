<?php
session_start();
header('Content-Type: application/json');

include_once '../config/config.php';
require_once 'adherence_utils.php';

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

$patient_id = $data->patient_id ?? ($_SESSION['user_id'] ?? '');
$log_id = $data->log_id ?? null;
$medicine_id = $data->medicine_id ?? null;
$status = $data->status ?? ''; // 'Taken' or 'Skipped'

if (empty($patient_id) || (empty($medicine_id) && empty($log_id)) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

if (!in_array($status, ['Taken', 'Skipped'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

$taken_at = null;
if ($status === 'Taken') {
    $taken_at = date('Y-m-d H:i:s');
}

try {
    $today = date('Y-m-d');

    $pdo->beginTransaction();

    if ($log_id) {
        $checkStmt = $pdo->prepare("SELECT id, medicine_id, status FROM intake_logs WHERE id = ? AND patient_id = ? LIMIT 1");
        $checkStmt->execute([$log_id, $patient_id]);
    } else {
        $checkStmt = $pdo->prepare("SELECT id, medicine_id, status FROM intake_logs WHERE patient_id = ? AND medicine_id = ? AND DATE(scheduled_time) = ? ORDER BY CASE WHEN status = 'Pending' THEN 0 ELSE 1 END, scheduled_time ASC LIMIT 1");
        $checkStmt->execute([$patient_id, $medicine_id, $today]);
    }
    $log = $checkStmt->fetch();
    $previousStatus = $log['status'] ?? null;
    $resolvedMedicineId = (int) ($log['medicine_id'] ?? $medicine_id);

    if (!$resolvedMedicineId) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Scheduled dose not found. Please refresh and try again.']);
        exit;
    }

    if ($previousStatus === 'Taken' && $status === 'Taken') {
        $medicineStmt = $pdo->prepare("SELECT name FROM medicines WHERE id = ? AND patient_id = ?");
        $medicineStmt->execute([$resolvedMedicineId, $patient_id]);
        $medicine = $medicineStmt->fetch();

        medtracker_log_overuse_event(
            $pdo,
            $patient_id,
            $resolvedMedicineId,
            $medicine['name'] ?? 'Medicine'
        );

        $pdo->commit();

        echo json_encode([
            'success' => false,
            'overuse_alert' => true,
            'message' => 'This dose was already logged today. Possible overuse attempt recorded.'
        ]);
        exit;
    }

    if ($log) {
        $stmt = $pdo->prepare("UPDATE intake_logs SET status = ?, taken_at = ? WHERE id = ?");
        $stmt->execute([$status, $taken_at, $log['id']]);
    } else {
        // If no pending schedule found, insert a new one immediately for tracking
        $stmt = $pdo->prepare("INSERT INTO intake_logs (patient_id, medicine_id, scheduled_time, status, taken_at) VALUES (?, ?, NOW(), ?, ?)");
        $stmt->execute([$patient_id, $resolvedMedicineId, $status, $taken_at]);
    }

    if ($status === 'Taken' && $previousStatus !== 'Taken') {
        $stockStmt = $pdo->prepare("UPDATE medicines SET quantity = GREATEST(quantity - 1, 0) WHERE id = ? AND patient_id = ?");
        $stockStmt->execute([$resolvedMedicineId, $patient_id]);
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => "Medication marked as $status."]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
