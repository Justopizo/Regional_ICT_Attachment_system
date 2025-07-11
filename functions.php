<?php


// Sanitize input data
function sanitizeInput($data) {
    global $conn;
    return htmlspecialchars(strip_tags(trim($conn->real_escape_string($data))));
}

// Get user by ID
function getUserById($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get all applications
function getAllApplications() {
    global $conn;
    $result = $conn->query("SELECT a.*, u.full_name, u.email, u.phone FROM applications a JOIN users u ON a.user_id = u.id ORDER BY a.applied_at DESC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get applications by status
function getApplicationsByStatus($status) {
    global $conn;
    $stmt = $conn->prepare("SELECT a.*, u.full_name, u.email, u.phone FROM applications a JOIN users u ON a.user_id = u.id WHERE a.status = ? ORDER BY a.applied_at DESC");
    $stmt->bind_param("s", $status);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get applications by department
function getApplicationsByDepartment($department) {
    global $conn;
    $stmt = $conn->prepare("SELECT a.*, u.full_name, u.email, u.phone FROM applications a JOIN users u ON a.user_id = u.id WHERE a.department_preference = ? ORDER BY a.applied_at DESC");
    $stmt->bind_param("s", $department);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get user applications
function getUserApplications($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM applications WHERE user_id = ? ORDER BY applied_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get system settings
function getSystemSettings() {
    global $conn;
    $result = $conn->query("SELECT * FROM system_settings ORDER BY id DESC LIMIT 1");
    return $result->fetch_assoc();
}

// Update system settings
function updateSystemSettings($window_open, $total_slots, $slots_remaining, $updated_by) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO system_settings (application_window_open, total_slots, slots_remaining, updated_by, updated_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiii", $window_open, $total_slots, $slots_remaining, $updated_by);
    return $stmt->execute();
}

// Update application status
function updateApplicationStatus($application_id, $status, $feedback = null) {
    global $conn;
    $stmt = $conn->prepare("UPDATE applications SET status = ?, feedback = ?, processed_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $status, $feedback, $application_id);
    return $stmt->execute();
}

// Forward application
function forwardApplication($application_id, $department) {
    global $conn;
    $stmt = $conn->prepare("UPDATE applications SET status = 'forwarded', forwarded_to = ? WHERE id = ?");
    $stmt->bind_param("si", $department, $application_id);
    return $stmt->execute();
}

// Get forwarded applications
function getForwardedApplications($department) {
    global $conn;
    $stmt = $conn->prepare("SELECT a.*, u.full_name, u.email, u.phone FROM applications a JOIN users u ON a.user_id = u.id WHERE a.forwarded_to = ? AND a.status = 'forwarded' ORDER BY a.applied_at DESC");
    $stmt->bind_param("s", $department);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Upload file
function uploadFile($file, $prefix = '') {
    $target_dir = UPLOAD_DIR;
    $file_name = $prefix . '_' . basename($file["name"]);
    $target_file = $target_dir . $file_name;
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if file already exists
    if (file_exists($target_file)) {
        unlink($target_file);
    }
    
    // Check file size (5MB max)
    if ($file["size"] > 5000000) {
        return ['success' => false, 'message' => 'File is too large. Maximum size is 5MB.'];
    }
    
    // Allow certain file formats
    $allowed_types = ['pdf', 'doc', 'docx', 'jpeg', 'jpg', 'png'];
    if (!in_array($file_type, $allowed_types)) {
        return ['success' => false, 'message' => 'Only PDF, DOC, DOCX, JPEG, JPG & PNG files are allowed.'];
    }
    
    // Upload file
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ['success' => true, 'file_name' => $file_name];
    } else {
        return ['success' => false, 'message' => 'Error uploading file.'];
    }
}
?>