<?php
require_once 'db_connect.php';
require_once 'functions.php';

check_role(['hr']);

$user = get_current_user_data();

// Helper function to normalize document paths
function normalize_doc_path($path) {
    global $pdo;
    if (empty($path)) {
        return null;
    }
    // Handle absolute server paths
    if (strpos($path, '/srv/disk7') !== false) {
        $path = preg_replace('#^/srv/disk7/\d+/www/kakamegaregionaictauthority\.atspace\.co\.uk/#', '', $path);
    }
    // Ensure path starts with 'uploads/'
    if (strpos($path, 'uploads/') === 0) {
        return $path;
    }
    return 'uploads/' . basename($path);
}

// Helper function to get document URL
function get_doc_url($path) {
    $normalized = normalize_doc_path($path);
    if (!$normalized) {
        return null;
    }
    return 'http://kakamegaregionaictauthority.atspace.co.uk/' . $normalized;
}

// Handle application window toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_window'])) {
    try {
        // Sanitize window_status manually since FILTER_VALIDATE_STRING doesn't exist
        $window_status = isset($_POST['window_status']) ? htmlspecialchars(strip_tags($_POST['window_status'])) : '';
        $status = ($window_status === 'open') ? 1 : 0;
        $hr_slots = filter_input(INPUT_POST, 'hr_slots', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $ict_slots = filter_input(INPUT_POST, 'ict_slots', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $registry_slots = filter_input(INPUT_POST, 'registry_slots', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        
        if ($hr_slots === false || $ict_slots === false || $registry_slots === false) {
            throw new Exception('Invalid slot values provided');
        }
        
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
    } catch (Exception $e) {
        error_log("Error updating application window: " . $e->getMessage());
        set_alert("Error updating application window: " . $e->getMessage(), 'error');
        redirect('hr_dashboard.php');
    }
}

// Handle application forwarding
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forward_application'])) {
    $application_id = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
    $department = isset($_POST['department']) ? htmlspecialchars(strip_tags($_POST['department'])) : '';
    $comments = htmlspecialchars(strip_tags($_POST['comments']));
    
    if ($application_id === false || $application_id <= 0 || !in_array($department, ['hr', 'ict', 'registry'])) {
        set_alert("Invalid application ID or department", 'error');
        redirect('hr_dashboard.php');
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE applications SET 
            status = 'forwarded',
            forwarded_to = ?,
            hr_notes = ?,
            updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([$department, $comments, $application_id]);
        
        set_alert("Application forwarded to " . htmlspecialchars($department) . " department", 'success');
        redirect('hr_dashboard.php');
    } catch (PDOException $e) {
        error_log("Error forwarding application: " . $e->getMessage());
        set_alert("Error forwarding application: " . $e->getMessage(), 'error');
        redirect('hr_dashboard.php');
    }
}

// Handle data export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_data'])) {
    // Buffer output to prevent headers already sent error
    ob_start();
    
    $start_date = isset($_POST['start_date']) ? htmlspecialchars(strip_tags($_POST['start_date'])) : '';
    $end_date = isset($_POST['end_date']) ? htmlspecialchars(strip_tags($_POST['end_date'])) : '';
    $status = isset($_POST['status']) ? htmlspecialchars(strip_tags($_POST['status'])) : '';
    
    // Validate dates
    if ($start_date && $end_date && strtotime($start_date) > strtotime($end_date)) {
        ob_end_clean();
        set_alert("End date must be after start date", 'error');
        redirect('hr_dashboard.php');
    }
    
    try {
        $query = "SELECT a.id, a.status, a.forwarded_to, a.updated_at, a.hr_notes,
                         u.full_name, u.email, u.phone, s.institution, s.course, 
                         s.year_of_study, s.preferred_department
                  FROM applications a
                  JOIN students s ON a.student_id = s.id
                  JOIN users u ON s.user_id = u.id
                  WHERE 1=1";
        
        $params = [];
        
        if ($start_date && $end_date) {
            $query .= " AND DATE(a.updated_at) BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
        }
        
        if ($status !== 'all') {
            if (!in_array($status, ['pending', 'forwarded', 'accepted', 'rejected', 'cancelled'])) {
                ob_end_clean();
                set_alert("Invalid status selected", 'error');
                redirect('hr_dashboard.php');
            }
            $query .= " AND a.status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY a.updated_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($applications)) {
            ob_end_clean();
            set_alert("No applications found for the selected criteria", 'warning');
            redirect('hr_dashboard.php');
        }
        
        // Generate CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="hr_applications_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        $headers = [
            'ID', 'Student Name', 'Email', 'Phone', 'Institution', 
            'Course', 'Year of Study', 'Preferred Department', 'Status', 
            'Decision Date', 'HR Notes', 'Forwarded To'
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
                $app['preferred_department'],
                ucfirst($app['status']),
                date('Y-m-d H:i:s', strtotime($app['updated_at'])),
                $app['hr_notes'] ?? 'N/A',
                $app['forwarded_to'] ?? 'N/A'
            ];
            fputcsv($output, $row);
        }
        
        fclose($output);
        ob_end_flush();
        exit();
        
    } catch (PDOException $e) {
        ob_end_clean();
        error_log("Error exporting data: " . $e->getMessage());
        set_alert("Error exporting data: " . $e->getMessage(), 'error');
        redirect('hr_dashboard.php');
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
    error_log("Error fetching applications: " . $e->getMessage());
    set_alert("Error fetching applications: " . $e->getMessage(), 'error');
}

// Get all applicants (processed and pending)
$all_applicants = [];
try {
    $stmt = $pdo->query("
        SELECT a.id, a.status, a.forwarded_to, a.updated_at, a.hr_notes,
               u.full_name, u.email, u.phone, s.institution, s.course, 
               s.preferred_department, s.year_of_study, s.side_hustle,
               a.application_letter_path, a.cv_path, a.introduction_letter_path, a.insurance_path
        FROM applications a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        ORDER BY a.updated_at DESC
    ");
    $all_applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching all applicants: " . $e->getMessage());
    set_alert("Error fetching all applicants: " . $e->getMessage(), 'error');
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
        error_log("Error fetching past applications: " . $e->getMessage());
        set_alert("Error fetching past applications: " . $e->getMessage(), 'error');
    }
}

$settings = get_application_window_status();

// Handle application details request
$application_details = null;
if (isset($_GET['view'])) {
    $app_id = intval($_GET['view']);
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.full_name, u.email, u.phone, s.institution, s.course, 
                   s.year_of_study, s.preferred_department, s.side_hustle
            FROM applications a
            JOIN students s ON a.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$app_id]);
        $application_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($application_details) {
            // Format dates
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
    <title>HR Dashboard - Western Region ICT Authority</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous">
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

        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            font-weight: 600;
            padding: 1rem 1.5rem;
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

        .table-custom td {
            vertical-align: middle;
            padding: 0.75rem;
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

        .badge-forwarded {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .badge-accepted {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-cancelled {
            background-color: #e9ecef;
            color: #495057;
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
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background-color: var(--light-bg);
            border-radius: 6px;
            margin: 0.25rem;
        }

        .document-link:hover {
            color: white;
            background-color: var(--secondary-color);
            text-decoration: none;
            transform: translateY(-1px);
        }

        .document-link i {
            margin-right: 0.5rem;
        }

        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }

        .nav-tabs .nav-link {
            color: var(--primary-color);
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

        @media (max-width: 768px) {
            .nav-tabs .nav-link {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
            .applicant-details-grid {
                grid-template-columns: 1fr;
            }
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
                                            value="<?= htmlspecialchars($settings['hr_slots']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="ict_slots" class="form-label fw-bold">
                                            <i class="fas fa-laptop-code"></i> ICT Department Slots
                                        </label>
                                        <input type="number" class="form-control" id="ict_slots" name="ict_slots" min="0" 
                                            value="<?= htmlspecialchars($settings['ict_slots']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="registry_slots" class="form-label fw-bold">
                                            <i class="fas fa-archive"></i> Registry Department Slots
                                        </label>
                                        <input type="number" class="form-control" id="registry_slots" name="registry_slots" min="0" 
                                            value="<?= htmlspecialchars($settings['registry_slots']) ?>" required>
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
                                                    <td><span class="badge bg-info"><?= strtoupper(htmlspecialchars($app['preferred_department'])) ?></span></td>
                                                    <td><span class="badge badge-status badge-<?= htmlspecialchars($app['status']) ?>"><?= ucfirst(htmlspecialchars($app['status'])) ?></span></td>
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
                                                    <span class="badge bg-info"><?= strtoupper(htmlspecialchars($app['preferred_department'])) ?></span>
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
                                                                onclick="forwardApplication(<?= $app['id'] ?>, '<?= htmlspecialchars($app['preferred_department']) ?>')">
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

                    <!-- All Applicants Tab -->
                    <div class="tab-pane fade" id="all-applicants" role="tabpanel">
                        <?php if (empty($all_applicants)): ?>
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="fas fa-info-circle me-2"></i> No applicants found.
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
                                            <th>Status</th>
                                            <th>Decision Date</th>
                                            <th>HR Notes</th>
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
                                                    <span class="badge bg-info"><?= strtoupper(htmlspecialchars($app['preferred_department'])) ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-status badge-<?= htmlspecialchars($app['status']) ?>">
                                                        <?= ucfirst(htmlspecialchars($app['status'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($app['updated_at'])) ?></td>
                                                <td>
                                                    <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($app['hr_notes'] ?? '') ?>">
                                                        <?= !empty($app['hr_notes']) ? htmlspecialchars(substr($app['hr_notes'], 0, 50) . (strlen($app['hr_notes']) > 50 ? '...' : '')) : 'N/A' ?>
                                                    </span>
                                                </td>
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
                                            <form method="POST" action="hr_dashboard.php" id="exportForm">
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
                                                        <option value="cancelled">Cancelled</option>
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
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewModalLabel">
                            <i class="fas fa-file-alt"></i> Application Details
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
                                        <div class="detail-item">
                                            <span class="detail-label">Preferred Department:</span>
                                            <div><span class="badge bg-info"><?= strtoupper(htmlspecialchars($application_details['preferred_department'])) ?></span></div>
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
                        <?php if ($application_details && $application_details['status'] == 'pending'): ?>
                            <button class="btn btn-success btn-action me-2" 
                                    onclick="forwardApplication(<?= $application_details['id'] ?>, '<?= htmlspecialchars($application_details['preferred_department']) ?>')">
                                <i class="fas fa-share"></i> Forward
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Close
                        </button>
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
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        
        <script>
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
                if (exportForm) {
                    exportForm.addEventListener('submit', function(e) {
                        const startDate = document.getElementById('start_date').value;
                        const endDate = document.getElementById('end_date').value;
                        
                        if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
                            e.preventDefault();
                            alert('End date must be after start date');
                            return false;
                        }
                    });
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
            });
    
            function viewApplication(appId) {
                window.location.href = `hr_dashboard.php?view=${appId}`;
            }
    
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
    
            function showPastApplications() {
                const pastAppsDiv = document.getElementById('pastApplications');
                pastAppsDiv.style.display = 'block';
                pastAppsDiv.scrollIntoView({ behavior: 'smooth' });
            }
        </script>
    </body>
    </html>