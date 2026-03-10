<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $post = trim($_POST['post'] ?? '');
    $relation = trim($_POST['relation'] ?? '');

    // Basic validation
    if (empty($role) || empty($name) || empty($phone) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
        exit;
    }

    if (!in_array($role, ['Admin', 'Health Worker', 'User'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
        exit;
    }

    // Role specific validation
    if ($role === 'Health Worker' && empty($post)) {
        echo json_encode(['success' => false, 'message' => 'Post is required for Health Workers.']);
        exit;
    }
    if ($role === 'User' && empty($relation)) {
        echo json_encode(['success' => false, 'message' => 'Relation is required for Users.']);
        exit;
    }

    // Determine prefix and starting number
    $prefix = '';
    $startNumber = 101;
    if ($role === 'User') {
        $prefix = 'U';
    } elseif ($role === 'Admin') {
        $prefix = 'A';
    } elseif ($role === 'Health Worker') {
        $prefix = 'H';
        $startNumber = 100;
    }

    try {
        // Fetch the last ID for this role to determine the next number
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ? ORDER BY CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC LIMIT 1");
        $stmt->execute([$role]);
        $lastIdRow = $stmt->fetch();

        if ($lastIdRow && preg_match('/^'.$prefix.'(\d+)$/', $lastIdRow['id'], $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = $startNumber;
        }

        $id = $prefix . $nextNumber;

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (id, role, name, phone, email, password_hash, plain_password, post, relation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, $role, $name, $phone, $email, $password_hash, $password, $post ?: null, $relation ?: null]);
        echo json_encode(['success' => true, 'message' => 'Signup successful!']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Integrity constraint violation (duplicate entry)
            echo json_encode(['success' => false, 'message' => 'Phone or Email already registered.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
