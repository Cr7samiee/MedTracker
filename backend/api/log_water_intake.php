<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'water_utils.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput);

if (!$data && !empty($_POST)) {
    $data = (object) $_POST;
}

$patientId = $_SESSION['user_id'] ?? null;
$role = strtolower(trim($_SESSION['role'] ?? ''));
$amountMl = (int) ($data->amount_ml ?? 0);
$mode = strtolower(trim((string) ($data->mode ?? 'add')));

if (!$patientId || $role !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Patient session not found. Please log in again.']);
    exit;
}

if ($amountMl <= 0 && $mode !== 'reset') {
    echo json_encode(['success' => false, 'message' => 'Water amount must be greater than zero.']);
    exit;
}

try {
    medtracker_ensure_water_schema($pdo);

    $today = date('Y-m-d');
    $goalMl = medtracker_water_goal_ml();

    $stmt = $pdo->prepare(
        "INSERT INTO water_logs (patient_id, intake_date, intake_ml)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
            intake_ml = CASE
                WHEN ? = 'set' THEN GREATEST(VALUES(intake_ml), 0)
                WHEN ? = 'reset' THEN 0
                ELSE GREATEST(intake_ml + VALUES(intake_ml), 0)
            END"
    );
    $stmt->execute([$patientId, $today, $amountMl, $mode, $mode]);

    $fetchStmt = $pdo->prepare(
        "SELECT intake_ml FROM water_logs WHERE patient_id = ? AND intake_date = ? LIMIT 1"
    );
    $fetchStmt->execute([$patientId, $today]);
    $currentMl = (int) (($fetchStmt->fetch()['intake_ml'] ?? 0));

    echo json_encode([
        'success' => true,
        'message' => 'Water intake updated.',
        'today_ml' => $currentMl,
        'goal_ml' => $goalMl,
        'progress_percent' => min(100, (int) round(($currentMl / max($goalMl, 1)) * 100)),
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

