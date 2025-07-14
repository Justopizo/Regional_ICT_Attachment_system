<?php
// Database configuration
$host = 'fdb1033.atspace.me';
$dbname = '4658325_attachment';
$username = '4658325_attachment';
$password = '3062Justin';

try {
    // Check if PDO is available
    if (!extension_loaded('pdo')) {
        throw new Exception('PDO extension is not loaded');
    }

    // Create connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // Set error mode (using string constant if class constant not available)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Alternative if PDO::ERRMODE_EXCEPTION not available
    // $pdo->setAttribute(3, 1); // 3 = ATTR_ERRMODE, 1 = ERRMODE_EXCEPTION
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
} catch (Exception $e) {
    die("System error: " . $e->getMessage());
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>