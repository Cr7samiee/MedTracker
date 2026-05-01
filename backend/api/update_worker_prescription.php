<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'worker_prescription_utils.php';
require_once 'notification_utils.php';
require_once 'audit_utils.php';

$sessionUserId = $_SESSION['user_id'] ?? '';
$sessionRole = medtracker_normalize_role($_SESSION['role'] ?? '');

if (!$sessionUserId || $sessionRole !== 'Health Worker') {
    echo json_encode(['success' => false, 'message' => 'Health worker session not found. Please log in again.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput);
if (!$data && !empty($_POST)) {
    $data = (object) $_POST;
}

$medicineId = (int) ($data->medicine_id ?? 0);
if ($medicineId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Provide a valid prescription to update.']);
    exit;
}

try {
    medtracker_ensure_schedule_schema($pdo);
    medtracker_ensure_assignment_schema($pdo);
    medtracker_ensure_notification_schema($pdo);
    medtracker_ensure_audit_schema($pdo);

    $medicine = medtracker_worker_fetch_prescription($pdo, $medicineId, $sessionUserId);
    if (!$medicine) {
        echo json_encode(['success' => false, 'message' => 'Prescription not found or access denied.']);
        exit;
    }

    $name = trim((string) ($data->name ?? ''));
    $dosage = trim((string) ($data->dosage ?? ''));
    $type = trim((string) ($data->type ?? 'Oral Tablet'));
    $instructions = trim((string) ($data->instructions ?? ''));
    $frequency = trim((string) ($data->frequency ?? ''));
    $customTimes = is_array($data->custom_times ?? null) ? $data->custom_times : [];
    $quantity = medtracker_uses_stock_tracking($type)
        ? max(0, (int) ($data->quantity ?? 0))
        : null;
    $treatmentMode = medtracker_normalize_treatment_mode($data->treatment_mode ?? null);
    $startDate = $data->start_date ?? date('Y-m-d');
    $startDateObject = date_create($startDate) ?: date_create(date('Y-m-d'));
    $startDate = $startDateObject->format('Y-m-d');

    if ($name === '' || $dosage === '' || $frequency === '') {
        echo json_encode(['success' => false, 'message' => 'Medicine name, dosage, and frequency are required.']);
        exit;
    }

    $expectedDoseCount = medtracker_dose_count_from_frequency($frequency);
    $normalizedCustomTimes = medtracker_parse_custom_times($customTimes, $expectedDoseCount);
    if (count($normalizedCustomTimes) !== $expectedDoseCount) {
        echo json_encode(['success' => false, 'message' => 'Choose exactly ' . $expectedDoseCount . ' doctor dose window' . ($expectedDoseCount === 1 ? '' : 's') . ' before updating this prescription.']);
        exit;
    }

    $doctorSlotsJson = medtracker_encode_custom_times($normalizedCustomTimes);
    $durationDays = $treatmentMode === 'ongoing'
        ? null
        : max(1, min((int) ($data->duration_days ?? 7), 365));
    $endDate = $treatmentMode === 'ongoing'
        ? null
        : medtracker_calculate_end_date($startDate, (int) $durationDays);

    $pdo->beginTransaction();

    $updateStmt = $pdo->prepare(
        "UPDATE medicines
         SET name = ?,
             dosage = ?,
             type = ?,
             instructions = ?,
             frequency = ?,
             doctor_slots_json = ?,
             treatment_mode = ?,
             start_date = ?,
             duration_days = ?,
             end_date = ?,
             quantity = ?
         WHERE id = ?
           AND prescriber_id = ?"
    );
    $updateStmt->execute([
        $name,
        $dosage,
        $type,
        $instructions,
        $frequency,
        $doctorSlotsJson,
        $treatmentMode,
        $startDate,
        $durationDays,
        $endDate,
        $quantity,
        $medicineId,
        $sessionUserId,
    ]);

    $updatedMedicine = medtracker_worker_fetch_prescription($pdo, $medicineId, $sessionUserId);
    if (!$updatedMedicine) {
        throw new RuntimeException('Unable to reload the updated prescription.');
    }

    $resyncedCount = medtracker_resync_single_medicine_schedule($pdo, $updatedMedicine);
    medtracker_log_audit_event(
        $pdo,
        $sessionUserId,
        $sessionRole,
        'worker_update_prescription',
        'medicine',
        (string) $medicineId,
        $updatedMedicine['patient_id'] ?? null,
        [
            'medicine_name' => $name,
            'frequency' => $frequency,
            'treatment_mode' => $treatmentMode,
            'resynced_count' => $resyncedCount,
        ]
    );
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Prescription updated successfully.',
        'resynced_count' => $resyncedCount,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode(['success' => false, 'message' => $e instanceof PDOException ? 'Database error: ' . $e->getMessage() : $e->getMessage()]);
}
?>
