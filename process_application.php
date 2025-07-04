<?php
require_once 'config.php';
require_once 'db_connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Only ICT admins should access this page
if ($_SESSION['role'] !== 'ict') {
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
    SELECT a.*, u.full_name, u.email 
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
$settings = $pdo->query("SELECT * FROM system_settings LIMIT 1")->fetch();
$accepted_count = $pdo->query("SELECT COUNT(*) as count FROM applications WHERE status = 'accepted'")->fetch()['count'];

if ($action === 'accept' && $accepted_count >= $settings['max_students']) {
    $_SESSION['error'] = 'All attachment slots have been filled. Cannot accept more applications.';
    header('Location: review_applications.php');
    exit;
}

// Process application
$status = $action === 'accept' ? 'accepted' : 'rejected';
$feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'reject' && empty($feedback)) {
        $error = 'Feedback is required when rejecting applications';
    } else {
        // Update application status
        $update_stmt = $pdo->prepare("
            UPDATE applications 
            SET status = ?, 
                feedback = ?,
                reviewed_by_ict = ?,
                reviewed_at = NOW()
            WHERE id = ?
        ");
        
        $success = $update_stmt->execute([
            $status,
            $feedback,
            $_SESSION['user_id'],
            $application_id
        ]);
        
        if ($success) {
            $_SESSION['success'] = "Application has been $status successfully.";
            header('Location: review_applications.php');
            exit;
        } else {
            $error = 'Failed to update application status';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($action); ?> Application - Kakamega ICT</title>
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
            max-width: 600px;
            margin: 20px auto;
            padding: 0 20px;
            flex: 1;
        }
        .process-form {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        .process-form h2 {
            margin-top: 0;
            color: var(--primary);
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .student-info {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        textarea.form-control {
            min-height: 120px;
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
            text-align: center;
            text-decoration: none;
        }
        .btn-primary {
            background-color: var(--secondary);
            color: white;
        }
        .btn-primary:hover {
            background-color: #2980b9;
        }
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        .btn-success:hover {
            background-color: #219955;
        }
        .btn-danger {
            background-color: var(--danger);
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
        .error {
            color: var(--danger);
            padding: 10px;
            background-color: #fde8e8;
            border-radius: 4px;
            margin-bottom: 15px;
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
            <h1>Kakamega ICT - <?php echo ucfirst($action); ?> Application</h1>
        </div>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="process-form">
            <h2><?php echo ucfirst($action); ?> Application</h2>
            
            <div class="student-info">
                <strong>Student:</strong> <?php echo htmlspecialchars($application['full_name']); ?><br>
                <strong>Email:</strong> <?php echo htmlspecialchars($application['email']); ?>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form action="process_application.php?id=<?php echo $application_id; ?>&action=<?php echo $action; ?>" method="post">
                <div class="form-group">
                    <label for="feedback" class="form-label">
                        Feedback <?php if ($action === 'reject'): ?>(Required)<?php endif; ?>
                    </label>
                    <textarea id="feedback" name="feedback" class="form-control" 
                        placeholder="Provide feedback to the applicant..."
                        <?php if ($action === 'reject') echo 'required'; ?>></textarea>
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
        &copy; <?php echo date('Y'); ?> Kakamega Regional ICT Authority. All rights reserved.
    </div>
</body>
</html>