<?php
require_once 'backend/config/config.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN plain_password VARCHAR(255) NOT NULL AFTER password_hash");
    echo "Done";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Already exists";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
