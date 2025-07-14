
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
                $success = 'Application submitted successfully!';
                header("Location: student_dashboard.php?success=" . urlencode($success));
                exit();
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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex flex-col md:flex-row">
        <aside class="w-full md:w-64 bg-white p-4 shadow-md">
            <div class="text-center mb-6">
                <img src="ictlogo.jpeg" alt="Kakamega ICT Logo" class="h-16 mx-auto">
                <h3 class="text-lg font-semibold mt-2">Student Dashboard</h3>
            </div>
            <nav class="space-y-2">
                <a href="student_dashboard.php" class="block p-2 text-gray-700 hover:bg-gray-200 rounded"><i class="fas fa-home mr-2"></i>Home</a>
                <a href="apply_attachment.php" class="block p-2 text-gray-700 bg-blue-100 rounded"><i class="fas fa-file-alt mr-2"></i>Apply for Attachment</a>
                <a href="update_profile.php" class="block p-2 text-gray-700 hover:bg-gray-200 rounded"><i class="fas fa-user-edit mr-2"></i>Update Profile</a>
                <a href="change_password.php" class="block p-2 text-gray-700 hover:bg-gray-200 rounded"><i class="fas fa-key mr-2"></i>Change Password</a>
                <a href="logout.php" class="block p-2 text-gray-700 hover:bg-gray-200 rounded"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
            </nav>
            <div class="mt-4 text-center text-sm text-gray-600">
                Logged in as: <strong><?php echo htmlspecialchars($user['username']); ?></strong>
            </div>
        </aside>

        <main class="flex-1 p-6">
            <header class="mb-6">
                <h1 class="text-2xl font-bold">Apply for Attachment</h1>
                <div class="mt-2 text-sm text-gray-600">
                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($user['full_name']); ?></span>
                    <span class="ml-4"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
                </div>
            </header>

            <?php if (!$settings['application_window_open']): ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 p-4 rounded mb-4">
                    <h3 class="font-bold"><i class="fas fa-exclamation-triangle"></i> Application Window Closed</h3>
                    <p>The application window is currently closed. Please check back later.</p>
                    <a href="student_dashboard.php" class="mt-2 inline-block bg-gray-500 text-white py-1 px-3 rounded hover:bg-gray-600">Back to Dashboard</a>
                </div>
            <?php elseif ($has_pending_application): ?>
                <div class="bg-blue-100 border border-blue-400 text-blue-700 p-4 rounded mb-4">
                    <h3 class="font-bold"><i class="fas fa-info-circle"></i> Pending Application</h3>
                    <p>You have a pending application. Please wait for processing.</p>
                    <a href="student_dashboard.php" class="mt-2 inline-block bg-gray-500 text-white py-1 px-3 rounded hover:bg-gray-600">View Status</a>
                </div>
            <?php else: ?>
                <div class="bg-white p-6 rounded shadow-md">
                    <h2 class="text-xl font-semibold mb-4"><i class="fas fa-file-alt"></i> Attachment Application Form</h2>
                    <?php if (!empty($errors['general'])): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 p-2 mb-4 rounded"><?php echo htmlspecialchars($errors['general']); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 p-2 mb-4 rounded"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <form action="apply_attachment.php" method="POST" enctype="multipart/form-data" class="space-y-4" id="applicationForm">
                        <div>
                            <label for="department_preference" class="block text-sm font-medium text-gray-700">Preferred Department</label>
                            <select id="department_preference" name="department_preference" required class="w-full p-2 border rounded">
                                <option value="">Select Department</option>
                                <option value="hr" <?php echo (isset($_POST['department_preference']) && $_POST['department_preference'] === 'hr') ? 'selected' : ''; ?>>HR Department</option>
                                <option value="ict" <?php echo (isset($_POST['department_preference']) && $_POST['department_preference'] === 'ict') ? 'selected' : ''; ?>>ICT Department</option>
                                <option value="registry" <?php echo (isset($_POST['department_preference']) && $_POST['department_preference'] === 'registry') ? 'selected' : ''; ?>>Registry Department</option>
                            </select>
                            <?php if (!empty($errors['department_preference'])): ?>
                                <p class="text-red-500 text-sm"><?php echo htmlspecialchars($errors['department_preference']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label for="application_letter" class="block text-sm font-medium text-gray-700">Application Letter (PDF/DOC/DOCX)</label>
                            <input type="file" id="application_letter" name="application_letter" accept=".pdf,.doc,.docx" required class="w-full p-2 border rounded">
                            <?php if (!empty($errors['application_letter'])): ?>
                                <p class="text-red-500 text-sm"><?php echo htmlspecialchars($errors['application_letter']); ?></p>
                            <?php endif; ?>
                            <small class="text-gray-500">Upload your formal application letter</small>
                        </div>

                        <div>
                            <label for="insurance" class="block text-sm font-medium text-gray-700">Insurance Cover (PDF/DOC/DOCX/JPG/JPEG/PNG)</label>
                            <input type="file" id="insurance" name="insurance" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required class="w-full p-2 border rounded">
                            <?php if (!empty($errors['insurance'])): ?>
                                <p class="text-red-500 text-sm"><?php echo htmlspecialchars($errors['insurance']); ?></p>
                            <?php endif; ?>
                            <small class="text-gray-500">Upload proof of valid insurance</small>
                        </div>

                        <div>
                            <label for="cv" class="block text-sm font-medium text-gray-700">Curriculum Vitae (PDF/DOC/DOCX)</label>
                            <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx" required class="w-full p-2 border rounded">
                            <?php if (!empty($errors['cv'])): ?>
                                <p class="text-red-500 text-sm"><?php echo htmlspecialchars($errors['cv']); ?></p>
                            <?php endif; ?>
                            <small class="text-gray-500">Upload your current CV</small>
                        </div>

                        <div>
                            <label for="introduction_letter" class="block text-sm font-medium text-gray-700">Introduction Letter (PDF/DOC/DOCX)</label>
                            <input type="file" id="introduction_letter" name="introduction_letter" accept=".pdf,.doc,.docx" required class="w-full p-2 border rounded">
                            <?php if (!empty($errors['introduction_letter'])): ?>
                                <p class="text-red-500 text-sm"><?php echo htmlspecialchars($errors['introduction_letter']); ?></p>
                            <?php endif; ?>
                            <small class="text-gray-500">Upload your institution's letter</small>
                        </div>

                        <div>
                            <label for="side_hustle" class="block text-sm font-medium text-gray-700">Side Hustles/Skills (Optional)</label>
                            <textarea id="side_hustle" name="side_hustle" rows="3" class="w-full p-2 border rounded"><?php echo htmlspecialchars($_POST['side_hustle'] ?? ''); ?></textarea>
                            <small class="text-gray-500">List any side hustles or skills</small>
                        </div>

                        <div class="flex space-x-2">
                            <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">Submit Application</button>
                            <a href="student_dashboard.php" class="bg-gray-500 text-white py-2 px-4 rounded hover:bg-gray-600">Cancel</a>
                        </div>
                    </form>
                </div>

                <div class="bg-white p-6 rounded shadow-md mt-6">
                    <h3 class="text-lg font-semibold mb-2"><i class="fas fa-info-circle"></i> Application Status</h3>
                    <p><strong>Application Window:</strong> <?php echo $settings['application_window_open'] ? 'OPEN' : 'CLOSED'; ?></p>
                    <p><strong>HR Slots Available:</strong> <?php echo $settings['hr_slots_remaining']; ?> / <?php echo $settings['hr_slots']; ?></p>
                    <p><strong>ICT Slots Available:</strong> <?php echo $settings['ict_slots_remaining']; ?> / <?php echo $settings['ict_slots']; ?></p>
                    <p><strong>Registry Slots Available:</strong> <?php echo $settings['registry_slots_remaining']; ?> / <?php echo $settings['registry_slots']; ?></p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <footer class="mt-6 text-center text-gray-600 text-sm">
        <p>© 2025 Kakamega Regional ICT Authority | Developed by Justin Ratemo - 0793031269</p>
    </footer>

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
    </script>
</body>
</html>
