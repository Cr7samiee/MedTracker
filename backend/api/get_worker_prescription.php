<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'worker_prescription_utils.php';

$sessionUserId = $_SESSION['user_id'] ?? '';
$sessionRole = medtracker_normalize_role($_SESSION['role'] ?? '');
$medicineId = (int) ($_GET['medicine_id'] ?? 0);

if (!$sessionUserId || $sessionRole !== 'Health Worker') {
    echo json_encode(['success' => false, 'message' => 'Health worker session not found. Please log in again.']);
    exit;
}

if ($medicineId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Provide a valid medicine ID.']);
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

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int) $medicine['id'],
            'patient_id' => $medicine['patient_id'],
            'patient_name' => $medicine['patient_name'] ?? '',
            'name' => $medicine['name'],
            'dosage' => $medicine['dosage'],
            'type' => $medicine['type'],
            'quantity' => $medicine['quantity'] === null ? null : (int) $medicine['quantity'],
            'stock_tracked' => medtracker_uses_stock_tracking($medicine['type'] ?? null),
            'frequency' => $medicine['frequency'],
            'custom_times' => medtracker_parse_custom_times(
                $medicine['doctor_slots_json'] ?? null,
                medtracker_dose_count_from_frequency((string) $medicine['frequency'])
            ),
            'prescription_status' => $medicine['prescription_status'] ?? 'active',
            'paused_at' => $medicine['paused_at'] ?? null,
            'stopped_at' => $medicine['stopped_at'] ?? null,
            'stop_reason' => $medicine['stop_reason'] ?? null,
            'treatment_mode' => $medicine['treatment_mode'] ?? 'course',
            'start_date' => $medicine['start_date'],
            'duration_days' => $medicine['duration_days'],
            'instructions' => $medicine['instructions'],
        ],
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e instanceof PDOException ? 'Database error: ' . $e->getMessage() : $e->getMessage()]);
}
?>
