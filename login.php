<?php
require_once 'config.php';
require_once 'db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login_submit'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                
                if ($user['role'] === 'student') {
                    header('Location: dashboard.php');
                } else {
                    header('Location: review_applications.php');
                }
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        }
    } elseif (isset($_POST['forgot_submit'])) {
        $email = trim($_POST['email']);
        
        if (empty($email)) {
            $error = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate a unique token
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour
                
                // Store token in password_resets table
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expiry) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expiry = ?");
                $stmt->execute([$email, $token, $expiry, $token, $expiry]);
                
                // Simulate sending email (replace with actual email service in production)
                $reset_link = "http://yoursite.byethost.com/reset_password.php?token=" . $token;
                $success = "A password reset link has been sent to $email. Please check your inbox.";
                // In production, use a library like PHPMailer:
                // $mail->setFrom('no-reply@yourdomain.com', 'Kakamega ICT');
                // $mail->addAddress($email);
                // $mail->Subject = 'Password Reset Request';
                // $mail->Body = "Click this link to reset your password: $reset_link";
                // $mail->send();
            } else {
                $error = 'No account found with that email address.';
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
    <title>Regional ICT Authority - Login</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #333;
        }
        .login-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 350px;
            padding: 30px;
            text-align: center;
            max-width: 90%; /* Responsive width */
        }
        .logo {
            margin-bottom: 20px;
        }
        .logo img {
            height: 80px;
            max-width: 100%; /* Ensure image scales */
        }
        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin-top: 10px;
        }
        button:hover {
            background-color: #2980b9;
        }
        .error {
            color: #e74c3c;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .success {
            color: #27ae60;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .register-link {
            margin-top: 20px;
            font-size: 14px;
        }
        .register-link a {
            color: #3498db;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .forgot-password {
            text-align: right;
            margin-top: 5px;
            font-size: 12px;
        }
        .forgot-password a {
            color: #3498db;
            text-decoration: none;
        }
        .forgot-password a:hover {
            text-decoration: underline;
        }
        /* Media Queries for Mobile */
        @media (max-width: 768px) {
            .login-container {
                padding: 20px;
                width: 95%;
            }
            .logo img {
                height: 60px;
            }
            h2 {
                font-size: 1.5em;
            }
            input[type="text"],
            input[type="password"],
            input[type="email"] {
                font-size: 12px;
            }
            button {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSp7IsPuR-i7yeK9PYSbvr79rvqt08UzFwoR3tMqEs_GNXK6IC2PAdk4S0&s" alt="Regional ICT Authority Logo">
        </div>
        <h2>Kakamega Regional ICT Authority</h2>
        <h3>Login</h3>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form action="login.php" method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" name="login_submit">Login</button>
        </form>
        
        <div class="forgot-password">
            <a href="#" onclick="document.getElementById('forgot-form').style.display='block'; this.style.display='none';">Forgot Password?</a>
        </div>
        
        <form id="forgot-form" action="login.php" method="post" style="display: none;">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" name="forgot_submit">Send Reset Link</button>
            <div class="forgot-password">
                <a href="#" onclick="document.getElementById('forgot-form').style.display='none'; document.querySelector('.forgot-password a').style.display='block';">Back to Login</a>
            </div>
        </form>
        
        <div class="register-link">
            Don't have an account? <a href="register.php">Register </a>
        </div>
    </div>
</body>
</html>