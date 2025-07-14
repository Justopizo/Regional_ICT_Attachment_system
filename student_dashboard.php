<?php
require_once 'db_connect.php';
require_once 'functions.php';

check_role(['student']);

$user = get_current_user_data();
$student_data = [];
$application = [];
$window_status = get_application_window_status();

// Function to process file uploads
function process_upload($field_name, $upload_dir) {
    if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error uploading {$field_name} file.");
    }

    $file = $_FILES[$field_name];
    
    // Validate file type (only PDF)
    $allowed_types = ['application/pdf'];
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($file_info, $file['tmp_name']);
    finfo_close($file_info);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception("Only PDF files are allowed for {$field_name}.");
    }

    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        throw new Exception("File size for {$field_name} exceeds 5MB limit.");
    }

    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $destination = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Failed to move uploaded file for {$field_name}.");
    }

    return $destination;
}

try {
    // Get student details
    $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get application if exists
    if ($student_data) {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE student_id = ?");
        $stmt->execute([$student_data['id']]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    set_alert("Database error: " . $e->getMessage(), 'error');
}

// Handle file uploads if application window is open and no existing application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply']) && $window_status['application_window_open'] && (!$application || $application['status'] === 'cancelled')) {
    try {
        $upload_dir = 'uploads/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Check if there are available slots
        $dept = $student_data['preferred_department'];
        if ($window_status[$dept.'_slots_remaining'] <= 0) {
            throw new Exception("No available slots in your preferred department.");
        }

        // Process each file upload
        $application_letter_path = process_upload('application_letter', $upload_dir);
        $insurance_path = process_upload('insurance', $upload_dir);
        $cv_path = process_upload('cv', $upload_dir);
        $introduction_letter_path = process_upload('introduction_letter', $upload_dir);

        // Create or update application record
        if ($application && $application['status'] === 'cancelled') {
            $stmt = $pdo->prepare("UPDATE applications SET 
                application_letter_path = ?,
                insurance_path = ?,
                cv_path = ?,
                introduction_letter_path = ?,
                status = 'pending',
                updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([
                $application_letter_path,
                $insurance_path,
                $cv_path,
                $introduction_letter_path,
                $application['id']
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO applications 
                (student_id, application_letter_path, insurance_path, cv_path, introduction_letter_path) 
                VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $student_data['id'],
                $application_letter_path,
                $insurance_path,
                $cv_path,
                $introduction_letter_path
            ]);
        }

        // Update slots remaining
        $stmt = $pdo->prepare("UPDATE system_settings SET {$dept}_slots_remaining = {$dept}_slots_remaining - 1 
                              WHERE id = 1");
        $stmt->execute();

        set_alert("Application submitted successfully!", 'success');
        redirect('student_dashboard.php');
    } catch (Exception $e) {
        set_alert("Upload failed: " . $e->getMessage(), 'error');
        redirect('student_dashboard.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Western Region ICT Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background-color: #f5f5f5;
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
        .header h1 {
            font-size: 1.5rem;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-menu a {
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .welcome-banner {
            background-color: #3498db;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .status-indicator {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-accepted {
            background-color: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-forwarded {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .application-form {
            margin-top: 30px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .btn:disabled {
            background-color: #95a5a6;
            cursor: not-allowed;
        }
        .slots-info {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .slots-info h4 {
            margin-bottom: 10px;
        }
        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .slot-item {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        .slot-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #3498db;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin: 0 auto 10px;
            font-weight: bold;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .slot-circle .remaining {
            font-size: 2rem;
            line-height: 1;
        }
        .slot-circle .total {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        .slot-department {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .slot-status {
            font-size: 0.8rem;
            color: #7f8c8d;
        }
        .slot-available {
            color: #27ae60;
        }
        .slot-full {
            color: #e74c3c;
        }
        .feedback-section {
            margin-top: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .feedback-section h4 {
            margin-bottom: 5px;
        }
        @media (max-width: 768px) {
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            .header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            .slots-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Western Region ICT Authority</h1>
        <div class="user-menu">
            <span><i class="fas fa-user"></i> <?= htmlspecialchars($user['full_name']) ?></span>
            <a href="profile.php"><i class="fas fa-cog"></i> Profile</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="container">
        <?php display_alert(); ?>

        <div class="welcome-banner">
            <h2>Welcome, <?= htmlspecialchars($user['full_name']) ?>!</h2>
            <p>Student Attachment Program Dashboard</p>
        </div>

        <div class="dashboard-cards">
            <div class="card">
                <h3><i class="fas fa-info-circle"></i> My Information</h3>
                <p><strong>Institution:</strong> <?= htmlspecialchars($student_data['institution'] ?? 'N/A') ?></p>
                <p><strong>Course:</strong> <?= htmlspecialchars($student_data['course'] ?? 'N/A') ?></p>
                <p><strong>Year of Study:</strong> <?= htmlspecialchars($student_data['year_of_study'] ?? 'N/A') ?></p>
                <p><strong>Preferred Department:</strong> 
                    <?= ucfirst(htmlspecialchars($student_data['preferred_department'] ?? 'N/A')) ?>
                </p>
            </div>

            <div class="card">
                <h3><i class="fas fa-file-alt"></i> Application Status</h3>
                <?php if ($application): ?>
                    <p><strong>Status:</strong> 
                        <span class="status-indicator status-<?= $application['status'] ?>">
                            <?= ucfirst($application['status']) ?>
                        </span>
                    </p>
                    <p><strong>Submitted:</strong> <?= date('M j, Y', strtotime($application['updated_at'])) ?></p>
                    
                    <?php if (in_array($application['status'], ['accepted', 'rejected', 'forwarded'])): ?>
                        <div class="feedback-section">
                            <h4>Feedback:</h4>
                            <?php if ($application['status'] === 'accepted' && !empty($application['ict_notes'])): ?>
                                <p><?= htmlspecialchars($application['ict_notes']) ?></p>
                            <?php elseif ($application['status'] === 'rejected' && !empty($application['ict_notes'])): ?>
                                <p><?= htmlspecialchars($application['ict_notes']) ?></p>
                            <?php elseif ($application['status'] === 'forwarded' && !empty($application['hr_notes'])): ?>
                                <p><?= htmlspecialchars($application['hr_notes']) ?></p>
                            <?php else: ?>
                                <p>No additional feedback provided.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p>No application submitted yet.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3><i class="fas fa-calendar-alt"></i> Application Window</h3>
                <p><strong>Status:</strong> 
                    <?= $window_status['application_window_open'] ? 
                        '<span style="color: green;">OPEN</span>' : 
                        '<span style="color: red;">CLOSED</span>' ?>
                </p>
                <?php if ($window_status['application_window_open'] && (!$application || $application['status'] === 'cancelled')): ?>
                    <p>You can now submit your application.</p>
                <?php elseif ($window_status['application_window_open'] && $application && !in_array($application['status'], ['cancelled'])): ?>
                    <p>You have already submitted an application.</p>
                <?php else: ?>
                    <p>The application window is currently closed. Please check back later.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($window_status['application_window_open'] && (!$application || $application['status'] === 'cancelled')): ?>
            <div class="slots-info">
                <h4><i class="fas fa-users"></i> Available Slots</h4>
                <div class="slots-grid">
                    <div class="slot-item">
                        <div class="slot-department">HR Department</div>
                        <div class="slot-circle">
                            <span class="remaining"><?= $window_status['hr_slots_remaining'] ?></span>
                            <span class="total">of <?= $window_status['hr_slots'] ?></span>
                        </div>
                        <div class="slot-status <?= $window_status['hr_slots_remaining'] > 0 ? 'slot-available' : 'slot-full' ?>">
                            <?= $window_status['hr_slots_remaining'] > 0 ? 'Available' : 'Full' ?>
                        </div>
                    </div>
                    <div class="slot-item">
                        <div class="slot-department">ICT Department</div>
                        <div class="slot-circle">
                            <span class="remaining"><?= $window_status['ict_slots_remaining'] ?></span>
                            <span class="total">of <?= $window_status['ict_slots'] ?></span>
                        </div>
                        <div class="slot-status <?= $window_status['ict_slots_remaining'] > 0 ? 'slot-available' : 'slot-full' ?>">
                            <?= $window_status['ict_slots_remaining'] > 0 ? 'Available' : 'Full' ?>
                        </div>
                    </div>
                    <div class="slot-item">
                        <div class="slot-department">Registry Department</div>
                        <div class="slot-circle">
                            <span class="remaining"><?= $window_status['registry_slots_remaining'] ?></span>
                            <span class="total">of <?= $window_status['registry_slots'] ?></span>
                        </div>
                        <div class="slot-status <?= $window_status['registry_slots_remaining'] > 0 ? 'slot-available' : 'slot-full' ?>">
                            <?= $window_status['registry_slots_remaining'] > 0 ? 'Available' : 'Full' ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card application-form">
                <h3><i class="fas fa-edit"></i> Submit Application</h3>
                <form action="student_dashboard.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="application_letter">Application Letter (PDF)</label>
                        <input type="file" id="application_letter" name="application_letter" accept=".pdf" required>
                    </div>
                    <div class="form-group">
                        <label for="insurance">Insurance Cover (PDF)</label>
                        <input type="file" id="insurance" name="insurance" accept=".pdf" required>
                    </div>
                    <div class="form-group">
                        <label for="cv">Curriculum Vitae (PDF)</label>
                        <input type="file" id="cv" name="cv" accept=".pdf" required>
                    </div>
                    <div class="form-group">
                        <label for="introduction_letter">Introduction Letter from Institution (PDF)</label>
                        <input type="file" id="introduction_letter" name="introduction_letter" accept=".pdf" required>
                    </div>
                    <button type="submit" name="apply" class="btn" 
                        <?= $window_status[$student_data['preferred_department'].'_slots_remaining'] <= 0 ? 'disabled' : '' ?>>
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                    <?php if ($window_status[$student_data['preferred_department'].'_slots_remaining'] <= 0): ?>
                        <p style="color: red; margin-top: 10px;">
                            No slots remaining in your preferred department. You cannot apply at this time.
                        </p>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>