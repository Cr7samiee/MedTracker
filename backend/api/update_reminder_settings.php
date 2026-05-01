<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'schedule_utils.php';
require_once 'notification_utils.php';
require_once 'audit_utils.php';

$role = medtracker_normalize_role($_SESSION['role'] ?? '');
if ($role !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Admin session not found. Please log in again.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!$data && !empty($_POST)) {
    $data = $_POST;
}

$settingsRows = is_array($data['settings'] ?? null) ? $data['settings'] : [];
if (!$settingsRows) {
    echo json_encode(['success' => false, 'message' => 'No reminder settings were provided.']);
    exit;
}

try {
    medtracker_ensure_reminder_settings_schema($pdo);
    medtracker_ensure_audit_schema($pdo);

    $pdo->beginTransaction();
    $savedSettings = medtracker_save_reminder_settings($pdo, $settingsRows);
    medtracker_append_system_log(
        $pdo,
        'SYSTEM',
        'REMINDER_SETTINGS_UPDATED|' . ($_SESSION['user_id'] ?? 'ADMIN'),
        'SUCCESS'
    );
    medtracker_log_audit_event(
        $pdo,
        $_SESSION['user_id'] ?? null,
        $role,
        'reminder_settings_updated',
        'reminder_settings',
        'global',
        null,
        ['settings' => $savedSettings]
    );
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Reminder settings updated successfully.',
        'settings' => $savedSettings,
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $error instanceof PDOException ? 'Database error: ' . $error->getMessage() : $error->getMessage(),
    ]);
}
