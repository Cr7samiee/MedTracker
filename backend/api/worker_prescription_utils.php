<?php

require_once 'assignment_utils.php';
require_once 'schedule_utils.php';

function medtracker_worker_fetch_prescription(PDO $pdo, int $medicineId, string $workerId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT
            m.*,
            u.name AS patient_name,
            u.email AS patient_email
         FROM medicines m
         INNER JOIN users u ON u.id = m.patient_id
         WHERE m.id = ?
           AND m.prescriber_id = ?
         LIMIT 1"
    );
    $stmt->execute([$medicineId, $workerId]);
    $medicine = $stmt->fetch();

    if (!$medicine) {
        return null;
    }

    if (!medtracker_worker_can_access_patient($pdo, $workerId, (string) $medicine['patient_id'])) {
        return null;
    }

    return $medicine;
}
