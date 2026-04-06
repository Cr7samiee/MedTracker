<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'adherence_utils.php';
require_once 'schedule_utils.php';
require_once 'water_utils.php';

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
    medtracker_ensure_water_schema($pdo);

    $dateSeries = medtracker_build_date_series($days);
    $dateFrom = $dateSeries[0];
    $today = date('Y-m-d');

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

    $waterMap = medtracker_fetch_water_map($pdo, $userId, $dateFrom);
    foreach ($chartMap as $date => &$dayData) {
        $dayData['water_ml'] = (int) ($waterMap[$date] ?? 0);
    }
    unset($dayData);

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

    $weeklyDates = array_slice($dateSeries, -7);
    $weekOverview = [];
    foreach ($weeklyDates as $date) {
        $day = $chartMap[$date] ?? ['taken' => 0, 'skipped' => 0, 'pending' => 0, 'overuse' => 0, 'water_ml' => 0];
        $totalForDay = (int) ($day['taken'] + $day['skipped'] + $day['pending']);

        if ($date > $today) {
            $status = 'upcoming';
        } elseif ($totalForDay === 0) {
            $status = 'none';
        } elseif ($day['taken'] === $totalForDay && $day['overuse'] === 0) {
            $status = 'complete';
        } elseif ($day['taken'] > 0 && ($day['skipped'] > 0 || $day['pending'] > 0 || $day['overuse'] > 0)) {
            $status = 'partial';
        } elseif ($day['skipped'] > 0 || $day['overuse'] > 0) {
            $status = 'missed';
        } else {
            $status = 'pending';
        }

        $weekOverview[] = [
            'date' => $date,
            'day_short' => date('D', strtotime($date)),
            'day_number' => date('j', strtotime($date)),
            'is_today' => $date === $today,
            'status' => $status,
            'taken' => (int) $day['taken'],
            'expected' => $totalForDay,
            'water_ml' => (int) ($day['water_ml'] ?? 0),
        ];
    }

    $waterGoal = medtracker_water_goal_ml();
    $todayWater = (int) ($waterMap[$today] ?? 0);
    $waterWeeklyAverage = count($weeklyDates) ? (int) round(array_sum(array_map(
        static fn($date) => (int) ($waterMap[$date] ?? 0),
        $weeklyDates
    )) / count($weeklyDates)) : 0;

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
        'week_overview' => $weekOverview,
        'water' => [
            'goal_ml' => $waterGoal,
            'today_ml' => $todayWater,
            'today_percent' => min(100, (int) round(($todayWater / max($waterGoal, 1)) * 100)),
            'weekly_average_ml' => $waterWeeklyAverage,
            'series' => array_map(static function ($date) use ($chartMap) {
                return [
                    'label' => date('M j', strtotime($date)),
                    'value' => (int) ($chartMap[$date]['water_ml'] ?? 0),
                ];
            }, $weeklyDates),
        ],
        'alerts' => array_slice($alerts, 0, 8),
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
