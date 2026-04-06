<?php
session_start();
header('Content-Type: application/json');
include_once '../config/config.php';

$data = json_decode(file_get_contents("php://input"));
$medicine_id = $data->medicine_id ?? null;
$added_stock = intval($data->added_stock ?? 0);

if (!$medicine_id || $added_stock <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid medicine ID or stock amount.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE medicines SET quantity = quantity + ? WHERE id = ?");
    $stmt->execute([$added_stock, $medicine_id]);

    echo json_encode(['success' => true, 'message' => 'Stock updated successfully.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
