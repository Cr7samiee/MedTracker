<?php

require_once 'assignment_utils.php';

function medtracker_video_room_name(string $patientId, string $workerId): string
{
    return 'medtracker-call-' . substr(hash('sha256', $patientId . '|' . $workerId), 0, 18);
}

function medtracker_ensure_consultation_schema(PDO $pdo): void
{
    medtracker_ensure_assignment_schema($pdo);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS consultation_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id VARCHAR(50) NOT NULL,
            receiver_id VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_consultation_pair (sender_id, receiver_id, created_at),
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS doctor_presence (
            worker_id VARCHAR(50) PRIMARY KEY,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'inactive',
            note VARCHAR(255) DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS video_appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            worker_id VARCHAR(50) NOT NULL,
            patient_id VARCHAR(50) NOT NULL,
            scheduled_at DATETIME NOT NULL,
            note VARCHAR(255) DEFAULT NULL,
            status ENUM('scheduled', 'cancelled', 'completed') NOT NULL DEFAULT 'scheduled',
            created_by VARCHAR(50) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_video_appointment_worker (worker_id, scheduled_at),
            INDEX idx_video_appointment_patient (patient_id, scheduled_at),
            FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        )"
    );
}

function medtracker_can_message_contact(PDO $pdo, string $actorId, string $actorRole, string $contactId): bool
{
    if ($actorRole === 'User') {
        return medtracker_worker_can_access_patient($pdo, $contactId, $actorId);
    }

    if ($actorRole === 'Health Worker') {
        return medtracker_worker_can_access_patient($pdo, $actorId, $contactId);
    }

    return false;
}

function medtracker_get_presence_map(PDO $pdo, array $workerIds): array
{
    $workerIds = array_values(array_unique(array_filter($workerIds)));
    if (!$workerIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($workerIds), '?'));
    $stmt = $pdo->prepare("SELECT worker_id, status, note, updated_at FROM doctor_presence WHERE worker_id IN ($placeholders)");
    $stmt->execute($workerIds);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(string) $row['worker_id']] = $row;
    }

    return $map;
}
