<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'consultation_utils.php';

try {
    $userId = (string) ($_SESSION['user_id'] ?? '');
    $role = trim((string) ($_SESSION['role'] ?? ''));
    $contactId = trim((string) ($_GET['contact_id'] ?? ''));

    if ($userId === '' || $role === '') {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }

    medtracker_ensure_consultation_schema($pdo);

    if ($contactId === '' || !medtracker_can_message_contact($pdo, $userId, $role, $contactId)) {
        echo json_encode(['success' => false, 'message' => 'Choose a linked consultation contact.']);
        exit;
    }

    $markRead = $pdo->prepare("UPDATE consultation_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
    $markRead->execute([$contactId, $userId]);

    $stmt = $pdo->prepare(
        "SELECT m.id, m.sender_id, m.receiver_id, m.message, m.is_read, m.created_at, sender.name AS sender_name
         FROM consultation_messages m
         INNER JOIN users sender ON sender.id = m.sender_id
         WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
         ORDER BY m.created_at ASC, m.id ASC
         LIMIT 100"
    );
    $stmt->execute([$userId, $contactId, $contactId, $userId]);

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
