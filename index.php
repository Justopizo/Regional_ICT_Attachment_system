<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole();
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = sanitizeInput($_POST['password']);
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            redirectByRole();
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Invalid username or password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kakamega ICT Authority - Attachment System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        <?php include 'styles.css'; ?>
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="ictlogo.jpeg" alt="Kakamega ICT Authority Logo" class="logo">
            <h1>Attachment System</h1>
            <p>Kakamega Regional ICT Authority</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form action="index.php" method="POST" class="login-form">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username</label>
                <input type="text" id="username" name="username" required placeholder="Enter your username">
            </div>
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
            <div class="forgot-password">
                <a href="forgot_password.php">Forgot Password?</a>
            </div>
        </form>
        
        <div class="login-footer">
            <p>Don't have an account? <a href="register.php">Register as Student</a></p>
            <p class="copyright">© 2025 Kakamega Regional ICT Authority</p>
            <p class="credits">Developed by Justin Ratemo - 0793031269</p>
        </div>
    </div>
</body>
</html>
