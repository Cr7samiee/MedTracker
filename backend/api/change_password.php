<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';

$userId = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if (!$userId || !$role) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    $data = $_POST;
}

$currentPassword = (string) ($data['current_password'] ?? '');
$newPassword = (string) ($data['new_password'] ?? '');
$confirmPassword = (string) ($data['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    echo json_encode(['success' => false, 'message' => 'Please fill in all password fields.']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
    exit;
}

if (strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, password_hash, plain_password
         FROM users
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Account not found.']);
        exit;
    }

    if (!password_verify($currentPassword, (string) $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    if (password_verify($newPassword, (string) $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Choose a different password from your current one.']);
        exit;
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo->beginTransaction();

    $updateStmt = $pdo->prepare(
        "UPDATE users
         SET password_hash = ?, plain_password = ?, reset_token = NULL, reset_token_expiry = NULL
         WHERE id = ?"
    );
    $updateStmt->execute([$newHash, $newPassword, $userId]);

    $verifyStmt = $pdo->prepare(
        "SELECT password_hash, plain_password
         FROM users
         WHERE id = ?
         LIMIT 1"
    );
    $verifyStmt->execute([$userId]);
    $updatedUser = $verifyStmt->fetch();

    $writeVerified = $updatedUser
        && (string) ($updatedUser['plain_password'] ?? '') === $newPassword
        && password_verify($newPassword, (string) ($updatedUser['password_hash'] ?? ''));

    if (!$writeVerified) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Password update could not be verified. Please try again.']);
        exit;
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Unexpected error: ' . $e->getMessage()]);
}
?>
