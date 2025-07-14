<?php
require_once 'db_connect.php';
require_once 'functions.php';

check_role(['ict']);

$user = get_current_user_data();

// Handle application decision
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['process_application'])) {
        $application_id = intval($_POST['application_id']);
        $decision = $_POST['decision'];
        $comments = sanitize_input($_POST['comments'] ?? '');
        
        try {
            $pdo->beginTransaction();
            
            // Update application status
            $stmt = $pdo->prepare("UPDATE applications SET 
                status = ?,
                ict_notes = ?,
                updated_at = NOW()
                WHERE id = ? AND forwarded_to = 'ict'");
            $stmt->execute([$decision, $comments, $application_id]);
            
            // If accepted, reduce available slots
            if ($decision === 'accepted') {
                // Get forwarded department from application
                $stmt = $pdo->prepare("SELECT forwarded_to FROM applications WHERE id = ?");
                $stmt->execute([$application_id]);
                $app_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($app_data && $app_data['forwarded_to']) {
                    // Reduce available slots in system settings
                    $column = $app_data['forwarded_to'] . '_slots_remaining';
                    $stmt = $pdo->prepare("UPDATE system_settings 
                                           SET $column = $column - 1 
                                           WHERE $column > 0");
                    $stmt->execute();
                    
                    if ($stmt->rowCount() === 0) {
                        throw new Exception("No available slots in this department");
                    }
                }
            }
            
            $pdo->commit();
            set_alert("Application has been " . $decision, 'success');
            redirect('ict_dashboard.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_alert("Error processing application: " . $e->getMessage(), 'error');
        }
    }
}

// Get applications forwarded to ICT
$applications = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, u.full_name, u.email, u.phone, s.institution, s.course, s.year_of_study
        FROM applications a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE a.forwarded_to = 'ict' AND a.status = 'forwarded'
        ORDER BY a.updated_at DESC
    ");
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_alert("Error fetching applications: " . $e->getMessage(), 'error');
}

// Get past applicants (processed applications, including those forwarded to ICT)
$past_applicants = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, u.full_name, u.email, u.phone, s.institution, s.course, s.year_of_study
        FROM applications a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE a.status IN ('accepted', 'rejected', 'pending')
        ORDER BY a.updated_at DESC
        LIMIT 50
    ");
    $past_applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_alert("Error fetching past applicants: " . $e->getMessage(), 'error');
}

// Handle document view request
$application_details = null;
if (isset($_GET['view'])) {
    $app_id = intval($_GET['view']);
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.full_name, u.email, u.phone, s.institution, s.course, 
                   s.year_of_study, s.side_hustle, s.attachment_start_date, s.attachment_end_date
            FROM applications a
            JOIN students s ON a.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE a.id = ? AND a.forwarded_to = 'ict'
        ");
        $stmt->execute([$app_id]);
        $application_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Format dates for display
        if ($application_details) {
            $application_details['formatted_start_date'] = $application_details['attachment_start_date'] 
                ? date('F j, Y', strtotime($application_details['attachment_start_date'])) 
                : 'Not specified';
                
            $application_details['formatted_end_date'] = $application_details['attachment_end_date'] 
                ? date('F j, Y', strtotime($application_details['attachment_end_date'])) 
                : 'Not specified';
                
            $application_details['formatted_updated_at'] = date('F j, Y g:i A', strtotime($application_details['updated_at']));
        }
    } catch (PDOException $e) {
        set_alert("Error fetching application details: " . $e->getMessage(), 'error');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICT Dashboard - Western Region ICT Authority</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .top-navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 20px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            font-weight: 600;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-forwarded {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-accepted {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .document-preview {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .document-item {
            margin-bottom: 20px;
        }
        
        .applicant-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .action-btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            display: none;
        }
        
        .spinner {
            width: 3rem;
            height: 3rem;
        }
        
        .document-link {
            display: inline-block;
            padding: 8px 15px;
            background-color: #f0f0f0;
            border-radius: 5px;
            margin-right: 10px;
            margin-bottom: 10px;
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .document-link:hover {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .document-link i {
            margin-right: 5px;
        }
        
        @media (max-width: 768px) {
            .applicant-details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <div class="top-navbar d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0">ICT Dashboard</h4>
        </div>
        <div class="d-flex align-items-center">
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle me-2"></i>
                    <?= htmlspecialchars($user['full_name']) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Page Content -->
    <div class="container-fluid p-4">
        <?php display_alert(); ?>
        
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <h2><i class="fas fa-tachometer-alt me-2"></i> ICT Dashboard</h2>
                    <p class="mb-0">Welcome back, <?= htmlspecialchars($user['full_name']) ?>!</p>
                </div>
            </div>
        </div>

        <!-- Applications Card -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i> Applications Forwarded to ICT</h5>
                <div>
                    <span class="badge bg-primary me-2"><?= count($applications) ?> pending</span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="viewPastApplicants()">
                        <i class="fas fa-history me-1"></i> View Past Applicants
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($applications)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No applications have been forwarded to ICT at this time.
                        <div class="mt-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewPastApplicants()">
                                <i class="fas fa-history me-1"></i> View Past Applicants
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Student Name</th>
                                    <th>Institution</th>
                                    <th>Course</th>
                                    <th>Submitted On</th>
                                    <th>HR Notes</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($app['full_name']) ?></td>
                                        <td><?= htmlspecialchars($app['institution']) ?></td>
                                        <td><?= htmlspecialchars($app['course']) ?></td>
                                        <td><?= date('M j, Y', strtotime($app['updated_at'])) ?></td>
                                        <td><?= !empty($app['hr_notes']) ? htmlspecialchars($app['hr_notes']) : '<span class="text-muted">N/A</span>' ?></td>
                                        <td>
                                            <span class="status-badge status-forwarded">Forwarded</span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap">
                                                <button class="btn btn-sm btn-primary action-btn me-2 mb-2" 
                                                        onclick="viewApplication(<?= $app['id'] ?>)">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </button>
                                                <button class="btn btn-sm btn-success action-btn me-2 mb-2" 
                                                        onclick="processApplication(<?= $app['id'] ?>, 'accepted')">
                                                    <i class="fas fa-check me-1"></i> Accept
                                                </button>
                                                <button class="btn btn-sm btn-danger action-btn mb-2" 
                                                        onclick="processApplication(<?= $app['id'] ?>, 'rejected')">
                                                    <i class="fas fa-times me-1"></i> Reject
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
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="viewModalLabel">
                        <i class="fas fa-file-alt me-2"></i> Application Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (isset($application_details)): ?>
                        <div class="applicant-details-grid">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-user me-2"></i> Personal Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="detail-item">
                                        <span class="detail-label">Full Name:</span>
                                        <div><?= htmlspecialchars($application_details['full_name']) ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Email:</span>
                                        <div><?= htmlspecialchars($application_details['email']) ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Phone:</span>
                                        <div><?= htmlspecialchars($application_details['phone']) ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-graduation-cap me-2"></i> Education Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="detail-item">
                                        <span class="detail-label">Institution:</span>
                                        <div><?= htmlspecialchars($application_details['institution']) ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Course:</span>
                                        <div><?= htmlspecialchars($application_details['course']) ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Year of Study:</span>
                                        <div><?= htmlspecialchars($application_details['year_of_study']) ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i> Attachment Period</h6>
                                </div>
                                <div class="card-body">
                                    <div class="detail-item">
                                        <span class="detail-label">Start Date:</span>
                                        <div><?= $application_details['formatted_start_date'] ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">End Date:</span>
                                        <div><?= $application_details['formatted_end_date'] ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i> Additional Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="detail-item">
                                        <span class="detail-label">Skills/Side Hustle:</span>
                                        <div><?= !empty($application_details['side_hustle']) ? htmlspecialchars($application_details['side_hustle']) : 'Not specified' ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">HR Notes:</span>
                                        <div><?= !empty($application_details['hr_notes']) ? htmlspecialchars($application_details['hr_notes']) : 'No notes' ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-file-pdf me-2"></i> Application Documents</h6>
                            </div>
                            <div class="card-body">
                                <div class="document-preview">
                                    <h5>Download Documents</h5>
                                    <div class="mt-3">
                                        <a href="<?= htmlspecialchars($application_details['application_letter_path']) ?>" class="document-link" download target="_blank">
                                            <i class="fas fa-file-word"></i> Application Letter
                                        </a>
                                        <a href="<?= htmlspecialchars($application_details['cv_path']) ?>" class="document-link" download target="_blank">
                                            <i class="fas fa-file-pdf"></i> Curriculum Vitae
                                        </a>
                                        <a href="<?= htmlspecialchars($application_details['insurance_path']) ?>" class="document-link" download target="_blank">
                                            <i class="fas fa-file-invoice"></i> Insurance Cover
                                        </a>
                                        <a href="<?= htmlspecialchars($application_details['introduction_letter_path']) ?>" class="document-link" download target="_blank">
                                            <i class="fas fa-file-signature"></i> Introduction Letter
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i> Error loading application details. Please try again.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Past Applicants Modal -->
    <div class="modal fade" id="pastApplicantsModal" tabindex="-1" aria-labelledby="pastApplicantsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="pastApplicantsModalLabel">
                        <i class="fas fa-history me-2"></i> Past Applicants
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($past_applicants)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No past applicants found.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Institution</th>
                                        <th>Course</th>
                                        <th>Status</th>
                                        <th>Decision Date</th>
                                        <th>ICT Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($past_applicants as $app): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($app['full_name']) ?></td>
                                            <td><?= htmlspecialchars($app['institution']) ?></td>
                                            <td><?= htmlspecialchars($app['course']) ?></td>
                                            <td>
                                                <?php if ($app['status'] == 'accepted'): ?>
                                                    <span class="status-badge status-accepted">Accepted</span>
                                                <?php elseif ($app['status'] == 'rejected'): ?>
                                                    <span class="status-badge status-rejected">Rejected</span>
                                                <?php else: ?>
                                                    <span class="status-badge"><?= ucfirst($app['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($app['updated_at'])) ?></td>
                                            <td><?= !empty($app['ict_notes']) ? htmlspecialchars($app['ict_notes']) : 'N/A' ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary action-btn" 
                                                        onclick="viewApplicationDetails(<?= $app['id'] ?>)">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Process Application Modal -->
    <div class="modal fade" id="processModal" tabindex="-1" aria-labelledby="processModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" id="processModalHeader">
                    <h5 class="modal-title" id="processModalLabel">
                        <i class="fas fa-cog me-2"></i> Process Application
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="processForm" action="ict_dashboard.php" method="POST" onsubmit="showLoading()">
                    <div class="modal-body">
                        <input type="hidden" id="process_application_id" name="application_id">
                        <input type="hidden" id="process_decision" name="decision">
                        <div class="mb-3">
                            <label for="comments" class="form-label">Feedback/Comments</label>
                            <textarea class="form-control" id="comments" name="comments" rows="6" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                        <button type="submit" name="process_application" class="btn" id="processButton">
                            <i class="fas fa-check me-1"></i> Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Show view modal if view parameter exists
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('view')) {
                var viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
                viewModal.show();
            }
        });

        // View application function
        function viewApplication(appId) {
            window.location.href = 'ict_dashboard.php?view=' + appId;
        }

        // View past applicants
        function viewPastApplicants() {
            var pastModal = new bootstrap.Modal(document.getElementById('pastApplicantsModal'));
            pastModal.show();
        }

        // View application details from past applicants
        function viewApplicationDetails(appId) {
            // You can implement this to show details in a modal or redirect to a details page
            alert('Viewing details for application ID: ' + appId);
            // Or redirect to a details page:
            // window.location.href = 'application_details.php?id=' + appId;
        }

        // Process application function
        function processApplication(appId, decision) {
            document.getElementById('process_application_id').value = appId;
            document.getElementById('process_decision').value = decision;
            
            const processModal = new bootstrap.Modal(document.getElementById('processModal'));
            const processButton = document.getElementById('processButton');
            const processModalHeader = document.getElementById('processModalHeader');
            const processModalLabel = document.getElementById('processModalLabel');
            const commentsField = document.getElementById('comments');
            
            // Set up modal based on decision type
            if (decision === 'accepted') {
                processModalLabel.innerHTML = '<i class="fas fa-check-circle me-2"></i> Accept Application';
                processButton.className = 'btn btn-success';
                processButton.innerHTML = '<i class="fas fa-check me-1"></i> Accept Application';
                processModalHeader.className = 'modal-header bg-success text-white';
                
                // Auto-fill feedback for accepted applications
                const defaultFeedback = `1. Dress code: Official attire is required\n2. Working hours: Arrival time is 8:00 AM\n3. Payments: No forms of payments are allowed\n4. Additional notes:\n\n`;
                commentsField.value = defaultFeedback;
            } else {
                processModalLabel.innerHTML = '<i class="fas fa-times-circle me-2"></i> Reject Application';
                processButton.className = 'btn btn-danger';
                processButton.innerHTML = '<i class="fas fa-times me-1"></i> Reject Application';
                processModalHeader.className = 'modal-header bg-danger text-white';
                
                // Clear comments for rejected applications
                commentsField.value = '';
            }
            
            processModal.show();
            
            // Focus on the comments field after modal is shown
            processModal._element.addEventListener('shown.bs.modal', function() {
                commentsField.focus();
            });
        }

        // Show loading overlay
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        // Close modal and remove view parameter
        document.getElementById('viewModal').addEventListener('hidden.bs.modal', function () {
            if (window.location.href.includes('view=')) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>
</html>