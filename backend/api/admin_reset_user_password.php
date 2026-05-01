<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'notification_utils.php';
require_once 'audit_utils.php';

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
    medtracker_ensure_notification_schema($pdo);
    medtracker_ensure_audit_schema($pdo);

    $targetUser = medtracker_fetch_user_contact($pdo, $userId);
    $adminUser = medtracker_fetch_user_contact($pdo, $_SESSION['user_id'] ?? '');

    $tempPassword = 'Med@' . random_int(1000, 9999);
    $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, plain_password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
    $stmt->execute([$hash, $tempPassword, $userId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    if ($targetUser) {
        medtracker_send_user_notifications($pdo, $targetUser, [
            'channels' => ['email'],
            'notification_type' => 'ADMIN_PASSWORD_RESET',
            'event_key' => 'ADMIN_PASSWORD_RESET|' . $userId . '|' . date('YmdHis'),
            'email_subject' => 'MedTracker account password reset',
            'email_html' => sprintf(
                'Hello %s,<br><br>An administrator reset your MedTracker password.<br><br>Your temporary password is: <b>%s</b><br><br>Please sign in and change it as soon as possible.',
                htmlspecialchars($targetUser['name'] ?? 'User', ENT_QUOTES),
                htmlspecialchars($tempPassword, ENT_QUOTES)
            ),
            'email_text' => sprintf(
                'Hello %s, an administrator reset your MedTracker password. Your temporary password is: %s. Please sign in and change it as soon as possible.',
                $targetUser['name'] ?? 'User',
                $tempPassword
            ),
        ]);
    }

    if ($adminUser) {
        medtracker_send_user_notifications($pdo, $adminUser, [
            'channels' => ['email'],
            'notification_type' => 'ADMIN_PASSWORD_RESET_AUDIT',
            'event_key' => 'ADMIN_PASSWORD_RESET_AUDIT|' . $userId . '|' . date('YmdHis'),
            'email_subject' => 'MedTracker admin action confirmation',
            'email_html' => sprintf(
                'Hello %s,<br><br>You reset the password for user <b>%s</b> (%s).<br><br>The temporary password issued was: <b>%s</b>.',
                htmlspecialchars($adminUser['name'] ?? 'Admin', ENT_QUOTES),
                htmlspecialchars($targetUser['name'] ?? $userId, ENT_QUOTES),
                htmlspecialchars($userId, ENT_QUOTES),
                htmlspecialchars($tempPassword, ENT_QUOTES)
            ),
            'email_text' => sprintf(
                'You reset the password for user %s (%s). Temporary password: %s.',
                $targetUser['name'] ?? $userId,
                $userId,
                $tempPassword
            ),
        ]);
    }

    medtracker_log_audit_event(
        $pdo,
        $_SESSION['user_id'] ?? null,
        $_SESSION['role'] ?? null,
        'admin_password_reset',
        'user',
        $userId,
        $userId,
        ['temporary_password_issued' => true]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully.',
        'temporary_password' => $tempPassword
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
