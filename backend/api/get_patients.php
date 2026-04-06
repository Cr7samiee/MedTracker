<?php
header('Content-Type: application/json');
require_once '../config/config.php';

try {
    // Fetch all users with role 'User'
    $stmt = $pdo->query("SELECT id, name, phone FROM users WHERE role = 'User' ORDER BY name ASC");
    $patients = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $patients]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
