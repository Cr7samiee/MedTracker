<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';

$role = strtolower(trim($_SESSION['role'] ?? ''));

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

try {
    $tempPassword = 'Med@' . random_int(1000, 9999);
    $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, plain_password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
    $stmt->execute([$hash, $tempPassword, $userId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully.',
        'temporary_password' => $tempPassword
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
