<?php
require_once 'config.php';
require_once 'functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole();
}

$errors = [];
$success = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $department = sanitizeInput($_POST['department']);
    $full_name = sanitizeInput($_POST['full_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
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
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    }
    
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors['general'] = 'Username or email already exists';
    }
    
    // If no errors, register the user
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'student';
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, department, full_name, phone, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssss", $username, $hashed_password, $email, $role, $department, $full_name, $phone);
        
        if ($stmt->execute()) {
            $success = 'Registration successful! You can now login.';
            // Clear form
            $_POST = [];
        } else {
            $errors['general'] = 'Registration failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Kakamega ICT Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        <?php include 'styles.css'; ?>
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="ictlogo.jpeg" alt="Kakamega ICT Authority Logo" class="logo">
            <h1>Student Registration</h1>
        </div>
        
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form action="register.php" method="POST" class="form">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username</label>
                <input type="text" id="username" name="username" value="<?php echo $_POST['username'] ?? ''; ?>" required>
                <?php if (!empty($errors['username'])): ?>
                    <span class="error"><?php echo $errors['username']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                <?php if (!empty($errors['email'])): ?>
                    <span class="error"><?php echo $errors['email']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="full_name"><i class="fas fa-id-card"></i> Full Name</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo $_POST['full_name'] ?? ''; ?>" required>
                <?php if (!empty($errors['full_name'])): ?>
                    <span class="error"><?php echo $errors['full_name']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                <input type="tel" id="phone" name="phone" value="<?php echo $_POST['phone'] ?? ''; ?>" required>
                <?php if (!empty($errors['phone'])): ?>
                    <span class="error"><?php echo $errors['phone']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="department"><i class="fas fa-building"></i> Preferred Department</label>
                <select id="department" name="department" required>
                    <option value="">Select Department</option>
                    <option value="ICT" <?php echo (isset($_POST['department']) && $_POST['department'] === 'ICT') ? 'selected' : ''; ?>>ICT Department</option>
                    <option value="Registry" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Registry') ? 'selected' : ''; ?>>Registry Department</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" required>
                <?php if (!empty($errors['password'])): ?>
                    <span class="error"><?php echo $errors['password']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <?php if (!empty($errors['confirm_password'])): ?>
                    <span class="error"><?php echo $errors['confirm_password']; ?></span>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn btn-primary">Register</button>
            <p class="text-center">Already have an account? <a href="index.php">Login here</a></p>
        </form>
        
        <div class="footer">
            <p class="copyright">© 2025 Kakamega Regional ICT Authority</p>
            <p class="credits">Developed by Justin Ratemo - 0793031269</p>
        </div>
    </div>
</body>
</html>