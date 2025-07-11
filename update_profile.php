<?php
require_once 'config.php';
require_once 'functions.php';

// Only allow logged in users to update profile
if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $full_name = sanitizeInput($_POST['full_name']);
    
    // Validate inputs
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    } elseif (strlen($username) < 4) {
        $errors['username'] = 'Username must be at least 4 characters';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required';
    } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors['phone'] = 'Invalid phone number';
    }
    
    if (empty($full_name)) {
        $errors['full_name'] = 'Full name is required';
    }
    
    // Check if username or email already exists (excluding current user)
    $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $stmt->bind_param("ssi", $username, $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors['general'] = 'Username or email already exists';
    }
    
    // If no errors, update the profile
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, phone = ?, full_name = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssssi", $username, $email, $phone, $full_name, $user_id);
        
        if ($stmt->execute()) {
            // Update session variables
            $_SESSION['username'] = $username;
            $_SESSION['full_name'] = $full_name;
            
            $success = 'Profile updated successfully!';
            $user = getUserById($user_id); // Refresh user data
        } else {
            $errors['general'] = 'Failed to update profile. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile - Kakamega ICT Authority</title>
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
                <li class="active"><a href="update_profile.php"><i class="fas fa-user-edit"></i> Update Profile</a></li>
                <li><a href="change_password.php"><i class="fas fa-key"></i> Change Password</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
            <div class="sidebar-footer">
                <p>Logged in as: <strong><?php echo $_SESSION['username']; ?></strong></p>
            </div>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Update Profile</h1>
                <div class="user-info">
                    <span><i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?></span>
                    <span><i class="fas fa-envelope"></i> <?php echo $user['email']; ?></span>
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
                    <form action="update_profile.php" method="POST">
                        <div class="form-group">
                            <label for="username"><i class="fas fa-user"></i> Username</label>
                            <input type="text" id="username" name="username" value="<?php echo $user['username']; ?>" required>
                            <?php if (!empty($errors['username'])): ?>
                                <span class="error"><?php echo $errors['username']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                            <?php if (!empty($errors['email'])): ?>
                                <span class="error"><?php echo $errors['email']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name"><i class="fas fa-id-card"></i> Full Name</label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo $user['full_name']; ?>" required>
                            <?php if (!empty($errors['full_name'])): ?>
                                <span class="error"><?php echo $errors['full_name']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo $user['phone']; ?>" required>
                            <?php if (!empty($errors['phone'])): ?>
                                <span class="error"><?php echo $errors['phone']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Profile</button>
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