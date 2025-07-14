<?php
define('DB_HOST', 'fdb1033.atspace.me');
define('DB_USER', '4658325_attachment');
define('DB_PASS', '3062@Justin');
define('DB_NAME', '4658325_attachment');

require_once 'db_connect.php';

date_default_timezone_set('Africa/Nairobi');

define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']));
define('UPLOAD_DIR', 'uploads/');

if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit();
    }
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function redirectByRole() {
    if (isLoggedIn()) {
        $role = getUserRole();
        $redirects = [
            'student' => 'student_dashboard.php',
            'hr' => 'hr_dashboard.php',
            'ict' => 'ict_dashboard.php',
            'registry' => 'registry_dashboard.php'
        ];
        header("Location: " . ($redirects[$role] ?? 'index.php'));
        exit();
    }
}
?>