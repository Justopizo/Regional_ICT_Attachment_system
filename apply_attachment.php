<?php
require_once 'config.php';
require_once 'functions.php';

// Only allow students to access this page
if (!isLoggedIn() || getUserRole() !== 'student') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$settings = getSystemSettings();
$applications = getUserApplications($user_id);
$has_pending_application = false;

foreach ($applications as $app) {
    if ($app['status'] === 'pending' || $app['status'] === 'forwarded') {
        $has_pending_application = true;
        break;
    }
}

$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $settings['application_window_open'] && !$has_pending_application) {
    $department_preference = sanitizeInput($_POST['department_preference']);
    $side_hustle = sanitizeInput($_POST['side_hustle']);
    
    // Validate uploaded files
    $application_letter = $_FILES['application_letter'];
    $insurance = $_FILES['insurance'];
    $cv = $_FILES['cv'];
    $introduction_letter = $_FILES['introduction_letter'];
    
    // Validate required fields
    if (empty($department_preference)) {
        $errors['department_preference'] = 'Department preference is required';
    }
    
    // Validate files
    $required_files = [
        'application_letter' => $application_letter,
        'insurance' => $insurance,
        'cv' => $cv,
        'introduction_letter' => $introduction_letter
    ];
    
    foreach ($required_files as $field => $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[$field] = 'File upload is required';
        }
    }
    
    // If no errors, process the application
    if (empty($errors)) {
        // Upload files
        $uploads = [];
        $upload_errors = false;
        
        foreach ($required_files as $field => $file) {
            $result = uploadFile($file, $user_id . '_' . $field);
            if ($result['success']) {
                $uploads[$field] = $result['file_name'];
            } else {
                $errors[$field] = $result['message'];
                $upload_errors = true;
            }
        }
        
        if (!$upload_errors) {
            // Insert application into database
            $stmt = $conn->prepare("INSERT INTO applications (user_id, department_preference, application_letter, insurance, cv, introduction_letter, side_hustle, applied_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("issssss", $user_id, $department_preference, $uploads['application_letter'], $uploads['insurance'], $uploads['cv'], $uploads['introduction_letter'], $side_hustle);
            
            if ($stmt->execute()) {
                // Decrement remaining slots
                if ($settings['slots_remaining'] > 0) {
                    $new_slots = $settings['slots_remaining'] - 1;
                    updateSystemSettings($settings['application_window_open'], $settings['total_slots'], $new_slots, $user_id);
                }
                
                $success = 'Application submitted successfully!';
                header("Location: student_dashboard.php");
                exit();
            } else {
                $errors['general'] = 'Failed to submit application. Please try again.';
                
                // Delete uploaded files if database insertion failed
                foreach ($uploads as $file) {
                    $file_path = UPLOAD_DIR . $file;
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Attachment - Kakamega ICT Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        <?php include 'styles.css'; ?>
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="ictlogo.jpeg" alt="Kakamega ICT Authority Logo" class="logo">
                <h3>Student Dashboard</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="student_dashboard.php"><i class="fas fa-home"></i> Home</a></li>
                <li class="active"><a href="apply_attachment.php"><i class="fas fa-file-alt"></i> Apply for Attachment</a></li>
                <li><a href="update_profile.php"><i class="fas fa-user-edit"></i> Update Profile</a></li>
                <li><a href="change_password.php"><i class="fas fa-key"></i> Change Password</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
            <div class="sidebar-footer">
                <p>Logged in as: <strong><?php echo $user['username']; ?></strong></p>
            </div>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Apply for Attachment</h1>
                <div class="user-info">
                    <span><i class="fas fa-user"></i> <?php echo $user['full_name']; ?></span>
                    <span><i class="fas fa-envelope"></i> <?php echo $user['email']; ?></span>
                </div>
            </div>
            
            <div class="dashboard-section">
                <?php if (!$settings['application_window_open']): ?>
                    <div class="alert alert-warning">
                        <h3><i class="fas fa-exclamation-triangle"></i> Application Window Closed</h3>
                        <p>The application window is currently closed. Please check back later for the next attachment period.</p>
                        <a href="student_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                    </div>
                <?php elseif ($has_pending_application): ?>
                    <div class="alert alert-info">
                        <h3><i class="fas fa-info-circle"></i> Pending Application</h3>
                        <p>You already have a pending application. Please wait for it to be processed before submitting another one.</p>
                        <a href="student_dashboard.php" class="btn btn-secondary">View Application Status</a>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <h2><i class="fas fa-file-alt"></i> Attachment Application Form</h2>
                        
                        <?php if (!empty($errors['general'])): ?>
                            <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form action="apply_attachment.php" method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="department_preference"><i class="fas fa-building"></i> Preferred Department</label>
                                <select id="department_preference" name="department_preference" required>
                                    <option value="">Select Department</option>
                                    <option value="ICT" <?php echo (isset($_POST['department_preference']) && $_POST['department_preference'] === 'ICT') ? 'selected' : ''; ?>>ICT Department</option>
                                    <option value="Registry" <?php echo (isset($_POST['department_preference']) && $_POST['department_preference'] === 'Registry') ? 'selected' : ''; ?>>Registry Department</option>
                                </select>
                                <?php if (!empty($errors['department_preference'])): ?>
                                    <span class="error"><?php echo $errors['department_preference']; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="application_letter"><i class="fas fa-file-alt"></i> Application Letter (PDF/DOC)</label>
                                <input type="file" id="application_letter" name="application_letter" accept=".pdf,.doc,.docx" required>
                                <?php if (!empty($errors['application_letter'])): ?>
                                    <span class="error"><?php echo $errors['application_letter']; ?></span>
                                <?php endif; ?>
                                <small class="form-text">Upload your formal application letter addressed to the ICT Authority</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="insurance"><i class="fas fa-file-medical"></i> Insurance Cover (PDF/DOC/Image)</label>
                                <input type="file" id="insurance" name="insurance" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                                <?php if (!empty($errors['insurance'])): ?>
                                    <span class="error"><?php echo $errors['insurance']; ?></span>
                                <?php endif; ?>
                                <small class="form-text">Upload proof of valid insurance cover</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="cv"><i class="fas fa-file-user"></i> Curriculum Vitae (PDF/DOC)</label>
                                <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx" required>
                                <?php if (!empty($errors['cv'])): ?>
                                    <span class="error"><?php echo $errors['cv']; ?></span>
                                <?php endif; ?>
                                <small class="form-text">Upload your current CV with relevant qualifications</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="introduction_letter"><i class="fas fa-file-signature"></i> University/College Introduction Letter (PDF/DOC)</label>
                                <input type="file" id="introduction_letter" name="introduction_letter" accept=".pdf,.doc,.docx" required>
                                <?php if (!empty($errors['introduction_letter'])): ?>
                                    <span class="error"><?php echo $errors['introduction_letter']; ?></span>
                                <?php endif; ?>
                                <small class="form-text">Upload official introduction letter from your institution</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="side_hustle"><i class="fas fa-business-time"></i> Side Hustles/Skills (Optional)</label>
                                <textarea id="side_hustle" name="side_hustle" rows="3"><?php echo $_POST['side_hustle'] ?? ''; ?></textarea>
                                <small class="form-text">List any side hustles, skills or projects you're involved in</small>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Application</button>
                                <a href="student_dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                    
                    <div class="card">
                        <h3><i class="fas fa-info-circle"></i> Application Status</h3>
                        <p><strong>Application Window:</strong> <?php echo $settings['application_window_open'] ? 'OPEN' : 'CLOSED'; ?></p>
                        <p><strong>Available Slots:</strong> <?php echo $settings['slots_remaining']; ?> out of <?php echo $settings['total_slots']; ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p class="copyright">© 2025 Kakamega Regional ICT Authority</p>
        <p class="credits">Developed by Justin Ratemo - 0793031269</p>
    </div>
    
    <script>
        <?php include 'script.js'; ?>
    </script>
</body>
</html>