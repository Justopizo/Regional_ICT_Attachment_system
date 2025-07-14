<?php
require_once 'db_connect.php';
require_once 'functions.php';

check_role(['hr']);

$user = get_current_user_data();

// Handle application window toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_window'])) {
    try {
        $status = $_POST['window_status'] === 'open' ? 1 : 0;
        $hr_slots = intval($_POST['hr_slots']);
        $ict_slots = intval($_POST['ict_slots']);
        $registry_slots = intval($_POST['registry_slots']);
        
        $stmt = $pdo->prepare("UPDATE system_settings SET 
            application_window_open = ?,
            hr_slots = ?,
            ict_slots = ?,
            registry_slots = ?,
            hr_slots_remaining = ?,
            ict_slots_remaining = ?,
            registry_slots_remaining = ?,
            updated_by = ?,
            updated_at = NOW()
            WHERE id = 1");
        
        $stmt->execute([
            $status,
            $hr_slots,
            $ict_slots,
            $registry_slots,
            $hr_slots,
            $ict_slots,
            $registry_slots,
            $_SESSION['user_id']
        ]);
        
        set_alert("Application window " . ($status ? "opened" : "closed") . " successfully!", 'success');
        redirect('hr_dashboard.php');
    } catch (PDOException $e) {
        set_alert("Error updating application window: " . $e->getMessage(), 'error');
    }
}

// Handle application forwarding
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forward_application'])) {
    $application_id = intval($_POST['application_id']);
    $department = $_POST['department'];
    $comments = sanitize_input($_POST['comments']);
    
    try {
        $stmt = $pdo->prepare("UPDATE applications SET 
            status = 'forwarded',
            forwarded_to = ?,
            hr_notes = ?,
            updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([$department, $comments, $application_id]);
        
        set_alert("Application forwarded to $department department", 'success');
        redirect('hr_dashboard.php');
    } catch (PDOException $e) {
        set_alert("Error forwarding application: " . $e->getMessage(), 'error');
    }
}

// Handle AJAX request for application details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_application'])) {
    $application_id = intval($_GET['get_application']);
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.full_name, u.email, u.phone,
                   s.institution, s.course, s.preferred_department, 
                   s.year_of_study, s.side_hustle, s.attachment_start_date, s.attachment_end_date,
                   a.application_letter_path AS cover_letter,
                   a.cv_path AS cv_filename,
                   a.introduction_letter_path AS recommendation_letter_filename,
                   a.insurance_path AS transcript_filename
            FROM applications a
            JOIN students s ON a.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($application) {
            header('Content-Type: application/json');
            echo json_encode($application);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Application not found']);
        }
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Get pending applications
$applications = [];
try {
    $stmt = $pdo->query("
        SELECT a.id, a.status, a.forwarded_to, a.updated_at,
               u.full_name, s.institution, s.course, s.preferred_department
        FROM applications a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE a.status = 'pending'
        ORDER BY a.updated_at DESC
    ");
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_alert("Error fetching applications: " . $e->getMessage(), 'error');
}

// Get past applications for when no pending applications exist
$past_applications = [];
if (empty($applications)) {
    try {
        $stmt = $pdo->query("
            SELECT a.id, a.status, a.forwarded_to, a.updated_at,
                   u.full_name, s.institution, s.course, s.preferred_department
            FROM applications a
            JOIN students s ON a.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE a.status != 'pending'
            ORDER BY a.updated_at DESC
            LIMIT 10
        ");
        $past_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        set_alert("Error fetching past applications: " . $e->getMessage(), 'error');
    }
}

$settings = get_application_window_status();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard - Western Region ICT Authority</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Styles -->
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #f8f9fa;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color), #34495e);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-custom .navbar-brand {
            color: white !important;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .navbar-custom .nav-link {
            color: rgba(255,255,255,0.9) !important;
        }

        .navbar-custom .nav-link:hover {
            color: white !important;
        }

        .welcome-banner {
            background: linear-gradient(135deg, var(--secondary-color), #5dade2);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(52, 152, 219, 0.3);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .table-custom {
            border-radius: 10px;
            overflow: hidden;
        }

        .table-custom thead th {
            background: linear-gradient(135deg, var(--primary-color), #34495e);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
        }

        .badge-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-pending {
            background: linear-gradient(135deg, #ffeaa7, #fdcb6e);
            color: #6c5ce7;
        }

        .btn-action {
            border-radius: 25px;
            padding: 0.4rem 1rem;
            font-size: 0.85rem;
            font-weight: 500;
            margin: 0.2rem;
            transition: all 0.3s ease;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-header {
            border-radius: 15px 15px 0 0;
            background: linear-gradient(135deg, var(--primary-color), #34495e);
            color: white;
        }

        .stats-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1rem;
        }

        .document-link {
            color: var(--secondary-color);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .document-link:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-building"></i> Western Region ICT Authority - HR Dashboard
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cogs"></i> Settings
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <form action="hr_dashboard.php" method="POST" class="p-3">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Application Window Status</label>
                                        <div class="radio-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="window_status" id="window_open" value="open" 
                                                    <?= $settings['application_window_open'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="window_open">
                                                    <i class="fas fa-unlock text-success"></i> Open
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="window_status" id="window_closed" value="closed" 
                                                    <?= !$settings['application_window_open'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="window_closed">
                                                    <i class="fas fa-lock text-danger"></i> Closed
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="hr_slots" class="form-label fw-bold">
                                            <i class="fas fa-users"></i> HR Department Slots
                                        </label>
                                        <input type="number" class="form-control" id="hr_slots" name="hr_slots" min="0" 
                                            value="<?= $settings['hr_slots'] ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="ict_slots" class="form-label fw-bold">
                                            <i class="fas fa-laptop-code"></i> ICT Department Slots
                                        </label>
                                        <input type="number" class="form-control" id="ict_slots" name="ict_slots" min="0" 
                                            value="<?= $settings['ict_slots'] ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="registry_slots" class="form-label fw-bold">
                                            <i class="fas fa-archive"></i> Registry Department Slots
                                        </label>
                                        <input type="number" class="form-control" id="registry_slots" name="registry_slots" min="0" 
                                            value="<?= $settings['registry_slots'] ?>" required>
                                    </div>
                                    <button type="submit" name="toggle_window" class="btn btn-primary btn-custom w-100">
                                        <i class="fas fa-save"></i> Save Settings
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($user['full_name']) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-cog"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php display_alert(); ?>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-tachometer-alt"></i> Welcome, <?= htmlspecialchars($user['full_name']) ?>!</h2>
                    <p class="mb-0">Human Resources Administrator Dashboard</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="stats-card bg-white bg-opacity-10 text-white">
                        <i class="fas fa-file-alt"></i>
                        <h3><?= count($applications) ?></h3>
                        <p class="mb-0">Pending Applications</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applications Section -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-file-alt"></i> Applications</h5>
            </div>
            <div class="card-body">
                <?php if (empty($applications)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No pending applications at this time.</h5>
                        <?php if (!empty($past_applications)): ?>
                            <button class="btn btn-primary btn-custom mt-3" onclick="showPastApplications()">
                                <i class="fas fa-history"></i> View Past Applications
                            </button>
                        <?php endif; ?>
                    </div>
                    <div id="pastApplications" style="display: none;">
                        <h5 class="mb-3">Past Applications</h5>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Institution</th>
                                        <th>Course</th>
                                        <th>Preferred Dept</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($past_applications as $app): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($app['full_name']) ?></td>
                                            <td><?= htmlspecialchars($app['institution']) ?></td>
                                            <td><?= htmlspecialchars($app['course']) ?></td>
                                            <td><span class="badge bg-info"><?= strtoupper($app['preferred_department']) ?></span></td>
                                            <td><span class="badge badge-status"><?= ucfirst($app['status']) ?></span></td>
                                            <td>
                                                <button class="btn btn-primary btn-action btn-sm" 
                                                        onclick="viewApplication(<?= $app['id'] ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Institution</th>
                                    <th>Course</th>
                                    <th>Preferred Dept</th>
                                    <th>Submitted On</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($app['full_name']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($app['institution']) ?></td>
                                        <td><?= htmlspecialchars($app['course']) ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= strtoupper($app['preferred_department']) ?></span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($app['updated_at'])) ?></td>
                                        <td>
                                            <span class="badge badge-status badge-pending">Pending</span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-primary btn-action btn-sm" 
                                                        onclick="viewApplication(<?= $app['id'] ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn btn-success btn-action btn-sm" 
                                                        onclick="forwardApplication(<?= $app['id'] ?>, '<?= $app['preferred_department'] ?>')">
                                                    <i class="fas fa-share"></i> Forward
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Application Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalLabel">
                        <i class="fas fa-file-alt"></i> Application Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="applicationDetails">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading application details...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Forward Application Modal -->
    <div class="modal fade" id="forwardModal" tabindex="-1" aria-labelledby="forwardModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="forwardModalLabel">
                        <i class="fas fa-share"></i> Forward Application
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="forwardForm" action="hr_dashboard.php" method="POST">
                        <input type="hidden" id="forward_application_id" name="application_id">
                        <div class="mb-3">
                            <label for="department" class="form-label fw-bold">Forward To Department</label>
                            <select id="department" name="department" class="form-select" required>
                                <option value="">Select Department</option>
                                <option value="ict">ICT Department</option>
                                <option value="registry">Registry Department</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="comments" class="form-label fw-bold">Comments</label>
                            <textarea id="comments" name="comments" rows="4" class="form-control" 
                                placeholder="Add any comments or notes for the receiving department..."></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="forward_application" class="btn btn-success btn-custom">
                                <i class="fas fa-share"></i> Forward Application
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        /**
         * Displays application details in a modal
         * @param {number} appId - The application ID
         */
        function viewApplication(appId) {
            const modal = new bootstrap.Modal(document.getElementById('viewModal'));
            modal.show();
            
            document.getElementById('applicationDetails').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading application details...</p>
                </div>
            `;
            
            fetch(`hr_dashboard.php?get_application=${appId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        showErrorInModal(data.error);
                        return;
                    }
                    displayApplicationDetails(data);
                })
                .catch(error => {
                    showErrorInModal('Error loading application details. Please try again.');
                });
        }

        /**
         * Displays application details in the modal
         * @param {object} data - Application data
         */
        function displayApplicationDetails(data) {
            const detailsHtml = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary"><i class="fas fa-user"></i> Personal Information</h6>
                                <div class="mb-2"><strong>Full Name:</strong> ${data.full_name}</div>
                                <div class="mb-2"><strong>Email:</strong> ${data.email}</div>
                                <div class="mb-2"><strong>Phone:</strong> ${data.phone}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary"><i class="fas fa-graduation-cap"></i> Academic Information</h6>
                                <div class="mb-2"><strong>Institution:</strong> ${data.institution}</div>
                                <div class="mb-2"><strong>Course:</strong> ${data.course}</div>
                                <div class="mb-2"><strong>Year of Study:</strong> ${data.year_of_study}</div>
                                <div class="mb-2"><strong>Preferred Department:</strong> 
                                    <span class="badge bg-info">${data.preferred_department.toUpperCase()}</span>
                                </div>
                                <div class="mb-2"><strong>Attachment Start:</strong> ${data.attachment_start_date || 'Not specified'}</div>
                                <div class="mb-2"><strong>Attachment End:</strong> ${data.attachment_end_date || 'Not specified'}</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                ${data.side_hustle ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary"><i class="fas fa-briefcase"></i> Side Hustle</h6>
                                <div class="border rounded p-3 bg-white">
                                    <p class="mb-0">${data.side_hustle}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}
                
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary"><i class="fas fa-file-text"></i> Application Letter</h6>
                                <div class="border rounded p-3 bg-white">
                                    <p class="mb-0">${data.cover_letter ? `<a href="${data.cover_letter}" target="_blank" class="document-link">View/Download Application Letter</a>` : 'No application letter provided.'}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary"><i class="fas fa-paperclip"></i> Uploaded Documents</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="text-center p-3 border rounded bg-white">
                                            <i class="fas fa-file-pdf fa-2x text-danger mb-2"></i>
                                            <div class="small"><strong>CV/Resume</strong></div>
                                            <div class="small">${data.cv_filename ? `<a href="${data.cv_filename}" target="_blank" class="document-link">View/Download</a>` : 'Not uploaded'}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center p-3 border rounded bg-white">
                                            <i class="fas fa-file-alt fa-2x text-info mb-2"></i>
                                            <div class="small"><strong>Introduction Letter</strong></div>
                                            <div class="small">${data.recommendation_letter_filename ? `<a href="${data.recommendation_letter_filename}" target="_blank" class="document-link">View/Download</a>` : 'Not uploaded'}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center p-3 border rounded bg-white">
                                            <i class="fas fa-file-alt fa-2x text-success mb-2"></i>
                                            <div class="small"><strong>Insurance</strong></div>
                                            <div class="small">${data.transcript_filename ? `<a href="${data.transcript_filename}" target="_blank" class="document-link">View/Download</a>` : 'Not uploaded'}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('applicationDetails').innerHTML = detailsHtml;
        }

        /**
         * Shows error message in the modal
         * @param {string} message - Error message to display
         */
        function showErrorInModal(message) {
            document.getElementById('applicationDetails').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> ${message}
                </div>
            `;
        }

        /**
         * Opens the forward application modal
         * @param {number} appId - Application ID to forward
         * @param {string} preferredDept - Preferred department for forwarding
         */
        function forwardApplication(appId, preferredDept) {
            document.getElementById('forward_application_id').value = appId;
            
            if (preferredDept) {
                const deptSelect = document.getElementById('department');
                for (let i = 0; i < deptSelect.options.length; i++) {
                    if (deptSelect.options[i].value === preferredDept) {
                        deptSelect.selectedIndex = i;
                        break;
                    }
                }
            }
            
            const modal = new bootstrap.Modal(document.getElementById('forwardModal'));
            modal.show();
        }

        /**
         * Shows past applications table
         */
        function showPastApplications() {
            const pastAppsDiv = document.getElementById('pastApplications');
            pastAppsDiv.style.display = 'block';
            pastAppsDiv.scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>