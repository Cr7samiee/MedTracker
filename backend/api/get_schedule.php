<?php
session_start();
header('Content-Type: application/json');
include_once '../config/config.php';

// Accept user_id/role or assume simple role based parameter for the prototype.
// Normally this would be secure tokens.
$role = $_GET['role'] ?? 'User';
$user_id = $_GET['user_id'] ?? null; 

try {
    $today = date('Y-m-d');
    
    // Core query to fetch all medicine assignments
    $query = "
        SELECT 
            m.id as medicine_id, 
            m.name as medicine_name, 
            m.dosage, 
            m.instructions,
            m.quantity as stock_remaining,
            m.patient_id, 
            u.name as patient_name,
            l.id as log_id,
            l.scheduled_time, 
            l.status 
        FROM medicines m
        LEFT JOIN intake_logs l ON m.id = l.medicine_id AND DATE(l.scheduled_time) = :today
        LEFT JOIN users u ON m.patient_id = u.id
        WHERE 1=1
    ";

    $params = [':today' => $today];

    if ($role === 'User' && $user_id) {
        $query .= " AND m.patient_id = :uid ";
        $params[':uid'] = $user_id;
    } elseif ($role === 'Health Worker' && $user_id) {
        $query .= " AND m.prescriber_id = :uid ";
        $params[':uid'] = $user_id;
    }
    // Admin gets everything

    $query .= " ORDER BY l.scheduled_time ASC, m.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $results]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
