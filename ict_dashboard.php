<?php
require_once 'db_connect.php';
require_once 'functions.php';

check_role(['ict']);

// Define base upload directory (relative to document root)
define('UPLOAD_DIR', '/uploads/');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . UPLOAD_DIR);

$user = get_current_user_data();

// Helper function to normalize document paths
function normalize_doc_path($path) {
    global $pdo;
    // Handle absolute server paths
    if (strpos($path, '/srv/disk7') !== false) {
        $path = preg_replace('#^/srv/disk7/\d+/www/kakamegaregionaictauthority\.atspace\.co\.uk/#', '', $path);
    }
    // Ensure path starts with 'uploads/'
    if (strpos($path, 'uploads/') === 0) {
        return $path;
    }
    // Handle malformed paths
    return 'uploads/' . basename($path);
}

// Helper function to get document URL
function get_doc_url($path) {
    $normalized = normalize_doc_path($path);
    return 'http://kakamegaregionaictauthority.atspace.co.uk/' . $normalized;
}

// Helper function to get server path
function get_server_path($path) {
    return $_SERVER['DOCUMENT_ROOT'] . '/' . normalize_doc_path($path);
}

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
                $stmt = $pdo->prepare("UPDATE system_settings 
                                       SET ict_slots_remaining = ict_slots_remaining - 1 
                                       WHERE ict_slots_remaining > 0");
                $stmt->execute();
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception("No available ICT slots remaining");
                }
            }
            
            $pdo->commit();
            set_alert("Application has been " . $decision, 'success');
            redirect('ict_dashboard.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error processing application ID $application_id: " . $e->getMessage());
            set_alert("Error processing application: " . $e->getMessage(), 'error');
            redirect('ict_dashboard.php');
        }
    }
    
    // Handle data export
    if (isset($_POST['export_data'])) {
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $status = $_POST['status'] ?? 'all';
        
        try {
            $query = "SELECT a.*, u.full_name, u.email, u.phone, s.institution, s.course, s.year_of_study
                      FROM applications a
                      JOIN students s ON a.student_id = s.id
                      JOIN users u ON s.user_id = u.id
                      WHERE a.forwarded_to = 'ict'";
            
            $params = [];
            
            if (!empty($start_date) && !empty($end_date)) {
                $query .= " AND DATE(a.updated_at) BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
            }
            
            if ($status !== 'all') {
                $query .= " AND a.status = ?";
                $params[] = $status;
            }
            
            $query .= " ORDER BY a.updated_at DESC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($applications)) {
                set_alert("No applications found for the selected criteria", 'warning');
                redirect('ict_dashboard.php');
            }
            
            // Generate CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="ict_applications_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // CSV headers
            $headers = [
                'ID', 'Student Name', 'Email', 'Phone', 'Institution', 
                'Course', 'Year of Study', 'Status', 'Decision Date',
                'HR Notes', 'ICT Notes', 'Registry Notes'
            ];
            fputcsv($output, $headers);
            
            // CSV data
            foreach ($applications as $app) {
                $row = [
                    $app['id'],
                    $app['full_name'],
                    $app['email'],
                    $app['phone'],
                    $app['institution'],
                    $app['course'],
                    $app['year_of_study'],
                    ucfirst($app['status']),
                    date('Y-m-d H:i:s', strtotime($app['updated_at'])),
                    $app['hr_notes'] ?? 'N/A',
                    $app['ict_notes'] ?? 'N/A',
                    $app['registry_notes'] ?? 'N/A'
                ];
                fputcsv($output, $row);
            }
            
            fclose($output);
            exit();
            
        } catch (PDOException $e) {
            error_log("Error exporting data: " . $e->getMessage());
            set_alert("Error exporting data: " . $e->getMessage(), 'error');
            redirect('ict_dashboard.php');
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
    error_log("Error fetching applications: " . $e->getMessage());
    set_alert("Error fetching applications: " . $e->getMessage(), 'error');
}

// Get all applicants (processed and pending)
$all_applicants = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, u.full_name, u.email, u.phone, s.institution, s.course, s.year_of_study
        FROM applications a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE a.forwarded_to = 'ict'
        ORDER BY a.updated_at DESC
    ");
    $all_applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching applicants: " . $e->getMessage());
    set_alert("Error fetching applicants: " . $e->getMessage(), 'error');
}

// Get recent decisions (last 10)
$recent_decisions = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, u.full_name, u.email, u.phone, s.institution, s.course, s.year_of_study
        FROM applications a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE a.forwarded_to = 'ict' AND a.status IN ('accepted', 'rejected')
        ORDER BY a.updated_at DESC
        LIMIT 10
    ");
    $recent_decisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching recent decisions: " . $e->getMessage());
    set_alert("Error fetching recent decisions: " . $e->getMessage(), 'error');
}

// Get system settings (slots)
$system_settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM system_settings LIMIT 1");
    $system_settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching system settings: " . $e->getMessage());
    set_alert("Error fetching system settings: " . $e->getMessage(), 'error');
}

// Handle application details request
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
        
        if ($application_details) {
            // Format dates
            $application_details['formatted_start_date'] = $application_details['attachment_start_date'] 
                ? date('F j, Y', strtotime($application_details['attachment_start_date'])) 
                : 'Not specified';
                
            $application_details['formatted_end_date'] = $application_details['attachment_end_date'] 
                ? date('F j, Y', strtotime($application_details['attachment_end_date'])) 
                : 'Not specified';
                
            $application_details['formatted_updated_at'] = date('F j, Y g:i A', strtotime($application_details['updated_at']));
            
            // Normalize document paths
            $doc_fields = ['application_letter_path', 'cv_path', 'insurance_path', 'introduction_letter_path'];
            foreach ($doc_fields as $field) {
                if (!empty($application_details[$field])) {
                    $application_details[$field] = normalize_doc_path($application_details[$field]);
                }
            }
        } else {
            error_log("Application not found for ID $app_id");
            set_alert("Application not found", 'error');
        }
    } catch (PDOException $e) {
        error_log("Error fetching application details for ID $app_id: " . $e->getMessage());
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous">
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
            background-color: var(--light-color);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        
        .top-navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            font-weight: 600;
            padding: 1rem 1.5rem;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
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
        
        .applicant-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .detail-item {
            margin-bottom: 1rem;
            padding: 0.5rem;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }
        
        .action-btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            display: none;
        }
        
        .document-link {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background-color: var(--light-color);
            border-radius: 6px;
            margin: 0.25rem;
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .document-link:hover {
            background-color: var(--secondary-color);
            color: white;
            transform: translateY(-1px);
        }
        
        .document-link i {
            margin-right: 0.5rem;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }
        
        .nav-tabs .nav-link {
            color: var(--dark-color);
            padding: 0.75rem 1.5rem;
            margin-bottom: -2px;
            border: none;
            border-bottom: 2px solid transparent;
        }
        
        .nav-tabs .nav-link:hover {
            border-bottom: 2px solid var(--secondary-color);
            color: var(--secondary-color);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--secondary-color);
            border-bottom: 2px solid var(--secondary-color);
            font-weight: 600;
        }
        
        .slot-indicator {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            background-color: #e9ecef;
        }
        
        .slot-indicator .remaining {
            font-weight: 600;
            color: var(--success-color);
        }
        
        .slot-indicator .total {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 1rem;
        }
        
        .table td {
            vertical-align: middle;
            padding: 0.75rem;
        }
        
        @media (max-width: 768px) {
            .applicant-details-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-tabs .nav-link {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
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
        <div class="d-flex align-items-center gap-3">
            <?php if ($system_settings): ?>
                <div class="slot-indicator">
                    Slots: <span class="remaining"><?= htmlspecialchars($system_settings['ict_slots_remaining']) ?></span> / 
                    <span class="total"><?= htmlspecialchars($system_settings['ict_slots']) ?></span> remaining
                </div>
            <?php endif; ?>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle me-2"></i>
                    <?= htmlspecialchars($user['full_name']) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Page Content -->
    <div class="container-fluid px-4 py-3">
        <?php display_alert(); ?>
        
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-12">
                    <h2 class="mb-2"><i class="fas fa-tachometer-alt me-2"></i> ICT Dashboard</h2>
                    <p class="mb-0">Welcome back, <?= htmlspecialchars($user['full_name']) ?>!</p>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="dashboardTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="applications-tab" data-bs-toggle="tab" data-bs-target="#applications" type="button" role="tab">
                            <i class="fas fa-file-alt me-1"></i> Applications (<?= count($applications) ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="all-applicants-tab" data-bs-toggle="tab" data-bs-target="#all-applicants" type="button" role="tab">
                            <i class="fas fa-users me-1"></i> All Applicants
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="recent-decisions-tab" data-bs-toggle="tab" data-bs-target="#recent-decisions" type="button" role="tab">
                            <i class="fas fa-history me-1"></i> Recent Decisions
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="export-tab" data-bs-toggle="tab" data-bs-target="#export" type="button" role="tab">
                            <i class="fas fa-download me-1"></i> Export Data
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="dashboardTabsContent">
                    <!-- Applications Tab -->
                    <div class="tab-pane fade show active" id="applications" role="tabpanel">
                        <?php if (empty($applications)): ?>
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="fas fa-info-circle me-2"></i> No applications have been forwarded to ICT at this time.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
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
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <button class="btn btn-sm btn-primary action-btn" 
                                                                onclick="viewApplication(<?= $app['id'] ?>)">
                                                            <i class="fas fa-eye me-1"></i> View
                                                        </button>
                                                        <button class="btn btn-sm btn-success action-btn" 
                                                                onclick="processApplication(<?= $app['id'] ?>, 'accepted')">
                                                            <i class="fas fa-check me-1"></i> Accept
                                                        </button>
                                                        <button class="btn btn-sm btn-danger action-btn" 
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
                    
                    <!-- All Applicants Tab -->
                    <div class="tab-pane fade" id="all-applicants" role="tabpanel">
                        <?php if (empty($all_applicants)): ?>
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="fas fa-info-circle me-2"></i> No applicants found.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
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
                                        <?php foreach ($all_applicants as $app): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($app['full_name']) ?></td>
                                                <td><?= htmlspecialchars($app['institution']) ?></td>
                                                <td><?= htmlspecialchars($app['course']) ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= htmlspecialchars($app['status']) ?>">
                                                        <?= ucfirst(htmlspecialchars($app['status'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($app['updated_at'])) ?></td>
                                                <td>
                                                    <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($app['ict_notes'] ?? '') ?>">
                                                        <?= !empty($app['ict_notes']) ? htmlspecialchars(substr($app['ict_notes'], 0, 50) . (strlen($app['ict_notes']) > 50 ? '...' : '')) : 'N/A' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary action-btn" 
                                                            onclick="viewApplication(<?= $app['id'] ?>)">
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
                    
                    <!-- Recent Decisions Tab -->
                    <div class="tab-pane fade" id="recent-decisions" role="tabpanel">
                        <?php if (empty($recent_decisions)): ?>
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="fas fa-info-circle me-2"></i> No recent decisions found.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
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
                                        <?php foreach ($recent_decisions as $app): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($app['full_name']) ?></td>
                                                <td><?= htmlspecialchars($app['institution']) ?></td>
                                                <td><?= htmlspecialchars($app['course']) ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= htmlspecialchars($app['status']) ?>">
                                                        <?= ucfirst(htmlspecialchars($app['status'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M j, Y g:i A', strtotime($app['updated_at'])) ?></td>
                                                <td>
                                                    <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($app['ict_notes'] ?? '') ?>">
                                                        <?= !empty($app['ict_notes']) ? htmlspecialchars(substr($app['ict_notes'], 0, 50) . (strlen($app['ict_notes']) > 50 ? '...' : '')) : 'N/A' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary action-btn" 
                                                            onclick="viewApplication(<?= $app['id'] ?>)">
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
                    
                    <!-- Export Data Tab -->
                    <div class="tab-pane fade" id="export" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-file-export me-2"></i> Export Application Data</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="ict_dashboard.php" id="exportForm">
                                            <input type="hidden" name="export_data" value="1">
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="start_date" class="form-label">Start Date</label>
                                                    <input type="date" class="form-control" id="start_date" name="start_date" max="<?= date('Y-m-d') ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="end_date" class="form-label">End Date</label>
                                                    <input type="date" class="form-control" id="end_date" name="end_date" max="<?= date('Y-m-d') ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="status" class="form-label">Status</label>
                                                <select class="form-select" id="status" name="status">
                                                    <option value="all">All Statuses</option>
                                                    <option value="pending">Pending</option>
                                                    <option value="forwarded">Forwarded</option>
                                                    <option value="accepted">Accepted</option>
                                                    <option value="rejected">Rejected</option>
                                                </select>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-download me-1"></i> Export as CSV
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Export Instructions</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>Export application data to CSV format for reporting and analysis.</p>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item">
                                                <i class="fas fa-calendar-check text-primary me-2"></i>
                                                Select date range to filter applications
                                            </li>
                                            <li class="list-group-item">
                                                <i class="fas fa-filter text-primary me-2"></i>
                                                Filter by application status
                                            </li>
                                            <li class="list-group-item">
                                                <i class="fas fa-file-csv text-primary me-2"></i>
                                                Data will be downloaded as CSV
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
                    <?php if (isset($application_details) && $application_details): ?>
                        <!-- Application Details -->
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
                                        <div><?= htmlspecialchars($application_details['formatted_start_date']) ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">End Date:</span>
                                        <div><?= htmlspecialchars($application_details['formatted_end_date']) ?></div>
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
                                    <div class="detail-item">
                                        <span class="detail-label">Last Updated:</span>
                                        <div><?= htmlspecialchars($application_details['formatted_updated_at']) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-file-pdf me-2"></i> Application Documents</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2">
                                    <?php 
                                    $doc_types = [
                                        'application_letter' => ['Application Letter', 'fa-file-word'],
                                        'cv' => ['Curriculum Vitae', 'fa-file-pdf'],
                                        'insurance' => ['Insurance Cover', 'fa-file-invoice'],
                                        'introduction_letter' => ['Introduction Letter', 'fa-file-signature']
                                    ];
                                    foreach ($doc_types as $doc_type => [$label, $icon]): ?>
                                        <?php if (!empty($application_details[$doc_type . '_path'])): ?>
                                            <a href="<?= htmlspecialchars(get_doc_url($application_details[$doc_type . '_path'])) ?>" 
                                               class="document-link" download>
                                                <i class="fas fa-download"></i> Download <?= $label ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="document-link disabled text-muted" title="Document not available">
                                                <i class="fas fa-download"></i> Download <?= $label ?> (N/A)
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger d-flex align-items-center">
                            <i class="fas fa-exclamation-circle me-2"></i> Error loading application details. Please try again.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <?php if ($application_details && $application_details['status'] == 'forwarded'): ?>
                        <button class="btn btn-success me-2" onclick="processApplication(<?= $application_details['id'] ?>, 'accepted')">
                            <i class="fas fa-check me-1"></i> Accept Application
                        </button>
                        <button class="btn btn-danger me-2" onclick="processApplication(<?= $application_details['id'] ?>, 'rejected')">
                            <i class="fas fa-times me-1"></i> Reject Application
                        </button>
                    <?php endif; ?>
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
                            <div class="invalid-feedback">
                                Please provide feedback or comments.
                            </div>
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

    <!-- Bootstrap JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Show view modal if view parameter exists
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('view')) {
                const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
                viewModal.show();
            }

            // Maintain active tab after page reload
            const activeTab = localStorage.getItem('activeTab');
            if (activeTab) {
                const tab = new bootstrap.Tab(document.querySelector(activeTab));
                tab.show();
            }

            // Save active tab to localStorage
            document.querySelectorAll('.nav-tabs .nav-link').forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(e) {
                    localStorage.setItem('activeTab', `#${e.target.id}`);
                });
            });

            // Form validation for export form
            const exportForm = document.getElementById('exportForm');
            exportForm.addEventListener('submit', function(e) {
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                
                if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
                    e.preventDefault();
                    alert('End date must be after start date');
                    return false;
                }
            });
        });

        // View application function
        function viewApplication(appId) {
            window.location.href = `ict_dashboard.php?view=${appId}`;
        }

        // Process application function
        function processApplication(appId, decision) {
            const processModal = new bootstrap.Modal(document.getElementById('processModal'));
            const processButton = document.getElementById('processButton');
            const processModalHeader = document.getElementById('processModalHeader');
            const processModalLabel = document.getElementById('processModalLabel');
            const commentsField = document.getElementById('comments');
            const processForm = document.getElementById('processForm');
            
            document.getElementById('process_application_id').value = appId;
            document.getElementById('process_decision').value = decision;
            
            // Set up modal based on decision type
            if (decision === 'accepted') {
                processModalLabel.innerHTML = '<i class="fas fa-check-circle me-2"></i> Accept Application';
                processButton.className = 'btn btn-success';
                processButton.innerHTML = '<i class="fas fa-check me-1"></i> Accept Application';
                processModalHeader.className = 'modal-header bg-success text-white';
                commentsField.value = `1. Dress code: Official attire is required\n2. Working hours: Arrival time is 8:00 AM\n3. Payments: No forms of payments are allowed\n4. Additional notes:\n\n`;
            } else {
                processModalLabel.innerHTML = '<i class="fas fa-times-circle me-2"></i> Reject Application';
                processButton.className = 'btn btn-danger';
                processButton.innerHTML = '<i class="fas fa-times me-1"></i> Reject Application';
                processModalHeader.className = 'modal-header bg-danger text-white';
                commentsField.value = '';
            }
            
            // Form validation
            processForm.classList.add('needs-validation');
            processForm.addEventListener('submit', function(e) {
                if (!processForm.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                processForm.classList.add('was-validated');
            }, { once: true });
            
            processModal.show();
            
            // Focus on comments field
            processModal._element.addEventListener('shown.bs.modal', function() {
                commentsField.focus();
            }, { once: true });
        }

        // Show loading overlay
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        // Clean URL on modal close
        document.getElementById('viewModal').addEventListener('hidden.bs.modal', function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('view')) {
                const activeTab = localStorage.getItem('activeTab') || '#applications-tab';
                window.history.replaceState({}, document.title, window.location.pathname);
                const tab = new bootstrap.Tab(document.querySelector(activeTab));
                tab.show();
            }
        });
    </script>
</body>
</html>