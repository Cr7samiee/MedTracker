<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';

$role = strtolower(trim($_SESSION['role'] ?? ''));

if ($role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin session not found. Please log in again.']);
    exit;
}

try {
    $stmt = $pdo->query(
        "SELECT id, role, name, phone, email, post, created_at
         FROM users
         ORDER BY created_at DESC"
    );

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
