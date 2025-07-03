<?php
require_once 'config.php';
require_once 'db_connect.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    die('Forbidden');
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('File upload failed');
}

$file = $_FILES['file'];
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validate file
if ($file['size'] > MAX_FILE_SIZE) {
    http_response_code(400);
    die('File exceeds maximum size of 5MB');
}

if (!in_array($file_ext, ALLOWED_TYPES)) {
    http_response_code(400);
    die('Only PDF and Word documents are allowed');
}

// Generate unique filename
$filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $file['name']);
$destination = UPLOAD_DIR . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $destination)) {
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'original_name' => $file['name']
    ]);
} else {
    http_response_code(500);
    die('Failed to move uploaded file');
}
?>