<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Only allow students
if (!isLoggedIn() || getUserRole() !== 'student') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$student = getStudentByUserId($user_id);
$settings = getSystemSettings();
$applications = getUserApplications($user_id);
$has_pending_application = false;

foreach ($applications as $app) {
    if ($app['status'] === 'pending' || $app['status'] === 'forwarded') {
        $has_pending_application = true;
        break;
    }
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $settings['application_window_open'] && !$has_pending_application) {
    $department_preference = sanitizeInput($_POST['department_preference']);
    $side_hustle = sanitizeInput($_POST['side_hustle']);

    $application_letter = $_FILES['application_letter'];
    $insurance = $_FILES['insurance'];
    $cv = $_FILES['cv'];
    $introduction_letter = $_FILES['introduction_letter'];

    // Validate inputs
    if (empty($department_preference)) {
        $errors['department_preference'] = 'Department preference is required';
    } elseif (!in_array($department_preference, ['hr', 'ict', 'registry'])) {
        $errors['department_preference'] = 'Invalid department selection';
    }

    $required_files = [
        'application_letter' => $application_letter,
        'insurance' => $insurance,
        'cv' => $cv,
        'introduction_letter' => $introduction_letter
    ];

    foreach ($required_files as $field => $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[$field] = 'File upload is required for ' . str_replace('_', ' ', $field);
        }
    }

    // Check available slots for the selected department
    if (empty($errors)) {
        $slot_field = $department_preference . '_slots_remaining';
        if ($settings[$slot_field] <= 0) {
            $errors['general'] = "No slots available in the $department_preference department.";
        }
    }

    // Handle file uploads
    if (empty($errors)) {
        $uploads = [];
        $upload_errors = false;

        foreach ($required_files as $field => $file) {
            $result = uploadFile($file, $student['id'] . '_' . $field);
            if ($result['success']) {
                $uploads[$field] = $result['file_name'];
            } else {
                $errors[$field] = $result['message'];
                $upload_errors = true;
            }
        }

        // Insert application if no upload errors
        if (!$upload_errors) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO applications (student_id, department_preference, application_letter, insurance, cv, introduction_letter, side_hustle, applied_at, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')
                ");
                $stmt->execute([
                    $student['id'],
                    $department_preference,
                    $uploads['application_letter'],
                    $uploads['insurance'],
                    $uploads['cv'],
                    $uploads['introduction_letter'],
                    $side_hustle
                ]);

                // Update slots
                $new_slots = $settings[$department_preference . '_slots_remaining'] - 1;
                $stmt = $pdo->prepare("
                    UPDATE system_settings 
                    SET {$department_preference}_slots_remaining = ?, updated_at = NOW(), updated_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$new_slots, $user_id, $settings['id']]);

                $pdo->commit();
                $success = 'Registration successful! Welcome to the Western Region ICT Authority attachment program.';
            } catch (PDOException $e) {
                $pdo->rollBack();
                foreach ($uploads as $file) {
                    $file_path = UPLOAD_DIR . $file;
                    if (file_exists($file_path)) unlink($file_path);
                }
                $errors['general'] = 'Failed to submit application: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Attachment - Kakamega ICT Authority</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #ffffff;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        .sidebar a.nav-link {
            color: #495057;
            transition: background-color 0.2s;
        }
        .sidebar a.nav-link:hover {
            background-color: #f1f3f5;
        }
        .sidebar a.active {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .card {
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <aside class="sidebar p-4">
            <div class="text-center mb-4">
                <img src="ictlogo.jpeg" alt="Kakamega ICT Logo" class="img-fluid mx-auto" style="max-height: 64px;">
                <h3 class="mt-2 fs-5 fw-bold">Student Dashboard</h3>
            </div>
            <nav class="nav flex-column">
                <a href="student_dashboard.php" class="nav-link"><i class="fas fa-home me-2"></i>Home</a>
                <a href="apply_attachment.php" class="nav-link active"><i class="fas fa-file-alt me-2"></i>Apply for Attachment</a>
                <a href="update_profile.php" class="nav-link"><i class="fas fa-user-edit me-2"></i>Update Profile</a>
                <a href="change_password.php" class="nav-link"><i class="fas fa-key me-2"></i>Change Password</a>
                <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
            </nav>
            <div class="mt-4 text-center text-muted small">
                Logged in as: <strong><?php echo htmlspecialchars($user['username']); ?></strong>
            </div>
        </aside>

        <main class="flex-grow-1 p-4">
            <header class="mb-4">
                <h1 class="h2 fw-bold">Apply for Attachment</h1>
                <div class="mt-2 text-muted small">
                    <span><i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($user['full_name']); ?></span>
                    <span class="ms-3"><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($user['email']); ?></span>
                </div>
            </header>

            <?php if (!$settings['application_window_open']): ?>
                <div class="alert alert-warning" role="alert">
                    <h3 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Application Window Closed</h3>
                    <p>The application window is currently closed. Please check back later.</p>
                    <a href="student_dashboard.php" class="btn btn-secondary mt-2">Back to Dashboard</a>
                </div>
            <?php elseif ($has_pending_application): ?>
                <div class="alert alert-info" role="alert">
                    <h3 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Pending Application</h3>
                    <p>You have a pending application. Please wait for processing.</p>
                    <a href="student_dashboard.php" class="btn btn-secondary mt-2">View Status</a>
                </div>
            <?php else: ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h2 class="card-title h4 fw-bold"><i class="fas fa-file-alt me-2"></i>Attachment Application Form</h2>
                        <?php if (!empty($errors['general'])): ?>
                            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($errors['general']); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert" id="successMessage"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <form action="apply_attachment.php" method="POST" enctype="multipart/form-data" class="row g-3" id="applicationForm">
                            <div class="col-12">
                                <label for="department_preference" class="form-label fw-bold">Preferred Department</label>
                                <select id="department_preference" name="department_preference" required class="form-select">
                                    <option value="">Select Department</option>
                                    <option value="hr" <?php echo (isset($_POST['department_preference']) && $_POST['department_preference'] === 'hr') ? 'selected' : ''; ?>>HR Department</option>
                                    <option value="ict" <?php echo (isset($_POST['department_preference']) && $_POST['department_preference'] === 'ict') ? 'selected' : ''; ?>>ICT Department</option>
                                    <option value="registry" <?php echo (isset($_POST['department_preference']) && $_POST['department_preference'] === 'registry') ? 'selected' : ''; ?>>Registry Department</option>
                                </select>
                                <?php if (!empty($errors['department_preference'])): ?>
                                    <div class="text-danger small"><?php echo htmlspecialchars($errors['department_preference']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <label for="application_letter" class="form-label fw-bold">Application Letter (PDF/DOC/DOCX)</label>
                                <input type="file" id="application_letter" name="application_letter" accept=".pdf,.doc,.docx" required class="form-control">
                                <?php if (!empty($errors['application_letter'])): ?>
                                    <div class="text-danger small"><?php echo htmlspecialchars($errors['application_letter']); ?></div>
                                <?php endif; ?>
                                <div class="form-text">Upload your formal application letter</div>
                            </div>

                            <div class="col-12">
                                <label for="insurance" class="form-label fw-bold">Insurance Cover (PDF/DOC/DOCX/JPG/JPEG/PNG)</label>
                                <input type="file" id="insurance" name="insurance" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required class="form-control">
                                <?php if (!empty($errors['insurance'])): ?>
                                    <div class="text-danger small"><?php echo htmlspecialchars($errors['insurance']); ?></div>
                                <?php endif; ?>
                                <div class="form-text">Upload proof of valid insurance</div>
                            </div>

                            <div class="col-12">
                                <label for="cv" class="form-label fw-bold">Curriculum Vitae (PDF/DOC/DOCX)</label>
                                <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx" required class="form-control">
                                <?php if (!empty($errors['cv'])): ?>
                                    <div class="text-danger small"><?php echo htmlspecialchars($errors['cv']); ?></div>
                                <?php endif; ?>
                                <div class="form-text">Upload your current CV</div>
                            </div>

                            <div class="col-12">
                                <label for="introduction_letter" class="form-label fw-bold">Introduction Letter (PDF/DOC/DOCX)</label>
                                <input type="file" id="introduction_letter" name="introduction_letter" accept=".pdf,.doc,.docx" required class="form-control">
                                <?php if (!empty($errors['introduction_letter'])): ?>
                                    <div class="text-danger small"><?php echo htmlspecialchars($errors['introduction_letter']); ?></div>
                                <?php endif; ?>
                                <div class="form-text">Upload your institution's letter</div>
                            </div>

                            <div class="col-12">
                                <label for="side_hustle" class="form-label fw-bold">Side Hustles/Skills (Optional)</label>
                                <textarea id="side_hustle" name="side_hustle" rows="3" class="form-control"><?php echo htmlspecialchars($_POST['side_hustle'] ?? ''); ?></textarea>
                                <div class="form-text">List any side hustles or skills</div>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary me-2"><i class="fas fa-paper-plane me-1"></i>Submit Application</button>
                                <a href="student_dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title h5 fw-bold"><i class="fas fa-info-circle me-2"></i>Application Status</h3>
                        <p><strong>Application Window:</strong> <?php echo $settings['application_window_open'] ? '<span class="text-success">OPEN</span>' : '<span class="text-danger">CLOSED</span>'; ?></p>
                        <p><strong>HR Slots Available:</strong> <?php echo $settings['hr_slots_remaining']; ?> / <?php echo $settings['hr_slots']; ?></p>
                        <p><strong>ICT Slots Available:</strong> <?php echo $settings['ict_slots_remaining']; ?> / <?php echo $settings['ict_slots']; ?></p>
                        <p><strong>Registry Slots Available:</strong> <?php echo $settings['registry_slots_remaining']; ?> / <?php echo $settings['registry_slots']; ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <footer class="mt-4 text-center text-muted small py-3">
        <p>© 2025 Kakamega Regional ICT Authority | Developed by Justin Ratemo - 0793031269</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('applicationForm')?.addEventListener('submit', function(e) {
            const files = {
                'application_letter': ['.pdf', '.doc', '.docx'],
                'insurance': ['.pdf', '.doc', '.docx', '.jpg', '.jpeg', '.png'],
                'cv': ['.pdf', '.doc', '.docx'],
                'introduction_letter': ['.pdf', '.doc', '.docx']
            };
            let hasError = false;
            for (const [field, allowedTypes] of Object.entries(files)) {
                const input = document.getElementById(field);
                if (!input.files[0]) {
                    hasError = true;
                    input.nextElementSibling.textContent = 'This file is required';
                } else {
                    const fileExt = '.' + input.files[0].name.split('.').pop().toLowerCase();
                    if (!allowedTypes.includes(fileExt)) {
                        hasError = true;
                        input.nextElementSibling.textContent = `Allowed types: ${allowedTypes.join(', ')}`;
                    }
                }
            }
            if (hasError) {
                e.preventDefault();
                alert('Please upload all required files with valid formats.');
            }
        });

        // Hide success message after 10 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                setTimeout(function() {
                    successMessage.style.display = 'none';
                }, 10000); // 10 seconds
            }
        });
    </script>
</body>
</html>