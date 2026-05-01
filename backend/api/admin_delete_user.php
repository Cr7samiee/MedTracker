<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'notification_utils.php';
require_once 'audit_utils.php';

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
    medtracker_ensure_notification_schema($pdo);
    medtracker_ensure_audit_schema($pdo);

    $targetUser = medtracker_fetch_user_contact($pdo, $userId);
    $adminUser = medtracker_fetch_user_contact($pdo, $adminId);

    if (!$targetUser) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    if ($targetUser) {
        medtracker_send_user_notifications($pdo, $targetUser, [
            'channels' => ['email'],
            'notification_type' => 'ADMIN_ACCOUNT_DELETE',
            'event_key' => 'ADMIN_ACCOUNT_DELETE|' . $userId . '|' . date('YmdHis'),
            'email_subject' => 'MedTracker account removal notice',
            'email_html' => sprintf(
                'Hello %s,<br><br>Your MedTracker account (%s) is being removed by an administrator.<br><br>If this was unexpected, please contact the project administrator.',
                htmlspecialchars($targetUser['name'] ?? 'User', ENT_QUOTES),
                htmlspecialchars($userId, ENT_QUOTES)
            ),
            'email_text' => sprintf(
                'Hello %s, your MedTracker account (%s) is being removed by an administrator. If this was unexpected, please contact the project administrator.',
                $targetUser['name'] ?? 'User',
                $userId
            ),
        ]);
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    if ($adminUser) {
        medtracker_send_user_notifications($pdo, $adminUser, [
            'channels' => ['email'],
            'notification_type' => 'ADMIN_ACCOUNT_DELETE_AUDIT',
            'event_key' => 'ADMIN_ACCOUNT_DELETE_AUDIT|' . $userId . '|' . date('YmdHis'),
            'email_subject' => 'MedTracker admin deletion confirmation',
            'email_html' => sprintf(
                'Hello %s,<br><br>You removed the MedTracker account for <b>%s</b> (%s).',
                htmlspecialchars($adminUser['name'] ?? 'Admin', ENT_QUOTES),
                htmlspecialchars($targetUser['name'] ?? $userId, ENT_QUOTES),
                htmlspecialchars($userId, ENT_QUOTES)
            ),
            'email_text' => sprintf(
                'You removed the MedTracker account for %s (%s).',
                $targetUser['name'] ?? $userId,
                $userId
            ),
        ]);
    }

    medtracker_log_audit_event(
        $pdo,
        $adminId ?: null,
        $_SESSION['role'] ?? null,
        'admin_delete_user',
        'user',
        $userId,
        $userId,
        ['deleted_name' => $targetUser['name'] ?? $userId]
    );

    echo json_encode(['success' => true, 'message' => 'User removed successfully.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
