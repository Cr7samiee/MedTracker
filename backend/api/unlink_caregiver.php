<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'assignment_utils.php';
require_once 'schedule_utils.php';

try {
    medtracker_ensure_assignment_schema($pdo);

    $sessionUserId = $_SESSION['user_id'] ?? '';
    $sessionRole = medtracker_normalize_role($_SESSION['role'] ?? '');

    if (!$sessionUserId) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput);

    if (!$data && !empty($_POST)) {
        $data = (object) $_POST;
    }

    $patientId = trim((string) ($data->patient_id ?? ''));
    $workerId = trim((string) ($data->worker_id ?? ''));

    if ($sessionRole === 'User') {
        $patientId = $sessionUserId;
        if ($workerId === '') {
            echo json_encode(['success' => false, 'message' => 'Choose a linked doctor first.']);
            exit;
        }
    } elseif ($sessionRole === 'Health Worker') {
        $workerId = $sessionUserId;
        if ($patientId === '') {
            echo json_encode(['success' => false, 'message' => 'Choose a patient first.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Only patients and health workers can remove care links.']);
        exit;
    }

    if (!medtracker_worker_can_access_patient($pdo, $workerId, $patientId)) {
        echo json_encode(['success' => false, 'message' => 'That care link was already removed or never existed.']);
        exit;
    }

    medtracker_remove_patient_worker_link($pdo, $patientId, $workerId);

    echo json_encode([
        'success' => true,
        'message' => $sessionRole === 'User'
            ? 'Doctor disconnected from your care team.'
            : 'Patient released from your monitoring list.',
        'assigned_workers' => $sessionRole === 'User' ? medtracker_get_assigned_workers($pdo, $patientId) : [],
        'assigned_patients' => $sessionRole === 'Health Worker' ? medtracker_fetch_assigned_patients($pdo, $workerId) : [],
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
