<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once 'assignment_utils.php';

$userId = $_SESSION['user_id'] ?? '';
$role = trim((string) ($_SESSION['role'] ?? ''));

if ($userId === '' || $role === '') {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$name = trim((string) ($payload['name'] ?? ''));
$phone = trim((string) ($payload['phone'] ?? ''));
$email = trim((string) ($payload['email'] ?? ''));
$gender = trim((string) ($payload['gender'] ?? ''));
$dob = trim((string) ($payload['dob'] ?? ''));
$post = trim((string) ($payload['post'] ?? ''));
$relation = trim((string) ($payload['relation'] ?? ''));
$disease = trim((string) ($payload['disease'] ?? ''));
$address = trim((string) ($payload['address'] ?? ''));

if ($name === '' || $phone === '' || $email === '' || $gender === '' || $dob === '') {
    echo json_encode(['success' => false, 'message' => 'Name, phone, email, gender, and date of birth are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

if (!preg_match('/^\d{10}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number must be 10 digits.']);
    exit;
}

if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
    echo json_encode(['success' => false, 'message' => 'Please choose a valid gender.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid date of birth.']);
    exit;
}

if (strtotime($dob) > time()) {
    echo json_encode(['success' => false, 'message' => 'Date of birth cannot be in the future.']);
    exit;
}

$normalizedRole = strtolower($role);
if ($normalizedRole === 'health worker' && $post === '') {
    echo json_encode(['success' => false, 'message' => 'Post is required for health workers.']);
    exit;
}

if ($normalizedRole === 'user' && $relation === '') {
    echo json_encode(['success' => false, 'message' => 'Relation is required for patients.']);
    exit;
}

try {
    medtracker_ensure_assignment_schema($pdo);

    $phoneCheck = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id <> ? LIMIT 1");
    $phoneCheck->execute([$phone, $userId]);
    if ($phoneCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'That phone number is already used by another account.']);
        exit;
    }

    $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
    $emailCheck->execute([$email, $userId]);
    if ($emailCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'That email is already used by another account.']);
        exit;
    }

    $updateStmt = $pdo->prepare(
        "UPDATE users
         SET name = ?, phone = ?, email = ?, gender = ?, dob = ?, post = ?, relation = ?, disease = ?, address = ?
         WHERE id = ?"
    );
    $updateStmt->execute([
        $name,
        $phone,
        $email,
        $gender,
        $dob,
        $normalizedRole === 'health worker' ? $post : null,
        $normalizedRole === 'user' ? $relation : null,
        $normalizedRole === 'user' ? $disease : null,
        $normalizedRole === 'user' ? $address : null,
        $userId
    ]);

    $fetchStmt = $pdo->prepare(
        "SELECT id, role, name, phone, email, post, relation, worker_code, disease, dob, gender, address
         FROM users
         WHERE id = ?
         LIMIT 1"
    );
    $fetchStmt->execute([$userId]);
    $user = $fetchStmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully.',
        'user' => $user,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
