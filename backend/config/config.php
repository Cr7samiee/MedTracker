<?php
// config.php
date_default_timezone_set('Asia/Kathmandu');

function medtracker_env($key, $default = '')
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

$db_host = medtracker_env('DB_HOST', 'localhost');
$db_port = medtracker_env('DB_PORT', '3306');
$db_user = medtracker_env('DB_USER', 'root');
$db_pass = medtracker_env('DB_PASS', '');
$db_name = medtracker_env('DB_NAME', 'medtracker_db');
$db_fallback_host = medtracker_env('DB_FALLBACK_HOST', '127.0.0.1');
$db_fallback_port = medtracker_env('DB_FALLBACK_PORT', '3307');
$db_fallback_user = medtracker_env('DB_FALLBACK_USER', 'medtracker_user');
$db_fallback_pass = medtracker_env('DB_FALLBACK_PASS', 'medtracker_password');
$db_fallback_name = medtracker_env('DB_FALLBACK_NAME', $db_name);
// --- SMTP Email Configuration ---

$smtp_host = medtracker_env('SMTP_HOST', 'smtp.gmail.com');
$smtp_port = (int) medtracker_env('SMTP_PORT', '587'); // or 465 for SSL
$smtp_username = medtracker_env('SMTP_USERNAME');
$smtp_password = medtracker_env('SMTP_PASSWORD');
$smtp_from_email = medtracker_env('SMTP_FROM_EMAIL', $smtp_username);
$smtp_from_name = medtracker_env('SMTP_FROM_NAME', 'MedTracker Alerts');

// --- Twilio SMS Configuration ---
// Fill these values to enable SMS reminders.
$twilio_account_sid = medtracker_env('TWILIO_ACCOUNT_SID');
$twilio_auth_token = medtracker_env('TWILIO_AUTH_TOKEN');
$twilio_from_number = medtracker_env('TWILIO_FROM_NUMBER');

if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

try {
    try {
        $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    } catch (PDOException $primaryException) {
        $pdo = new PDO("mysql:host=$db_fallback_host;port=$db_fallback_port;dbname=$db_fallback_name;charset=utf8mb4", $db_fallback_user, $db_fallback_pass);
    }

    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET time_zone = '+05:45'");
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => "Database connection failed."]));
}
?>
