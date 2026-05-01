<?php

function medtracker_ensure_water_schema(PDO $pdo): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    $schemaChecked = true;

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS water_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id VARCHAR(50) NOT NULL,
            intake_date DATE NOT NULL,
            intake_ml INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_patient_day (patient_id, intake_date),
            FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );
}

function medtracker_water_goal_ml(): int
{
    return 2000;
}

function medtracker_fetch_water_map(PDO $pdo, string $patientId, string $dateFrom): array
{
    $stmt = $pdo->prepare(
        "SELECT intake_date, intake_ml
         FROM water_logs
         WHERE patient_id = ? AND intake_date >= ?"
    );
    $stmt->execute([$patientId, $dateFrom]);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['intake_date']] = (int) $row['intake_ml'];
    }

    return $map;
}

