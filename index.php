<?php
require_once 'config.php';
require_once 'db_connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


// Redirect to appropriate dashboard based on role
switch ($_SESSION['role']) {
    case 'student':
        header('Location: dashboard.php');
        break;
    case 'hr':
    case 'ict':
        header('Location: review_applications.php');
        break;
    default:
        header('Location: login.php');
        break;
}
?>