<?php
// config.php
$db_host = 'localhost';
$db_user = 'root'; // default XAMPP user
$db_pass = '';     // default XAMPP password
$db_name = 'medtracker_db';
// --- SMTP Email Configuration ---

$smtp_host = 'smtp.gmail.com';
$smtp_port = 587; // or 465 for SSL
$smtp_username = '';
$smtp_password = '';   
$smtp_from_email = ''; 
$smtp_from_name = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => "Database connection failed."]));
}
?>