<?php
require_once 'config.php';
require_once 'db_connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Only students should access this page
if ($_SESSION['role'] !== 'student') {
    header('Location: index.php');
    exit;
}

// Get student's application
$stmt = $pdo->prepare("SELECT * FROM applications WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$application = $stmt->fetch();

if (!$application) {
    header('Location: dashboard.php');
    exit;
}

// Get user details
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application - Regional ICT Authority</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
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
        }
        .application-details {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        .application-details h2 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .detail-row {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        .detail-label {
            width: 200px;
            font-weight: 500;
            color: #7f8c8d;
        }
        .detail-value {
            flex: 1;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 500;
        }
        .status-pending {
            background-color: #fef5e7;
            color: #f39c12;
        }
        .status-accepted {
            background-color: #e8f8f0;
            color: #27ae60;
        }
        .status-rejected {
            background-color: #fde8e8;
            color: #e74c3c;
        }
        .document-link {
            display: inline-block;
            padding: 5px 10px;
            background-color: #e8f4fd;
            color: #3498db;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .document-link:hover {
            background-color: #d4e6f7;
        }
        .feedback-box {
            background-color: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-top: 15px;
            border-radius: 0 4px 4px 0;
        }
        .feedback-title {
            font-weight: 500;
            margin-bottom: 5px;
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
        .footer {
            text-align: center;
            padding: 20px;
            background-color: #2c3e50;
            color: white;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSp7IsPuR-i7yeK9PYSbvr79rvqt08UzFwoR3tMqEs_GNXK6IC2PAdk4S0&s" alt="Regional ICT Authority Logo">
            <h1>Regional ICT Authority - View Application</h1>
        </div>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="application-details">
            <h2>Your Attachment Application Details</h2>
            
            <div class="detail-row">
                <div class="detail-label">Full Name</div>
                <div class="detail-value"><?php echo htmlspecialchars($user['full_name']); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Email</div>
                <div class="detail-value"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Phone Number</div>
                <div class="detail-value"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Application Status</div>
                <div class="detail-value">
                    <span class="status-badge status-<?php echo $application['status']; ?>">
                        <?php echo ucfirst($application['status']); ?>
                    </span>
                </div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Side Hustle</div>
                <div class="detail-value"><?php echo htmlspecialchars($application['side_hustle'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Attachment Period</div>
                <div class="detail-value">
                    <?php 
                        echo htmlspecialchars(date('F j, Y', strtotime($application['start_date']))) . 
                        ' to ' . 
                        htmlspecialchars(date('F j, Y', strtotime($application['end_date'])));
                    ?>
                </div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Submitted Documents</div>
                <div class="detail-value">
                    <a href="<?php echo UPLOAD_DIR . htmlspecialchars($application['cv_path']); ?>" class="document-link" target="_blank">View CV</a>
                    <a href="<?php echo UPLOAD_DIR . htmlspecialchars($application['insurance_path']); ?>" class="document-link" target="_blank">View Insurance</a>
                    <a href="<?php echo UPLOAD_DIR . htmlspecialchars($application['intro_letter_path']); ?>" class="document-link" target="_blank">View Introduction Letter</a>
                    <a href="<?php echo UPLOAD_DIR . htmlspecialchars($application['application_letter_path']); ?>" class="document-link" target="_blank">View Application Letter</a>
                </div>
            </div>
            
            <?php if ($application['feedback']): ?>
                <div class="feedback-box">
                    <div class="feedback-title">Feedback from Administrators:</div>
                    <div><?php echo nl2br(htmlspecialchars($application['feedback'])); ?></div>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 20px;">
                <a href="dashboard.php" class="btn">Back to Dashboard</a>
            </div>
        </div>
    </div>
    
    <div class="footer">
        &copy; <?php echo date('Y'); ?> Regional ICT Authority. All rights reserved.
    </div>
</body>
</html>