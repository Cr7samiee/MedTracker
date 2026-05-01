<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'schedule_utils.php';

$role = medtracker_normalize_role($_SESSION['role'] ?? '');
if ($role !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Admin session not found. Please log in again.']);
    exit;
}

try {
    medtracker_ensure_reminder_settings_schema($pdo);

    echo json_encode([
        'success' => true,
        'settings' => array_values(medtracker_get_reminder_settings_map($pdo)),
    ]);
} catch (Throwable $error) {
    echo json_encode([
        'success' => false,
        'message' => $error instanceof PDOException ? 'Database error: ' . $error->getMessage() : $error->getMessage(),
    ]);
}
