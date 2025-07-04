<?php
require_once 'config.php';
require_once 'db_connect.php';

// Only authenticated ICT admins should access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ict') {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = trim($_POST['current_password']);
    $new_username = trim($_POST['new_username']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = 'User not found';
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = 'Current password is incorrect';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif (empty($new_username)) {
        $error = 'Username cannot be empty';
    } else {
        // Update credentials
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
        
        if ($stmt->execute([$new_username, $hashed_password, $_SESSION['user_id']])) {
            $_SESSION['success'] = 'Credentials updated successfully!';
            $_SESSION['username'] = $new_username;
        } else {
            $error = 'Failed to update credentials';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Credentials - Regional ICT Authority</title>
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            display: flex;
            flex-direction: column;
        }
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .logo {
            display: flex;
            align-items: center;
        }
        .logo img {
            height: 50px;
            margin-right: 15px;
        }
        .logo h1 {
            margin: 0;
            font-size: 20px;
        }
        .user-info {
            display: flex;
            align-items: center;
        }
        .user-info span {
            margin-right: 15px;
        }
        .logout-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            водитель: pointer;
            font-size: 14px;
        }
        .logout-btn:hover {
            background-color: #c0392b;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 0 20px;
            flex: 1;
        }
        .credentials-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        .credentials-card h2 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #7f8c8d;
        }
        input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            border: none;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .btn-secondary {
            background-color: #95a5a6;
        }
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        .error {
            background-color: #fde8e8;
            color: #e74c3c;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .alert-success {
            background-color: #e8f8f0;
            color: #27ae60;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            background-color: #2c3e50;
            color: white;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSp7IsPuR-i7yeK9PYSbvr79rvqt08UzFwoR3tMqEs_GNXK6IC2PAdk4S0&s" alt="Regional ICT Authority Logo">
            <h1>Regional ICT Authority - Change Credentials</h1>
        </div>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="credentials-card">
            <h2>Change Admin Credentials</h2>
            
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
                <?php unset($_SESSION['success']); ?>
                <script>
                    setTimeout(() => {
                        window.location.href = 'review_applications.php';
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label>New Username</label>
                    <input type="text" name="new_username" value="<?= htmlspecialchars($_SESSION['username']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn">Update Credentials</button>
                <a href="review_applications.php" class="btn btn-secondary">Back to Applications</a>
            </form>
        </div>
    </div>
    
    <div class="footer">
        © <?php echo date('Y'); ?> Regional ICT Authority. All rights reserved.
    </div>
</body>
</html>