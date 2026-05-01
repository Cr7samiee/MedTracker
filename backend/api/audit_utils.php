<?php

function medtracker_audit_table_exists(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'audit_logs'
         LIMIT 1"
    );
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function medtracker_ensure_audit_schema(PDO $pdo): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    if ($pdo->inTransaction()) {
        if (medtracker_audit_table_exists($pdo)) {
            $schemaChecked = true;
            return;
        }

        throw new RuntimeException('Audit log table is missing. Please import the latest database schema before continuing.');
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_user_id VARCHAR(50) DEFAULT NULL,
            actor_role VARCHAR(50) DEFAULT NULL,
            action_key VARCHAR(80) NOT NULL,
            entity_type VARCHAR(60) NOT NULL,
            entity_id VARCHAR(80) DEFAULT NULL,
            target_user_id VARCHAR(50) DEFAULT NULL,
            details_json TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_created_at (created_at),
            INDEX idx_audit_actor (actor_user_id),
            INDEX idx_audit_action (action_key)
        )"
    );

    $schemaChecked = true;
}

function medtracker_log_audit_event(
    PDO $pdo,
    ?string $actorUserId,
    ?string $actorRole,
    string $actionKey,
    string $entityType,
    ?string $entityId = null,
    ?string $targetUserId = null,
    array $details = []
): void {
    medtracker_ensure_audit_schema($pdo);

    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (
            actor_user_id,
            actor_role,
            action_key,
            entity_type,
            entity_id,
            target_user_id,
            details_json
         ) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $actorUserId,
        $actorRole,
        $actionKey,
        $entityType,
        $entityId,
        $targetUserId,
        $details ? json_encode($details) : null,
    ]);
}
