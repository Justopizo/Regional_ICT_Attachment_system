<?php
require_once 'db_connect.php';
require_once 'functions.php';

check_role(['registry']);

$user = get_current_user_data();

// Handle application decision
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_application'])) {
    $application_id = intval($_POST['application_id']);
    $decision = $_POST['decision'];
    $comments = sanitize_input($_POST['comments']);
    
    try {
        $stmt = $pdo->prepare("UPDATE applications SET 
            status = ?,
            registry_notes = ?,
            updated_at = NOW()
            WHERE id = ? AND forwarded_to = 'registry'");
        $stmt->execute([$decision, $comments, $application_id]);
        
        set_alert("Application marked as " . $decision, 'success');
        redirect('registry_dashboard.php');
    } catch (PDOException $e) {
        set_alert("Error processing application: " . $e->getMessage(), 'error');
    }
}

// Determine which applications to show
$show_pending = true;
if (isset($_GET['show']) && $_GET['show'] === 'past') {
    $show_pending = false;
}

// Get applications based on current view
$applications = [];
try {
    if ($show_pending) {
        // Get pending applications forwarded to Registry
        $stmt = $pdo->query("
            SELECT a.*, u.full_name, s.institution, s.course, s.year_of_study, s.side_hustle,
                   s.preferred_department, s.attachment_start_date, s.attachment_end_date
            FROM applications a
            JOIN students s ON a.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE a.forwarded_to = 'registry' AND a.status = 'forwarded'
            ORDER BY a.updated_at DESC
        ");
    } else {
        // Get past processed applications (both accepted and rejected)
        $stmt = $pdo->query("
            SELECT a.*, u.full_name, s.institution, s.course, s.year_of_study, s.side_hustle,
                   s.preferred_department, s.attachment_start_date, s.attachment_end_date
            FROM applications a
            JOIN students s ON a.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE a.forwarded_to = 'registry' AND a.status IN ('accepted', 'rejected')
            ORDER BY a.updated_at DESC
        ");
    }
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_alert("Error fetching applications: " . $e->getMessage(), 'error');
}

// Get single application details for modal view
if (isset($_GET['view_application'])) {
    $app_id = intval($_GET['view_application']);
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.full_name, u.email, u.phone, 
                   s.institution, s.course, s.year_of_study, s.side_hustle,
                   s.preferred_department, s.attachment_start_date, s.attachment_end_date
            FROM applications a
            JOIN students s ON a.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE a.id = ? AND a.forwarded_to = 'registry'
        ");
        $stmt->execute([$app_id]);
        $application_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($application_details) {
            header('Content-Type: application/json');
            echo json_encode($application_details);
            exit;
        }
    } catch (PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registry Dashboard - Western Region ICT Authority</title>
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
        .view-toggle {
            display: flex;
            margin-bottom: 20px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .view-toggle a {
            flex: 1;
            text-align: center;
            padding: 10px;
            background-color: white;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
        }
        .view-toggle a.active {
            background-color: #3498db;
            color: white;
        }
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 30px;
        }
        .card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f9f9f9;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
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
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .action-btn {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        .view-btn {
            background-color: #3498db;
            color: white;
        }
        .accept-btn {
            background-color: #2ecc71;
            color: white;
        }
        .reject-btn {
            background-color: #e74c3c;
            color: white;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .close-modal {
            float: right;
            cursor: pointer;
            font-size: 1.5rem;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            min-height: 100px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-success {
            background-color: #2ecc71;
            color: white;
        }
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .detail-item {
            margin-bottom: 10px;
        }
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        .documents-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .document-link {
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
            padding: 8px 15px;
            background-color: #f2f2f2;
            border-radius: 4px;
            text-decoration: none;
            color: #3498db;
        }
        .document-link:hover {
            background-color: #e2e2e2;
        }
        .notes-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
        @media (max-width: 768px) {
            table {
                display: block;
                overflow-x: auto;
            }
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Western Region ICT Authority - Registry Dashboard</h1>
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
            <p>Registry Department Administrator Dashboard</p>
        </div>

        <div class="view-toggle">
            <a href="?show=pending" class="<?= $show_pending ? 'active' : '' ?>">
                <i class="fas fa-inbox"></i> Pending Applications
            </a>
            <a href="?show=past" class="<?= !$show_pending ? 'active' : '' ?>">
                <i class="fas fa-history"></i> Past Applications
            </a>
        </div>

        <div class="card">
            <h3>
                <i class="fas <?= $show_pending ? 'fa-inbox' : 'fa-history' ?>"></i> 
                <?= $show_pending ? 'Applications Forwarded to Registry' : 'Past Applications (Accepted/Rejected)' ?>
            </h3>
            
            <?php if (empty($applications)): ?>
                <p>No <?= $show_pending ? 'pending' : 'past' ?> applications found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Institution</th>
                            <th>Course</th>
                            <th>Submitted On</th>
                            <th>HR Comments</th>
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
                                <td><?= htmlspecialchars($app['hr_notes'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($app['status'] === 'forwarded'): ?>
                                        <span class="status-badge status-forwarded">Forwarded</span>
                                    <?php elseif ($app['status'] === 'accepted'): ?>
                                        <span class="status-badge status-accepted">Accepted</span>
                                    <?php else: ?>
                                        <span class="status-badge status-rejected">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <button class="action-btn view-btn" 
                                        onclick="viewApplication(<?= $app['id'] ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($show_pending): ?>
                                        <button class="action-btn accept-btn" 
                                            onclick="processApplication(<?= $app['id'] ?>, 'accepted')">
                                            <i class="fas fa-check"></i> Accept
                                        </button>
                                        <button class="action-btn reject-btn" 
                                            onclick="processApplication(<?= $app['id'] ?>, 'rejected')">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Application Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('viewModal')">&times;</span>
            <h3>Application Details</h3>
            <div id="applicationDetails">
                <div class="details-grid">
                    <div>
                        <div class="detail-item">
                            <span class="detail-label">Full Name:</span>
                            <span id="detail-full_name"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email:</span>
                            <span id="detail-email"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Phone:</span>
                            <span id="detail-phone"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Institution:</span>
                            <span id="detail-institution"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Course:</span>
                            <span id="detail-course"></span>
                        </div>
                    </div>
                    <div>
                        <div class="detail-item">
                            <span class="detail-label">Year of Study:</span>
                            <span id="detail-year_of_study"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Side Hustle:</span>
                            <span id="detail-side_hustle"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Preferred Department:</span>
                            <span id="detail-preferred_department"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Attachment Start Date:</span>
                            <span id="detail-attachment_start_date"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Attachment End Date:</span>
                            <span id="detail-attachment_end_date"></span>
                        </div>
                    </div>
                </div>
                
                <div class="documents-section">
                    <h4>Documents</h4>
                    <div id="document-links">
                        <!-- Document links will be inserted here -->
                    </div>
                </div>
                
                <div class="notes-section">
                    <h4>HR Notes</h4>
                    <p id="hr-notes"></p>
                    
                    <h4>ICT Notes</h4>
                    <p id="ict-notes"></p>
                    
                    <h4>Registry Notes</h4>
                    <p id="registry-notes"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Process Application Modal -->
    <div id="processModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('processModal')">&times;</span>
            <h3 id="processModalTitle">Process Application</h3>
            <form id="processForm" action="registry_dashboard.php" method="POST">
                <input type="hidden" id="process_application_id" name="application_id">
                <input type="hidden" id="process_decision" name="decision">
                <div class="form-group">
                    <label for="comments">Comments</label>
                    <textarea id="comments" name="comments" rows="4" class="form-control" required></textarea>
                </div>
                <button type="submit" name="process_application" class="btn" id="processButton">
                    <i class="fas fa-check"></i> Submit
                </button>
            </form>
        </div>
    </div>

    <script>
        async function viewApplication(appId) {
            try {
                const response = await fetch(`?view_application=${appId}`);
                if (!response.ok) {
                    throw new Error('Failed to fetch application details');
                }
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                // Populate basic details
                document.getElementById('detail-full_name').textContent = data.full_name;
                document.getElementById('detail-email').textContent = data.email;
                document.getElementById('detail-phone').textContent = data.phone;
                document.getElementById('detail-institution').textContent = data.institution;
                document.getElementById('detail-course').textContent = data.course;
                document.getElementById('detail-year_of_study').textContent = data.year_of_study;
                document.getElementById('detail-side_hustle').textContent = data.side_hustle || 'N/A';
                document.getElementById('detail-preferred_department').textContent = data.preferred_department.toUpperCase();
                document.getElementById('detail-attachment_start_date').textContent = data.attachment_start_date ? formatDate(data.attachment_start_date) : 'Not specified';
                document.getElementById('detail-attachment_end_date').textContent = data.attachment_end_date ? formatDate(data.attachment_end_date) : 'Not specified';
                
                // Populate notes
                document.getElementById('hr-notes').textContent = data.hr_notes || 'No notes from HR';
                document.getElementById('ict-notes').textContent = data.ict_notes || 'No notes from ICT';
                document.getElementById('registry-notes').textContent = data.registry_notes || 'No notes from Registry';
                
                // Create document links
                const documentsContainer = document.getElementById('document-links');
                documentsContainer.innerHTML = '';
                
                const documents = [
                    { name: 'Application Letter', path: data.application_letter_path },
                    { name: 'Insurance Document', path: data.insurance_path },
                    { name: 'CV/Resume', path: data.cv_path },
                    { name: 'Introduction Letter', path: data.introduction_letter_path }
                ];
                
                documents.forEach(doc => {
                    if (doc.path) {
                        const link = document.createElement('a');
                        link.href = doc.path;
                        link.className = 'document-link';
                        link.target = '_blank';
                        link.innerHTML = `<i class="fas fa-file-pdf"></i> ${doc.name}`;
                        link.onclick = (e) => {
                            e.preventDefault();
                            window.open(doc.path, '_blank');
                        };
                        documentsContainer.appendChild(link);
                    }
                });
                
                if (documentsContainer.children.length === 0) {
                    documentsContainer.innerHTML = '<p>No documents uploaded</p>';
                }
                
                document.getElementById('viewModal').style.display = 'flex';
            } catch (error) {
                alert('Error: ' + error.message);
                console.error(error);
            }
        }
        
        function formatDate(dateString) {
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            return new Date(dateString).toLocaleDateString(undefined, options);
        }

        function processApplication(appId, decision) {
            document.getElementById('process_application_id').value = appId;
            document.getElementById('process_decision').value = decision;
            
            if (decision === 'accepted') {
                document.getElementById('processModalTitle').textContent = 'Accept Application';
                document.getElementById('processButton').className = 'btn btn-success';
                document.getElementById('processButton').innerHTML = '<i class="fas fa-check"></i> Accept Application';
            } else {
                document.getElementById('processModalTitle').textContent = 'Reject Application';
                document.getElementById('processButton').className = 'btn btn-danger';
                document.getElementById('processButton').innerHTML = '<i class="fas fa-times"></i> Reject Application';
            }
            
            document.getElementById('processModal').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>