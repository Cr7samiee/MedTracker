<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'consultation_utils.php';
require_once 'notification_utils.php';

try {
    $userId = (string) ($_SESSION['user_id'] ?? '');
    $role = trim((string) ($_SESSION['role'] ?? ''));
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $patientId = trim((string) ($data['patient_id'] ?? ''));
    $scheduledAt = trim((string) ($data['scheduled_at'] ?? ''));
    $note = trim((string) ($data['note'] ?? ''));

    if ($userId === '' || $role !== 'Health Worker') {
        echo json_encode(['success' => false, 'message' => 'Health worker session not found.']);
        exit;
    }

    medtracker_ensure_consultation_schema($pdo);

    if ($patientId === '' || $scheduledAt === '') {
        echo json_encode(['success' => false, 'message' => 'Patient and appointment time are required.']);
        exit;
    }

    if (!medtracker_worker_can_access_patient($pdo, $userId, $patientId)) {
        echo json_encode(['success' => false, 'message' => 'You can only set appointments for linked patients.']);
        exit;
    }

    $timestamp = strtotime($scheduledAt);
    if ($timestamp === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid appointment time.']);
        exit;
    }

    $dbDate = date('Y-m-d H:i:s', $timestamp);
    $stmt = $pdo->prepare(
        "INSERT INTO video_appointments (worker_id, patient_id, scheduled_at, note, created_by)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $patientId, $dbDate, $note !== '' ? substr($note, 0, 255) : null, $userId]);
    $appointmentId = (int) $pdo->lastInsertId();

    $patient = medtracker_fetch_user_contact($pdo, $patientId);
    $doctor = medtracker_fetch_user_contact($pdo, $userId);
    $notificationSummary = null;

    if ($patient) {
        $doctorName = $doctor['name'] ?? 'your health worker';
        $appointmentLabel = date('M j, Y h:i A', $timestamp);
        $safeDoctorName = htmlspecialchars($doctorName, ENT_QUOTES);
        $safeAppointmentLabel = htmlspecialchars($appointmentLabel, ENT_QUOTES);
        $safeNote = htmlspecialchars($note !== '' ? $note : 'Please join from your MedTracker video consultation page.', ENT_QUOTES);

        $notificationResults = medtracker_send_user_notifications($pdo, $patient, [
            'channels' => ['email'],
            'event_key' => 'video_appointment_' . $appointmentId,
            'notification_type' => 'VIDEO_APPOINTMENT',
            'email_subject' => 'Video Consultation Appointment',
            'email_html' => "
                <p><strong>{$safeDoctorName}</strong> has scheduled a video consultation with you.</p>
                <p><strong>Appointment time:</strong> {$safeAppointmentLabel}</p>
                <p><strong>Note:</strong> {$safeNote}</p>
                <p>Open MedTracker and go to <strong>Video Call</strong> to join the consultation room.</p>
            ",
            'email_text' => "{$doctorName} scheduled a video consultation for {$appointmentLabel}. Note: " . ($note !== '' ? $note : 'Open MedTracker and go to Video Call to join.'),
        ]);
        $notificationSummary = medtracker_build_notification_summary($notificationResults);
    }

    echo json_encode(['success' => true, 'message' => 'Video appointment set.', 'notification_summary' => $notificationSummary]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
