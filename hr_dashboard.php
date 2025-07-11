<?php
require_once 'config.php';
require_once 'functions.php';

// Only allow HR admin to access this page
if (!isLoggedIn() || getUserRole() !== 'hr') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$settings = getSystemSettings();
$applications = getAllApplications();
$pending_applications = getApplicationsByStatus('pending');
$forwarded_applications = array_merge(
    getForwardedApplications('ict'),
    getForwardedApplications('registry')
);

// Handle application forwarding
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forward_application'])) {
    $application_id = sanitizeInput($_POST['application_id']);
    $department = sanitizeInput($_POST['department']);
    
    if (forwardApplication($application_id, $department)) {
        header("Location: hr_dashboard.php?success=Application forwarded successfully");
        exit();
    } else {
        $error = "Failed to forward application";
    }
}

// Handle application status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $application_id = sanitizeInput($_POST['application_id']);
    $status = sanitizeInput($_POST['status']);
    $feedback = sanitizeInput($_POST['feedback']);
    
    if (updateApplicationStatus($application_id, $status, $feedback)) {
        header("Location: hr_dashboard.php?success=Status updated successfully");
        exit();
    } else {
        $error = "Failed to update status";
    }
}

// Handle system settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $window_open = isset($_POST['application_window_open']) ? 1 : 0;
    $total_slots = sanitizeInput($_POST['total_slots']);
    $slots_remaining = sanitizeInput($_POST['slots_remaining']);
    
    if (updateSystemSettings($window_open, $total_slots, $slots_remaining, $user_id)) {
        header("Location: hr_dashboard.php?success=Settings updated successfully");
        exit();
    } else {
        $error = "Failed to update settings";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard - Kakamega ICT Authority</title>
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
                <h3>HR Dashboard</h3>
            </div>
            <ul class="sidebar-menu">
                <li class="active"><a href="hr_dashboard.php"><i class="fas fa-home"></i> Home</a></li>
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
                <h1>HR Administrator Dashboard</h1>
                <div class="user-info">
                    <span><i class="fas fa-user"></i> <?php echo $user['full_name']; ?></span>
                    <span><i class="fas fa-envelope"></i> <?php echo $user['email']; ?></span>
                </div>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success"><?php echo $_GET['success']; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="dashboard-section">
                <h2><i class="fas fa-cog"></i> System Settings</h2>
                <div class="card">
                    <form action="hr_dashboard.php" method="POST">
                        <div class="form-group">
                            <label for="application_window_open">Application Window</label>
                            <div class="checkbox-group">
                                <input type="checkbox" id="application_window_open" name="application_window_open" value="1" <?php echo $settings['application_window_open'] ? 'checked' : ''; ?>>
                                <label for="application_window_open">Open for Applications</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="total_slots">Total Slots Available</label>
                            <input type="number" id="total_slots" name="total_slots" value="<?php echo $settings['total_slots']; ?>" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="slots_remaining">Slots Remaining</label>
                            <input type="number" id="slots_remaining" name="slots_remaining" value="<?php echo $settings['slots_remaining']; ?>" min="0" required>
                        </div>
                        
                        <button type="submit" name="update_settings" class="btn btn-primary">Update Settings</button>
                    </form>
                </div>
            </div>
            
            <div class="dashboard-section">
                <h2><i class="fas fa-users"></i> Pending Applications (<?php echo count($pending_applications); ?>)</h2>
                <div class="card">
                    <?php if (empty($pending_applications)): ?>
                        <p>No pending applications at this time.</p>
                    <?php else: ?>
                        <div class="applications-list">
                            <?php foreach ($pending_applications as $application): ?>
                                <div class="application-item">
                                    <div class="application-header">
                                        <h4>Application #<?php echo $application['id']; ?> - <?php echo $application['full_name']; ?></h4>
                                        <span class="status-badge <?php echo $application['status']; ?>">
                                            <?php echo ucfirst($application['status']); ?>
                                        </span>
                                    </div>
                                    <div class="application-details">
                                        <p><strong>Department Preference:</strong> <?php echo $application['department_preference']; ?></p>
                                        <p><strong>Applied On:</strong> <?php echo date('d M Y H:i', strtotime($application['applied_at'])); ?></p>
                                        <p><strong>Contact:</strong> <?php echo $application['phone']; ?> | <?php echo $application['email']; ?></p>
                                    </div>
                                    <div class="application-actions">
                                        <a href="view_application.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-secondary">View Details</a>
                                        
                                        <form action="hr_dashboard.php" method="POST" class="inline-form">
                                            <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                            <select name="department" required>
                                                <option value="">Select Department</option>
                                                <option value="ict">ICT Department</option>
                                                <option value="registry">Registry Department</option>
                                            </select>
                                            <button type="submit" name="forward_application" class="btn btn-sm btn-primary">Forward</button>
                                        </form>
                                        
                                        <form action="hr_dashboard.php" method="POST" class="inline-form">
                                            <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                            <select name="status" required>
                                                <option value="">Select Status</option>
                                                <option value="rejected">Reject</option>
                                                <option value="cancelled">Cancel</option>
                                            </select>
                                            <input type="text" name="feedback" placeholder="Feedback (optional)">
                                            <button type="submit" name="update_status" class="btn btn-sm btn-warning">Update</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dashboard-section">
                <h2><i class="fas fa-share-square"></i> Forwarded Applications (<?php echo count($forwarded_applications); ?>)</h2>
                <div class="card">
                    <?php if (empty($forwarded_applications)): ?>
                        <p>No forwarded applications at this time.</p>
                    <?php else: ?>
                        <div class="applications-list">
                            <?php foreach ($forwarded_applications as $application): ?>
                                <div class="application-item">
                                    <div class="application-header">
                                        <h4>Application #<?php echo $application['id']; ?> - <?php echo $application['full_name']; ?></h4>
                                        <span class="status-badge <?php echo $application['status']; ?>">
                                            <?php echo ucfirst($application['status']); ?>
                                        </span>
                                    </div>
                                    <div class="application-details">
                                        <p><strong>Department:</strong> <?php echo ucfirst($application['forwarded_to']); ?></p>
                                        <p><strong>Preference:</strong> <?php echo $application['department_preference']; ?></p>
                                        <p><strong>Forwarded On:</strong> <?php echo date('d M Y H:i', strtotime($application['processed_at'])); ?></p>
                                    </div>
                                    <div class="application-actions">
                                        <a href="view_application.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-secondary">View Details</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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