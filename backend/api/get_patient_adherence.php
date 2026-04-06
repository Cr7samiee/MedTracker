<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'adherence_utils.php';
require_once 'schedule_utils.php';

$userId = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? null);
$role = $_SESSION['role'] ?? 'User';
$days = intval($_GET['days'] ?? 7);
$days = max(7, min($days, 30));

if (!$userId || strtolower(trim($role)) !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Patient session not found. Please log in again.']);
    exit;
}

try {
    medtracker_ensure_schedule_schema($pdo);

    $dateSeries = medtracker_build_date_series($days);
    $dateFrom = $dateSeries[0];

    $medicineStmt = $pdo->prepare(
        "SELECT id, patient_id, frequency, start_date, duration_days, end_date
         FROM medicines
         WHERE patient_id = ?
           AND end_date >= ?"
    );
    $medicineStmt->execute([$userId, $dateFrom]);
    foreach ($medicineStmt->fetchAll() as $medicine) {
        medtracker_create_schedule_logs($pdo, $medicine);
    }

    $logStmt = $pdo->prepare(
        "SELECT DATE(scheduled_time) AS log_date, status, COUNT(*) AS total
         FROM intake_logs
         WHERE patient_id = ? AND DATE(scheduled_time) >= ?
         GROUP BY DATE(scheduled_time), status
         ORDER BY DATE(scheduled_time) ASC"
    );
    $logStmt->execute([$userId, $dateFrom]);
    $logRows = $logStmt->fetchAll();

    $summaryStmt = $pdo->prepare(
        "SELECT
            SUM(CASE WHEN status = 'Taken' THEN 1 ELSE 0 END) AS taken_total,
            SUM(CASE WHEN status = 'Skipped' THEN 1 ELSE 0 END) AS skipped_total,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_total,
            COUNT(*) AS total_logs
         FROM intake_logs
         WHERE patient_id = ? AND DATE(scheduled_time) >= ?"
    );
    $summaryStmt->execute([$userId, $dateFrom]);
    $summary = $summaryStmt->fetch() ?: [];

    $stockStmt = $pdo->prepare(
        "SELECT id, name, dosage, quantity
         FROM medicines
         WHERE patient_id = ? AND quantity <= 5
         ORDER BY quantity ASC, created_at DESC"
    );
    $stockStmt->execute([$userId]);
    $lowStockMeds = $stockStmt->fetchAll();

    $overuseEvents = medtracker_fetch_overuse_logs($pdo, $userId, $dateFrom);

    $chartMap = [];
    foreach ($dateSeries as $date) {
        $chartMap[$date] = [
            'label' => date('M j', strtotime($date)),
            'taken' => 0,
            'skipped' => 0,
            'pending' => 0,
            'overuse' => 0,
        ];
    }

    foreach ($logRows as $row) {
        $date = $row['log_date'];
        if (!isset($chartMap[$date])) {
            continue;
        }

        $statusKey = strtolower($row['status']);
        if (isset($chartMap[$date][$statusKey])) {
            $chartMap[$date][$statusKey] = (int) $row['total'];
        }
    }

    foreach ($overuseEvents as $event) {
        $date = date('Y-m-d', strtotime($event['created_at']));
        if (isset($chartMap[$date])) {
            $chartMap[$date]['overuse'] += 1;
        }
    }

    $takenTotal = (int) ($summary['taken_total'] ?? 0);
    $skippedTotal = (int) ($summary['skipped_total'] ?? 0);
    $pendingTotal = (int) ($summary['pending_total'] ?? 0);
    $overuseTotal = count($overuseEvents);
    $trackedTotal = max($takenTotal + $skippedTotal + $pendingTotal, 1);
    $adherenceRate = (int) round(($takenTotal / $trackedTotal) * 100);

    $streak = 0;
    for ($index = count($dateSeries) - 1; $index >= 0; $index--) {
        $day = $chartMap[$dateSeries[$index]];
        $hasGoodDay = $day['taken'] > 0 && $day['skipped'] === 0 && $day['overuse'] === 0;
        if (!$hasGoodDay) {
            break;
        }
        $streak++;
    }

    $alerts = [];
    foreach ($overuseEvents as $event) {
        $alerts[] = [
            'type' => 'overuse',
            'message' => $event['medicine_name'] . ' was logged more than prescribed.',
            'created_at' => $event['created_at'],
        ];
    }

    foreach ($lowStockMeds as $medicine) {
        $alerts[] = [
            'type' => 'low_stock',
            'message' => $medicine['name'] . ' is running low with only ' . $medicine['quantity'] . ' doses left.',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    usort($alerts, function ($left, $right) {
        return strcmp($right['created_at'], $left['created_at']);
    });

    echo json_encode([
        'success' => true,
        'summary' => [
            'adherence_rate' => $adherenceRate,
            'taken_total' => $takenTotal,
            'skipped_total' => $skippedTotal,
            'pending_total' => $pendingTotal,
            'overuse_total' => $overuseTotal,
            'expected_doses' => $takenTotal + $skippedTotal + $pendingTotal,
            'streak_days' => $streak,
            'low_stock_total' => count($lowStockMeds),
        ],
        'chart' => array_values($chartMap),
        'alerts' => array_slice($alerts, 0, 8),
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
