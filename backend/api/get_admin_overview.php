<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'adherence_utils.php';
require_once 'water_utils.php';
require_once 'notification_utils.php';
require_once 'audit_utils.php';
require_once 'schedule_utils.php';

$role = strtolower(trim($_SESSION['role'] ?? ($_GET['role'] ?? '')));

if ($role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin session not found. Please log in again.']);
    exit;
}

try {
    medtracker_ensure_water_schema($pdo);
    medtracker_ensure_notification_schema($pdo);
    medtracker_ensure_audit_schema($pdo);
    medtracker_ensure_reminder_settings_schema($pdo);

    $period = strtolower(trim((string) ($_GET['period'] ?? 'week')));
    if (!in_array($period, ['day', 'week', 'year'], true)) {
        $period = 'week';
    }

    $selectedDateObject = date_create((string) ($_GET['date'] ?? date('Y-m-d'))) ?: date_create(date('Y-m-d'));
    $selectedDate = $selectedDateObject->format('Y-m-d');

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $buildReportWindow = static function (string $periodValue, string $anchorDateValue): array {
        $anchor = new DateTimeImmutable($anchorDateValue);

        if ($periodValue === 'day') {
            $dateValue = $anchor->format('Y-m-d');
            return [
                'period' => 'day',
                'period_label' => 'Daily',
                'series' => [$dateValue],
                'start_date' => $dateValue,
                'end_date' => $dateValue,
                'range_label' => $anchor->format('M j, Y'),
            ];
        }

        if ($periodValue === 'year') {
            $year = (int) $anchor->format('Y');
            $series = [];
            for ($month = 1; $month <= 12; $month++) {
                $series[] = sprintf('%04d-%02d-01', $year, $month);
            }

            return [
                'period' => 'year',
                'period_label' => 'Yearly',
                'series' => $series,
                'start_date' => sprintf('%04d-01-01', $year),
                'end_date' => sprintf('%04d-12-31', $year),
                'range_label' => sprintf('Year %04d', $year),
            ];
        }

        $series = [];
        for ($offset = 6; $offset >= 0; $offset--) {
            $series[] = $anchor->sub(new DateInterval('P' . $offset . 'D'))->format('Y-m-d');
        }

        return [
            'period' => 'week',
            'period_label' => 'Weekly',
            'series' => $series,
            'start_date' => $series[0],
            'end_date' => $series[count($series) - 1],
            'range_label' => 'Week ending ' . $anchor->format('M j, Y'),
        ];
    };
    $reportWindow = $buildReportWindow($period, $selectedDate);
    $chartSeries = $reportWindow['series'];
    $chartStartDate = $reportWindow['start_date'];
    $chartEndDate = $reportWindow['end_date'];
    $usageBucketExpression = $period === 'year'
        ? "DATE_FORMAT(scheduled_time, '%Y-%m-01')"
        : "DATE(scheduled_time)";
    $notificationBucketExpression = $period === 'year'
        ? "DATE_FORMAT(created_at, '%Y-%m-01')"
        : "DATE(created_at)";
    $activityBucketFromSchedule = $period === 'year'
        ? "DATE_FORMAT(DATE(scheduled_time), '%Y-%m-01')"
        : "DATE(scheduled_time)";
    $activityBucketFromWater = $period === 'year'
        ? "DATE_FORMAT(intake_date, '%Y-%m-01')"
        : "intake_date";
    $activityBucketFromPrescription = $period === 'year'
        ? "DATE_FORMAT(DATE(created_at), '%Y-%m-01')"
        : "DATE(created_at)";

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

    $activeUsersTodayStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT user_id) AS active_users
         FROM (
            SELECT patient_id AS user_id
            FROM intake_logs
            WHERE DATE(scheduled_time) = ?

            UNION ALL

            SELECT patient_id AS user_id
            FROM water_logs
            WHERE intake_date = ?

            UNION ALL

            SELECT prescriber_id AS user_id
            FROM medicines
            WHERE prescriber_id IS NOT NULL AND DATE(created_at) = ?

            UNION ALL

            SELECT patient_id AS user_id
            FROM medicines
            WHERE prescriber_id IS NULL AND DATE(created_at) = ?
         ) AS today_activity_stream"
    );
    $activeUsersTodayStmt->execute([$today, $today, $today, $today]);
    $activeUsersToday = (int) (($activeUsersTodayStmt->fetch()['active_users'] ?? 0));

    $medicineStmt = $pdo->query(
        "SELECT COUNT(*) AS total_medicines FROM medicines"
    );
    $medicineCount = (int) (($medicineStmt->fetch()['total_medicines'] ?? 0));

    $lowStockStmt = $pdo->query(
        "SELECT COUNT(*) AS total_low_stock
         FROM medicines
         WHERE quantity <= 5
           AND LOWER(TRIM(COALESCE(type, ''))) NOT LIKE '%liquid%'
           AND LOWER(TRIM(COALESCE(type, ''))) NOT LIKE '%syrup%'
           AND LOWER(TRIM(COALESCE(type, ''))) NOT LIKE '%inhaler%'"
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

    $usageChartStmt = $pdo->prepare(
        "SELECT
            {$usageBucketExpression} AS usage_date,
            SUM(CASE WHEN status = 'Taken' THEN 1 ELSE 0 END) AS taken,
            SUM(CASE WHEN status = 'Skipped' THEN 1 ELSE 0 END) AS skipped,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending,
            COUNT(*) AS total
         FROM intake_logs
         WHERE DATE(scheduled_time) BETWEEN ? AND ?
         GROUP BY {$usageBucketExpression}
         ORDER BY {$usageBucketExpression} ASC"
    );
    $usageChartStmt->execute([$chartStartDate, $chartEndDate]);
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

    $notificationChartStmt = $pdo->prepare(
        "SELECT
            {$notificationBucketExpression} AS notify_date,
            SUM(CASE WHEN status = 'SENT' THEN 1 ELSE 0 END) AS sent_total,
            SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) AS failed_total,
            SUM(CASE WHEN status = 'SKIPPED' THEN 1 ELSE 0 END) AS skipped_total,
            SUM(CASE WHEN channel = 'email' AND status = 'SENT' THEN 1 ELSE 0 END) AS email_sent,
            SUM(CASE WHEN channel = 'sms' AND status = 'SENT' THEN 1 ELSE 0 END) AS sms_sent
         FROM notification_logs
         WHERE DATE(created_at) BETWEEN ? AND ?
         GROUP BY {$notificationBucketExpression}
         ORDER BY {$notificationBucketExpression} ASC"
    );
    $notificationChartStmt->execute([$chartStartDate, $chartEndDate]);
    $notificationRows = $notificationChartStmt->fetchAll();

    $notificationChartMap = [];
    foreach ($chartSeries as $date) {
        $notificationChartMap[$date] = [
            'notify_date' => $date,
            'sent_total' => 0,
            'failed_total' => 0,
            'skipped_total' => 0,
            'email_sent' => 0,
            'sms_sent' => 0,
            'delivery_rate' => 0,
        ];
    }

    foreach ($notificationRows as $row) {
        $dateKey = $row['notify_date'];
        if (!isset($notificationChartMap[$dateKey])) {
            continue;
        }

        $sent = (int) ($row['sent_total'] ?? 0);
        $failed = (int) ($row['failed_total'] ?? 0);
        $attempted = $sent + $failed;
        $notificationChartMap[$dateKey] = [
            'notify_date' => $dateKey,
            'sent_total' => $sent,
            'failed_total' => $failed,
            'skipped_total' => (int) ($row['skipped_total'] ?? 0),
            'email_sent' => (int) ($row['email_sent'] ?? 0),
            'sms_sent' => (int) ($row['sms_sent'] ?? 0),
            'delivery_rate' => $attempted ? (int) round(($sent / $attempted) * 100) : 0,
        ];
    }

    $mostMissedStmt = $pdo->prepare(
        "SELECT
            m.id AS medicine_id,
            m.name AS medicine_name,
            m.dosage,
            u.name AS patient_name,
            COUNT(*) AS missed_total
         FROM intake_logs l
         INNER JOIN medicines m ON m.id = l.medicine_id
         LEFT JOIN users u ON u.id = m.patient_id
         WHERE l.status = 'Skipped'
           AND DATE(l.scheduled_time) BETWEEN ? AND ?
         GROUP BY m.id, m.name, m.dosage, u.name
         ORDER BY missed_total DESC, m.name ASC
         LIMIT 8"
    );
    $mostMissedStmt->execute([$chartStartDate, $chartEndDate]);
    $mostMissedMedicines = $mostMissedStmt->fetchAll();

    $auditStmt = $pdo->prepare(
        "SELECT actor_user_id, actor_role, action_key, entity_type, entity_id, target_user_id, details_json, created_at
         FROM audit_logs
         WHERE DATE(created_at) >= ?
         ORDER BY created_at DESC
         LIMIT 12"
    );
    $auditStmt->execute([$yesterday]);
    $auditRows = array_map(static function ($row) {
        $row['details'] = [];
        if (!empty($row['details_json'])) {
            $decoded = json_decode((string) $row['details_json'], true);
            if (is_array($decoded)) {
                $row['details'] = $decoded;
            }
        }
        unset($row['details_json']);
        return $row;
    }, $auditStmt->fetchAll());

    $overuseEvents = array_values(array_filter(
        medtracker_fetch_overuse_logs($pdo, null, $chartStartDate),
        static function ($event) use ($chartEndDate) {
            $eventDate = date('Y-m-d', strtotime((string) ($event['created_at'] ?? '')));
            return $eventDate !== '' && $eventDate <= $chartEndDate;
        }
    ));

    $activeUsersStmt = $pdo->prepare(
        "SELECT activity_bucket AS usage_date, COUNT(DISTINCT user_id) AS active_users
         FROM (
            SELECT {$activityBucketFromSchedule} AS activity_bucket, patient_id AS user_id
            FROM intake_logs
            WHERE DATE(scheduled_time) BETWEEN ? AND ?

            UNION ALL

            SELECT {$activityBucketFromWater} AS activity_bucket, patient_id AS user_id
            FROM water_logs
            WHERE intake_date BETWEEN ? AND ?

            UNION ALL

            SELECT {$activityBucketFromPrescription} AS activity_bucket, prescriber_id AS user_id
            FROM medicines
            WHERE prescriber_id IS NOT NULL AND DATE(created_at) BETWEEN ? AND ?

            UNION ALL

            SELECT {$activityBucketFromPrescription} AS activity_bucket, patient_id AS user_id
            FROM medicines
            WHERE prescriber_id IS NULL AND DATE(created_at) BETWEEN ? AND ?
         ) AS activity_stream
         GROUP BY activity_bucket
         ORDER BY activity_bucket ASC"
    );
    $activeUsersStmt->execute([
        $chartStartDate, $chartEndDate,
        $chartStartDate, $chartEndDate,
        $chartStartDate, $chartEndDate,
        $chartStartDate, $chartEndDate,
    ]);
    $activeUserRows = $activeUsersStmt->fetchAll();

    $usageChartMap = [];
    $activeUserChartMap = [];
    foreach ($chartSeries as $date) {
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
        $dateKey = $period === 'year'
            ? date('Y-m-01', strtotime((string) $event['created_at']))
            : date('Y-m-d', strtotime((string) $event['created_at']));
        if (!isset($usageChartMap[$dateKey])) {
            continue;
        }

        $usageChartMap[$dateKey]['overuse']++;
    }

    foreach ($activeUserRows as $row) {
        $dateKey = $row['usage_date'];
        if (!isset($activeUserChartMap[$dateKey])) {
            continue;
        }

        $activeUserChartMap[$dateKey]['active_users'] = (int) ($row['active_users'] ?? 0);
    }

    $totalUsers = $roleCounts['Admin'] + $roleCounts['Health Worker'] + $roleCounts['User'];
    $activeUsersWeeklyPeak = max(array_map(
        static fn($row) => (int) ($row['active_users'] ?? 0),
        $activeUserChartMap
    ));

    $notificationSentTotal = array_sum(array_map(static fn($row) => (int) ($row['sent_total'] ?? 0), $notificationChartMap));
    $notificationFailedTotal = array_sum(array_map(static fn($row) => (int) ($row['failed_total'] ?? 0), $notificationChartMap));
    $notificationAttemptedTotal = max(1, $notificationSentTotal + $notificationFailedTotal);
    $notificationDeliveryRate = (int) round(($notificationSentTotal / $notificationAttemptedTotal) * 100);
    $usageGraphDescription = $period === 'day'
        ? 'Selected-day system activity with status breakdown and overuse monitoring.'
        : ($period === 'year'
            ? 'Monthly system activity across the selected year.'
            : 'Seven-day system activity with status breakdown and overuse monitoring.');
    $activeUsersDescription = $period === 'day'
        ? 'Distinct users interacting with the site on the selected day.'
        : ($period === 'year'
            ? 'Distinct active users grouped by month for the selected year.'
            : 'Distinct users interacting with the site each day in the selected week.');
    $notificationDescription = $period === 'day'
        ? 'Delivery performance for the selected day across email and SMS reminder channels.'
        : ($period === 'year'
            ? 'Monthly delivery performance across email and SMS reminder channels.'
            : 'Daily delivery performance across email and SMS reminder channels.');
    $peakLabel = $period === 'day'
        ? 'Selected Day'
        : ($period === 'year' ? 'Yearly Peak' : 'Weekly Peak');
    $peakDescription = $period === 'day'
        ? 'Active users recorded on the selected day'
        : ($period === 'year'
            ? 'Highest active-user month this year'
            : 'Highest active-user day this week');
    $failedDescription = $period === 'day'
        ? 'Email or SMS failures on the selected day'
        : ($period === 'year'
            ? 'Email or SMS failures in the selected year'
            : 'Email or SMS failures this week');
    $usageIndexText = $period === 'day'
        ? 'Bars show taken, skipped, and pending dose totals for the selected day. The red line shows same-day overuse alerts.'
        : ($period === 'year'
            ? 'Bars show monthly taken, skipped, and pending dose totals. The red line shows overuse alerts over the selected year.'
            : 'Bars show taken, skipped, and pending dose totals. The red line shows overuse alerts for the same seven-day window.');

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
            'period_active_peak' => $activeUsersWeeklyPeak,
            'weekly_active_peak' => $activeUsersWeeklyPeak,
            'notification_sent_total' => $notificationSentTotal,
            'notification_failed_total' => $notificationFailedTotal,
            'notification_delivery_rate' => $notificationDeliveryRate,
        ],
        'report_meta' => [
            'period' => $reportWindow['period'],
            'period_label' => $reportWindow['period_label'],
            'selected_date' => $selectedDate,
            'range_start' => $chartStartDate,
            'range_end' => $chartEndDate,
            'range_label' => $reportWindow['range_label'],
            'usage_graph_description' => $usageGraphDescription,
            'active_users_description' => $activeUsersDescription,
            'notification_description' => $notificationDescription,
            'peak_label' => $peakLabel,
            'peak_description' => $peakDescription,
            'failed_description' => $failedDescription,
            'usage_index_text' => $usageIndexText,
        ],
        'usage_chart' => array_values($usageChartMap),
        'active_users_chart' => array_values($activeUserChartMap),
        'notification_chart' => array_values($notificationChartMap),
        'doctor_activity' => $doctorActivity,
        'audit_trail' => $auditRows,
        'most_missed_medicines' => $mostMissedMedicines,
        'recent_logs' => $recentLogs,
        'reminder_settings' => array_values(medtracker_get_reminder_settings_map($pdo)),
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
