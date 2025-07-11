<?php
// Database configuration
define('DB_HOST', 'sql111.byethost33.com');
define('DB_USER', 'b33_39391444');
define('DB_PASS', '3062@Justin');
define('DB_NAME', 'b33_39391444_attachment_system');

// Start session
session_start();

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set timezone
date_default_timezone_set('Africa/Nairobi');

// Base URL
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']));

// File upload directory
define('UPLOAD_DIR', 'uploads/');

// Ensure upload directory exists
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit();
    }
}

// Get user role
function getUserRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

// Redirect based on role
function redirectByRole() {
    if (isLoggedIn()) {
        $role = getUserRole();
        switch ($role) {
            case 'student':
                header("Location: student_dashboard.php");
                break;
            case 'hr':
                header("Location: hr_dashboard.php");
                break;
            case 'ict':
                header("Location: ict_dashboard.php");
                break;
            case 'registry':
                header("Location: registry_dashboard.php");
                break;
        }
        exit();
    }
}
?>