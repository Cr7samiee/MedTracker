<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';

$role = strtolower(trim($_SESSION['role'] ?? ''));
$adminId = $_SESSION['user_id'] ?? '';

if ($role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin session not found. Please log in again.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"));
$userId = $data->user_id ?? '';

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID is required.']);
    exit;
}

if ($userId === $adminId) {
    echo json_encode(['success' => false, 'message' => 'You cannot remove your own admin account.']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'User removed successfully.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
