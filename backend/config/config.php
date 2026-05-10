<?php
// config.php
date_default_timezone_set('Asia/Kathmandu');

$db_host = 'localhost';
$db_port = '3306';
$db_user = 'root';
$db_pass = '';
$db_name = 'medtracker_db';

// --- SMTP Email Configuration ---
$smtp_host = 'smtp.gmail.com';
$smtp_port = 587; // or 465 for SSL
$smtp_username = '';
$smtp_password = '';
$smtp_from_email = $smtp_username;
$smtp_from_name = 'MedTracker Alerts';

// --- Twilio SMS Configuration ---
$twilio_account_sid = '';
$twilio_auth_token = '';
$twilio_from_number = '';

try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);

    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET time_zone = '+05:45'");
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
}
