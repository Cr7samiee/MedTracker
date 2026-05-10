<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'consultation_utils.php';

try {
    $userId = (string) ($_SESSION['user_id'] ?? '');
    $role = trim((string) ($_SESSION['role'] ?? ''));

    if ($userId === '' || $role === '') {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }

    medtracker_ensure_consultation_schema($pdo);

    $contacts = [];
    if ($role === 'User') {
        $workers = medtracker_get_assigned_workers($pdo, $userId);
        $presenceMap = medtracker_get_presence_map($pdo, array_column($workers, 'id'));

        foreach ($workers as $worker) {
            $presence = $presenceMap[(string) $worker['id']] ?? null;
            $contacts[] = [
                'id' => $worker['id'],
                'name' => $worker['name'],
                'role' => 'Health Worker',
                'subtitle' => $worker['post'] ?: 'Healthcare Professional',
                'room_name' => medtracker_video_room_name($userId, (string) $worker['id']),
                'presence' => [
                    'status' => $presence['status'] ?? 'inactive',
                    'note' => $presence['note'] ?? '',
                    'updated_at' => $presence['updated_at'] ?? null,
                ],
            ];
        }
    } elseif ($role === 'Health Worker') {
        foreach (medtracker_fetch_assigned_patients($pdo, $userId) as $patient) {
            $profileParts = array_filter([$patient['phone'] ?? '', $patient['gender'] ?? '', $patient['dob'] ?? '']);
            $contacts[] = [
                'id' => $patient['id'],
                'name' => $patient['name'],
                'role' => 'User',
                'subtitle' => $profileParts ? implode(' • ', $profileParts) : 'Linked patient',
                'room_name' => medtracker_video_room_name((string) $patient['id'], $userId),
                'presence' => ['status' => 'patient', 'note' => '', 'updated_at' => null],
            ];
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Video calls are available for patients and health workers only.']);
        exit;
    }

    $unreadStmt = $pdo->prepare(
        "SELECT sender_id, COUNT(*) AS unread_count
         FROM consultation_messages
         WHERE receiver_id = ? AND is_read = 0
         GROUP BY sender_id"
    );
    $unreadStmt->execute([$userId]);
    $unreadMap = [];
    foreach ($unreadStmt->fetchAll() as $row) {
        $unreadMap[(string) $row['sender_id']] = (int) $row['unread_count'];
    }

    foreach ($contacts as &$contact) {
        $contact['unread_count'] = $unreadMap[(string) $contact['id']] ?? 0;
    }
    unset($contact);

    echo json_encode([
        'success' => true,
        'data' => [
            'current_user' => ['id' => $userId, 'role' => $role, 'name' => $_SESSION['name'] ?? 'User'],
            'contacts' => $contacts,
        ],
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
