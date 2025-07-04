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

// Get system settings
$settings = $pdo->query("SELECT * FROM system_settings LIMIT 1")->fetch();

// Count accepted applications
$accepted_count = $pdo->query("SELECT COUNT(*) as count FROM applications WHERE status = 'accepted'")->fetch()['count'];
$slots_available = $settings['max_students'] - $accepted_count;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Kakamega ICT</title>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --light: #ecf0f1;
            --dark: #34495e;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .header {
            background-color: var(--primary);
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
            background-color: var(--danger);
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
        .dashboard-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        .dashboard-card h2 {
            margin-top: 0;
            color: var(--primary);
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
            animation: fadeIn 0.5s, fadeOut 0.5s 4s forwards;
        }
        .alert-info {
            background-color: #d9edf7;
            color: #31708f;
            border: 1px solid #bce8f1;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        .status-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .status-info {
            flex: 1;
        }
        .status-label {
            font-weight: 500;
            margin-bottom: 5px;
        }
        .status-value {
            font-size: 18px;
        }
        .status-pending {
            color: var(--warning);
        }
        .status-accepted {
            color: var(--success);
        }
        .status-rejected {
            color: var(--danger);
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: var(--secondary);
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
        .btn-disabled {
            background-color: #95a5a6;
            cursor: not-allowed;
        }
        .rules-list {
            padding-left: 20px;
        }
        .rules-list li {
            margin-bottom: 10px;
        }
        .feedback-box {
            background-color: #f8f9fa;
            border-left: 4px solid var(--secondary);
            padding: 15px;
            margin-top: 15px;
            border-radius: 0 4px 4px 0;
        }
        .feedback-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        .slots-info {
            font-weight: 500;
            margin: 15px 0;
            padding: 10px;
            background-color: #e8f4fd;
            border-radius: 4px;
            border-left: 4px solid var(--secondary);
        }
        .slots-info.warning {
            background-color: #fef5e7;
            border-left-color: var(--warning);
        }
        .slots-info.full {
            background-color: #fde8e8;
            border-left-color: var(--danger);
        }
        .footer {
            text-align: center;
            padding: 20px;
            background-color: var(--primary);
            color: white;
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSp7IsPuR-i7yeK9PYSbvr79rvqt08UzFwoR3tMqEs_GNXK6IC2PAdk4S0&s" alt="Kakamega ICT Logo">
            <h1>Kakamega ICT - Student Dashboard</h1>
        </div>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="dashboard-card">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <h2>Attachment Application</h2>
            
            <?php if (!$settings['application_open']): ?>
                <div class="alert alert-info">
                    Applications are currently closed by administrators. Please check back later.
                </div>
            <?php endif; ?>
            
            <?php if ($settings['application_open'] && $slots_available > 0): ?>
                <div class="slots-info">
                    There are <?php echo $slots_available; ?> attachment slots available out of <?php echo $settings['max_students']; ?>.
                </div>
            <?php elseif ($settings['application_open']): ?>
                <div class="slots-info full">
                    All attachment slots have been filled. Applications are closed.
                </div>
            <?php endif; ?>
            
            <?php if ($application): ?>
                <div class="status-card">
                    <div class="status-info">
                        <div class="status-label">Application Status</div>
                        <div class="status-value status-<?php echo $application['status']; ?>">
                            <?php echo ucfirst($application['status']); ?>
                        </div>
                    </div>
                    <a href="view_application.php" class="btn">View Application</a>
                </div>
                
                <?php if ($application['feedback']): ?>
                    <div class="feedback-box">
                        <div class="feedback-title">Feedback from Administrators:</div>
                        <div><?php echo nl2br(htmlspecialchars($application['feedback'])); ?></div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p>Welcome to the Kakamega Regional ICT Authority Student Attachment Program. Please submit your application to be considered for an attachment opportunity.</p>
                
                <h3>Application Requirements</h3>
                <ul class="rules-list">
                    <li>Curriculum Vitae (CV)</li>
                    <li>Insurance Cover</li>
                    <li>Introduction Letter from your University</li>
                    <li>Your Application Letter</li>
                </ul>
                
                <h3>Rules and Regulations</h3>
                <ul class="rules-list">
                    <li>NO form of payment is allowed during attachment period</li>
                    <li>Arrival time is 8am , Closing Time is 5pm</li>
                    <li>Weekend and Public Holiday we are Closed</li>
                    <li>Dress code is professional/smart casual</li>
                    <li>Attachment duration is minimum 8 weeks</li>
                    <li>You must adhere to all organizational policies</li>
                </ul>
                
                <?php if ($settings['application_open'] && $slots_available > 0): ?>
                    <a href="apply.php" class="btn">Apply Now</a>
                <?php else: ?>
                    <button class="btn btn-disabled" disabled>Applications Closed</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="footer">
        &copy; <?php echo date('Y'); ?> Kakamega Regional ICT Authority. All rights reserved.
    </div>
</body>
</html>