<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'assignment_utils.php';

try {
    $userId = $_SESSION['user_id'] ?? '';
    $role = $_SESSION['role'] ?? '';

    if (!$userId || !$role) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }

    medtracker_ensure_assignment_schema($pdo);

    $stmt = $pdo->prepare(
        "SELECT id, role, name, phone, email, post, relation, worker_code
         FROM users
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User record not found.']);
        exit;
    }

    $assignedWorker = null;
    $assignedWorkers = [];
    $assignedPatients = [];
    if ($user['role'] === 'User') {
        $assignedWorkers = medtracker_get_assigned_workers($pdo, $userId);
        $assignedWorker = $assignedWorkers[0] ?? null;
    } elseif ($user['role'] === 'Health Worker') {
        $assignedPatients = medtracker_fetch_assigned_patients($pdo, $userId);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'user' => $user,
            'assigned_worker' => $assignedWorker,
            'assigned_workers' => $assignedWorkers,
            'assigned_patients' => $assignedPatients,
        ],
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
