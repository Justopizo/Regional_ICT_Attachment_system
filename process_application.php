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

// Check if application ID and action are provided
if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header('Location: review_applications.php');
    exit;
}

$application_id = $_GET['id'];
$action = $_GET['action'];

// Validate action
if (!in_array($action, ['accept', 'reject'])) {
    header('Location: review_applications.php');
    exit;
}

// Get application details with user info
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name 
    FROM applications a
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$application_id]);
$application = $stmt->fetch();

if (!$application) {
    header('Location: review_applications.php');
    exit;
}

// Check if application is already processed
if ($application['status'] !== 'pending') {
    header('Location: review_applications.php');
    exit;
}

// Check if slots are available for acceptance
$settings_stmt = $pdo->query("SELECT * FROM system_settings LIMIT 1");
$settings = $settings_stmt->fetch();

$accepted_stmt = $pdo->query("SELECT COUNT(*) as count FROM applications WHERE status = 'accepted'");
$accepted_count = $accepted_stmt->fetch()['count'];

if ($action === 'accept' && $accepted_count >= $settings['max_students']) {
    $_SESSION['error'] = 'All attachment slots have been filled. Cannot accept more applications.';
    header('Location: review_applications.php');
    exit;
}

// Process application
$status = $action === 'accept' ? 'accepted' : 'rejected';
$feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update application status
    $update_stmt = $pdo->prepare("
        UPDATE applications 
        SET status = ?, 
            feedback = ?,
            " . ($_SESSION['role'] === 'hr' ? 'reviewed_by_hr' : 'reviewed_by_ict') . " = ?,
            feedback = ?
        WHERE id = ?
    ");
    
    $update_stmt->execute([
        $status,
        $feedback,
        $_SESSION['user_id'],
        $feedback,
        $application_id
    ]);
    
    $_SESSION['success'] = "Application has been $status successfully.";
    header('Location: review_applications.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($action); ?> Application - Regional ICT Authority</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: #f5f7fa;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .logo-container {
            display: flex;
            align-items: center;
        }
        .logo {
            height: 50px;
            margin-right: 15px;
        }
        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
        }
        .user-info {
            display: flex;
            align-items: center;
        }
        .username {
            margin-right: 15px;
            font-weight: 500;
        }
        .logout-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .logout-btn:hover {
            background-color: #c0392b;
        }
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        .card-title {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            font-size: 1.3rem;
        }
        .action-message {
            margin-bottom: 20px;
            font-size: 1.1rem;
        }
        .student-name {
            font-weight: 600;
            color: #3498db;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            border-color: #3498db;
            outline: none;
        }
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background-color: #2980b9;
        }
        .btn-success {
            background-color: #27ae60;
            color: white;
        }
        .btn-success:hover {
            background-color: #219955;
        }
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
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
            border-radius: 0 0 8px 8px;
        }
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }
            .logo-container {
                margin-bottom: 15px;
                justify-content: center;
            }
            .user-info {
                justify-content: center;
                width: 100%;
            }
            .btn-group {
                flex-direction: column;
            }
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSp7IsPuR-i7yeK9PYSbvr79rvqt08UzFwoR3tMqEs_GNXK6IC2PAdk4S0&s" alt="Regional ICT Authority Logo" class="logo">
            <h1 class="header-title">Regional ICT Authority</h1>
        </div>
        <div class="user-info">
            <span class="username">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2 class="card-title"><?php echo ucfirst($action); ?> Application</h2>
            
            <p class="action-message">
                You are about to <strong><?php echo $action; ?></strong> the application from 
                <span class="student-name"><?php echo isset($application['full_name']) ? htmlspecialchars($application['full_name']) : 'Student'; ?></span>.
            </p>
            
            <form action="process_application.php?id=<?php echo $application_id; ?>&action=<?php echo $action; ?>" method="post">
                <div class="form-group">
                    <label for="feedback" class="form-label">Feedback (Optional)</label>
                    <textarea id="feedback" name="feedback" class="form-control" placeholder="Provide feedback to the applicant..."></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn <?php echo $action === 'accept' ? 'btn-success' : 'btn-danger'; ?>">
                        <?php echo ucfirst($action); ?> Application
                    </button>
                    <a href="review_applications.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="footer">
        &copy; <?php echo date('Y'); ?> Regional ICT Authority. All rights reserved.
    </div>
</body>
</html>