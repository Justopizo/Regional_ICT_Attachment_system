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
$settings = $pdo->query("SELECT * FROM system_settings LIMIT 1")->fetch();

// Count accepted applications
$accepted_stmt = $pdo->query("SELECT COUNT(*) as count FROM applications WHERE status = 'accepted'");
$accepted_count = $accepted_stmt->fetch()['count'];
$slots_available = $settings['max_students'] - $accepted_count;

$is_ict = ($_SESSION['role'] === 'ict');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Applications - Kakamega ICT</title>
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
            max-width: 100%; /* Ensure image scales */
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
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 10px; /* Reduced padding for mobile */
            flex: 1;
            width: 100%; /* Full width for responsiveness */
        }
        .system-status {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* Allow wrapping on small screens */
        }
        .status-indicator {
            display: flex;
            align-items: center;
            margin-bottom: 10px; /* Space below on mobile */
        }
        .status-dot {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .status-open {
            background-color: var(--success);
        }
        .status-closed {
            background-color: var(--danger);
        }
        .slots-info {
            font-weight: 500;
        }
        .applications-list {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 15px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            overflow-x: auto; /* Horizontal scroll for mobile */
            display: block; /* Enable scrolling */
        }
        th, td {
            padding: 10px 12px; /* Reduced padding for mobile */
            text-align: left;
            border-bottom: 1px solid #eee;
            white-space: nowrap; /* Prevent text wrapping */
            min-width: 100px; /* Minimum width for columns */
        }
        th {
            background-color: #f8f9fa;
            font-weight: 500;
            color: var(--dark);
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
            color: var(--warning);
        }
        .status-accepted {
            background-color: #e8f8f0;
            color: var(--success);
        }
        .status-rejected {
            background-color: #fde8e8;
            color: var(--danger);
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
            background-color: var(--secondary);
            color: white;
        }
        .view-btn:hover {
            background-color: #2980b9;
        }
        .accept-btn {
            background-color: var(--success);
            color: white;
        }
        .accept-btn:hover {
            background-color: #219955;
        }
        .reject-btn {
            background-color: var(--danger);
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
        .settings-btn {
            background-color: var(--primary);
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
        }
        .footer {
            text-align: center;
            padding: 20px;
            background-color: var(--primary);
            color: white;
            margin-top: 50px;
        }

        /* Media Queries for Mobile */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                padding: 10px;
            }
            .logo img {
                height: 40px; /* Smaller logo on mobile */
            }
            .logo h1 {
                font-size: 16px; /* Smaller title */
            }
            .user-info {
                flex-direction: column;
                margin-top: 10px;
            }
            .user-info span {
                margin-right: 0;
                margin-bottom: 10px;
            }
            .container {
                padding: 0 5px; /* Minimal padding on mobile */
            }
            .system-status {
                flex-direction: column;
                text-align: center;
            }
            .settings-btn {
                margin-top: 10px;
                width: 100%; /* Full width on mobile */
            }
            table {
                font-size: 14px; /* Smaller text */
            }
            th, td {
                padding: 8px 10px; /* Further reduced padding */
            }
        }

        @media (max-width: 480px) {
            .logo h1 {
                font-size: 14px; /* Even smaller on tiny screens */
            }
            .user-info span {
                font-size: 12px; /* Smaller welcome text */
            }
            .logout-btn {
                font-size: 12px; /* Smaller logout button */
                padding: 6px 12px;
            }
            .status-indicator {
                margin-bottom: 15px;
            }
            .settings-btn {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSp7IsPuR-i7yeK9PYSbvr79rvqt08UzFwoR3tMqEs_GNXK6IC2PAdk4S0&s" alt="Kakamega ICT Logo">
            <h1>Kakamega ICT - Review Applications</h1>
        </div>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="system-status">
            <div class="status-indicator">
                <div class="status-dot <?php echo $settings['application_open'] ? 'status-open' : 'status-closed'; ?>"></div>
                <div>
                    Applications: <strong><?php echo $settings['application_open'] ? 'OPEN' : 'CLOSED'; ?></strong> | 
                    Slots: <strong><?php echo $accepted_count; ?>/<?php echo $settings['max_students']; ?></strong>
                </div>
            </div>
            <?php if ($is_ict): ?>
                <a href="admin_settings.php" class="settings-btn">System Settings</a>
            <?php endif; ?>
        </div>
        
        <div class="applications-list">
            <h2>Student Applications</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Period</th>
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
                                
                                <?php if ($is_ict): ?>
                                    <?php if ($app['status'] === 'pending'): ?>
                                        <a href="process_application.php?id=<?php echo $app['id']; ?>&action=accept" class="action-btn accept-btn">Accept</a>
                                        <a href="process_application.php?id=<?php echo $app['id']; ?>&action=reject" class="action-btn reject-btn">Reject</a>
                                    <?php else: ?>
                                        <span class="action-btn disabled-btn">Processed</span>
                                    <?php endif; ?>
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
        © <?php echo date('Y'); ?> Kakamega Regional ICT Authority. All rights reserved.
    </div>
</body>
</html>