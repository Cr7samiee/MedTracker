<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'consultation_utils.php';

try {
    $userId = (string) ($_SESSION['user_id'] ?? '');
    $role = trim((string) ($_SESSION['role'] ?? ''));
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $receiverId = trim((string) ($data['receiver_id'] ?? ''));
    $message = trim((string) ($data['message'] ?? ''));

    if ($userId === '' || $role === '') {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }

    medtracker_ensure_consultation_schema($pdo);

    if ($receiverId === '' || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Receiver and message are required.']);
        exit;
    }

    if (strlen($message) > 1000) {
        echo json_encode(['success' => false, 'message' => 'Message is too long.']);
        exit;
    }

    if (!medtracker_can_message_contact($pdo, $userId, $role, $receiverId)) {
        echo json_encode(['success' => false, 'message' => 'You can only message linked consultation contacts.']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO consultation_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $receiverId, $message]);

    echo json_encode(['success' => true, 'message' => 'Message sent.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
