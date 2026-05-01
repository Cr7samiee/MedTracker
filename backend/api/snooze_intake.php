<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'schedule_utils.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput);

if (!$data && !empty($_POST)) {
    $data = (object) $_POST;
}

$patientId = $_SESSION['user_id'] ?? '';
$role = medtracker_normalize_role($_SESSION['role'] ?? '');
$logId = (int) ($data->log_id ?? 0);
$minutes = max(1, min((int) ($data->minutes ?? 10), 120));

if (!$patientId || $role !== 'User') {
    echo json_encode(['success' => false, 'message' => 'Patient session not found. Please log in again.']);
    exit;
}

if ($logId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Choose a valid dose to snooze.']);
    exit;
}

try {
    medtracker_ensure_schedule_schema($pdo);

    $checkStmt = $pdo->prepare(
        "SELECT id, scheduled_time, status
         FROM intake_logs
         WHERE id = ?
           AND patient_id = ?
         LIMIT 1"
    );
    $checkStmt->execute([$logId, $patientId]);
    $log = $checkStmt->fetch();

    if (!$log) {
        echo json_encode(['success' => false, 'message' => 'Dose not found. Please refresh and try again.']);
        exit;
    }

    if (($log['status'] ?? '') !== 'Pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending doses can be snoozed.']);
        exit;
    }

    $snoozeUntil = date('Y-m-d H:i:s', strtotime('+' . $minutes . ' minutes'));
    $updateStmt = $pdo->prepare(
        "UPDATE intake_logs
         SET snooze_until = ?,
             snooze_count = snooze_count + 1
         WHERE id = ?
           AND patient_id = ?
           AND status = 'Pending'"
    );
    $updateStmt->execute([$snoozeUntil, $logId, $patientId]);

    echo json_encode([
        'success' => $updateStmt->rowCount() > 0,
        'message' => $updateStmt->rowCount() > 0 ? 'Dose snoozed successfully.' : 'Dose could not be snoozed.',
        'snooze_until' => $snoozeUntil,
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e instanceof PDOException ? 'Database error: ' . $e->getMessage() : $e->getMessage()]);
}
?>

