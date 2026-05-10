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

    $workerId = $role === 'Health Worker' ? $userId : $contactId;
    $patientId = $role === 'Health Worker' ? $contactId : $userId;

    $stmt = $pdo->prepare(
        "SELECT va.id, va.worker_id, va.patient_id, va.scheduled_at, va.note, va.status, va.created_at,
                worker.name AS worker_name, patient.name AS patient_name
         FROM video_appointments va
         INNER JOIN users worker ON worker.id = va.worker_id
         INNER JOIN users patient ON patient.id = va.patient_id
         WHERE va.worker_id = ? AND va.patient_id = ?
         ORDER BY va.scheduled_at DESC
         LIMIT 10"
    );
    $stmt->execute([$workerId, $patientId]);

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
