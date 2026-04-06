<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'adherence_utils.php';
require_once 'water_utils.php';

$role = strtolower(trim($_SESSION['role'] ?? ($_GET['role'] ?? '')));

if ($role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin session not found. Please log in again.']);
    exit;
}

try {
    medtracker_ensure_water_schema($pdo);

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $roleStmt = $pdo->query(
        "SELECT role, COUNT(*) AS total FROM users GROUP BY role"
    );
    $roleCounts = [
        'Admin' => 0,
        'Health Worker' => 0,
        'User' => 0,
    ];
    foreach ($roleStmt->fetchAll() as $row) {
        $roleCounts[$row['role']] = (int) $row['total'];
    }

    $usageStmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_logs,
            SUM(CASE WHEN status = 'Taken' THEN 1 ELSE 0 END) AS taken_today,
            SUM(CASE WHEN status = 'Skipped' THEN 1 ELSE 0 END) AS skipped_today,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_today
         FROM intake_logs
         WHERE DATE(scheduled_time) = ?"
    );
    $usageStmt->execute([$today]);
    $usage = $usageStmt->fetch() ?: [];

    $medicineStmt = $pdo->query(
        "SELECT COUNT(*) AS total_medicines FROM medicines"
    );
    $medicineCount = (int) (($medicineStmt->fetch()['total_medicines'] ?? 0));

    $lowStockStmt = $pdo->query(
        "SELECT COUNT(*) AS total_low_stock FROM medicines WHERE quantity <= 5"
    );
    $lowStockTotal = (int) (($lowStockStmt->fetch()['total_low_stock'] ?? 0));

    $logsStmt = $pdo->prepare(
        "SELECT id, log_type, message, status, created_at
         FROM system_logs
         WHERE DATE(created_at) >= ?
         ORDER BY created_at DESC
         LIMIT 10"
    );
    $logsStmt->execute([$yesterday]);
    $recentLogs = $logsStmt->fetchAll();

    $chartStartDate = date('Y-m-d', strtotime('-6 day'));
    $usageChartStmt = $pdo->prepare(
        "SELECT
            DATE(scheduled_time) AS usage_date,
            SUM(CASE WHEN status = 'Taken' THEN 1 ELSE 0 END) AS taken,
            SUM(CASE WHEN status = 'Skipped' THEN 1 ELSE 0 END) AS skipped,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending,
            COUNT(*) AS total
         FROM intake_logs
         WHERE DATE(scheduled_time) >= ?
         GROUP BY DATE(scheduled_time)
         ORDER BY DATE(scheduled_time) ASC"
    );
    $usageChartStmt->execute([$chartStartDate]);
    $usageChartRows = $usageChartStmt->fetchAll();

    $doctorActivityStmt = $pdo->query(
        "SELECT
            COALESCE(u.name, 'Patient Self Added') AS prescriber_name,
            COALESCE(m.prescriber_id, 'SELF') AS prescriber_id,
            COUNT(*) AS total_medicines
         FROM medicines m
         LEFT JOIN users u ON m.prescriber_id = u.id
         GROUP BY COALESCE(m.prescriber_id, 'SELF'), COALESCE(u.name, 'Patient Self Added')
         ORDER BY total_medicines DESC, prescriber_name ASC
         LIMIT 8"
    );
    $doctorActivity = $doctorActivityStmt->fetchAll();

    $overuseEvents = medtracker_fetch_overuse_logs($pdo, null, $chartStartDate);

    $activeUsersStmt = $pdo->prepare(
        "SELECT activity_date, COUNT(DISTINCT user_id) AS active_users
         FROM (
            SELECT DATE(scheduled_time) AS activity_date, patient_id AS user_id
            FROM intake_logs
            WHERE DATE(scheduled_time) >= ?

            UNION ALL

            SELECT intake_date AS activity_date, patient_id AS user_id
            FROM water_logs
            WHERE intake_date >= ?

            UNION ALL

            SELECT DATE(created_at) AS activity_date, prescriber_id AS user_id
            FROM medicines
            WHERE prescriber_id IS NOT NULL AND DATE(created_at) >= ?

            UNION ALL

            SELECT DATE(created_at) AS activity_date, patient_id AS user_id
            FROM medicines
            WHERE prescriber_id IS NULL AND DATE(created_at) >= ?
         ) AS activity_stream
         GROUP BY activity_date
         ORDER BY activity_date ASC"
    );
    $activeUsersStmt->execute([$chartStartDate, $chartStartDate, $chartStartDate, $chartStartDate]);
    $activeUserRows = $activeUsersStmt->fetchAll();

    $usageChartMap = [];
    $activeUserChartMap = [];
    foreach (medtracker_build_date_series(7) as $date) {
        $usageChartMap[$date] = [
            'usage_date' => $date,
            'taken' => 0,
            'skipped' => 0,
            'pending' => 0,
            'overuse' => 0,
            'total' => 0,
        ];
        $activeUserChartMap[$date] = [
            'usage_date' => $date,
            'active_users' => 0,
        ];
    }

    foreach ($usageChartRows as $row) {
        $dateKey = $row['usage_date'];
        if (!isset($usageChartMap[$dateKey])) {
            continue;
        }

        $usageChartMap[$dateKey]['taken'] = (int) ($row['taken'] ?? 0);
        $usageChartMap[$dateKey]['skipped'] = (int) ($row['skipped'] ?? 0);
        $usageChartMap[$dateKey]['pending'] = (int) ($row['pending'] ?? 0);
        $usageChartMap[$dateKey]['total'] = (int) ($row['total'] ?? 0);
    }

    foreach ($overuseEvents as $event) {
        $dateKey = date('Y-m-d', strtotime($event['created_at']));
        if (!isset($usageChartMap[$dateKey])) {
            continue;
        }

        $usageChartMap[$dateKey]['overuse']++;
    }

    foreach ($activeUserRows as $row) {
        $dateKey = $row['activity_date'];
        if (!isset($activeUserChartMap[$dateKey])) {
            continue;
        }

        $activeUserChartMap[$dateKey]['active_users'] = (int) ($row['active_users'] ?? 0);
    }

    $totalUsers = $roleCounts['Admin'] + $roleCounts['Health Worker'] + $roleCounts['User'];
    $activeUsersToday = (int) ($activeUserChartMap[$today]['active_users'] ?? 0);
    $activeUsersWeeklyPeak = max(array_map(
        static fn($row) => (int) ($row['active_users'] ?? 0),
        $activeUserChartMap
    ));

    echo json_encode([
        'success' => true,
        'metrics' => [
            'admin_count' => $roleCounts['Admin'],
            'health_worker_count' => $roleCounts['Health Worker'],
            'patient_count' => $roleCounts['User'],
            'total_user_count' => $totalUsers,
            'active_prescriptions' => $medicineCount,
            'today_activity' => (int) ($usage['total_logs'] ?? 0),
            'taken_today' => (int) ($usage['taken_today'] ?? 0),
            'skipped_today' => (int) ($usage['skipped_today'] ?? 0),
            'pending_today' => (int) ($usage['pending_today'] ?? 0),
            'low_stock_total' => $lowStockTotal,
            'overuse_total' => count($overuseEvents),
            'active_users_today' => $activeUsersToday,
            'weekly_active_peak' => $activeUsersWeeklyPeak,
        ],
        'usage_chart' => array_values($usageChartMap),
        'active_users_chart' => array_values($activeUserChartMap),
        'doctor_activity' => $doctorActivity,
        'recent_logs' => $recentLogs,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
