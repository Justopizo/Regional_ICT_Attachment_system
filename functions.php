<?php
require_once 'db_connect.php';

// Function to sanitize input data
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to redirect users
function redirect($url) {
    header("Location: $url");
    exit();
}

// Function to check user role
function check_role($allowed_roles) {
    if (!is_logged_in() || !in_array($_SESSION['role'], $allowed_roles)) {
        redirect('index.php');
    }
}

// Function to display alert messages
function display_alert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        echo "<div style='padding: 10px; margin: 10px 0; border-radius: 4px; background-color: {$alert['bg']}; color: white;'>{$alert['message']}</div>";
        unset($_SESSION['alert']);
    }
}

// Function to set alert message
function set_alert($message, $type = 'success') {
    $bg = $type === 'success' ? '#4CAF50' : ($type === 'error' ? '#F44336' : '#2196F3');
    $_SESSION['alert'] = ['message' => $message, 'bg' => $bg];
}

// Function to get current user data
function get_current_user_data() {
    global $pdo;
    if (!is_logged_in()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get application window status
function get_application_window_status() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM system_settings LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>