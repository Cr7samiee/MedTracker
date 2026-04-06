<?php
session_start();
header('Content-Type: application/json');
require_once '../config/config.php';
require_once 'assignment_utils.php';

try {
    medtracker_ensure_assignment_schema($pdo);

    $role = strtolower(trim($_SESSION['role'] ?? ''));
    $workerId = $_SESSION['user_id'] ?? '';

    if ($role !== 'health worker' || !$workerId) {
        echo json_encode(['success' => false, 'message' => 'Health worker session not found. Please log in again.']);
        exit;
    }

    $patients = medtracker_fetch_assigned_patients($pdo, $workerId);
    
    echo json_encode(['success' => true, 'data' => $patients]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
