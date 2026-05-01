<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/schedule_utils.php';
require_once __DIR__ . '/../api/notification_utils.php';

header('Content-Type: text/plain');

$lockHandle = null;
$lockFile = __DIR__ . '/run_reminders.lock';

try {
    $lockHandle = fopen($lockFile, 'c+');
    if (!$lockHandle) {
        throw new RuntimeException('Unable to open reminder lock file.');
    }

    if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
        echo "Reminder runner skipped: another instance is already running.\n";
        exit(0);
    }

    medtracker_ensure_schedule_schema($pdo);
    medtracker_ensure_notification_schema($pdo);
    medtracker_cleanup_unscheduled_pending_logs($pdo);

    $today = date('Y-m-d');
    $activeMedicineStmt = $pdo->prepare(
        "SELECT id, patient_id, prescriber_id, frequency, custom_times_json, custom_times_effective_at, treatment_mode, prescription_status, start_date, duration_days, end_date
         FROM medicines
         WHERE start_date <= ?
           AND prescription_status = 'active'
           AND (end_date IS NULL OR end_date >= ?)"
    );
    $activeMedicineStmt->execute([$today, $today]);

    foreach ($activeMedicineStmt->fetchAll() as $medicine) {
        medtracker_create_schedule_logs($pdo, $medicine);
    }

    $now = new DateTimeImmutable();
    $candidateLimit = $now->modify('+' . medtracker_upcoming_reminder_minutes() . ' minutes')->format('Y-m-d H:i:s');

    $candidateStmt = $pdo->prepare(
        "SELECT
            l.id AS log_id,
            l.patient_id,
            l.scheduled_time,
            l.snooze_until,
            COALESCE(l.snooze_until, l.scheduled_time) AS effective_time,
            m.id AS medicine_id,
            m.name AS medicine_name,
            m.dosage,
            m.instructions,
            m.frequency
         FROM intake_logs l
         INNER JOIN medicines m ON m.id = l.medicine_id
         WHERE l.status = 'Pending'
           AND m.prescription_status = 'active'
           AND COALESCE(l.snooze_until, l.scheduled_time) <= ?
         ORDER BY COALESCE(l.snooze_until, l.scheduled_time) ASC"
    );
    $candidateStmt->execute([$candidateLimit]);

    $upcomingRows = [];
    $dueNowRows = [];
    $missedRows = [];
    foreach ($candidateStmt->fetchAll() as $row) {
        $scenarioSettings = medtracker_get_reminder_setting_for_frequency($pdo, (string) ($row['frequency'] ?? ''));
        $upcomingMinutes = max(1, (int) ($scenarioSettings['upcoming_minutes'] ?? 5));
        $missedMinutes = max(1, (int) ($scenarioSettings['missed_minutes'] ?? 15));
        $sendDueNow = !empty($scenarioSettings['send_due_now']);
        $autoMarkSkipped = !empty($scenarioSettings['auto_mark_skipped']);
        $scheduledAt = new DateTimeImmutable($row['effective_time']);
        $missedAt = $scheduledAt->modify('+' . $missedMinutes . ' minutes');
        $row['upcoming_minutes'] = $upcomingMinutes;
        $row['missed_minutes'] = $missedMinutes;
        $row['send_due_now'] = $sendDueNow;
        $row['auto_mark_skipped'] = $autoMarkSkipped;

        if ($now >= $missedAt) {
            $missedRows[] = $row;
            continue;
        }

        $upcomingAt = $scheduledAt->modify('-' . $upcomingMinutes . ' minutes');
        if ($sendDueNow && $now >= $scheduledAt) {
            $dueNowRows[] = $row;
            continue;
        }

        if ($now >= $upcomingAt && $now < $scheduledAt) {
            $upcomingRows[] = $row;
        }
    }

    $sentCount = 0;
    $autoSkippedCount = 0;
    $processedMessages = [];
    $markSkippedStmt = $pdo->prepare(
        "UPDATE intake_logs
         SET status = 'Skipped',
             skip_reason = COALESCE(skip_reason, 'Auto-marked missed after reminder window'),
             snooze_until = NULL
         WHERE id = ?
           AND status = 'Pending'"
    );

    foreach ($upcomingRows as $row) {
        $patient = medtracker_fetch_user_contact($pdo, $row['patient_id']);
        if (!$patient) {
            continue;
        }

        $scheduledAt = new DateTimeImmutable($row['effective_time']);
        $upcomingMinutes = (int) ($row['upcoming_minutes'] ?? 5);
        $eventKey = 'UPCOMING_' . $upcomingMinutes . '|' . $row['log_id'] . '|' . $scheduledAt->format('YmdHis');
        $subject = 'MedTracker Reminder: medicine due soon';
        $emailHtml = sprintf(
            'Hello %s,<br><br>Your medicine <b>%s</b> (%s) is due at <b>%s</b>, which is %d minutes from now.<br>%s<br><br>Please open MedTracker and log your dose on time.',
            htmlspecialchars($patient['name'] ?? 'User', ENT_QUOTES),
            htmlspecialchars($row['medicine_name'], ENT_QUOTES),
            htmlspecialchars($row['dosage'], ENT_QUOTES),
            htmlspecialchars($scheduledAt->format('M j, Y g:i A'), ENT_QUOTES),
            $upcomingMinutes,
            htmlspecialchars($row['instructions'] ?: 'Follow the prescribed instructions.', ENT_QUOTES)
        );
        $emailText = sprintf(
            'Hello %s, your medicine %s (%s) is due at %s, which is %d minutes from now. %s Please open MedTracker and log your dose on time.',
            $patient['name'] ?? 'User',
            $row['medicine_name'],
            $row['dosage'],
            $scheduledAt->format('M j, Y g:i A'),
            $upcomingMinutes,
            $row['instructions'] ?: 'Follow the prescribed instructions.'
        );
        $smsBody = sprintf(
            'MedTracker: %s (%s) is due at %s in %d minutes. Please take it and update your dose log.',
            $row['medicine_name'],
            $row['dosage'],
            $scheduledAt->format('g:i A'),
            $upcomingMinutes
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

    foreach ($dueNowRows as $row) {
        $patient = medtracker_fetch_user_contact($pdo, $row['patient_id']);
        if (!$patient) {
            continue;
        }

        $scheduledAt = new DateTimeImmutable($row['effective_time']);
        $missedMinutes = (int) ($row['missed_minutes'] ?? 15);
        $eventKey = 'DUE_NOW|' . $row['log_id'] . '|' . $scheduledAt->format('YmdHis');
        $subject = 'MedTracker Reminder: take medicine now';
        $emailHtml = sprintf(
            'Hello %s,<br><br>It is now time to take <b>%s</b> (%s). It was scheduled for <b>%s</b>.<br>%s<br><br>You still have %d minutes to mark this dose as taken before the system auto-marks it as missed. If you forget to mark it in time, you can still log it late afterward.',
            htmlspecialchars($patient['name'] ?? 'User', ENT_QUOTES),
            htmlspecialchars($row['medicine_name'], ENT_QUOTES),
            htmlspecialchars($row['dosage'], ENT_QUOTES),
            htmlspecialchars($scheduledAt->format('M j, Y g:i A'), ENT_QUOTES),
            htmlspecialchars($row['instructions'] ?: 'Follow the prescribed instructions.', ENT_QUOTES),
            $missedMinutes
        );
        $emailText = sprintf(
            'Hello %s, it is now time to take %s (%s). It was scheduled for %s. %s You still have %d minutes to mark this dose as taken before the system auto-marks it as missed. If you forget to mark it in time, you can still log it late afterward.',
            $patient['name'] ?? 'User',
            $row['medicine_name'],
            $row['dosage'],
            $scheduledAt->format('M j, Y g:i A'),
            $row['instructions'] ?: 'Follow the prescribed instructions.',
            $missedMinutes
        );
        $smsBody = sprintf(
            'MedTracker: It is time to take %s (%s) now. You have %d minutes to mark it as taken before it becomes missed.',
            $row['medicine_name'],
            $row['dosage'],
            $missedMinutes
        );

        $results = medtracker_send_user_notifications($pdo, $patient, [
            'channels' => ['email', 'sms'],
            'notification_type' => 'DUE_NOW_REMINDER',
            'event_key' => $eventKey,
            'medicine_id' => (int) $row['medicine_id'],
            'intake_log_id' => (int) $row['log_id'],
            'email_subject' => $subject,
            'email_html' => $emailHtml,
            'email_text' => $emailText,
            'sms_body' => $smsBody,
        ]);

        $processedMessages[] = 'DUE_NOW ' . $row['log_id'] . ' -> ' . medtracker_build_notification_summary($results);
        $sentCount += count(array_filter($results, static fn($result) => ($result['status'] ?? '') === 'SENT'));
    }

    foreach ($missedRows as $row) {
        $patient = medtracker_fetch_user_contact($pdo, $row['patient_id']);
        if (!$patient) {
            continue;
        }

        $scheduledAt = new DateTimeImmutable($row['effective_time']);
        $missedMinutes = (int) ($row['missed_minutes'] ?? 15);
        $eventKey = 'MISSED_' . $missedMinutes . '|' . $row['log_id'] . '|' . $scheduledAt->format('YmdHis');
        $subject = 'MedTracker Alert: missed medicine reminder';
        $emailHtml = sprintf(
            'Hello %s,<br><br>Your medicine <b>%s</b> (%s) was scheduled for <b>%s</b> and was still not marked as taken after %d minutes, so it has now been marked as missed.<br><br>If you already took it but forgot to log it, you can still open MedTracker and record the dose late.',
            htmlspecialchars($patient['name'] ?? 'User', ENT_QUOTES),
            htmlspecialchars($row['medicine_name'], ENT_QUOTES),
            htmlspecialchars($row['dosage'], ENT_QUOTES),
            htmlspecialchars($scheduledAt->format('M j, Y g:i A'), ENT_QUOTES),
            $missedMinutes
        );
        $emailText = sprintf(
            'Hello %s, your medicine %s (%s) was scheduled for %s and was still not marked as taken after %d minutes, so it has now been marked as missed. If you already took it but forgot to log it, you can still open MedTracker and record the dose late.',
            $patient['name'] ?? 'User',
            $row['medicine_name'],
            $row['dosage'],
            $scheduledAt->format('M j, Y g:i A'),
            $missedMinutes
        );
        $smsBody = sprintf(
            'MedTracker: %s (%s) scheduled at %s is now marked missed after %d minutes. You can still log it late in the app.',
            $row['medicine_name'],
            $row['dosage'],
            $scheduledAt->format('g:i A'),
            $missedMinutes
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

        $autoSkippedLabel = '';
        if (!empty($row['auto_mark_skipped'])) {
            $markSkippedStmt->execute([(int) $row['log_id']]);
            $autoSkippedCount += $markSkippedStmt->rowCount();
            $autoSkippedLabel = ' | AUTO-SKIPPED';
        }

        $processedMessages[] = 'MISSED ' . $row['log_id'] . ' -> ' . medtracker_build_notification_summary($results) . $autoSkippedLabel;
        $sentCount += count(array_filter($results, static fn($result) => ($result['status'] ?? '') === 'SENT'));
    }

    medtracker_append_system_log(
        $pdo,
        'SYSTEM',
        sprintf(
            'REMINDER_RUN|sent=%d|items=%d|upcoming=%d|due_now=%d|missed=%d|auto_skipped=%d',
            $sentCount,
            count($processedMessages),
            count($upcomingRows),
            count($dueNowRows),
            count($missedRows),
            $autoSkippedCount
        ),
        'SUCCESS'
    );

    echo "Reminder runner completed.\n";
    echo 'Notifications sent: ' . $sentCount . "\n";
    echo 'Upcoming reminders: ' . count($upcomingRows) . "\n";
    echo 'Take-now reminders: ' . count($dueNowRows) . "\n";
    echo 'Missed reminders: ' . count($missedRows) . "\n";
    echo 'Auto-skipped doses: ' . $autoSkippedCount . "\n";
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
} finally {
    if (is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}
