<?php

function medtracker_ensure_assignment_schema(PDO $pdo): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    $schemaChecked = true;

    $userColumns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('worker_code', $userColumns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN worker_code VARCHAR(20) DEFAULT NULL AFTER post");
    }

    if (!in_array('dob', $userColumns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN dob DATE DEFAULT NULL AFTER disease");
    }

    if (!in_array('gender', $userColumns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN gender VARCHAR(20) DEFAULT NULL AFTER dob");
    }

    if (!in_array('disease', $userColumns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN disease VARCHAR(100) DEFAULT NULL AFTER relation");
    }

    if (!in_array('address', $userColumns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN address VARCHAR(255) DEFAULT NULL AFTER gender");
    }

    $indexes = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'uniq_worker_code'")->fetchAll();
    if (!$indexes) {
        $pdo->exec("ALTER TABLE users ADD UNIQUE KEY uniq_worker_code (worker_code)");
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS caregiver_patient (
            caregiver_id VARCHAR(50) NOT NULL,
            patient_id VARCHAR(50) NOT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (caregiver_id, patient_id),
            FOREIGN KEY (caregiver_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );

    $patientIndexes = $pdo->query("SHOW INDEX FROM caregiver_patient WHERE Key_name = 'uniq_patient_assignment'")->fetchAll();
    if (!$patientIndexes) {
        // Multi-doctor support: a patient can be linked with more than one health worker.
    } else {
        $pdo->exec("ALTER TABLE caregiver_patient DROP INDEX uniq_patient_assignment");
    }

    $healthWorkers = $pdo->query(
        "SELECT id FROM users WHERE role = 'Health Worker' AND (worker_code IS NULL OR worker_code = '')"
    )->fetchAll();

    $updateStmt = $pdo->prepare("UPDATE users SET worker_code = ? WHERE id = ?");
    foreach ($healthWorkers as $worker) {
        $updateStmt->execute([medtracker_generate_worker_code((string) $worker['id']), $worker['id']]);
    }
}

function medtracker_generate_worker_code(string $workerId): string
{
    $suffix = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($workerId));
    return 'HW-' . $suffix;
}

function medtracker_find_worker_by_code(PDO $pdo, string $workerCode): ?array
{
    $normalizedCode = strtoupper(trim($workerCode));
    if ($normalizedCode === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, name, role, worker_code
         FROM users
         WHERE role = 'Health Worker' AND worker_code = ?
         LIMIT 1"
    );
    $stmt->execute([$normalizedCode]);

    return $stmt->fetch() ?: null;
}

function medtracker_get_assigned_worker(PDO $pdo, string $patientId): ?array
{
    $workers = medtracker_get_assigned_workers($pdo, $patientId);
    return $workers[0] ?? null;
}

function medtracker_get_assigned_workers(PDO $pdo, string $patientId): array
{
    $stmt = $pdo->prepare(
        "SELECT u.id, u.name, u.post, u.worker_code, cp.assigned_at
         FROM caregiver_patient cp
         INNER JOIN users u ON u.id = cp.caregiver_id
         WHERE cp.patient_id = ?
         ORDER BY cp.assigned_at DESC, u.name ASC"
    );
    $stmt->execute([$patientId]);

    return $stmt->fetchAll();
}

function medtracker_worker_can_access_patient(PDO $pdo, string $workerId, string $patientId): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM caregiver_patient
         WHERE caregiver_id = ? AND patient_id = ?
         LIMIT 1"
    );
    $stmt->execute([$workerId, $patientId]);

    return (bool) $stmt->fetchColumn();
}

function medtracker_assign_patient_to_worker(PDO $pdo, string $patientId, string $workerId, bool $allowSwitch = false): array
{
    $alreadyLinked = medtracker_worker_can_access_patient($pdo, $workerId, $patientId);

    $stmt = $pdo->prepare(
        "INSERT INTO caregiver_patient (caregiver_id, patient_id)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE assigned_at = caregiver_patient.assigned_at"
    );
    $stmt->execute([$workerId, $patientId]);

    $worker = medtracker_find_worker_by_id($pdo, $workerId);

    return [
        'success' => true,
        'worker' => $worker,
        'already_linked' => $alreadyLinked,
    ];
}

function medtracker_find_worker_by_id(PDO $pdo, string $workerId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, name, post, worker_code
         FROM users
         WHERE id = ? AND role = 'Health Worker'
         LIMIT 1"
    );
    $stmt->execute([$workerId]);

    return $stmt->fetch() ?: null;
}

function medtracker_fetch_assigned_patients(PDO $pdo, string $workerId, bool $includeUnassigned = false): array
{
    if ($includeUnassigned) {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.name, u.phone, u.email, u.relation, u.dob, u.gender, u.address
             FROM users u
             LEFT JOIN caregiver_patient cp ON cp.patient_id = u.id
             WHERE u.role = 'User'
               AND (cp.caregiver_id = :worker OR cp.caregiver_id IS NULL)
             ORDER BY
               CASE WHEN cp.caregiver_id = :worker THEN 0 ELSE 1 END,
               u.name ASC"
        );
        $stmt->execute([':worker' => $workerId]);
        return $stmt->fetchAll();
    }

    $stmt = $pdo->prepare(
        "SELECT u.id, u.name, u.phone, u.email, u.relation, u.dob, u.gender, u.address
         FROM caregiver_patient cp
         INNER JOIN users u ON u.id = cp.patient_id
         WHERE cp.caregiver_id = ?
         ORDER BY u.name ASC"
    );
    $stmt->execute([$workerId]);

    return $stmt->fetchAll();
}

function medtracker_remove_patient_worker_link(PDO $pdo, string $patientId, string $workerId): bool
{
    $stmt = $pdo->prepare(
        "DELETE FROM caregiver_patient
         WHERE caregiver_id = ? AND patient_id = ?"
    );
    $stmt->execute([$workerId, $patientId]);

    return $stmt->rowCount() > 0;
}
