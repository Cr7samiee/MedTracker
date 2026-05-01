<?php
session_start();
header('Content-Type: application/json');
include_once '../config/config.php';
require_once 'schedule_utils.php';
require_once 'audit_utils.php';
require_once 'assignment_utils.php';

$data = json_decode(file_get_contents("php://input"));
$medicine_id = $data->medicine_id ?? null;
$added_stock = intval($data->added_stock ?? 0);

if (!$medicine_id || $added_stock <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid medicine ID or stock amount.']);
    exit;
}

try {
    $actorUserId = $_SESSION['user_id'] ?? null;
    $actorRole = medtracker_normalize_role($_SESSION['role'] ?? '');
    if (!$actorUserId || !$actorRole) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }

    medtracker_ensure_schedule_schema($pdo);
    medtracker_ensure_assignment_schema($pdo);
    medtracker_ensure_audit_schema($pdo);

    $lookupStmt = $pdo->prepare("SELECT patient_id, name, type, quantity, prescriber_id, prescription_status FROM medicines WHERE id = ? LIMIT 1");
    $lookupStmt->execute([$medicine_id]);
    $medicine = $lookupStmt->fetch();

    if (!$medicine) {
        echo json_encode(['success' => false, 'message' => 'Medicine not found.']);
        exit;
    }

    if (($medicine['prescription_status'] ?? 'active') === 'stopped') {
        echo json_encode(['success' => false, 'message' => 'Stopped medicines cannot be refilled.']);
        exit;
    }

    if (!medtracker_uses_stock_tracking($medicine['type'] ?? null)) {
        echo json_encode(['success' => false, 'message' => 'Quantity is not used for liquid or inhaler medicines.']);
        exit;
    }

    if ($actorRole === 'User' && (string) ($medicine['patient_id'] ?? '') !== (string) $actorUserId) {
        echo json_encode(['success' => false, 'message' => 'You can only refill your own medicines.']);
        exit;
    }

    if ($actorRole === 'Health Worker' && !medtracker_worker_can_access_patient($pdo, (string) $actorUserId, (string) ($medicine['patient_id'] ?? ''))) {
        echo json_encode(['success' => false, 'message' => 'Access denied for this patient medicine.']);
        exit;
    }

    if (!in_array($actorRole, ['Admin', 'Health Worker', 'User'], true)) {
        echo json_encode(['success' => false, 'message' => 'This session is not allowed to change stock.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE medicines SET quantity = COALESCE(quantity, 0) + ? WHERE id = ?");
    $stmt->execute([$added_stock, $medicine_id]);

    if ($stmt->rowCount() > 0 && $medicine) {
        medtracker_log_audit_event(
            $pdo,
            $actorUserId,
            $actorRole ?: ($_SESSION['role'] ?? null),
            'stock_refill',
            'medicine',
            (string) $medicine_id,
            $medicine['patient_id'] ?? null,
            [
                'medicine_name' => $medicine['name'] ?? '',
                'added_stock' => $added_stock,
                'previous_quantity' => (int) ($medicine['quantity'] ?? 0),
                'new_quantity' => (int) ($medicine['quantity'] ?? 0) + $added_stock,
            ]
        );
    }

    echo json_encode(['success' => true, 'message' => 'Stock updated successfully.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
