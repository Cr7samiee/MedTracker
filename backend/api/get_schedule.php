<?php
session_start();
header('Content-Type: application/json');
include_once '../config/config.php';
require_once 'schedule_utils.php';
require_once 'assignment_utils.php';

$role = medtracker_normalize_role($_GET['role'] ?? $_SESSION['role'] ?? 'User');
$user_id = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? null);

try {
    medtracker_ensure_schedule_schema($pdo);
    medtracker_ensure_assignment_schema($pdo);
    medtracker_cleanup_unscheduled_pending_logs($pdo);

    if (($role === 'User' || $role === 'Health Worker') && !$user_id) {
        echo json_encode(['success' => false, 'message' => 'User session not found. Please log in again.']);
        exit;
    }

    $today = date('Y-m-d');

    $medicineQuery = "
        SELECT
            m.id,
            m.patient_id,
            m.prescriber_id,
            m.frequency,
            m.custom_times_json,
            m.custom_times_effective_at,
            m.doctor_slots_json,
            m.treatment_mode,
            m.prescription_status,
            m.paused_at,
            m.stopped_at,
            m.stop_reason,
            m.start_date,
            m.duration_days,
            m.end_date
        FROM medicines m
        WHERE 1=1
    ";
    $medicineParams = [];

    if ($role === 'User' && $user_id) {
        $medicineQuery .= " AND m.patient_id = :uid AND m.prescription_status = 'active' ";
        $medicineParams[':uid'] = $user_id;
    } elseif ($role === 'Health Worker' && $user_id) {
        $medicineQuery .= " AND m.prescriber_id = :uid
            AND EXISTS (
                SELECT 1
                FROM caregiver_patient cp
                WHERE cp.patient_id = m.patient_id
                  AND cp.caregiver_id = :uid
            ) ";
        $medicineParams[':uid'] = $user_id;
    }

    $medicineStmt = $pdo->prepare($medicineQuery);
    $medicineStmt->execute($medicineParams);
    $medicines = $medicineStmt->fetchAll();

    foreach ($medicines as $medicine) {
        medtracker_create_schedule_logs($pdo, $medicine);
    }

    $query = "
        SELECT 
            m.id as medicine_id, 
            m.name as medicine_name, 
            m.dosage, 
            m.type,
            m.instructions,
            m.quantity as stock_remaining,
            CASE
                WHEN LOWER(TRIM(COALESCE(m.type, ''))) LIKE '%liquid%'
                  OR LOWER(TRIM(COALESCE(m.type, ''))) LIKE '%syrup%'
                  OR LOWER(TRIM(COALESCE(m.type, ''))) LIKE '%inhaler%'
                THEN 0
                ELSE 1
            END AS stock_tracked,
            m.frequency,
            m.custom_times_json,
            m.custom_times_effective_at,
            m.doctor_slots_json,
            m.treatment_mode,
            m.prescription_status,
            m.paused_at,
            m.stopped_at,
            m.stop_reason,
            m.start_date,
            m.duration_days,
            m.end_date,
            m.created_at,
            m.prescriber_id,
            m.patient_id, 
            u.name as patient_name,
            p.name as prescriber_name,
            CASE WHEN m.prescriber_id IS NULL THEN 'patient' ELSE 'health_worker' END AS source_type,
            l.id as log_id,
            l.scheduled_time, 
            l.snooze_until,
            COALESCE(l.snooze_until, l.scheduled_time) AS effective_time,
            l.skip_reason,
            l.snooze_count,
            l.status,
            TIMESTAMPDIFF(DAY, m.start_date, DATE(l.scheduled_time)) + 1 AS treatment_day,
            CASE WHEN m.end_date IS NULL THEN NULL ELSE GREATEST(DATEDIFF(m.end_date, :today) + 1, 0) END AS days_remaining
        FROM medicines m
        LEFT JOIN intake_logs l ON m.id = l.medicine_id AND DATE(l.scheduled_time) = :today
        LEFT JOIN users u ON m.patient_id = u.id
        LEFT JOIN users p ON m.prescriber_id = p.id
        WHERE 1=1
    ";

    $params = [':today' => $today];

    if ($role === 'User' && $user_id) {
        $query .= " AND m.patient_id = :uid AND m.prescription_status = 'active' ";
        $params[':uid'] = $user_id;
    } elseif ($role === 'Health Worker' && $user_id) {
        $query .= " AND m.prescriber_id = :uid
            AND EXISTS (
                SELECT 1
                FROM caregiver_patient cp
                WHERE cp.patient_id = m.patient_id
                  AND cp.caregiver_id = :uid
            ) ";
        $params[':uid'] = $user_id;
    }
    // Admin gets everything

    $query .= " AND m.start_date <= :today AND (m.end_date IS NULL OR m.end_date >= :today) ";

    $query .= " ORDER BY COALESCE(COALESCE(l.snooze_until, l.scheduled_time), CONCAT(:today, ' 23:59:59')) ASC, m.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $results]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
