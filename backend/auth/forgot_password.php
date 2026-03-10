<?php
// backend/auth/forgot_password.php
require_once '../config/config.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');

    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Phone number is required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate a 6-digit OTP
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $hashed_otp = password_hash($otp, PASSWORD_DEFAULT);
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE phone = ?");
            $updateStmt->execute([$hashed_otp, $expiry, $phone]);

            // Send this OTP via Email using PHPMailer
            $mail = new PHPMailer(true);

            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = $smtp_host;
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtp_username;
                $mail->Password   = $smtp_password;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $smtp_port;

                // Recipients
                $mail->setFrom($smtp_from_email, $smtp_from_name);
                $mail->addAddress($user['email'], $user['name']);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Your MedTracker Password Reset OTP';
                $mail->Body    = "Hello {$user['name']},<br><br>Your OTP for resetting your password is: <b>{$otp}</b><br><br>This OTP is valid for 15 minutes.<br><br>If you did not request this, please ignore this email.";
                $mail->AltBody = "Hello {$user['name']},\n\nYour OTP for resetting your password is: {$otp}\n\nThis OTP is valid for 15 minutes.\n\nIf you did not request this, please ignore this email.";

                $mail->send();
                echo json_encode(['success' => true, 'message' => 'OTP sent successfully to your registered email address.']);
            } catch (Exception $e) {
                // Return generic error or log it. We echo it for debugging but in production keep it generic.
                echo json_encode(['success' => false, 'message' => 'OTP generated but failed to send email. Mailer Error: ' . $mail->ErrorInfo]);
            }
        } else {
            // Do not reveal if the user exists or not for security, just say we sent it if valid.
            echo json_encode(['success' => false, 'message' => 'If that phone number is registered, an OTP process has started.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
