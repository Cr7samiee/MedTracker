<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'schedule_utils.php';
require_once 'audit_utils.php';

$sessionUserId = $_SESSION['user_id'] ?? '';
$sessionRole = medtracker_normalize_role($_SESSION['role'] ?? '');

if (!$sessionUserId || $sessionRole !== 'User') {
    echo json_encode(['success' => false, 'message' => 'Patient session not found. Please log in again.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput);

if (!$data && !empty($_POST)) {
    $data = (object) $_POST;
}

$medicineId = (int) ($data->medicine_id ?? 0);
$customTimes = is_array($data->custom_times ?? null) ? $data->custom_times : [];

if ($medicineId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Provide a valid medicine to update.']);
    exit;
}

try {
    medtracker_ensure_schedule_schema($pdo);
    medtracker_ensure_audit_schema($pdo);

    $lookupStmt = $pdo->prepare(
        "SELECT id, patient_id, prescriber_id, frequency, custom_times_json, custom_times_effective_at, treatment_mode, start_date, duration_days, end_date
         FROM medicines
         WHERE id = ?
           AND patient_id = ?
         LIMIT 1"
    );
    $lookupStmt->execute([$medicineId, $sessionUserId]);
    $medicine = $lookupStmt->fetch();

    if (!$medicine) {
        echo json_encode(['success' => false, 'message' => 'Medicine not found for this patient.']);
        exit;
    }

    $expectedDoseCount = medtracker_dose_count_from_frequency((string) ($medicine['frequency'] ?? ''));
    $normalizedCustomTimes = medtracker_parse_custom_times($customTimes, $expectedDoseCount);
    if (count($normalizedCustomTimes) !== $expectedDoseCount) {
        echo json_encode([
            'success' => false,
            'message' => 'Please provide exactly ' . $expectedDoseCount . ' valid reminder time' . ($expectedDoseCount === 1 ? '' : 's') . '.',
        ]);
        exit;
    }

    $pdo->beginTransaction();
    $effectiveAt = date('Y-m-d H:i:s');

    $updateStmt = $pdo->prepare(
        "UPDATE medicines
         SET custom_times_json = ?,
             custom_times_effective_at = ?
         WHERE id = ?
           AND patient_id = ?"
    );
    $updateStmt->execute([
        medtracker_encode_custom_times($normalizedCustomTimes),
        $effectiveAt,
        $medicineId,
        $sessionUserId,
    ]);

    $lookupStmt->execute([$medicineId, $sessionUserId]);
    $updatedMedicine = $lookupStmt->fetch();
    if (!$updatedMedicine) {
        throw new RuntimeException('Unable to reload the updated schedule.');
    }

    $resyncedCount = medtracker_resync_single_medicine_schedule($pdo, $updatedMedicine);
    medtracker_log_audit_event(
        $pdo,
        $sessionUserId,
        $sessionRole,
        'patient_schedule_times_updated',
        'medicine',
        (string) $medicineId,
        $sessionUserId,
        [
            'custom_times' => $normalizedCustomTimes,
            'resynced_count' => $resyncedCount,
        ]
    );
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Reminder times updated successfully.',
        'custom_times' => $normalizedCustomTimes,
        'resynced_count' => $resyncedCount,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e instanceof PDOException ? 'Database error: ' . $e->getMessage() : $e->getMessage(),
    ]);
}
?>
