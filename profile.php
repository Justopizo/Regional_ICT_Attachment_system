<?php
require_once 'db_connect.php';
require_once 'functions.php';

if (!is_logged_in()) {
    redirect('index.php');
}

$user = get_current_user_data();
$student_data = [];
$errors = [];
$success = '';

// Initialize all form variables with empty defaults
$full_name = $user['full_name'] ?? '';
$phone = $user['phone'] ?? '';
$institution = $course = $year_of_study = $side_hustle = '';

if ($_SESSION['role'] === 'student') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $student_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Set student-specific variables
        if ($student_data) {
            $institution = $student_data['institution'] ?? '';
            $course = $student_data['course'] ?? '';
            $year_of_study = $student_data['year_of_study'] ?? '';
            $side_hustle = $student_data['side_hustle'] ?? '';
        }
    } catch (PDOException $e) {
        set_alert("Database error: " . $e->getMessage(), 'error');
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$full_name, $phone, $_SESSION['user_id']]);
        
        if ($_SESSION['role'] === 'student') {
            $institution = sanitize_input($_POST['institution'] ?? '');
            $course = sanitize_input($_POST['course'] ?? '');
            $year_of_study = sanitize_input($_POST['year_of_study'] ?? '');
            $side_hustle = sanitize_input($_POST['side_hustle'] ?? '');
            
            $stmt = $pdo->prepare("UPDATE students SET 
                institution = ?, 
                course = ?, 
                year_of_study = ?, 
                side_hustle = ?
                WHERE user_id = ?");
            $stmt->execute([
                $institution, 
                $course, 
                $year_of_study, 
                $side_hustle,
                $_SESSION['user_id']
            ]);
        }
        
        $_SESSION['full_name'] = $full_name;
        set_alert("Profile updated successfully!", 'success');
        redirect('profile.php');
    } catch (PDOException $e) {
        set_alert("Error updating profile: " . $e->getMessage(), 'error');
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (!password_verify($current_password, $user['password'])) {
        $errors[] = "Current password is incorrect";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            set_alert("Password changed successfully!", 'success');
            redirect('profile.php');
        } catch (PDOException $e) {
            set_alert("Error changing password: " . $e->getMessage(), 'error');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Western Region ICT Authority</title>
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
            max-width: 1000px;
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
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
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
        .error {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }
        .tab.active {
            border-bottom-color: #3498db;
            font-weight: 600;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            .tabs {
                flex-direction: column;
                border-bottom: none;
            }
            .tab {
                border-bottom: 1px solid #ddd;
                border-left: 3px solid transparent;
            }
            .tab.active {
                border-left-color: #3498db;
                border-bottom-color: #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Western Region ICT Authority</h1>
        <div class="user-menu">
            <span><i class="fas fa-user"></i> <?= htmlspecialchars($user['full_name']) ?></span>
            <a href="<?= $_SESSION['role'] === 'student' ? 'student_dashboard.php' : ($_SESSION['role'] === 'hr' ? 'hr_dashboard.php' : ($_SESSION['role'] === 'ict' ? 'ict_dashboard.php' : 'registry_dashboard.php')) ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="container">
        <?php display_alert(); ?>

        <div class="welcome-banner">
            <h2>Profile Management</h2>
            <p>Update your personal information and password</p>
        </div>

        <div class="tabs">
            <div class="tab active" onclick="openTab(event, 'profile-tab')">Profile Information</div>
            <div class="tab" onclick="openTab(event, 'password-tab')">Change Password</div>
        </div>

        <div id="profile-tab" class="tab-content active">
            <div class="card">
                <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                <form action="profile.php" method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" 
                                value="<?= htmlspecialchars($full_name) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                value="<?= htmlspecialchars($phone) ?>" required>
                        </div>
                    </div>
                    
                    <?php if ($_SESSION['role'] === 'student'): ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="institution">Institution</label>
                                <input type="text" id="institution" name="institution" 
                                    value="<?= htmlspecialchars($institution) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="course">Course</label>
                                <input type="text" id="course" name="course" 
                                    value="<?= htmlspecialchars($course) ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="year_of_study">Year of Study</label>
                                <select id="year_of_study" name="year_of_study" required>
                                    <option value="">Select Year</option>
                                    <option value="1st Year" <?= $year_of_study === '1st Year' ? 'selected' : '' ?>>1st Year</option>
                                    <option value="2nd Year" <?= $year_of_study === '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                                    <option value="3rd Year" <?= $year_of_study === '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                                    <option value="4th Year" <?= $year_of_study === '4th Year' ? 'selected' : '' ?>>4th Year</option>
                                    <option value="5th Year" <?= $year_of_study === '5th Year' ? 'selected' : '' ?>>5th Year</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="side_hustle">Skills/Side Hustle</label>
                                <textarea id="side_hustle" name="side_hustle"><?= htmlspecialchars($side_hustle) ?></textarea>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" name="update_profile" class="btn">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>

        <div id="password-tab" class="tab-content">
            <div class="card">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
                <?php if (!empty($errors)): ?>
                    <div style="color: #e74c3c; margin-bottom: 15px;">
                        <?php foreach ($errors as $error): ?>
                            <p><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form action="profile.php" method="POST">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="change_password" class="btn">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            // Hide all tab contents
            const tabContents = document.getElementsByClassName("tab-content");
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove("active");
            }
            
            // Remove active class from all tabs
            const tabs = document.getElementsByClassName("tab");
            for (let i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("active");
            }
            
            // Show the current tab and mark button as active
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>
</html>