<?php
require_once 'config.php';
require_once 'db_connect.php';

// Only ICT admins should access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ict') {
    header('Location: login.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_slots'])) {
        $max_students = filter_var($_POST['max_students'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($max_students === false) {
            $_SESSION['error'] = 'Please enter a valid number of slots (minimum 1).';
        } else {
            $pdo->prepare("UPDATE system_settings SET max_students = ?")->execute([$max_students]);
            $_SESSION['success'] = 'Maximum slots updated successfully!';
        }
    } elseif (isset($_POST['toggle_applications'])) {
        $status = $_POST['application_status'] === 'open' ? 1 : 0;
        $pdo->prepare("UPDATE system_settings SET application_open = ?")->execute([$status]);
        $_SESSION['success'] = 'Application window ' . $_POST['application_status'] . 'ed successfully!';
    }
}

// Get current settings
$settings = $pdo->query("SELECT * FROM system_settings LIMIT 1")->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - Regional ICT Authority</title>
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
        .settings-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        .settings-card h2 {
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
        input, select {
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
        .alert-success {
            background-color: #e8f8f0;
            color: #27ae60;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .error {
            background-color: #fde8e8;
            color: #e74c3c;
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
            <h1>Regional ICT Authority - Admin Settings</h1>
        </div>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="settings-card">
            <h2>System Settings</h2>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
                <?php unset($_SESSION['success']); ?>
                <script>
                    setTimeout(() => {
                        window.location.href = 'review_applications.php';
                    }, 3000);
                </script>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error"><?= htmlspecialchars($_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label>Maximum Attachment Slots</label>
                    <input type="number" name="max_students" value="<?= htmlspecialchars($settings['max_students']) ?>" min="1" required>
                </div>
                <button type="submit" name="update_slots" class="btn">Update Slots</button>
            </form>
            
            <form method="post" style="margin-top: 30px;">
                <div class="form-group">
                    <label>Application Window</label>
                    <select name="application_status" required>
                        <option value="open" <?= $settings['application_open'] ? 'selected' : '' ?>>Open</option>
                        <option value="close" <?= !$settings['application_open'] ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <button type="submit" name="toggle_applications" class="btn">Update Status</button>
            </form>
            
            <div style="margin-top: 30px;">
                <a href="change_credentials.php" class="btn">Change My Credentials</a>
                <a href="review_applications.php" class="btn btn-secondary">Back to Applications</a>
            </div>
        </div>
    </div>
    
    <div class="footer">
        © <?php echo date('Y'); ?> Regional ICT Authority. All rights reserved.
    </div>
</body>
</html>