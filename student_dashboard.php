<?php
ob_start(); // Start output buffering to prevent header issues
require_once 'db_connect.php';
require_once 'functions.php';
check_role(['student']);

$user = get_current_user_data();
$student_data = [];
$application = [];
$window_status = get_application_window_status();

// Function to process file uploads
function process_upload($field_name, $upload_dir) {
    if (!isset($_FILES[$field_name])) {
        throw new Exception("No file uploaded for {$field_name}.");
    }
    
    if ($_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error uploading {$field_name} file. Error code: " . $_FILES[$field_name]['error']);
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
    $destination = rtrim($upload_dir, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Failed to move uploaded file for {$field_name}.");
    }

    return $destination;
}

// Function to generate acceptance letter
function generate_acceptance_letter($pdo, $student_data, $application) {
    // Fetch user data for full_name, phone, and address
    $stmt = $pdo->prepare("SELECT u.full_name, u.phone, u.email AS address 
                          FROM users u 
                          WHERE u.id = ?");
    $stmt->execute([$student_data['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        throw new Exception("User data not found for student ID {$student_data['user_id']}.");
    }

    // Use attachment dates if available, otherwise calculate defaults
    $letter_date = date('F j, Y');
    $start_date = !empty($student_data['attachment_start_date']) 
        ? date('F j, Y', strtotime($student_data['attachment_start_date'])) 
        : date('F j, Y', strtotime('+1 week'));
    $end_date = !empty($student_data['attachment_end_date']) 
        ? date('F j, Y', strtotime($student_data['attachment_end_date'])) 
        : date('F j, Y', strtotime('+3 months'));
    
    $letter_content = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Acceptance Letter</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; }
            .header { text-align: right; margin-bottom: 30px; }
            .content { margin: 20px 0; }
            .footer { margin-top: 50px; }
            .signature { margin-top: 50px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <strong>Western Region ICT Authority</strong><br>
                P.O. Box 218-50100<br>
                Kakamega, Kenya<br>
                Tel: +254 702536641<br>
                Email: seif.ouma@ict.go.ke<br>
                $letter_date
            </div>
            
            <div>
                <strong>To:</strong><br>
                {$user_data['full_name']}<br>
                {$student_data['institution']}<br>
                {$student_data['course']} Student<br>
                Email: {$user_data['address']}<br>
                Tel: {$user_data['phone']}
            </div>
            
            <div class='content'>
                <h3 class='text-center'>RE: ACCEPTANCE LETTER FOR STUDENT ATTACHMENT</h3>
                
                <p>Dear {$user_data['full_name']},</p>
                
                <p>Following your application for student attachment at Western Region ICT Authority, we are pleased to inform you that your application has been successful. You have been assigned to the {$student_data['preferred_department']} Department.</p>
                
                <p>You are expected to report to our offices on <strong>{$start_date}</strong> at 8:00 AM. The attachment program will run until <strong>{$end_date}</strong>.</p>
                
                <p>Please bring with you the following documents on your reporting day:</p>
                
                <ol>
                    <li>Original and copy of your National ID</li>
                    <li>Original and copy of your institution's introduction letter</li>
                    <li>Original and copy of your insurance cover</li>
                    <li>Two (2) passport size photographs</li>
                </ol>
                
                <p>During your attachment period, you will be expected to adhere to the Authority's rules and regulations, maintain high standards of discipline, and demonstrate commitment to your assigned duties.</p>
                
                <p>Please confirm your acceptance of this offer by replying to this email within three (3) days of receipt.</p>
                
                <p>We look forward to welcoming you to our team.</p>
            </div>
            
            <div class='signature'>
                <p>Yours faithfully,</p>
                <p><strong>_________________________</strong><br>
                <strong>Admin Office</strong><br>
                Western Region ICT Authority<br>
                Regional Headquarters<br>
                Kakamega</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $filename = 'acceptance_letter_' . $student_data['id'] . '_' . date('YmdHis') . '.html';
    $filepath = __DIR__ . '/acceptance_letters/' . $filename;
    
    if (!file_exists(__DIR__ . '/acceptance_letters')) {
        mkdir(__DIR__ . '/acceptance_letters', 0755, true);
    }
    
    if (!file_put_contents($filepath, $letter_content)) {
        throw new Exception("Failed to save acceptance letter.");
    }
    
    // Update the application with the acceptance letter path
    $stmt = $pdo->prepare("UPDATE applications SET acceptance_letter_path = ? WHERE id = ?");
    $stmt->execute([$filepath, $application['id']]);
    
    return $filepath;
}

try {
    // Fetch student data with attachment dates
    $stmt = $pdo->prepare("SELECT s.*, u.full_name, u.phone, u.email AS address 
                          FROM students s 
                          LEFT JOIN users u ON s.user_id = u.id 
                          WHERE s.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student_data) {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE student_id = ?");
        $stmt->execute([$student_data['id']]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        error_log("No student data found for user_id: " . $_SESSION['user_id']);
        set_alert("Student profile not found. Please complete your profile.", 'error');
        redirect('profile.php');
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    set_alert("Database error occurred. Please try again later.", 'error');
    redirect('student_dashboard.php');
}

// Handle viewing acceptance letter
if (isset($_GET['view_acceptance'])) {
    if ($application && $application['status'] === 'accepted') {
        try {
            // If no acceptance letter path, generate one
            if (empty($application['acceptance_letter_path'])) {
                $filepath = generate_acceptance_letter($pdo, $student_data, $application);
                // Refresh application data to get the new path
                $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
                $stmt->execute([$application['id']]);
                $application = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $filepath = realpath($application['acceptance_letter_path']);
            }
            
            if ($filepath && file_exists($filepath) && is_readable($filepath)) {
                header('Content-Type: text/html; charset=UTF-8');
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                readfile($filepath);
                ob_end_flush(); // Flush output buffer
                exit;
            } else {
                error_log("Acceptance letter file not found or not accessible: " . ($application['acceptance_letter_path'] ?? 'null'));
                set_alert("Unable to display acceptance letter. File not found or inaccessible.", 'error');
                redirect('student_dashboard.php');
            }
        } catch (Exception $e) {
            error_log("Error generating/viewing acceptance letter: " . $e->getMessage());
            set_alert("Failed to generate or display acceptance letter: " . $e->getMessage(), 'error');
            redirect('student_dashboard.php');
        }
    } else {
        set_alert("No valid acceptance letter available.", 'error');
        redirect('student_dashboard.php');
    }
}

// Handle downloading acceptance letter
if (isset($_GET['download_acceptance'])) {
    if ($application && $application['status'] === 'accepted') {
        try {
            // If no acceptance letter path, generate one
            if (empty($application['acceptance_letter_path'])) {
                $filepath = generate_acceptance_letter($pdo, $student_data, $application);
                // Refresh application data to get the new path
                $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
                $stmt->execute([$application['id']]);
                $application = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $filepath = realpath($application['acceptance_letter_path']);
            }
            
            if ($filepath && file_exists($filepath) && is_readable($filepath)) {
                header('Content-Type: text/html');
                header('Content-Disposition: attachment; filename="acceptance_letter.html"');
                header('Content-Length: ' . filesize($filepath));
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                readfile($filepath);
                ob_end_flush(); // Flush output buffer
                exit;
            } else {
                error_log("Acceptance letter file not found or not accessible for download: " . ($application['acceptance_letter_path'] ?? 'null'));
                set_alert("Unable to download acceptance letter. File not found or inaccessible.", 'error');
                redirect('student_dashboard.php');
            }
        } catch (Exception $e) {
            error_log("Error generating/downloading acceptance letter: " . $e->getMessage());
            set_alert("Failed to generate or download acceptance letter: " . $e->getMessage(), 'error');
            redirect('student_dashboard.php');
        }
    } else {
        set_alert("No valid acceptance letter available for download.", 'error');
        redirect('student_dashboard.php');
    }
}

// Handle file uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply']) && $window_status['application_window_open'] && (!$application || $application['status'] === 'cancelled')) {
    try {
        $upload_dir = __DIR__ . '/uploads/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $dept = $student_data['preferred_department'];
        if ($window_status[$dept.'_slots_remaining'] <= 0) {
            throw new Exception("No available slots in your preferred department.");
        }

        $application_letter_path = process_upload('application_letter', $upload_dir);
        $insurance_path = process_upload('insurance', $upload_dir);
        $cv_path = process_upload('cv', $upload_dir);
        $introduction_letter_path = process_upload('introduction_letter', $upload_dir);

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

        $stmt = $pdo->prepare("UPDATE system_settings SET {$dept}_slots_remaining = {$dept}_slots_remaining - 1 
                              WHERE id = 1");
        $stmt->execute();

        set_alert("Application submitted successfully!", 'success');
        redirect('student_dashboard.php');
    } catch (Exception $e) {
        error_log("Upload error: " . $e->getMessage());
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .card {
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .status-indicator {
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
        .slot-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #0d6efd;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin: 0 auto 10px;
            font-weight: bold;
        }
        .slot-circle .remaining {
            font-size: 2rem;
            line-height: 1;
        }
        .slot-circle .total {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        .slot-status.slot-available {
            color: #28a745;
        }
        .slot-status.slot-full {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Western Region ICT Authority</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link"><i class="fas fa-user me-1"></i> <?= htmlspecialchars($user['full_name'] ?? 'Guest') ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php"><i class="fas fa-cog me-1"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if (display_alert()): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['alert_type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['alert_message'] ?? '') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="alert alert-primary mb-4">
            <h2 class="h4 mb-1">Welcome, <?= htmlspecialchars($user['full_name'] ?? 'Guest') ?>!</h2>
            <p>Student Attachment Program Dashboard</p>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
            <div class="col">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title"><i class="fas fa-info-circle me-2"></i> My Information</h3>
                        <p><strong>Institution:</strong> <?= htmlspecialchars($student_data['institution'] ?? 'N/A') ?></p>
                        <p><strong>Course:</strong> <?= htmlspecialchars($student_data['course'] ?? 'N/A') ?></p>
                        <p><strong>Year of Study:</strong> <?= htmlspecialchars($student_data['year_of_study'] ?? 'N/A') ?></p>
                        <p><strong>Preferred Department:</strong> 
                            <?= ucfirst(htmlspecialchars($student_data['preferred_department'] ?? 'N/A')) ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="col">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title"><i class="fas fa-file-alt me-2"></i> Application Status</h3>
                        <?php if ($application): ?>
                            <p><strong>Status:</strong> 
                                <span class="status-indicator status-<?= htmlspecialchars($application['status']) ?>">
                                    <?= ucfirst(htmlspecialchars($application['status'])) ?>
                                </span>
                            </p>
                            <p><strong>Submitted:</strong> <?= date('M j, Y', strtotime($application['updated_at'])) ?></p>
                            
                            <?php if ($application['status'] === 'accepted'): ?>
                                <div class="alert alert-success">
                                    <h4 class="alert-heading"><i class="fas fa-file-alt me-2"></i> Acceptance Letter</h4>
                                    <p>Your application has been accepted! You can now download or view your acceptance letter.</p>
                                    <div class="d-flex gap-2">
                                        <a href="?download_acceptance=1" class="btn btn-success"><i class="fas fa-download me-1"></i> Download Letter</a>
                                        <a href="?view_acceptance=1" class="btn btn-primary"><i class="fas fa-eye me-1"></i> View Letter</a>
                                    </div>
                                </div>
                            <?php elseif (in_array($application['status'], ['rejected', 'forwarded'])): ?>
                                <div class="alert alert-info">
                                    <h4 class="alert-heading">Feedback:</h4>
                                    <?php if ($application['status'] === 'rejected' && !empty($application['ict_notes'])): ?>
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
                </div>
            </div>

            <div class="col">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title"><i class="fas fa-calendar-alt me-2"></i> Application Window</h3>
                        <p><strong>Status:</strong> 
                            <?= $window_status['application_window_open'] ? 
                                '<span class="text-success">OPEN</span>' : 
                                '<span class="text-danger">CLOSED</span>' ?>
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
            </div>
        </div>

        <?php if ($window_status['application_window_open'] && (!$application || $application['status'] === 'cancelled')): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <h3 class="card-title"><i class="fas fa-users me-2"></i> Available Slots</h3>
                    <div class="row row-cols-1 row-cols-md-3 g-4">
                        <div class="col">
                            <div class="card h-100 text-center">
                                <div class="card-body">
                                    <div class="slot-department fw-bold">HR Department</div>
                                    <div class="slot-circle">
                                        <span class="remaining"><?= $window_status['hr_slots_remaining'] ?></span>
                                        <span class="total">of <?= $window_status['hr_slots'] ?></span>
                                    </div>
                                    <div class="slot-status <?= $window_status['hr_slots_remaining'] > 0 ? 'slot-available' : 'slot-full' ?>">
                                        <?= $window_status['hr_slots_remaining'] > 0 ? 'Available' : 'Full' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card h-100 text-center">
                                <div class="card-body">
                                    <div class="slot-department fw-bold">ICT Department</div>
                                    <div class="slot-circle">
                                        <span class="remaining"><?= $window_status['ict_slots_remaining'] ?></span>
                                        <span class="total">of <?= $window_status['ict_slots'] ?></span>
                                    </div>
                                    <div class="slot-status <?= $window_status['ict_slots_remaining'] > 0 ? 'slot-available' : 'slot-full' ?>">
                                        <?= $window_status['ict_slots_remaining'] > 0 ? 'Available' : 'Full' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card h-100 text-center">
                                <div class="card-body">
                                    <div class="slot-department fw-bold">Registry Department</div>
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
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="card-title"><i class="fas fa-edit me-2"></i> Submit Application</h3>
                    <form action="student_dashboard.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="application_letter" class="form-label fw-bold">Application Letter (PDF)</label>
                            <input type="file" class="form-control" id="application_letter" name="application_letter" accept=".pdf" required>
                        </div>
                        <div class="mb-3">
                            <label for="insurance" class="form-label fw-bold">Insurance Cover (PDF)</label>
                            <input type="file" class="form-control" id="insurance" name="insurance" accept=".pdf" required>
                        </div>
                        <div class="mb-3">
                            <label for="cv" class="form-label fw-bold">Curriculum Vitae (PDF)</label>
                            <input type="file" class="form-control" id="cv" name="cv" accept=".pdf" required>
                        </div>
                        <div class="mb-3">
                            <label for="introduction_letter" class="form-label fw-bold">Introduction Letter from Institution (PDF)</label>
                            <input type="file" class="form-control" id="introduction_letter" name="introduction_letter" accept=".pdf" required>
                        </div>
                        <button type="submit" name="apply" class="btn btn-primary" 
                            <?= $window_status[$student_data['preferred_department'].'_slots_remaining'] <= 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-paper-plane me-1"></i> Submit Application
                        </button>
                        <?php if ($window_status[$student_data['preferred_department'].'_slots_remaining'] <= 0): ?>
                            <p class="text-danger mt-2">
                                No slots remaining in your preferred department. You cannot apply at this time.
                            </p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php ob_end_flush(); // Flush output buffer at the end ?>
</body>
</html>