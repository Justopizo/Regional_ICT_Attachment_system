<?php
function process_upload($field_name, $upload_dir = 'uploads/') {
    // Check if file was uploaded
    if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception("No file uploaded for $field_name");
    }

    $file = $_FILES[$field_name];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Upload error: " . $file['error']);
    }

    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        throw new Exception("File size exceeds 5MB limit");
    }

    // Validate file type (only allow PDF)
    $allowed_types = ['application/pdf'];
    $detected_type = mime_content_type($file['tmp_name']);
    if (!in_array($detected_type, $allowed_types)) {
        throw new Exception("Only PDF files are allowed");
    }

    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        throw new Exception("Could not create upload directory");
    }

    // Generate safe filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $destination = rtrim($upload_dir, '/') . '/' . $filename;

    // Move the uploaded file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Failed to save uploaded file");
    }

    return $filename;
}
?>