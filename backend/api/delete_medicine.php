<?php
session_start();
header('Content-Type: application/json');
include_once '../config/config.php';

$data = json_decode(file_get_contents("php://input"));
$medicine_id = $data->medicine_id ?? null;

if (!$medicine_id) {
    echo json_encode(['success' => false, 'message' => 'No medicine_id provided.']);
    exit;
}

try {
    // Delete from medicines (intake_logs should cascade if schema has ON DELETE CASCADE)
    $stmt = $pdo->prepare("DELETE FROM medicines WHERE id = ?");
    $stmt->execute([$medicine_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Medicine deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Medicine not found.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
