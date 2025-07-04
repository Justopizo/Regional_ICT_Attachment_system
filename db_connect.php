<?php
require_once 'config.php';

// Database connection
$host = 'sql111.byethost33.com';
$dbname = 'b33_39391444_attachment_system';
$username = 'b33_39391444';
$password = '3062@Justin';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>