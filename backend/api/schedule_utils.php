<?php

require_once __DIR__ . '/reminder_settings_utils.php';

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

    if (!in_array('custom_times_json', $columns, true)) {
        $pdo->exec("ALTER TABLE medicines ADD COLUMN custom_times_json TEXT DEFAULT NULL AFTER frequency");
    }

    if (!in_array('custom_times_effective_at', $columns, true)) {
        $pdo->exec("ALTER TABLE medicines ADD COLUMN custom_times_effective_at DATETIME DEFAULT NULL AFTER custom_times_json");
    }

    if (!in_array('doctor_slots_json', $columns, true)) {
        $pdo->exec("ALTER TABLE medicines ADD COLUMN doctor_slots_json TEXT DEFAULT NULL AFTER custom_times_effective_at");
    }

    if (!in_array('treatment_mode', $columns, true)) {
        $pdo->exec("ALTER TABLE medicines ADD COLUMN treatment_mode ENUM('course', 'ongoing') NOT NULL DEFAULT 'course' AFTER custom_times_json");
    }

    if (!in_array('prescription_status', $columns, true)) {
        $pdo->exec("ALTER TABLE medicines ADD COLUMN prescription_status ENUM('active', 'paused', 'stopped') NOT NULL DEFAULT 'active' AFTER treatment_mode");
    }

    if (!in_array('paused_at', $columns, true)) {
        $pdo->exec("ALTER TABLE medicines ADD COLUMN paused_at DATETIME DEFAULT NULL AFTER prescription_status");
    }

    if (!in_array('stopped_at', $columns, true)) {
        $pdo->exec("ALTER TABLE medicines ADD COLUMN stopped_at DATETIME DEFAULT NULL AFTER paused_at");
    }

    if (!in_array('stop_reason', $columns, true)) {
        $pdo->exec("ALTER TABLE medicines ADD COLUMN stop_reason VARCHAR(255) DEFAULT NULL AFTER stopped_at");
    }

    $intakeColumns = $pdo->query("SHOW COLUMNS FROM intake_logs")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('skip_reason', $intakeColumns, true)) {
        $pdo->exec("ALTER TABLE intake_logs ADD COLUMN skip_reason VARCHAR(255) DEFAULT NULL AFTER taken_at");
    }

    if (!in_array('snooze_until', $intakeColumns, true)) {
        $pdo->exec("ALTER TABLE intake_logs ADD COLUMN snooze_until DATETIME DEFAULT NULL AFTER skip_reason");
    }

    if (!in_array('snooze_count', $intakeColumns, true)) {
        $pdo->exec("ALTER TABLE intake_logs ADD COLUMN snooze_count INT NOT NULL DEFAULT 0 AFTER snooze_until");
    }

    $pdo->exec("UPDATE medicines SET start_date = COALESCE(start_date, DATE(created_at))");
    $pdo->exec("UPDATE medicines SET duration_days = COALESCE(duration_days, 30)");
    $pdo->exec("UPDATE medicines SET end_date = COALESCE(end_date, DATE_SUB(DATE_ADD(start_date, INTERVAL duration_days DAY), INTERVAL 1 DAY)) WHERE treatment_mode = 'course'");
    $pdo->exec(
        "UPDATE medicines
         SET doctor_slots_json = custom_times_json,
             custom_times_json = NULL,
             custom_times_effective_at = NULL
         WHERE prescriber_id IS NOT NULL
           AND doctor_slots_json IS NULL
           AND custom_times_json IS NOT NULL
           AND custom_times_json <> ''
           AND custom_times_json <> '[]'
           AND custom_times_effective_at IS NOT NULL
           AND ABS(TIMESTAMPDIFF(SECOND, created_at, custom_times_effective_at)) <= 60"
    );
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

function medtracker_upcoming_reminder_minutes(?string $frequency = null): int
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        return 5;
    }

    if ($frequency !== null) {
        $settings = medtracker_get_reminder_setting_for_frequency($pdo, $frequency);
        return max(1, (int) ($settings['upcoming_minutes'] ?? 5));
    }

    return medtracker_get_max_upcoming_minutes($pdo);
}

function medtracker_missed_grace_minutes(?string $frequency = null): int
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        return 15;
    }

    if ($frequency !== null) {
        $settings = medtracker_get_reminder_setting_for_frequency($pdo, $frequency);
        return max(1, (int) ($settings['missed_minutes'] ?? 15));
    }

    $settingsMap = medtracker_get_reminder_settings_map($pdo);
    $values = array_map(static fn($row) => (int) ($row['missed_minutes'] ?? 15), array_values($settingsMap));

    return $values ? max($values) : 15;
}

function medtracker_normalize_reminder_time(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
        return null;
    }

    $parts = explode(':', $value);
    $hour = (int) ($parts[0] ?? 0);
    $minute = (int) ($parts[1] ?? 0);
    $second = (int) ($parts[2] ?? 0);

    if ($hour > 23 || $minute > 59 || $second > 59) {
        return null;
    }

    return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
}

function medtracker_dose_count_from_frequency(string $frequency): int
{
    $normalized = strtolower(trim($frequency));

    if (strpos($normalized, '3x') !== false || strpos($normalized, 'three') !== false || strpos($normalized, 'thrice') !== false) {
        return 3;
    }

    if (strpos($normalized, '2x') !== false || strpos($normalized, 'twice') !== false || strpos($normalized, 'two') !== false) {
        return 2;
    }

    return 1;
}

function medtracker_uses_stock_tracking(?string $medicineType): bool
{
    $normalized = strtolower(trim((string) $medicineType));

    if ($normalized === '') {
        return true;
    }

    return strpos($normalized, 'liquid') === false
        && strpos($normalized, 'syrup') === false
        && strpos($normalized, 'inhaler') === false;
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

function medtracker_parse_custom_times($rawValue, int $expectedDoseCount): array
{
    if (is_string($rawValue) && trim($rawValue) !== '') {
        $decoded = json_decode($rawValue, true);
        $rawValue = is_array($decoded) ? $decoded : [];
    }

    if (!is_array($rawValue)) {
        return [];
    }

    $times = [];
    foreach ($rawValue as $value) {
        $normalized = medtracker_normalize_reminder_time((string) $value);
        if ($normalized) {
            $times[] = $normalized;
        }
    }

    $times = array_values(array_unique($times));
    sort($times);

    return count($times) === max(1, $expectedDoseCount) ? $times : [];
}

function medtracker_encode_custom_times(array $times): ?string
{
    $normalized = [];
    foreach ($times as $time) {
        $value = medtracker_normalize_reminder_time((string) $time);
        if ($value) {
            $normalized[] = $value;
        }
    }

    $normalized = array_values(array_unique($normalized));
    sort($normalized);

    return $normalized ? json_encode($normalized) : null;
}

function medtracker_requires_patient_time_setup(array $medicine): bool
{
    $frequency = (string) ($medicine['frequency'] ?? '');
    $expectedDoseCount = medtracker_dose_count_from_frequency($frequency);
    $customSlots = medtracker_parse_custom_times($medicine['custom_times_json'] ?? null, $expectedDoseCount);

    return count($customSlots) !== $expectedDoseCount;
}

function medtracker_resolve_schedule_slots(array $medicine): array
{
    if (medtracker_requires_patient_time_setup($medicine)) {
        return [];
    }

    $frequency = (string) ($medicine['frequency'] ?? '');
    $expectedDoseCount = medtracker_dose_count_from_frequency($frequency);
    $customSlots = medtracker_parse_custom_times($medicine['custom_times_json'] ?? null, $expectedDoseCount);
    return $customSlots;
}

function medtracker_schedule_effective_at(array $medicine): ?DateTimeImmutable
{
    $rawValue = trim((string) ($medicine['custom_times_effective_at'] ?? ''));
    if ($rawValue === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($rawValue);
    } catch (Throwable $error) {
        return null;
    }
}

function medtracker_normalize_treatment_mode(?string $value): string
{
    $value = strtolower(trim((string) $value));

    if (in_array($value, ['ongoing', 'chronic', 'long_term', 'long-term'], true)) {
        return 'ongoing';
    }

    return 'course';
}

function medtracker_is_ongoing_medicine(array $medicine): bool
{
    return medtracker_normalize_treatment_mode($medicine['treatment_mode'] ?? null) === 'ongoing';
}

function medtracker_is_active_prescription(array $medicine): bool
{
    return strtolower(trim((string) ($medicine['prescription_status'] ?? 'active'))) === 'active';
}

function medtracker_resolve_schedule_window(array $medicine, ?DateTimeImmutable $reference = null): array
{
    $reference = $reference ?: new DateTimeImmutable();
    $startDate = $medicine['start_date'] ?? $reference->format('Y-m-d');
    $start = new DateTimeImmutable($startDate);

    if (medtracker_is_ongoing_medicine($medicine)) {
        $windowStart = $reference->modify('-30 day');
        $windowEnd = $reference->modify('+30 day');

        if ($start > $windowStart) {
            $windowStart = $start;
        }

        return [$windowStart, $windowEnd];
    }

    $durationDays = max(1, (int) ($medicine['duration_days'] ?? 1));
    $endDate = $medicine['end_date'] ?? medtracker_calculate_end_date($startDate, $durationDays);

    return [$start, new DateTimeImmutable($endDate)];
}

function medtracker_calculate_end_date(string $startDate, int $durationDays): string
{
    $durationDays = max(1, $durationDays);
    $start = new DateTimeImmutable($startDate);

    return $start->modify('+' . ($durationDays - 1) . ' day')->format('Y-m-d');
}

function medtracker_create_schedule_logs(PDO $pdo, array $medicine): void
{
    if (!medtracker_is_active_prescription($medicine)) {
        return;
    }

    $slots = medtracker_resolve_schedule_slots($medicine);
    $effectiveAt = medtracker_schedule_effective_at($medicine);

    $checkStmt = $pdo->prepare(
        "SELECT id FROM intake_logs WHERE medicine_id = ? AND patient_id = ? AND scheduled_time = ? LIMIT 1"
    );
    $insertStmt = $pdo->prepare(
        "INSERT INTO intake_logs (medicine_id, patient_id, scheduled_time, status) VALUES (?, ?, ?, 'Pending')"
    );

    [$current, $lastDate] = medtracker_resolve_schedule_window($medicine);

    while ($current <= $lastDate) {
        $currentDate = $current->format('Y-m-d');

        foreach ($slots as $slot) {
            $scheduledTime = $currentDate . ' ' . $slot;
            if ($effectiveAt && new DateTimeImmutable($scheduledTime) < $effectiveAt) {
                continue;
            }
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
        'custom_times_json' => $medicine['custom_times_json'] ?? null,
        'custom_times_effective_at' => $medicine['custom_times_effective_at'] ?? null,
        'treatment_mode' => $medicine['treatment_mode'] ?? 'course',
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

    $slots = medtracker_resolve_schedule_slots($medicine);
    if (!$slots) {
        return;
    }
    $scheduledTime = $date . ' ' . ($slots[0] ?? '08:00:00');

    $insertStmt = $pdo->prepare(
        "INSERT INTO intake_logs (medicine_id, patient_id, scheduled_time, status) VALUES (?, ?, ?, 'Pending')"
    );
    $insertStmt->execute([
        $medicine['id'],
        $medicine['patient_id'],
        $scheduledTime,
    ]);
}

function medtracker_resync_future_schedule_logs(PDO $pdo, ?DateTimeImmutable $reference = null): int
{
    $reference = $reference ?: new DateTimeImmutable();
    $today = $reference->format('Y-m-d');
    $resyncedCount = 0;

    $medicineStmt = $pdo->prepare(
        "SELECT id, patient_id, prescriber_id, frequency, custom_times_json, custom_times_effective_at, treatment_mode, start_date, duration_days, end_date
         FROM medicines
         WHERE start_date <= ?
           AND (end_date IS NULL OR end_date >= ?)"
    );
    $medicineStmt->execute([$today, $today]);

    $deleteStmt = $pdo->prepare(
        "DELETE FROM intake_logs
         WHERE medicine_id = ?
           AND status = 'Pending'
           AND COALESCE(snooze_until, scheduled_time) > ?"
    );

    foreach ($medicineStmt->fetchAll() as $medicine) {
        $effectiveAt = medtracker_schedule_effective_at($medicine);
        $deleteFrom = $effectiveAt
            ? $effectiveAt->format('Y-m-d 00:00:00')
            : $reference->format('Y-m-d H:i:s');
        $deleteStmt->execute([
            $medicine['id'],
            $deleteFrom,
        ]);
        $resyncedCount += $deleteStmt->rowCount();

        medtracker_create_schedule_logs($pdo, $medicine);
    }

    return $resyncedCount;
}

function medtracker_resync_single_medicine_schedule(PDO $pdo, array $medicine, ?DateTimeImmutable $reference = null): int
{
    $reference = $reference ?: new DateTimeImmutable();
    $effectiveAt = medtracker_schedule_effective_at($medicine);
    $deleteFrom = $effectiveAt
        ? $effectiveAt->format('Y-m-d 00:00:00')
        : $reference->format('Y-m-d H:i:s');

    $deleteStmt = $pdo->prepare(
        "DELETE FROM intake_logs
         WHERE medicine_id = ?
           AND status = 'Pending'
           AND COALESCE(snooze_until, scheduled_time) > ?"
    );
    $deleteStmt->execute([
        $medicine['id'],
        $deleteFrom,
    ]);
    $deletedCount = $deleteStmt->rowCount();

    medtracker_create_schedule_logs($pdo, $medicine);

    return $deletedCount;
}

function medtracker_cleanup_unscheduled_pending_logs(PDO $pdo): int
{
    $deleteStmt = $pdo->prepare(
        "DELETE l
         FROM intake_logs l
         INNER JOIN medicines m ON m.id = l.medicine_id
         WHERE l.status = 'Pending'
           AND (m.custom_times_json IS NULL OR m.custom_times_json = '' OR m.custom_times_json = '[]')"
    );
    $deleteStmt->execute();

    return $deleteStmt->rowCount();
}

function medtracker_delete_pending_logs_from(PDO $pdo, int $medicineId, string $fromDateTime): int
{
    $deleteStmt = $pdo->prepare(
        "DELETE FROM intake_logs
         WHERE medicine_id = ?
           AND status = 'Pending'
           AND COALESCE(snooze_until, scheduled_time) >= ?"
    );
    $deleteStmt->execute([$medicineId, $fromDateTime]);

    return $deleteStmt->rowCount();
}
