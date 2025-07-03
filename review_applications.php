<?php
require_once 'config.php';
require_once 'db_connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Only HR and ICT admins should access this page
if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'ict') {
    header('Location: index.php');
    exit;
}

// Get all applications with user details
$stmt = $pdo->query("
    SELECT a.*, u.full_name, u.email, u.phone 
    FROM applications a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.status, a.submitted_at DESC
");
$applications = $stmt->fetchAll();

// Get system settings
$settings_stmt = $pdo->query("SELECT * FROM system_settings LIMIT 1");
$settings = $settings_stmt->fetch();

// Count accepted applications
$accepted_stmt = $pdo->query("SELECT COUNT(*) as count FROM applications WHERE status = 'accepted'");
$accepted_count = $accepted_stmt->fetch()['count'];
$slots_available = $settings['max_students'] - $accepted_count;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Applications - Regional ICT Authority</title>
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
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .applications-list {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        .applications-list h2 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .slots-info {
            font-weight: 500;
            margin: 15px 0;
            padding: 10px;
            background-color: #e8f4fd;
            border-radius: 4px;
            border-left: 4px solid #3498db;
        }
        .slots-info.warning {
            background-color: #fef5e7;
            border-left-color: #f39c12;
        }
        .slots-info.full {
            background-color: #fde8e8;
            border-left-color: #e74c3c;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 500;
            color: #2c3e50;
        }
        tr:hover {
            background-color: #f8f9fa;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 12px;
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
        .action-btn {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
            margin-right: 5px;
            display: inline-block;
        }
        .view-btn {
            background-color: #3498db;
            color: white;
        }
        .view-btn:hover {
            background-color: #2980b9;
        }
        .accept-btn {
            background-color: #27ae60;
            color: white;
        }
        .accept-btn:hover {
            background-color: #219955;
        }
        .reject-btn {
            background-color: #e74c3c;
            color: white;
        }
        .reject-btn:hover {
            background-color: #c0392b;
        }
        .disabled-btn {
            background-color: #95a5a6;
            color: white;
            cursor: not-allowed;
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
            <h1>Regional ICT Authority - Review Applications</h1>
        </div>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="applications-list">
            <h2>Student Attachment Applications</h2>
            
            <div class="slots-info <?php 
                echo $slots_available <= 0 ? 'full' : 
                    ($slots_available <= 3 ? 'warning' : ''); 
            ?>">
                <?php if ($slots_available > 0): ?>
                    There are <?php echo $slots_available; ?> attachment slots available out of <?php echo $settings['max_students']; ?>.
                <?php else: ?>
                    All attachment slots have been filled. No more applications can be accepted.
                <?php endif; ?>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Attachment Period</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                            <td><?php echo htmlspecialchars($app['phone'] ?? 'N/A'); ?></td>
                            <td>
                                <?php 
                                    echo htmlspecialchars(date('M j, Y', strtotime($app['start_date']))) . 
                                    ' to ' . 
                                    htmlspecialchars(date('M j, Y', strtotime($app['end_date'])));
                                ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $app['status']; ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="view_application_admin.php?id=<?php echo $app['id']; ?>" class="action-btn view-btn">View</a>
                                
                                <?php if ($_SESSION['role'] === 'hr' && $app['status'] === 'pending'): ?>
                                    <a href="process_application.php?id=<?php echo $app['id']; ?>&action=accept" class="action-btn accept-btn">Accept</a>
                                    <a href="process_application.php?id=<?php echo $app['id']; ?>&action=reject" class="action-btn reject-btn">Reject</a>
                                <?php elseif ($_SESSION['role'] === 'ict' && $app['status'] === 'pending'): ?>
                                    <a href="process_application.php?id=<?php echo $app['id']; ?>&action=accept" class="action-btn accept-btn">Accept</a>
                                    <a href="process_application.php?id=<?php echo $app['id']; ?>&action=reject" class="action-btn reject-btn">Reject</a>
                                <?php elseif ($app['status'] !== 'pending'): ?>
                                    <span class="action-btn disabled-btn">Processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No applications found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="footer">
        &copy; <?php echo date('Y'); ?> Regional ICT Authority. All rights reserved.
    </div>
</body>
</html>