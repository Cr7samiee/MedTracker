<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'consultation_utils.php';

try {
    $userId = (string) ($_SESSION['user_id'] ?? '');
    $role = trim((string) ($_SESSION['role'] ?? ''));
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $status = strtolower(trim((string) ($data['status'] ?? 'inactive')));
    $note = trim((string) ($data['note'] ?? ''));

    if ($userId === '' || $role !== 'Health Worker') {
        echo json_encode(['success' => false, 'message' => 'Health worker session not found.']);
        exit;
    }

    medtracker_ensure_consultation_schema($pdo);

    if (!in_array($status, ['active', 'inactive'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO doctor_presence (worker_id, status, note)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note)"
    );
    $stmt->execute([$userId, $status, $note !== '' ? substr($note, 0, 255) : null]);

    echo json_encode(['success' => true, 'message' => 'Availability updated.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
