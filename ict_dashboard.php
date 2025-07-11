<?php
require_once 'config.php';
require_once 'functions.php';

// Only allow ICT admin to access this page
if (!isLoggedIn() || getUserRole() !== 'ict') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$applications = getForwardedApplications('ict');
$accepted_applications = getApplicationsByDepartment('ICT');
$error = '';

// Handle application status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $application_id = sanitizeInput($_POST['application_id']);
    $status = sanitizeInput($_POST['status']);
    $feedback = sanitizeInput($_POST['feedback']);
    
    if (updateApplicationStatus($application_id, $status, $feedback)) {
        header("Location: ict_dashboard.php?success=Status updated successfully");
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
    <title>ICT Dashboard - Kakamega ICT Authority</title>
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
                <h3>ICT Dashboard</h3>
            </div>
            <ul class="sidebar-menu">
                <li class="active"><a href="ict_dashboard.php"><i class="fas fa-home"></i> Home</a></li>
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
                <h1>ICT Administrator Dashboard</h1>
                <div class="user-info">
                    <span><i class="fas fa-user"></i> <?php echo $user['full_name']; ?></span>
                    <span><i class="fas fa-envelope"></i> <?php echo $user['email']; ?></span>
                </div>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success"><?php echo $_GET['success']; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="dashboard-section">
                <h2><i class="fas fa-share-square"></i> Forwarded Applications (<?php echo count($applications); ?>)</h2>
                <div class="card">
                    <?php if (empty($applications)): ?>
                        <p>No applications have been forwarded to ICT department at this time.</p>
                    <?php else: ?>
                        <div class="applications-list">
                            <?php foreach ($applications as $application): ?>
                                <div class="application-item">
                                    <div class="application-header">
                                        <h4>Application #<?php echo $application['id']; ?> - <?php echo $application['full_name']; ?></h4>
                                        <span class="status-badge <?php echo $application['status']; ?>">
                                            <?php echo ucfirst($application['status']); ?>
                                        </span>
                                    </div>
                                    <div class="application-details">
                                        <p><strong>Department Preference:</strong> <?php echo $application['department_preference']; ?></p>
                                        <p><strong>Forwarded On:</strong> <?php echo date('d M Y H:i', strtotime($application['processed_at'])); ?></p>
                                        <p><strong>Contact:</strong> <?php echo $application['phone']; ?> | <?php echo $application['email']; ?></p>
                                    </div>
                                    <div class="application-actions">
                                        <a href="view_application.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-secondary">View Details</a>
                                        
                                        <form action="ict_dashboard.php" method="POST" class="inline-form">
                                            <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                            <select name="status" required>
                                                <option value="">Select Action</option>
                                                <option value="accepted">Accept</option>
                                                <option value="rejected">Reject</option>
                                            </select>
                                            <input type="text" name="feedback" placeholder="Feedback (optional)">
                                            <button type="submit" name="update_status" class="btn btn-sm btn-primary">Update</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dashboard-section">
                <h2><i class="fas fa-check-circle"></i> Accepted Applications (<?php echo count($accepted_applications); ?>)</h2>
                <div class="card">
                    <?php if (empty($accepted_applications)): ?>
                        <p>No accepted applications in ICT department at this time.</p>
                    <?php else: ?>
                        <div class="applications-list">
                            <?php foreach ($accepted_applications as $application): ?>
                                <div class="application-item">
                                    <div class="application-header">
                                        <h4>Application #<?php echo $application['id']; ?> - <?php echo $application['full_name']; ?></h4>
                                        <span class="status-badge <?php echo $application['status']; ?>">
                                            <?php echo ucfirst($application['status']); ?>
                                        </span>
                                    </div>
                                    <div class="application-details">
                                        <p><strong>Processed On:</strong> <?php echo date('d M Y H:i', strtotime($application['processed_at'])); ?></p>
                                        <?php if (!empty($application['feedback'])): ?>
                                            <p><strong>Feedback:</strong> <?php echo $application['feedback']; ?></p>
                                        <?php endif; ?>
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