<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'worker_prescription_utils.php';

$sessionUserId = $_SESSION['user_id'] ?? '';
$sessionRole = medtracker_normalize_role($_SESSION['role'] ?? '');

if (!$sessionUserId || $sessionRole !== 'Health Worker') {
    echo json_encode(['success' => false, 'message' => 'Health worker session not found. Please log in again.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'));
$medicineId = (int) ($data->medicine_id ?? 0);

if ($medicineId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Provide a valid prescription to delete.']);
    exit;
}

try {
    medtracker_ensure_schedule_schema($pdo);
    medtracker_ensure_assignment_schema($pdo);

    $medicine = medtracker_worker_fetch_prescription($pdo, $medicineId, $sessionUserId);
    if (!$medicine) {
        echo json_encode(['success' => false, 'message' => 'Prescription not found or access denied.']);
        exit;
    }

    $deleteStmt = $pdo->prepare(
        "DELETE FROM medicines
         WHERE id = ?
           AND prescriber_id = ?"
    );
    $deleteStmt->execute([$medicineId, $sessionUserId]);

    echo json_encode([
        'success' => $deleteStmt->rowCount() > 0,
        'message' => $deleteStmt->rowCount() > 0 ? 'Prescription deleted successfully.' : 'Prescription could not be deleted.',
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e instanceof PDOException ? 'Database error: ' . $e->getMessage() : $e->getMessage()]);
}
?>
