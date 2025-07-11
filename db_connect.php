<?php
$host = 'sql111.byethost33.com';
$dbname = 'b33_39391444_attachment_system';
$username = 'b33_39391444';
$password = '3062@Justin';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>