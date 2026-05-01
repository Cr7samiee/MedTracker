<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'adherence_utils.php';
require_once 'schedule_utils.php';
require_once 'water_utils.php';
require_once 'audit_utils.php';

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
    medtracker_ensure_audit_schema($pdo);
    medtracker_cleanup_unscheduled_pending_logs($pdo);

    $dateSeries = medtracker_build_date_series($days);
    $dateFrom = $dateSeries[0];
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    $medicineStmt = $pdo->prepare(
        "SELECT id, patient_id, prescriber_id, frequency, custom_times_json, custom_times_effective_at, treatment_mode, prescription_status, start_date, duration_days, end_date
         FROM medicines
         WHERE patient_id = ?
           AND prescription_status = 'active'
           AND start_date <= ?
           AND (end_date IS NULL OR end_date >= ?)"
    );
    $medicineStmt->execute([$userId, $today, $dateFrom]);
    foreach ($medicineStmt->fetchAll() as $medicine) {
        medtracker_create_schedule_logs($pdo, $medicine);
    }

    $logStmt = $pdo->prepare(
        "SELECT DATE(scheduled_time) AS log_date, status, COUNT(*) AS total
         FROM intake_logs
         WHERE patient_id = ?
           AND scheduled_time >= ?
           AND scheduled_time <= ?
         GROUP BY DATE(scheduled_time), status
         ORDER BY DATE(scheduled_time) ASC"
    );
    $logStmt->execute([$userId, $dateFrom . ' 00:00:00', $now]);
    $logRows = $logStmt->fetchAll();

    $detailStmt = $pdo->prepare(
        "SELECT
            DATE(l.scheduled_time) AS log_date,
            l.status,
            l.scheduled_time,
            l.taken_at,
            l.skip_reason,
            m.frequency,
            m.name AS medicine_name,
            m.id AS medicine_id
         FROM intake_logs l
         INNER JOIN medicines m ON m.id = l.medicine_id
         WHERE l.patient_id = ?
           AND l.scheduled_time >= ?
           AND l.scheduled_time <= ?
         ORDER BY l.scheduled_time ASC"
    );
    $detailStmt->execute([$userId, $dateFrom . ' 00:00:00', $now]);
    $detailRows = $detailStmt->fetchAll();

    $activeMedicineStmt = $pdo->prepare(
        "SELECT id, name, dosage, type, quantity, created_at
         FROM medicines
         WHERE patient_id = ?
           AND prescription_status = 'active'
         ORDER BY name ASC"
    );
    $activeMedicineStmt->execute([$userId]);
    $activeMedicines = $activeMedicineStmt->fetchAll();
    $trackedActiveMedicines = array_values(array_filter(
        $activeMedicines,
        static fn($medicine) => medtracker_uses_stock_tracking($medicine['type'] ?? null)
    ));
    $lowStockMeds = array_values(array_filter(
        $trackedActiveMedicines,
        static fn($medicine) => (int) ($medicine['quantity'] ?? 0) <= 5
    ));
    usort($lowStockMeds, static function ($left, $right) {
        $quantityComparison = ((int) ($left['quantity'] ?? 0)) <=> ((int) ($right['quantity'] ?? 0));
        if ($quantityComparison !== 0) {
            return $quantityComparison;
        }

        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });

    $overuseEvents = medtracker_fetch_overuse_logs($pdo, $userId, $dateFrom);

    $chartMap = [];
    foreach ($dateSeries as $date) {
        $chartMap[$date] = [
            'label' => date('M j', strtotime($date)),
            'taken' => 0,
            'skipped' => 0,
            'pending' => 0,
            'late' => 0,
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

    $takenTotal = 0;
    $lateTakenTotal = 0;
    $skippedTotal = 0;
    $pendingTotal = 0;
    $lateRows = [];
    $lateAlerts = [];
    $lateByMedicineMap = [];
    $missedReasonMap = [];

    foreach ($detailRows as $row) {
        $status = (string) ($row['status'] ?? '');
        $logDate = (string) ($row['log_date'] ?? '');

        if ($status === 'Taken') {
            $takenTotal++;
        } elseif ($status === 'Skipped') {
            $skippedTotal++;
            $reason = trim((string) ($row['skip_reason'] ?? ''));
            $reasonKey = $reason !== '' ? $reason : 'No reason provided';
            $missedReasonMap[$reasonKey] = ($missedReasonMap[$reasonKey] ?? 0) + 1;
        } elseif ($status === 'Pending') {
            $pendingTotal++;
        }

        if ($status !== 'Taken' || empty($row['taken_at'])) {
            continue;
        }

        $scheduledTimestamp = strtotime((string) $row['scheduled_time']);
        $takenTimestamp = strtotime((string) $row['taken_at']);
        if ($scheduledTimestamp === false || $takenTimestamp === false) {
            continue;
        }

        $minutesLate = (int) floor(($takenTimestamp - $scheduledTimestamp) / 60);
        $lateGraceMinutes = medtracker_missed_grace_minutes((string) ($row['frequency'] ?? '1x daily'));
        if ($minutesLate <= $lateGraceMinutes) {
            continue;
        }

        $lateTakenTotal++;
        $medicineKey = (int) ($row['medicine_id'] ?? 0);
        if ($medicineKey > 0) {
            if (!isset($lateByMedicineMap[$medicineKey])) {
                $lateByMedicineMap[$medicineKey] = [
                    'medicine_id' => $medicineKey,
                    'medicine_name' => (string) ($row['medicine_name'] ?? 'Medicine'),
                    'late_total' => 0,
                ];
            }
            $lateByMedicineMap[$medicineKey]['late_total']++;
        }
        if (!isset($lateRows[$logDate])) {
            $lateRows[$logDate] = ['log_date' => $logDate, 'total' => 0];
        }
        $lateRows[$logDate]['total']++;
        $lateAlerts[] = [
            'type' => 'late_intake',
            'message' => ($row['medicine_name'] ?? 'Medicine') . ' was logged late at ' . date('M j g:i A', $takenTimestamp) . '.',
            'created_at' => date('Y-m-d H:i:s', $takenTimestamp),
        ];
    }

    foreach ($lateRows as $row) {
        $date = $row['log_date'];
        if (isset($chartMap[$date])) {
            $chartMap[$date]['late'] = (int) $row['total'];
        }
    }

    $waterMap = medtracker_fetch_water_map($pdo, $userId, $dateFrom);
    foreach ($chartMap as $date => &$dayData) {
        $dayData['water_ml'] = (int) ($waterMap[$date] ?? 0);
    }
    unset($dayData);

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

    foreach (array_slice(array_reverse($lateAlerts), 0, 5) as $lateAlert) {
        $alerts[] = $lateAlert;
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

    $refillAuditStmt = $pdo->prepare(
        "SELECT created_at, details_json
         FROM audit_logs
         WHERE action_key = 'stock_refill'
           AND target_user_id = ?
           AND DATE(created_at) >= ?
         ORDER BY created_at DESC"
    );
    $refillAuditStmt->execute([$userId, $dateFrom]);
    $refillActions = [];
    foreach ($refillAuditStmt->fetchAll() as $row) {
        $details = json_decode((string) ($row['details_json'] ?? ''), true);
        $refillActions[] = [
            'created_at' => $row['created_at'],
            'medicine_name' => (string) ($details['medicine_name'] ?? 'Medicine'),
            'added_stock' => (int) ($details['added_stock'] ?? 0),
            'new_quantity' => (int) ($details['new_quantity'] ?? 0),
        ];
    }

    $activeMedicineCount = count($trackedActiveMedicines);
    $safeStockCount = count(array_filter($trackedActiveMedicines, static fn($medicine) => (int) ($medicine['quantity'] ?? 0) > 5));
    $outOfStockCount = count(array_filter($trackedActiveMedicines, static fn($medicine) => (int) ($medicine['quantity'] ?? 0) <= 0));
    $refillCoverageRate = $activeMedicineCount ? (int) round(($safeStockCount / $activeMedicineCount) * 100) : 100;
    $waterPercent = min(100, (int) round(($todayWater / max($waterGoal, 1)) * 100));
    $wellnessScore = (int) round(((int) ($adherenceRate ?? 0) + $waterPercent) / 2);
    arsort($missedReasonMap);
    usort($refillActions, static fn($left, $right) => strcmp($right['created_at'], $left['created_at']));
    usort($lateByMedicineMap, static fn($left, $right) => ($right['late_total'] <=> $left['late_total']) ?: strcmp($left['medicine_name'], $right['medicine_name']));

    echo json_encode([
        'success' => true,
        'summary' => [
            'adherence_rate' => $adherenceRate,
            'taken_total' => $takenTotal,
            'late_taken_total' => $lateTakenTotal,
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
            }, $dateSeries),
        ],
        'late_by_medicine' => array_values($lateByMedicineMap),
        'missed_reason_breakdown' => array_map(
            static fn($reason, $count) => ['reason' => $reason, 'count' => $count],
            array_keys($missedReasonMap),
            array_values($missedReasonMap)
        ),
        'refill_adherence' => [
            'active_medicines' => $activeMedicineCount,
            'safe_stock_count' => $safeStockCount,
            'low_stock_count' => count($lowStockMeds),
            'out_of_stock_count' => $outOfStockCount,
            'refill_actions_total' => count($refillActions),
            'refill_coverage_rate' => $refillCoverageRate,
            'recent_refills' => array_slice($refillActions, 0, 6),
            'low_stock_medicines' => array_map(static function ($medicine) {
                return [
                    'name' => (string) ($medicine['name'] ?? 'Medicine'),
                    'dosage' => (string) ($medicine['dosage'] ?? ''),
                    'quantity' => (int) ($medicine['quantity'] ?? 0),
                ];
            }, $lowStockMeds),
        ],
        'wellness_summary' => [
            'adherence_percent' => $adherenceRate,
            'hydration_percent' => $waterPercent,
            'wellness_score' => $wellnessScore,
            'water_today_ml' => $todayWater,
            'goal_ml' => $waterGoal,
        ],
        'alerts' => array_slice($alerts, 0, 8),
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
