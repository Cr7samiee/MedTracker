<?php
session_start();
require_once '../config/config.php';
require_once '../api/assignment_utils.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $post = trim($_POST['post'] ?? '');
    $relation = trim($_POST['relation'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $healthWorkerCode = strtoupper(trim($_POST['health_worker_code'] ?? ''));
    $linkedWorker = null;

    // Basic validation
    if (empty($role) || empty($name) || empty($phone) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
        exit;
    }

    if (!in_array($role, ['Admin', 'Health Worker', 'User'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
        exit;
    }

    // Role specific validation
    if ($role === 'Health Worker' && empty($post)) {
        echo json_encode(['success' => false, 'message' => 'Post is required for Health Workers.']);
        exit;
    }
    if ($role === 'User' && empty($relation)) {
        echo json_encode(['success' => false, 'message' => 'Relation is required for Users.']);
        exit;
    }
    if (empty($gender)) {
        echo json_encode(['success' => false, 'message' => 'Gender is required.']);
        exit;
    }
    if ($role === 'User' && empty($dob)) {
        echo json_encode(['success' => false, 'message' => 'Date of birth is required for Users.']);
        exit;
    }
    if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a valid date of birth.']);
        exit;
    }
    if ($dob !== '' && strtotime($dob) > time()) {
        echo json_encode(['success' => false, 'message' => 'Date of birth cannot be in the future.']);
        exit;
    }
    if ($gender !== '' && !in_array($gender, ['Male', 'Female', 'Other'], true)) {
        echo json_encode(['success' => false, 'message' => 'Please choose a valid gender.']);
        exit;
    }

    // Determine prefix and starting number
    $prefix = '';
    $startNumber = 101;
    if ($role === 'User') {
        $prefix = 'U';
    } elseif ($role === 'Admin') {
        $prefix = 'A';
    } elseif ($role === 'Health Worker') {
        $prefix = 'H';
        $startNumber = 100;
    }

    try {
        medtracker_ensure_assignment_schema($pdo);
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ? ORDER BY CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC LIMIT 1");
        $stmt->execute([$role]);
        $lastIdRow = $stmt->fetch();

        if ($lastIdRow && preg_match('/^'.$prefix.'(\d+)$/', $lastIdRow['id'], $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = $startNumber;
        }

        $id = $prefix . $nextNumber;
        $workerCode = $role === 'Health Worker' ? medtracker_generate_worker_code($id) : null;

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        if ($role === 'User' && $healthWorkerCode !== '') {
            $linkedWorker = medtracker_find_worker_by_code($pdo, $healthWorkerCode);
            if (!$linkedWorker) {
                echo json_encode(['success' => false, 'message' => 'That doctor code was not found. Please check and try again.']);
                exit;
            }
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO users (id, role, name, phone, email, password_hash, plain_password, post, relation, worker_code, dob, gender) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, $role, $name, $phone, $email, $password_hash, $password, $post ?: null, $relation ?: null, $workerCode, $dob ?: null, $gender ?: null]);

        if ($role === 'User' && !empty($linkedWorker)) {
            $assignmentResult = medtracker_assign_patient_to_worker($pdo, $id, $linkedWorker['id']);
            if (!$assignmentResult['success']) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $assignmentResult['message']]);
                exit;
            }
        }

        $pdo->commit();

        $message = 'Signup successful!';
        if ($role === 'Health Worker' && $workerCode) {
            $message .= ' Your doctor code is ' . $workerCode . '.';
        } elseif ($role === 'User' && !empty($linkedWorker)) {
            $message .= ' You are now connected with ' . $linkedWorker['name'] . '.';
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'worker_code' => $workerCode,
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e->getCode() == 23000) { // Integrity constraint violation (duplicate entry)
            echo json_encode(['success' => false, 'message' => 'Phone or Email already registered.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
