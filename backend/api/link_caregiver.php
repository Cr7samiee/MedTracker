<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'assignment_utils.php';

$patientId = $_SESSION['user_id'] ?? '';
$role = strtolower(trim($_SESSION['role'] ?? ''));

if (!$patientId || $role !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Patient session not found. Please log in again.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput);

if (!$data && !empty($_POST)) {
    $data = (object) $_POST;
}

$workerCode = strtoupper(trim((string) ($data->worker_code ?? '')));
if ($workerCode === '') {
    echo json_encode(['success' => false, 'message' => 'Enter a valid doctor code first.']);
    exit;
}

try {
    medtracker_ensure_assignment_schema($pdo);

    $worker = medtracker_find_worker_by_code($pdo, $workerCode);
    if (!$worker) {
        echo json_encode(['success' => false, 'message' => 'Doctor code not found. Please check it and try again.']);
        exit;
    }

    $result = medtracker_assign_patient_to_worker($pdo, $patientId, $worker['id'], true);
    if (!$result['success']) {
        echo json_encode(['success' => false, 'message' => $result['message']]);
        exit;
    }

    $message = !empty($result['already_linked'])
        ? (($result['worker']['name'] ?? 'This health worker') . ' is already linked to your account.')
        : ('You added ' . ($result['worker']['name'] ?? 'a health worker') . ' to your care team.');

    echo json_encode([
        'success' => true,
        'message' => $message,
        'assigned_worker' => $result['worker'],
        'assigned_workers' => medtracker_get_assigned_workers($pdo, $patientId),
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
