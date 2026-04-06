<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/schedule_utils.php';
require_once __DIR__ . '/../api/notification_utils.php';

header('Content-Type: text/plain');

try {
    medtracker_ensure_schedule_schema($pdo);
    medtracker_ensure_notification_schema($pdo);

    $today = date('Y-m-d');
    $activeMedicineStmt = $pdo->prepare(
        "SELECT id, patient_id, frequency, start_date, duration_days, end_date
         FROM medicines
         WHERE end_date >= ?"
    );
    $activeMedicineStmt->execute([$today]);

    foreach ($activeMedicineStmt->fetchAll() as $medicine) {
        medtracker_create_schedule_logs($pdo, $medicine);
    }

    $now = new DateTimeImmutable();
    $upcomingLimit = $now->modify('+30 minutes')->format('Y-m-d H:i:s');
    $missedLimit = $now->modify('-30 minutes')->format('Y-m-d H:i:s');

    $upcomingStmt = $pdo->prepare(
        "SELECT
            l.id AS log_id,
            l.patient_id,
            l.scheduled_time,
            m.id AS medicine_id,
            m.name AS medicine_name,
            m.dosage,
            m.instructions
         FROM intake_logs l
         INNER JOIN medicines m ON m.id = l.medicine_id
         WHERE l.status = 'Pending'
           AND l.scheduled_time BETWEEN ? AND ?
         ORDER BY l.scheduled_time ASC"
    );
    $upcomingStmt->execute([$now->format('Y-m-d H:i:s'), $upcomingLimit]);
    $upcomingRows = $upcomingStmt->fetchAll();

    $missedStmt = $pdo->prepare(
        "SELECT
            l.id AS log_id,
            l.patient_id,
            l.scheduled_time,
            m.id AS medicine_id,
            m.name AS medicine_name,
            m.dosage,
            m.instructions
         FROM intake_logs l
         INNER JOIN medicines m ON m.id = l.medicine_id
         WHERE l.status = 'Pending'
           AND l.scheduled_time <= ?
         ORDER BY l.scheduled_time ASC"
    );
    $missedStmt->execute([$missedLimit]);
    $missedRows = $missedStmt->fetchAll();

    $sentCount = 0;
    $processedMessages = [];

    foreach ($upcomingRows as $row) {
        $patient = medtracker_fetch_user_contact($pdo, $row['patient_id']);
        if (!$patient) {
            continue;
        }

        $scheduledAt = new DateTimeImmutable($row['scheduled_time']);
        $eventKey = 'UPCOMING_30|' . $row['log_id'];
        $subject = 'MedTracker Reminder: medicine due in 30 minutes';
        $emailHtml = sprintf(
            'Hello %s,<br><br>Your medicine <b>%s</b> (%s) is due at <b>%s</b>.<br>%s<br><br>Please open MedTracker and log your dose on time.',
            htmlspecialchars($patient['name'] ?? 'User', ENT_QUOTES),
            htmlspecialchars($row['medicine_name'], ENT_QUOTES),
            htmlspecialchars($row['dosage'], ENT_QUOTES),
            htmlspecialchars($scheduledAt->format('M j, Y g:i A'), ENT_QUOTES),
            htmlspecialchars($row['instructions'] ?: 'Follow the prescribed instructions.', ENT_QUOTES)
        );
        $emailText = sprintf(
            'Hello %s, your medicine %s (%s) is due at %s. %s Please open MedTracker and log your dose on time.',
            $patient['name'] ?? 'User',
            $row['medicine_name'],
            $row['dosage'],
            $scheduledAt->format('M j, Y g:i A'),
            $row['instructions'] ?: 'Follow the prescribed instructions.'
        );
        $smsBody = sprintf(
            'MedTracker: %s (%s) is due at %s. Please take it and update your dose log.',
            $row['medicine_name'],
            $row['dosage'],
            $scheduledAt->format('g:i A')
        );

        $results = medtracker_send_user_notifications($pdo, $patient, [
            'channels' => ['email', 'sms'],
            'notification_type' => 'UPCOMING_REMINDER',
            'event_key' => $eventKey,
            'medicine_id' => (int) $row['medicine_id'],
            'intake_log_id' => (int) $row['log_id'],
            'email_subject' => $subject,
            'email_html' => $emailHtml,
            'email_text' => $emailText,
            'sms_body' => $smsBody,
        ]);

        $processedMessages[] = 'UPCOMING ' . $row['log_id'] . ' -> ' . medtracker_build_notification_summary($results);
        $sentCount += count(array_filter($results, static fn($result) => ($result['status'] ?? '') === 'SENT'));
    }

    foreach ($missedRows as $row) {
        $patient = medtracker_fetch_user_contact($pdo, $row['patient_id']);
        if (!$patient) {
            continue;
        }

        $scheduledAt = new DateTimeImmutable($row['scheduled_time']);
        $eventKey = 'MISSED_30|' . $row['log_id'];
        $subject = 'MedTracker Alert: missed medicine reminder';
        $emailHtml = sprintf(
            'Hello %s,<br><br>Your medicine <b>%s</b> (%s) was scheduled for <b>%s</b> and is still not marked as taken.<br><br>Please open MedTracker to log the dose or mark it as skipped.',
            htmlspecialchars($patient['name'] ?? 'User', ENT_QUOTES),
            htmlspecialchars($row['medicine_name'], ENT_QUOTES),
            htmlspecialchars($row['dosage'], ENT_QUOTES),
            htmlspecialchars($scheduledAt->format('M j, Y g:i A'), ENT_QUOTES)
        );
        $emailText = sprintf(
            'Hello %s, your medicine %s (%s) was scheduled for %s and is still not marked as taken. Please open MedTracker to log the dose or mark it as skipped.',
            $patient['name'] ?? 'User',
            $row['medicine_name'],
            $row['dosage'],
            $scheduledAt->format('M j, Y g:i A')
        );
        $smsBody = sprintf(
            'MedTracker: You missed %s (%s) scheduled at %s. Please open the app and update your dose status.',
            $row['medicine_name'],
            $row['dosage'],
            $scheduledAt->format('g:i A')
        );

        $results = medtracker_send_user_notifications($pdo, $patient, [
            'channels' => ['email', 'sms'],
            'notification_type' => 'MISSED_REMINDER',
            'event_key' => $eventKey,
            'medicine_id' => (int) $row['medicine_id'],
            'intake_log_id' => (int) $row['log_id'],
            'email_subject' => $subject,
            'email_html' => $emailHtml,
            'email_text' => $emailText,
            'sms_body' => $smsBody,
        ]);

        $processedMessages[] = 'MISSED ' . $row['log_id'] . ' -> ' . medtracker_build_notification_summary($results);
        $sentCount += count(array_filter($results, static fn($result) => ($result['status'] ?? '') === 'SENT'));
    }

    medtracker_append_system_log(
        $pdo,
        'SYSTEM',
        sprintf('REMINDER_RUN|sent=%d|items=%d', $sentCount, count($processedMessages)),
        'SUCCESS'
    );

    echo "Reminder runner completed.\n";
    echo 'Notifications sent: ' . $sentCount . "\n";
    echo 'Processed items: ' . count($processedMessages) . "\n";
    if ($processedMessages) {
        echo implode("\n", $processedMessages) . "\n";
    }
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO) {
        medtracker_append_system_log($pdo, 'SYSTEM', 'REMINDER_RUN_FAILED|' . $error->getMessage(), 'ERROR');
    }

    http_response_code(500);
    echo 'Reminder runner failed: ' . $error->getMessage() . "\n";
}
