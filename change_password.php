<?php
require_once 'config.php';
require_once 'functions.php';

// Only allow logged in users to change password
if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($current_password)) {
        $errors['current_password'] = 'Current password is required';
    }
    
    if (empty($new_password)) {
        $errors['new_password'] = 'New password is required';
    } elseif (strlen($new_password) < 6) {
        $errors['new_password'] = 'Password must be at least 6 characters';
    }
    
    if ($new_password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    // If no errors, verify current password and update
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (password_verify($current_password, $user['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success = 'Password changed successfully!';
                // Clear form
                $_POST = [];
            } else {
                $errors['general'] = 'Failed to change password. Please try again.';
            }
        } else {
            $errors['current_password'] = 'Current password is incorrect';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Kakamega ICT Authority</title>
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
                <h3><?php echo ucfirst(getUserRole()); ?> Dashboard</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="<?php echo getUserRole() === 'student' ? 'student_dashboard.php' : getUserRole() . '_dashboard.php'; ?>"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="update_profile.php"><i class="fas fa-user-edit"></i> Update Profile</a></li>
                <li class="active"><a href="change_password.php"><i class="fas fa-key"></i> Change Password</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
            <div class="sidebar-footer">
                <p>Logged in as: <strong><?php echo $_SESSION['username']; ?></strong></p>
            </div>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Change Password</h1>
                <div class="user-info">
                    <span><i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?></span>
                    <span><i class="fas fa-envelope"></i> <?php echo $_SESSION['email'] ?? ''; ?></span>
                </div>
            </div>
            
            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="dashboard-section">
                <div class="card">
                    <form action="change_password.php" method="POST">
                        <div class="form-group">
                            <label for="current_password"><i class="fas fa-lock"></i> Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                            <?php if (!empty($errors['current_password'])): ?>
                                <span class="error"><?php echo $errors['current_password']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password"><i class="fas fa-key"></i> New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                            <?php if (!empty($errors['new_password'])): ?>
                                <span class="error"><?php echo $errors['new_password']; ?></span>
                            <?php endif; ?>
                            <small class="form-text">Password must be at least 6 characters long</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-key"></i> Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <?php if (!empty($errors['confirm_password'])): ?>
                                <span class="error"><?php echo $errors['confirm_password']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Change Password</button>
                        <a href="<?php echo getUserRole() === 'student' ? 'student_dashboard.php' : getUserRole() . '_dashboard.php'; ?>" class="btn btn-secondary">Cancel</a>
                    </form>
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