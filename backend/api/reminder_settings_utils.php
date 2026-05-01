<?php

function medtracker_default_reminder_settings(): array
{
    return [
        [
            'scenario_key' => '1x_daily',
            'scenario_label' => 'Once A Day',
            'dose_count' => 1,
            'upcoming_minutes' => 5,
            'missed_minutes' => 15,
            'send_due_now' => 1,
            'auto_mark_skipped' => 1,
        ],
        [
            'scenario_key' => '2x_daily',
            'scenario_label' => 'Twice A Day',
            'dose_count' => 2,
            'upcoming_minutes' => 5,
            'missed_minutes' => 15,
            'send_due_now' => 1,
            'auto_mark_skipped' => 1,
        ],
        [
            'scenario_key' => '3x_daily',
            'scenario_label' => 'Three Times A Day',
            'dose_count' => 3,
            'upcoming_minutes' => 5,
            'missed_minutes' => 15,
            'send_due_now' => 1,
            'auto_mark_skipped' => 1,
        ],
    ];
}

function medtracker_ensure_reminder_settings_schema(PDO $pdo): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    $schemaChecked = true;

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS reminder_settings (
            scenario_key VARCHAR(20) PRIMARY KEY,
            scenario_label VARCHAR(50) NOT NULL,
            dose_count TINYINT NOT NULL UNIQUE,
            upcoming_minutes INT NOT NULL DEFAULT 5,
            missed_minutes INT NOT NULL DEFAULT 15,
            send_due_now TINYINT(1) NOT NULL DEFAULT 1,
            auto_mark_skipped TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    $insertStmt = $pdo->prepare(
        "INSERT INTO reminder_settings (
            scenario_key, scenario_label, dose_count, upcoming_minutes, missed_minutes, send_due_now, auto_mark_skipped
         ) VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            scenario_label = VALUES(scenario_label),
            dose_count = VALUES(dose_count)"
    );

    foreach (medtracker_default_reminder_settings() as $row) {
        $insertStmt->execute([
            $row['scenario_key'],
            $row['scenario_label'],
            $row['dose_count'],
            $row['upcoming_minutes'],
            $row['missed_minutes'],
            $row['send_due_now'],
            $row['auto_mark_skipped'],
        ]);
    }
}

function medtracker_scenario_key_from_frequency(string $frequency): string
{
    $doseCount = medtracker_dose_count_from_frequency($frequency);
    if ($doseCount >= 3) {
        return '3x_daily';
    }
    if ($doseCount === 2) {
        return '2x_daily';
    }

    return '1x_daily';
}

function medtracker_get_reminder_settings_map(PDO $pdo): array
{
    medtracker_ensure_reminder_settings_schema($pdo);

    if (isset($GLOBALS['medtracker_reminder_settings_cache']) && is_array($GLOBALS['medtracker_reminder_settings_cache'])) {
        return $GLOBALS['medtracker_reminder_settings_cache'];
    }

    $stmt = $pdo->query(
        "SELECT scenario_key, scenario_label, dose_count, upcoming_minutes, missed_minutes, send_due_now, auto_mark_skipped, updated_at
         FROM reminder_settings
         ORDER BY dose_count ASC"
    );

    $cache = [];
    foreach ($stmt->fetchAll() as $row) {
        $cache[$row['scenario_key']] = [
            'scenario_key' => (string) $row['scenario_key'],
            'scenario_label' => (string) $row['scenario_label'],
            'dose_count' => (int) $row['dose_count'],
            'upcoming_minutes' => max(1, (int) $row['upcoming_minutes']),
            'missed_minutes' => max(1, (int) $row['missed_minutes']),
            'send_due_now' => !empty($row['send_due_now']) ? 1 : 0,
            'auto_mark_skipped' => !empty($row['auto_mark_skipped']) ? 1 : 0,
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    $GLOBALS['medtracker_reminder_settings_cache'] = $cache;

    return $cache;
}

function medtracker_reset_reminder_settings_cache(): void
{
    unset($GLOBALS['medtracker_reminder_settings_cache']);
}

function medtracker_get_reminder_setting_for_frequency(PDO $pdo, string $frequency): array
{
    $map = medtracker_get_reminder_settings_map($pdo);
    $scenarioKey = medtracker_scenario_key_from_frequency($frequency);

    return $map[$scenarioKey] ?? medtracker_default_reminder_settings()[0];
}

function medtracker_get_max_upcoming_minutes(PDO $pdo): int
{
    $map = medtracker_get_reminder_settings_map($pdo);
    $values = array_map(static fn($row) => (int) ($row['upcoming_minutes'] ?? 5), array_values($map));

    return $values ? max($values) : 5;
}

function medtracker_save_reminder_settings(PDO $pdo, array $settingsRows): array
{
    medtracker_ensure_reminder_settings_schema($pdo);

    $defaultsByKey = [];
    foreach (medtracker_default_reminder_settings() as $defaultRow) {
        $defaultsByKey[$defaultRow['scenario_key']] = $defaultRow;
    }

    $upsertStmt = $pdo->prepare(
        "INSERT INTO reminder_settings (
            scenario_key, scenario_label, dose_count, upcoming_minutes, missed_minutes, send_due_now, auto_mark_skipped
         ) VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            scenario_label = VALUES(scenario_label),
            dose_count = VALUES(dose_count),
            upcoming_minutes = VALUES(upcoming_minutes),
            missed_minutes = VALUES(missed_minutes),
            send_due_now = VALUES(send_due_now),
            auto_mark_skipped = VALUES(auto_mark_skipped)"
    );

    foreach ($settingsRows as $row) {
        $scenarioKey = (string) ($row['scenario_key'] ?? '');
        if (!isset($defaultsByKey[$scenarioKey])) {
            throw new RuntimeException('Unknown reminder scenario: ' . $scenarioKey);
        }

        $defaultRow = $defaultsByKey[$scenarioKey];
        $upcomingMinutes = max(1, min((int) ($row['upcoming_minutes'] ?? $defaultRow['upcoming_minutes']), 180));
        $missedMinutes = max(1, min((int) ($row['missed_minutes'] ?? $defaultRow['missed_minutes']), 360));
        $sendDueNow = !empty($row['send_due_now']) ? 1 : 0;
        $autoMarkSkipped = !empty($row['auto_mark_skipped']) ? 1 : 0;

        $upsertStmt->execute([
            $scenarioKey,
            $defaultRow['scenario_label'],
            (int) $defaultRow['dose_count'],
            $upcomingMinutes,
            $missedMinutes,
            $sendDueNow,
            $autoMarkSkipped,
        ]);
    }

    medtracker_reset_reminder_settings_cache();

    return array_values(medtracker_get_reminder_settings_map($pdo));
}
