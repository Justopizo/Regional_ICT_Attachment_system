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
$applications = getUserApplications($user_id);
$settings = getSystemSettings();
$has_pending_application = false;

foreach ($applications as $app) {
    if ($app['status'] === 'pending' || $app['status'] === 'forwarded') {
        $has_pending_application = true;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Kakamega ICT Authority</title>
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
                <li class="active"><a href="student_dashboard.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="apply_attachment.php"><i class="fas fa-file-alt"></i> Apply for Attachment</a></li>
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
                <h1>Welcome, <?php echo $user['full_name']; ?></h1>
                <div class="user-info">
                    <span><i class="fas fa-envelope"></i> <?php echo $user['email']; ?></span>
                    <span><i class="fas fa-phone"></i> <?php echo $user['phone']; ?></span>
                </div>
            </div>
            
            <div class="dashboard-section">
                <h2><i class="fas fa-info-circle"></i> About Kakamega ICT Authority Attachment</h2>
                <div class="card">
                    <p>The Kakamega Regional ICT Authority offers attachment opportunities to students from various institutions to gain practical experience in ICT and related fields. Our program provides hands-on training and exposure to real-world projects.</p>
                    
                    <h3><i class="fas fa-requirements"></i> Requirements</h3>
                    <ul class="requirements-list">
                        <li><i class="fas fa-file-alt"></i> Application Letter</li>
                        <li><i class="fas fa-file-medical"></i> Insurance Cover</li>
                        <li><i class="fas fa-file-user"></i> Curriculum Vitae (CV)</li>
                        <li><i class="fas fa-file-signature"></i> University/College Introduction Letter</li>
                    </ul>
                    
                    <h3><i class="fas fa-rules"></i> Rules & Regulations</h3>
                    <ul class="rules-list">
                        <li><i class="fas fa-clock"></i> Arrival Time: 8:00 AM</li>
                        <li><i class="fas fa-clock"></i> Departure Time: 5:00 PM</li>
                        <li><i class="fas fa-tshirt"></i> Dress Code: Official Attire</li>
                        <li><i class="fas fa-money-bill-wave"></i> No form of payment is required for attachment</li>
                        <li><i class="fas fa-user-shield"></i> Maintain professional conduct at all times</li>
                    </ul>
                </div>
            </div>
            
            <div class="dashboard-section">
                <h2><i class="fas fa-clipboard-list"></i> Application Status</h2>
                <div class="card">
                    <?php if ($settings['application_window_open']): ?>
                        <div class="alert alert-info">
                            <p><strong>Application Window:</strong> OPEN</p>
                            <p><strong>Slots Available:</strong> <?php echo $settings['slots_remaining']; ?> out of <?php echo $settings['total_slots']; ?></p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <p><strong>Application Window:</strong> CLOSED</p>
                            <p>Please check back later for the next application period.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($applications)): ?>
                        <p>You haven't submitted any applications yet.</p>
                        <?php if ($settings['application_window_open']): ?>
                            <a href="apply_attachment.php" class="btn btn-primary"><i class="fas fa-plus"></i> Apply Now</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="applications-list">
                            <?php foreach ($applications as $application): ?>
                                <div class="application-item">
                                    <div class="application-header">
                                        <h4>Application #<?php echo $application['id']; ?></h4>
                                        <span class="status-badge <?php echo $application['status']; ?>">
                                            <?php echo ucfirst($application['status']); ?>
                                        </span>
                                    </div>
                                    <div class="application-details">
                                        <p><strong>Department:</strong> <?php echo $application['department_preference']; ?></p>
                                        <p><strong>Applied On:</strong> <?php echo date('d M Y H:i', strtotime($application['applied_at'])); ?></p>
                                        <?php if ($application['status'] === 'rejected' || $application['status'] === 'accepted'): ?>
                                            <p><strong>Processed On:</strong> <?php echo date('d M Y H:i', strtotime($application['processed_at'])); ?></p>
                                            <?php if (!empty($application['feedback'])): ?>
                                                <p><strong>Feedback:</strong> <?php echo $application['feedback']; ?></p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <a href="view_application.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-secondary">View Details</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($settings['application_window_open'] && !$has_pending_application): ?>
                            <a href="apply_attachment.php" class="btn btn-primary"><i class="fas fa-plus"></i> Apply Again</a>
                        <?php endif; ?>
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