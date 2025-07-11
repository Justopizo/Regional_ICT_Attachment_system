<?php
require_once 'config.php';
require_once 'functions.php';

// Only allow logged in users to view applications
if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// Check if application ID is provided
if (!isset($_GET['id'])) {
    header("Location: " . (getUserRole() === 'student' ? 'student_dashboard.php' : $_SESSION['role'] . '_dashboard.php'));
    exit();
}

$application_id = sanitizeInput($_GET['id']);
$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Get application details
$stmt = $conn->prepare("SELECT a.*, u.username, u.email, u.phone, u.full_name FROM applications a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();

// Check if application exists and user has permission to view it
if (!$application || ($user_role === 'student' && $application['user_id'] !== $user_id)) {
    header("Location: " . ($user_role === 'student' ? 'student_dashboard.php' : $_SESSION['role'] . '_dashboard.php'));
    exit();
}

// Handle status update for admins
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && in_array($user_role, ['hr', 'ict', 'registry'])) {
    $status = sanitizeInput($_POST['status']);
    $feedback = sanitizeInput($_POST['feedback']);
    
    if (updateApplicationStatus($application_id, $status, $feedback)) {
        header("Location: view_application.php?id=$application_id&success=Status updated successfully");
        exit();
    } else {
        $error = "Failed to update status";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application - Kakamega ICT Authority</title>
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
                <h3><?php echo ucfirst($user_role); ?> Dashboard</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="<?php echo $user_role === 'student' ? 'student_dashboard.php' : $user_role . '_dashboard.php'; ?>"><i class="fas fa-home"></i> Home</a></li>
                <?php if ($user_role === 'student'): ?>
                    <li><a href="apply_attachment.php"><i class="fas fa-file-alt"></i> Apply for Attachment</a></li>
                <?php endif; ?>
                <li><a href="update_profile.php"><i class="fas fa-user-edit"></i> Update Profile</a></li>
                <li><a href="change_password.php"><i class="fas fa-key"></i> Change Password</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
            <div class="sidebar-footer">
                <p>Logged in as: <strong><?php echo $_SESSION['username']; ?></strong></p>
            </div>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Application Details</h1>
                <div class="user-info">
                    <span><i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?></span>
                    <span><i class="fas fa-envelope"></i> <?php echo $_SESSION['email'] ?? ''; ?></span>
                </div>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success"><?php echo $_GET['success']; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="dashboard-section">
                <div class="card">
                    <div class="application-header">
                        <h2>Application #<?php echo $application['id']; ?></h2>
                        <span class="status-badge <?php echo $application['status']; ?>">
                            <?php echo ucfirst($application['status']); ?>
                        </span>
                    </div>
                    
                    <div class="application-details-grid">
                        <div class="detail-group">
                            <h3><i class="fas fa-user"></i> Applicant Information</h3>
                            <p><strong>Name:</strong> <?php echo $application['full_name']; ?></p>
                            <p><strong>Username:</strong> <?php echo $application['username']; ?></p>
                            <p><strong>Email:</strong> <?php echo $application['email']; ?></p>
                            <p><strong>Phone:</strong> <?php echo $application['phone']; ?></p>
                        </div>
                        
                        <div class="detail-group">
                            <h3><i class="fas fa-building"></i> Application Details</h3>
                            <p><strong>Department Preference:</strong> <?php echo $application['department_preference']; ?></p>
                            <p><strong>Applied On:</strong> <?php echo date('d M Y H:i', strtotime($application['applied_at'])); ?></p>
                            <?php if ($application['status'] === 'forwarded'): ?>
                                <p><strong>Forwarded To:</strong> <?php echo ucfirst($application['forwarded_to']); ?></p>
                                <p><strong>Forwarded On:</strong> <?php echo date('d M Y H:i', strtotime($application['processed_at'])); ?></p>
                            <?php elseif ($application['status'] === 'accepted' || $application['status'] === 'rejected'): ?>
                                <p><strong>Processed On:</strong> <?php echo date('d M Y H:i', strtotime($application['processed_at'])); ?></p>
                                <?php if (!empty($application['feedback'])): ?>
                                    <p><strong>Feedback:</strong> <?php echo $application['feedback']; ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="detail-group">
                            <h3><i class="fas fa-business-time"></i> Side Hustles/Skills</h3>
                            <p><?php echo !empty($application['side_hustle']) ? $application['side_hustle'] : 'Not specified'; ?></p>
                        </div>
                        
                        <div class="detail-group documents-group">
                            <h3><i class="fas fa-file"></i> Documents</h3>
                            <div class="documents-list">
                                <a href="<?php echo UPLOAD_DIR . $application['application_letter']; ?>" target="_blank" class="document-item">
                                    <i class="fas fa-file-alt"></i> Application Letter
                                </a>
                                <a href="<?php echo UPLOAD_DIR . $application['insurance']; ?>" target="_blank" class="document-item">
                                    <i class="fas fa-file-medical"></i> Insurance Cover
                                </a>
                                <a href="<?php echo UPLOAD_DIR . $application['cv']; ?>" target="_blank" class="document-item">
                                    <i class="fas fa-file-user"></i> Curriculum Vitae
                                </a>
                                <a href="<?php echo UPLOAD_DIR . $application['introduction_letter']; ?>" target="_blank" class="document-item">
                                    <i class="fas fa-file-signature"></i> Introduction Letter
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (in_array($user_role, ['hr', 'ict', 'registry']) && ($application['status'] === 'pending' || $application['status'] === 'forwarded')): ?>
                        <div class="status-update-form">
                            <h3><i class="fas fa-edit"></i> Update Application Status</h3>
                            <form action="view_application.php?id=<?php echo $application_id; ?>" method="POST">
                                <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                                
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" required>
                                        <?php if ($user_role === 'hr' && $application['status'] === 'pending'): ?>
                                            <option value="">Select Status</option>
                                            <option value="forwarded">Forward to Department</option>
                                            <option value="rejected">Reject</option>
                                            <option value="cancelled">Cancel</option>
                                        <?php elseif (($user_role === 'ict' || $user_role === 'registry') && $application['status'] === 'forwarded' && $application['forwarded_to'] === strtolower($user_role)): ?>
                                            <option value="">Select Status</option>
                                            <option value="accepted">Accept</option>
                                            <option value="rejected">Reject</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="feedback">Feedback (Optional)</label>
                                    <textarea id="feedback" name="feedback" rows="3"></textarea>
                                </div>
                                
                                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                            </form>
                        </div>
                    <?php endif; ?>
                    
                    <div class="back-button">
                        <a href="<?php echo $user_role === 'student' ? 'student_dashboard.php' : $user_role . '_dashboard.php'; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
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