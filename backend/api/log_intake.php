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

$patient_id = $data->patient_id ?? '';
$medicine_id = $data->medicine_id ?? null;
$status = $data->status ?? ''; // 'Taken' or 'Skipped'

if (empty($patient_id) || empty($medicine_id) || empty($status)) {
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
    // In a real application, you might update an existing scheduled log.
    // For this prototype, if there's an existing 'Pending' log for today, update it, otherwise insert as new.
    $today = date('Y-m-d');
    
    // Check for pending log today
    $checkStmt = $pdo->prepare("SELECT id FROM intake_logs WHERE patient_id = ? AND medicine_id = ? AND DATE(scheduled_time) = ? ORDER BY scheduled_time ASC LIMIT 1");
    $checkStmt->execute([$patient_id, $medicine_id, $today]);
    $log = $checkStmt->fetch();

    if ($log) {
        $stmt = $pdo->prepare("UPDATE intake_logs SET status = ?, taken_at = ? WHERE id = ?");
        $stmt->execute([$status, $taken_at, $log['id']]);
    } else {
        // If no pending schedule found, insert a new one immediately for tracking
        $stmt = $pdo->prepare("INSERT INTO intake_logs (patient_id, medicine_id, scheduled_time, status, taken_at) VALUES (?, ?, NOW(), ?, ?)");
        $stmt->execute([$patient_id, $medicine_id, $status, $taken_at]);
    }

    echo json_encode(['success' => true, 'message' => "Medication marked as $status."]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
