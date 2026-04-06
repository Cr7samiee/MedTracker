<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function medtracker_ensure_notification_schema(PDO $pdo): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    $schemaChecked = true;

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS notification_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(50) NOT NULL,
            medicine_id INT DEFAULT NULL,
            intake_log_id INT DEFAULT NULL,
            notification_type VARCHAR(60) NOT NULL,
            channel ENUM('email', 'sms') NOT NULL,
            event_key VARCHAR(150) DEFAULT NULL,
            recipient VARCHAR(150) DEFAULT NULL,
            status ENUM('SENT', 'FAILED', 'SKIPPED') DEFAULT 'SENT',
            response_message TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_event_channel (event_key, channel),
            INDEX idx_notification_user (user_id),
            INDEX idx_notification_log (intake_log_id)
        )"
    );
}

function medtracker_notification_already_logged(PDO $pdo, string $eventKey, string $channel): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM notification_logs
         WHERE event_key = ? AND channel = ?
         LIMIT 1"
    );
    $stmt->execute([$eventKey, $channel]);

    return (bool) $stmt->fetchColumn();
}

function medtracker_log_notification(
    PDO $pdo,
    string $userId,
    ?int $medicineId,
    ?int $intakeLogId,
    string $notificationType,
    string $channel,
    ?string $eventKey,
    ?string $recipient,
    string $status,
    string $responseMessage
): void {
    $stmt = $pdo->prepare(
        "INSERT INTO notification_logs (
            user_id, medicine_id, intake_log_id, notification_type, channel, event_key, recipient, status, response_message
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            response_message = VALUES(response_message),
            recipient = VALUES(recipient)"
    );
    $stmt->execute([
        $userId,
        $medicineId,
        $intakeLogId,
        $notificationType,
        $channel,
        $eventKey,
        $recipient,
        $status,
        $responseMessage,
    ]);
}

function medtracker_append_system_log(PDO $pdo, string $logType, string $message, string $status = 'SUCCESS'): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO system_logs (log_type, message, status) VALUES (?, ?, ?)"
    );
    $stmt->execute([$logType, $message, $status]);
}

function medtracker_fetch_user_contact(PDO $pdo, string $userId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, name, phone, email, role
         FROM users
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);

    return $stmt->fetch() ?: null;
}

function medtracker_send_email_message(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): array
{
    global $smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_from_email, $smtp_from_name;

    if (empty($smtp_host) || empty($smtp_port) || empty($smtp_username) || empty($smtp_password) || empty($smtp_from_email)) {
        return ['status' => 'SKIPPED', 'message' => 'SMTP settings are incomplete.'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_port;
        $mail->setFrom($smtp_from_email, $smtp_from_name ?: 'MedTracker Alerts');
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();

        return ['status' => 'SENT', 'message' => 'Email sent.'];
    } catch (Exception $error) {
        return ['status' => 'FAILED', 'message' => 'Email failed: ' . $mail->ErrorInfo];
    }
}

function medtracker_send_twilio_sms(string $toPhone, string $messageBody): array
{
    global $twilio_account_sid, $twilio_auth_token, $twilio_from_number;

    if (empty($twilio_account_sid) || empty($twilio_auth_token) || empty($twilio_from_number)) {
        return ['status' => 'SKIPPED', 'message' => 'Twilio settings are incomplete.'];
    }

    $url = sprintf(
        'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
        rawurlencode($twilio_account_sid)
    );

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $twilio_account_sid . ':' . $twilio_auth_token,
        CURLOPT_POSTFIELDS => http_build_query([
            'To' => $toPhone,
            'From' => $twilio_from_number,
            'Body' => $messageBody,
        ]),
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response === false || $curlError) {
        return ['status' => 'FAILED', 'message' => 'SMS failed: ' . ($curlError ?: 'Unknown cURL error')];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return ['status' => 'FAILED', 'message' => 'SMS failed: Twilio returned HTTP ' . $httpCode . '.'];
    }

    return ['status' => 'SENT', 'message' => 'SMS sent.'];
}

function medtracker_send_user_notifications(PDO $pdo, array $user, array $payload): array
{
    medtracker_ensure_notification_schema($pdo);

    $results = [];
    $channels = $payload['channels'] ?? ['email', 'sms'];
    $eventKey = $payload['event_key'] ?? null;
    $medicineId = isset($payload['medicine_id']) ? (int) $payload['medicine_id'] : null;
    $intakeLogId = isset($payload['intake_log_id']) ? (int) $payload['intake_log_id'] : null;
    $notificationType = $payload['notification_type'] ?? 'GENERAL';

    foreach ($channels as $channel) {
        if ($eventKey && medtracker_notification_already_logged($pdo, $eventKey, $channel)) {
            $results[$channel] = ['status' => 'SKIPPED', 'message' => 'Duplicate notification prevented.'];
            continue;
        }

        if ($channel === 'email') {
            if (empty($user['email'])) {
                $results[$channel] = ['status' => 'SKIPPED', 'message' => 'No registered email found.'];
            } else {
                $results[$channel] = medtracker_send_email_message(
                    $user['email'],
                    $user['name'] ?? 'User',
                    $payload['email_subject'] ?? 'MedTracker Notification',
                    $payload['email_html'] ?? '',
                    $payload['email_text'] ?? strip_tags((string) ($payload['email_html'] ?? ''))
                );
            }

            medtracker_log_notification(
                $pdo,
                $user['id'],
                $medicineId,
                $intakeLogId,
                $notificationType,
                'email',
                $eventKey,
                $user['email'] ?? null,
                $results[$channel]['status'],
                $results[$channel]['message']
            );
            medtracker_append_system_log(
                $pdo,
                'EMAIL',
                sprintf(
                    '%s|%s|%s|%s',
                    $notificationType,
                    $user['id'],
                    $results[$channel]['status'],
                    $results[$channel]['message']
                ),
                $results[$channel]['status'] === 'FAILED' ? 'ERROR' : 'SUCCESS'
            );
            continue;
        }

        if ($channel === 'sms') {
            if (empty($user['phone'])) {
                $results[$channel] = ['status' => 'SKIPPED', 'message' => 'No registered phone found.'];
            } else {
                $results[$channel] = medtracker_send_twilio_sms(
                    $user['phone'],
                    $payload['sms_body'] ?? ($payload['email_text'] ?? 'MedTracker reminder')
                );
            }

            medtracker_log_notification(
                $pdo,
                $user['id'],
                $medicineId,
                $intakeLogId,
                $notificationType,
                'sms',
                $eventKey,
                $user['phone'] ?? null,
                $results[$channel]['status'],
                $results[$channel]['message']
            );
            medtracker_append_system_log(
                $pdo,
                'SMS',
                sprintf(
                    '%s|%s|%s|%s',
                    $notificationType,
                    $user['id'],
                    $results[$channel]['status'],
                    $results[$channel]['message']
                ),
                $results[$channel]['status'] === 'FAILED' ? 'ERROR' : 'SUCCESS'
            );
        }
    }

    return $results;
}

function medtracker_build_notification_summary(array $results): string
{
    if (!$results) {
        return 'No notification channels were processed.';
    }

    $parts = [];
    foreach ($results as $channel => $result) {
        $parts[] = strtoupper($channel) . ': ' . ($result['status'] ?? 'UNKNOWN');
    }

    return implode(' | ', $parts);
}
