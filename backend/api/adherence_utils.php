<?php

function medtracker_log_overuse_event(PDO $pdo, string $patientId, int $medicineId, string $medicineName): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO system_logs (log_type, message, status) VALUES ('SYSTEM', ?, 'ERROR')"
    );
    $stmt->execute([
        sprintf('OVERUSE_ATTEMPT|%s|%d|%s', $patientId, $medicineId, $medicineName)
    ]);
}

function medtracker_fetch_overuse_logs(PDO $pdo, ?string $patientId = null, ?string $dateFrom = null): array
{
    $query = "SELECT id, message, created_at FROM system_logs WHERE log_type = 'SYSTEM' AND message LIKE 'OVERUSE_ATTEMPT|%'";
    $params = [];

    if ($dateFrom) {
        $query .= " AND DATE(created_at) >= :date_from";
        $params[':date_from'] = $dateFrom;
    }

    $query .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $events = [];

    foreach ($rows as $row) {
        $parts = explode('|', $row['message'], 4);
        if (count($parts) < 4) {
            continue;
        }

        if ($patientId !== null && $parts[1] !== $patientId) {
            continue;
        }

        $events[] = [
            'id' => (int) $row['id'],
            'patient_id' => $parts[1],
            'medicine_id' => (int) $parts[2],
            'medicine_name' => $parts[3],
            'created_at' => $row['created_at'],
        ];
    }

    return $events;
}

function medtracker_build_date_series(int $days): array
{
    $series = [];
    $today = new DateTimeImmutable('today');

    for ($offset = $days - 1; $offset >= 0; $offset--) {
        $series[] = $today->sub(new DateInterval('P' . $offset . 'D'))->format('Y-m-d');
    }

    return $series;
}
