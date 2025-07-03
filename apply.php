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

// Check if student has already applied
$stmt = $pdo->prepare("SELECT * FROM applications WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$existing_application = $stmt->fetch();

if ($existing_application) {
    header('Location: dashboard.php');
    exit;
}

// Check if applications are still open and slots available
$settings_stmt = $pdo->query("SELECT * FROM system_settings LIMIT 1");
$settings = $settings_stmt->fetch();

$accepted_stmt = $pdo->query("SELECT COUNT(*) as count FROM applications WHERE status = 'accepted'");
$accepted_count = $accepted_stmt->fetch()['count'];
$slots_available = $settings['max_students'] - $accepted_count;

if (!$settings['application_open'] || $slots_available <= 0) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Form submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $side_hustle = trim($_POST['side_hustle']);
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);
    
    // Validate inputs
    if (empty($start_date) || empty($end_date)) {
        $error = 'Please fill in all required fields.';
    } elseif (strtotime($start_date) >= strtotime($end_date)) {
        $error = 'End date must be after start date.';
    } else {
        // Process file uploads
        $upload_errors = [];
        $file_paths = [];
        
        $required_files = [
            'cv' => 'CV',
            'insurance' => 'Insurance Document',
            'intro_letter' => 'Introduction Letter',
            'application_letter' => 'Application Letter'
        ];
        
        foreach ($required_files as $field => $label) {
            if (empty($_FILES[$field]['name'])) {
                $upload_errors[] = "$label is required.";
                continue;
            }
            
            $file = $_FILES[$field];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Validate file
            if ($file['size'] > MAX_FILE_SIZE) {
                $upload_errors[] = "$label exceeds maximum file size of 5MB.";
            } elseif (!in_array($file_ext, ALLOWED_TYPES)) {
                $upload_errors[] = "$label must be a PDF or Word document.";
            } else {
                // Generate unique filename
                $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $file['name']);
                $destination = UPLOAD_DIR . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $file_paths[$field . '_path'] = $filename;
                } else {
                    $upload_errors[] = "Failed to upload $label.";
                }
            }
        }
        
        if (empty($upload_errors)) {
            // Insert application into database
            try {
                $stmt = $pdo->prepare("INSERT INTO applications 
                    (user_id, cv_path, insurance_path, intro_letter_path, application_letter_path, 
                    side_hustle, start_date, end_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    $file_paths['cv_path'],
                    $file_paths['insurance_path'],
                    $file_paths['intro_letter_path'],
                    $file_paths['application_letter_path'],
                    $side_hustle,
                    $start_date,
                    $end_date
                ]);
                
                $success = 'Application submitted successfully!';
                header('Refresh: 2; URL=dashboard.php');
            } catch (PDOException $e) {
                $error = 'Failed to submit application. Please try again.';
                
                // Clean up uploaded files if database insert failed
                foreach ($file_paths as $file_path) {
                    if (file_exists(UPLOAD_DIR . $file_path)) {
                        unlink(UPLOAD_DIR . $file_path);
                    }
                }
            }
        } else {
            $error = implode('<br>', $upload_errors);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Attachment - Regional ICT Authority</title>
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
        .application-form {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        .application-form h2 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        .file-upload {
            margin-bottom: 15px;
        }
        .file-upload label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .file-upload input[type="file"] {
            display: block;
            margin-bottom: 5px;
        }
        .file-upload .file-info {
            font-size: 12px;
            color: #7f8c8d;
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 16px;
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
            color: #e74c3c;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #fde8e8;
            border-radius: 4px;
            border-left: 4px solid #e74c3c;
        }
        .success {
            color: #27ae60;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #e8f8f0;
            border-radius: 4px;
            border-left: 4px solid #27ae60;
        }
        .required:after {
            content: " *";
            color: #e74c3c;
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
            <h1>Regional ICT Authority - Student Attachment Application</h1>
        </div>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="application-form">
            <h2>Attachment Application Form</h2>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php else: ?>
                <form action="apply.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="required">Upload Your CV</label>
                        <div class="file-upload">
                            <input type="file" name="cv" accept=".pdf,.doc,.docx" required>
                            <div class="file-info">PDF or Word document, max 5MB</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Upload Insurance Document</label>
                        <div class="file-upload">
                            <input type="file" name="insurance" accept=".pdf,.doc,.docx" required>
                            <div class="file-info">PDF or Word document, max 5MB</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Upload Introduction Letter from University</label>
                        <div class="file-upload">
                            <input type="file" name="intro_letter" accept=".pdf,.doc,.docx" required>
                            <div class="file-info">PDF or Word document, max 5MB</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Upload Your Application Letter</label>
                        <div class="file-upload">
                            <input type="file" name="application_letter" accept=".pdf,.doc,.docx" required>
                            <div class="file-info">PDF or Word document, max 5MB</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="side_hustle">Side Hustle (if any)</label>
                        <input type="text" id="side_hustle" name="side_hustle" placeholder="Enter your side hustle or N/A if none">
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date" class="required">Attachment Start Date</label>
                        <input type="date" id="start_date" name="start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date" class="required">Attachment End Date</label>
                        <input type="date" id="end_date" name="end_date" required>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn">Submit Application</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="footer">
        &copy; <?php echo date('Y'); ?> Regional ICT Authority. All rights reserved.
    </div>
</body>
</html>