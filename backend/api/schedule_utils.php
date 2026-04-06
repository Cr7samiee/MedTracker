<?php

function medtracker_ensure_schedule_schema(PDO $pdo): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    $schemaChecked = true;

    $columns = $pdo->query("SHOW COLUMNS FROM medicines")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('start_date', $columns, true)) {
        $pdo->exec("ALTER TABLE medicines ADD COLUMN start_date DATE DEFAULT NULL AFTER frequency");
    }

    if (!in_array('duration_days', $columns, true)) {
        $pdo->exec("ALTER TABLE medicines ADD COLUMN duration_days INT DEFAULT NULL AFTER start_date");
    }

    if (!in_array('end_date', $columns, true)) {
        $pdo->exec("ALTER TABLE medicines ADD COLUMN end_date DATE DEFAULT NULL AFTER duration_days");
    }

    $pdo->exec("UPDATE medicines SET start_date = COALESCE(start_date, DATE(created_at))");
    $pdo->exec("UPDATE medicines SET duration_days = COALESCE(duration_days, 30)");
    $pdo->exec("UPDATE medicines SET end_date = COALESCE(end_date, DATE_SUB(DATE_ADD(start_date, INTERVAL duration_days DAY), INTERVAL 1 DAY))");
}

function medtracker_normalize_role(?string $role): string
{
    $role = strtolower(trim((string) $role));

    if ($role === 'admin') {
        return 'Admin';
    }

    if ($role === 'health worker' || $role === 'healthworker' || $role === 'doctor') {
        return 'Health Worker';
    }

    return 'User';
}

function medtracker_parse_frequency_slots(string $frequency): array
{
    $normalized = strtolower(trim($frequency));

    if (strpos($normalized, '3x') !== false || strpos($normalized, 'three') !== false) {
        return ['08:00:00', '13:00:00', '20:00:00'];
    }

    if (strpos($normalized, '2x') !== false || strpos($normalized, 'twice') !== false) {
        return ['08:00:00', '20:00:00'];
    }

    if (strpos($normalized, 'evening') !== false || strpos($normalized, 'night') !== false) {
        return ['20:00:00'];
    }

    if (strpos($normalized, 'afternoon') !== false || strpos($normalized, 'lunch') !== false) {
        return ['13:00:00'];
    }

    return ['08:00:00'];
}

function medtracker_default_scheduled_time(string $frequency, ?string $date = null): string
{
    $date = $date ?: date('Y-m-d');
    $slots = medtracker_parse_frequency_slots($frequency);

    return $date . ' ' . ($slots[0] ?? '08:00:00');
}

function medtracker_calculate_end_date(string $startDate, int $durationDays): string
{
    $durationDays = max(1, $durationDays);
    $start = new DateTimeImmutable($startDate);

    return $start->modify('+' . ($durationDays - 1) . ' day')->format('Y-m-d');
}

function medtracker_create_schedule_logs(PDO $pdo, array $medicine): void
{
    $startDate = $medicine['start_date'] ?? date('Y-m-d');
    $durationDays = max(1, (int) ($medicine['duration_days'] ?? 1));
    $endDate = $medicine['end_date'] ?? medtracker_calculate_end_date($startDate, $durationDays);
    $slots = medtracker_parse_frequency_slots((string) ($medicine['frequency'] ?? ''));

    $checkStmt = $pdo->prepare(
        "SELECT id FROM intake_logs WHERE medicine_id = ? AND patient_id = ? AND scheduled_time = ? LIMIT 1"
    );
    $insertStmt = $pdo->prepare(
        "INSERT INTO intake_logs (medicine_id, patient_id, scheduled_time, status) VALUES (?, ?, ?, 'Pending')"
    );

    $current = new DateTimeImmutable($startDate);
    $lastDate = new DateTimeImmutable($endDate);

    while ($current <= $lastDate) {
        $currentDate = $current->format('Y-m-d');

        foreach ($slots as $slot) {
            $scheduledTime = $currentDate . ' ' . $slot;
            $checkStmt->execute([
                $medicine['id'],
                $medicine['patient_id'],
                $scheduledTime,
            ]);

            if (!$checkStmt->fetch()) {
                $insertStmt->execute([
                    $medicine['id'],
                    $medicine['patient_id'],
                    $scheduledTime,
                ]);
            }
        }

        $current = $current->modify('+1 day');
    }
}

function medtracker_ensure_today_log(PDO $pdo, array $medicine, ?string $date = null): void
{
    $date = $date ?: date('Y-m-d');

    medtracker_create_schedule_logs($pdo, [
        'id' => $medicine['id'],
        'patient_id' => $medicine['patient_id'],
        'frequency' => $medicine['frequency'] ?? '',
        'start_date' => $medicine['start_date'] ?? $date,
        'duration_days' => $medicine['duration_days'] ?? 1,
        'end_date' => $medicine['end_date'] ?? ($medicine['start_date'] ?? $date),
    ]);

    $checkStmt = $pdo->prepare(
        "SELECT id FROM intake_logs WHERE medicine_id = ? AND DATE(scheduled_time) = ? LIMIT 1"
    );
    $checkStmt->execute([$medicine['id'], $date]);

    if ($checkStmt->fetch()) {
        return;
    }

    $scheduledTime = medtracker_default_scheduled_time($medicine['frequency'] ?? '', $date);

    $insertStmt = $pdo->prepare(
        "INSERT INTO intake_logs (medicine_id, patient_id, scheduled_time, status) VALUES (?, ?, ?, 'Pending')"
    );
    $insertStmt->execute([
        $medicine['id'],
        $medicine['patient_id'],
        $scheduledTime,
    ]);
}
