
<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Only allow logged-in users to view applications
if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// Check if application ID is provided
if (!isset($_GET['id'])) {
    header("Location: " . (getUserRole() === 'student' ? 'student_dashboard.php' : $_SESSION['role'] . '_dashboard.php'));
    exit();
}

$application_id = sanitizeInput($_GET['id']);
$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Get application details, joining with students and users
$stmt = $pdo->prepare("
    SELECT a.*, u.username, u.email, u.phone, u.full_name, s.side_hustle, s.preferred_department
    FROM applications a 
    JOIN students s ON a.student_id = s.id 
    JOIN users u ON s.user_id = u.id 
    WHERE a.id = ?
");
$stmt->execute([$application_id]);
$application = $stmt->fetch();

// Check if application exists and user has permission to view it
if (!$application || ($user_role === 'student' && $application['user_id'] !== $user_id)) {
    header("Location: " . ($user_role === 'student' ? 'student_dashboard.php' : $_SESSION['role'] . '_dashboard.php'));
    exit();
}

// Handle status update for admins
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && in_array($user_role, ['hr', 'ict', 'registry'])) {
    $status = sanitizeInput($_POST['status']);
    $feedback = sanitizeInput($_POST['feedback']);
    $forwarded_to = ($status === 'forwarded' && $user_role === 'hr') ? sanitizeInput($_POST['forwarded_to']) : $application['forwarded_to'];

    // Validate forwarded department and slots
    if ($status === 'forwarded' && $user_role === 'hr') {
        if (!in_array($forwarded_to, ['ict', 'registry'])) {
            $error = "Invalid department selected for forwarding.";
        } else {
            $settings = getSystemSettings();
            $slot_field = $forwarded_to . '_slots_remaining';
            if ($settings[$slot_field] <= 0) {
                $error = "No slots available in the $forwarded_to department.";
            }
        }
    }

    if (empty($error)) {
        if ($status === 'forwarded' && $user_role === 'hr') {
            if (forwardApplication($application_id, $forwarded_to)) {
                // Decrement slots for the forwarded department
                $settings = getSystemSettings();
                $new_slots = $settings[$forwarded_to . '_slots_remaining'] - 1;
                $stmt = $pdo->prepare("
                    UPDATE system_settings 
                    SET {$forwarded_to}_slots_remaining = ?, updated_at = NOW(), updated_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$new_slots, $user_id, $settings['id']]);
                header("Location: view_application.php?id=$application_id&success=Application forwarded successfully");
                exit();
            } else {
                $error = "Failed to forward application.";
            }
        } elseif (updateApplicationStatus($application_id, $status, $feedback)) {
            header("Location: view_application.php?id=$application_id&success=Status updated successfully");
            exit();
        } else {
            $error = "Failed to update status.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application - Kakamega ICT Authority</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex flex-col md:flex-row">
        <aside class="w-full md:w-64 bg-gray-800 text-white p-4 shadow-md">
            <div class="text-center mb-6">
                <img src="ictlogo.jpeg" alt="Kakamega ICT Logo" class="h-16 mx-auto">
                <h3 class="text-lg font-semibold mt-2"><?php echo ucfirst($user_role); ?> Dashboard</h3>
            </div>
            <nav class="space-y-2">
                <a href="<?php echo $user_role === 'student' ? 'student_dashboard.php' : $user_role . '_dashboard.php'; ?>" class="block p-2 text-white hover:bg-gray-700 rounded"><i class="fas fa-home mr-2"></i>Home</a>
                <?php if ($user_role === 'student'): ?>
                    <a href="apply_attachment.php" class="block p-2 text-white hover:bg-gray-700 rounded"><i class="fas fa-file-alt mr-2"></i>Apply for Attachment</a>
                <?php endif; ?>
                <a href="update_profile.php" class="block p-2 text-white hover:bg-gray-700 rounded"><i class="fas fa-user-edit mr-2"></i>Update Profile</a>
                <a href="change_password.php" class="block p-2 text-white hover:bg-gray-700 rounded"><i class="fas fa-key mr-2"></i>Change Password</a>
                <a href="logout.php" class="block p-2 text-white hover:bg-gray-700 rounded"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
            </nav>
            <div class="mt-4 text-center text-sm">
                Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
            </div>
        </aside>

        <main class="flex-1 p-6">
            <header class="mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Application Details</h1>
                <div class="mt-2 text-sm text-gray-600">
                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <span class="ml-4"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></span>
                </div>
            </header>

            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="bg-white p-6 rounded shadow-md">
                <div class="application-header flex justify-between items-start mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Application #<?php echo htmlspecialchars($application['id']); ?></h2>
                    <span class="px-2 py-1 rounded text-sm <?php echo $application['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : ($application['status'] === 'forwarded' ? 'bg-blue-100 text-blue-800' : ($application['status'] === 'accepted' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800')); ?>"><?php echo ucfirst(htmlspecialchars($application['status'])); ?></span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gray-50 p-4 rounded">
                        <h3 class="text-lg font-medium mb-2 flex items-center"><i class="fas fa-user mr-2"></i> Applicant Information</h3>
                        <p class="text-gray-600"><strong>Name:</strong> <?php echo htmlspecialchars($application['full_name']); ?></p>
                        <p class="text-gray-600"><strong>Username:</strong> <?php echo htmlspecialchars($application['username']); ?></p>
                        <p class="text-gray-600"><strong>Email:</strong> <?php echo htmlspecialchars($application['email']); ?></p>
                        <p class="text-gray-600"><strong>Phone:</strong> <?php echo htmlspecialchars($application['phone']); ?></p>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded">
                        <h3 class="text-lg font-medium mb-2 flex items-center"><i class="fas fa-building mr-2"></i> Application Details</h3>
                        <p class="text-gray-600"><strong>Department Preference:</strong> <?php echo ucfirst(htmlspecialchars($application['preferred_department'])); ?></p>
                        <p class="text-gray-600"><strong>Applied On:</strong> <?php echo date('d M Y H:i', strtotime($application['applied_at'])); ?></p>
                        <?php if ($application['status'] === 'forwarded'): ?>
                            <p class="text-gray-600"><strong>Forwarded To:</strong> <?php echo ucfirst(htmlspecialchars($application['forwarded_to'])); ?></p>
                            <p class="text-gray-600"><strong>Forwarded On:</strong> <?php echo date('d M Y H:i', strtotime($application['updated_at'])); ?></p>
                        <?php elseif ($application['status'] === 'accepted' || $application['status'] === 'rejected' || $application['status'] === 'cancelled'): ?>
                            <p class="text-gray-600"><strong>Processed On:</strong> <?php echo date('d M Y H:i', strtotime($application['updated_at'])); ?></p>
                            <?php if (!empty($application['feedback'])): ?>
                                <p class="text-gray-600"><strong>Feedback:</strong> <?php echo htmlspecialchars($application['feedback']); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded">
                        <h3 class="text-lg font-medium mb-2 flex items-center"><i class="fas fa-business-time mr-2"></i> Side Hustles/Skills</h3>
                        <p class="text-gray-600"><?php echo !empty($application['side_hustle']) ? htmlspecialchars($application['side_hustle']) : 'Not specified'; ?></p>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded col-span-2">
                        <h3 class="text-lg font-medium mb-2 flex items-center"><i class="fas fa-file mr-2"></i> Documents</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <a href="<?php echo htmlspecialchars(UPLOAD_DIR . $application['application_letter']); ?>" target="_blank" class="document-item flex items-center p-2 bg-white border rounded hover:bg-blue-50">
                                <i class="fas fa-file-alt mr-2 text-blue-500"></i> Application Letter
                            </a>
                            <a href="<?php echo htmlspecialchars(UPLOAD_DIR . $application['insurance']); ?>" target="_blank" class="document-item flex items-center p-2 bg-white border rounded hover:bg-blue-50">
                                <i class="fas fa-file-medical mr-2 text-blue-500"></i> Insurance Cover
                            </a>
                            <a href="<?php echo htmlspecialchars(UPLOAD_DIR . $application['cv']); ?>" target="_blank" class="document-item flex items-center p-2 bg-white border rounded hover:bg-blue-50">
                                <i class="fas fa-file-user mr-2 text-blue-500"></i> Curriculum Vitae
                            </a>
                            <a href="<?php echo htmlspecialchars(UPLOAD_DIR . $application['introduction_letter']); ?>" target="_blank" class="document-item flex items-center p-2 bg-white border rounded hover:bg-blue-50">
                                <i class="fas fa-file-signature mr-2 text-blue-500"></i> Introduction Letter
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if (in_array($user_role, ['hr', 'ict', 'registry']) && ($application['status'] === 'pending' || $application['status'] === 'forwarded')): ?>
                    <div class="mt-6 pt-4 border-t">
                        <h3 class="text-lg font-medium mb-2 flex items-center"><i class="fas fa-edit mr-2"></i> Update Application Status</h3>
                        <form action="view_application.php?id=<?php echo htmlspecialchars($application_id); ?>" method="POST" class="space-y-4" id="statusForm">
                            <input type="hidden" name="application_id" value="<?php echo htmlspecialchars($application_id); ?>">
                            
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                <select id="status" name="status" required class="w-full p-2 border rounded">
                                    <?php if ($user_role === 'hr' && $application['status'] === 'pending'): ?>
                                        <option value="">Select Status</option>
                                        <option value="forwarded">Forward to Department</option>
                                        <option value="rejected">Reject</option>
                                        <option value="cancelled">Cancel</option>
                                    <?php elseif (($user_role === 'ict' || $user_role === 'registry') && $application['status'] === 'forwarded' && $application['forwarded_to'] === strtolower($user_role)): ?>
                                        <option value="">Select Status</option>
                                        <option value="accepted">Accept</option>
                                        <option value="rejected">Reject</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <?php if ($user_role === 'hr' && $application['status'] === 'pending'): ?>
                                <div id="forwarded_dept" class="hidden">
                                    <label for="forwarded_to" class="block text-sm font-medium text-gray-700">Forward to Department</label>
                                    <select id="forwarded_to" name="forwarded_to" class="w-full p-2 border rounded">
                                        <option value="">Select Department</option>
                                        <option value="ict">ICT</option>
                                        <option value="registry">Registry</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                            
                            <div>
                                <label for="feedback" class="block text-sm font-medium text-gray-700">Feedback (Optional)</label>
                                <textarea id="feedback" name="feedback" rows="3" class="w-full p-2 border rounded"><?php echo htmlspecialchars($_POST['feedback'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" name="update_status" class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">Update Status</button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <div class="mt-6">
                    <a href="<?php echo $user_role === 'student' ? 'student_dashboard.php' : $user_role . '_dashboard.php'; ?>" class="bg-gray-500 text-white py-2 px-4 rounded hover:bg-gray-600 flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </main>
    </div>

    <footer class="mt-6 text-center text-gray-600 text-sm">
        <p>© 2025 Kakamega Regional ICT Authority | Developed by Justin Ratemo - 0793031269</p>
    </footer>

    <script>
        document.getElementById('statusForm')?.addEventListener('submit', function(e) {
            const status = document.getElementById('status').value;
            const forwardedDept = document.getElementById('forwarded_to')?.value;
            if (status === 'forwarded' && !forwardedDept) {
                e.preventDefault();
                alert('Please select a department to forward to.');
            }
        });

        const statusSelect = document.getElementById('status');
        const forwardedDeptDiv = document.getElementById('forwarded_dept');
        if (statusSelect && forwardedDeptDiv) {
            statusSelect.addEventListener('change', function() {
                forwardedDeptDiv.classList.toggle('hidden', this.value !== 'forwarded');
            });
        }
    </script>
</body>
</html>
