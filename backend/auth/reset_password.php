<?php
// backend/auth/reset_password.php
require_once '../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $otp = trim($_POST['otp'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if (empty($phone) || empty($otp) || empty($new_password)) {
        echo json_encode(['success' => false, 'message' => 'Phone, OTP, and new password are required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, reset_token, reset_token_expiry FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if ($user && $user['reset_token']) {
            if (strtotime($user['reset_token_expiry']) < time()) {
                echo json_encode(['success' => false, 'message' => 'OTP has expired.']);
                exit;
            }

            if (password_verify($otp, $user['reset_token'])) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
                $updateStmt->execute([$new_hash, $user['id']]);
                
                echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid OTP.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid request or user not found.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
