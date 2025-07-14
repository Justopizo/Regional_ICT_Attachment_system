<?php
require_once 'db_connect.php';
require_once 'functions.php';

if (is_logged_in()) {
    redirect('student_dashboard.php');
}

// Initialize all form variables
$full_name = $email = $phone = $institution = $course = $year_of_study = $side_hustle = $preferred_department = '';
$attachment_start_date = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $institution = sanitize_input($_POST['institution'] ?? '');
    $course = sanitize_input($_POST['course'] ?? '');
    $year_of_study = sanitize_input($_POST['year_of_study'] ?? '');
    $side_hustle = sanitize_input($_POST['side_hustle'] ?? '');
    $preferred_department = sanitize_input($_POST['preferred_department'] ?? '');
    $attachment_start_date = sanitize_input($_POST['attachment_start_date'] ?? '');

    // Validation
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    if (empty($institution)) $errors[] = "Institution is required";
    if (empty($course)) $errors[] = "Course is required";
    if (empty($year_of_study)) $errors[] = "Year of study is required";
    if (empty($attachment_start_date)) $errors[] = "Attachment start date is required";
    if (!in_array($preferred_department, ['hr', 'ict', 'registry'])) $errors[] = "Invalid department selection";

    // Validate and calculate end date
    if (!empty($attachment_start_date)) {
        $start_date = DateTime::createFromFormat('Y-m-d', $attachment_start_date);
        if (!$start_date) {
            $errors[] = "Invalid start date format (use YYYY-MM-DD)";
        } else {
            $end_date = clone $start_date;
            $end_date->add(new DateInterval('P3M')); // Add 3 months
            $attachment_end_date = $end_date->format('Y-m-d');
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Email already registered";
            } else {
                // Create user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, full_name, phone) VALUES (?, ?, ?, 'student', ?, ?)");
                $username = strtolower(str_replace(' ', '', $full_name)) . rand(100, 999);
                $stmt->execute([$username, $hashed_password, $email, $full_name, $phone]);
                $user_id = $pdo->lastInsertId();

                // Create student record with attachment dates
                $stmt = $pdo->prepare("INSERT INTO students (user_id, institution, course, year_of_study, side_hustle, preferred_department, attachment_start_date, attachment_end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $institution, $course, $year_of_study, $side_hustle, $preferred_department, $attachment_start_date, $attachment_end_date]);

                $pdo->commit();

                // Auto-login after registration
                $_SESSION['user_id'] = $user_id;
                $_SESSION['role'] = 'student';
                $_SESSION['full_name'] = $full_name;

                set_alert("Registration successful! Welcome to the Western Region ICT Authority attachment program.", 'success');
                redirect('student_dashboard.php');
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Western Region ICT Authority - Student Registration</title>
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
            padding: 20px;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo i {
            font-size: 48px;
            color: #3498db;
        }
        h2 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 20px;
        }
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        .form-group {
            flex: 1 0 200px;
            margin: 0 10px 20px;
            min-width: 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
        }
        .form-group input[readonly] {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
        .btn {
            padding: 12px 20px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            display: block;
            width: 200px;
            margin: 20px auto;
            text-align: center;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .error {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }
        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .links {
            text-align: center;
            margin-top: 20px;
        }
        .links a {
            color: #3498db;
            text-decoration: none;
        }
        .links a:hover {
            text-decoration: underline;
        }
        @media (max-width: 600px) {
            .form-group {
                flex: 1 0 100%;
            }
            .btn {
                width: 100%;
            }
        }
    </style>
    <script>
        function calculateEndDate() {
            const startDateInput = document.getElementById('attachment_start_date');
            const endDateInput = document.getElementById('attachment_end_date');
            
            if (startDateInput.value) {
                const startDate = new Date(startDateInput.value);
                const endDate = new Date(startDate);
                
                // Add 3 months to the start date
                endDate.setMonth(endDate.getMonth() + 3);
                
                // Format the date as YYYY-MM-DD
                const formattedEndDate = endDate.toISOString().split('T')[0];
                endDateInput.value = formattedEndDate;
            } else {
                endDateInput.value = '';
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="logo">
            <i class="fas fa-laptop-code"></i>
            <h2>Student Registration</h2>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="full_name"><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($full_name) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($phone) ?>" required>
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password (min 8 characters)</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="institution"><i class="fas fa-university"></i> Institution</label>
                    <input type="text" id="institution" name="institution" value="<?= htmlspecialchars($institution) ?>" required>
                </div>
                <div class="form-group">
                    <label for="course"><i class="fas fa-book"></i> Course</label>
                    <input type="text" id="course" name="course" value="<?= htmlspecialchars($course) ?>" required>
                </div>
                <div class="form-group">
                    <label for="year_of_study"><i class="fas fa-calendar-alt"></i> Year of Study</label>
                    <select id="year_of_study" name="year_of_study" required>
                        <option value="">Select Year</option>
                        <option value="1st Year" <?= $year_of_study === '1st Year' ? 'selected' : '' ?>>1st Year</option>
                        <option value="2nd Year" <?= $year_of_study === '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                        <option value="3rd Year" <?= $year_of_study === '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                        <option value="4th Year" <?= $year_of_study === '4th Year' ? 'selected' : '' ?>>4th Year</option>
                        <option value="5th Year" <?= $year_of_study === '5th Year' ? 'selected' : '' ?>>5th Year</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="side_hustle"><i class="fas fa-code"></i> Skills/Side Hustle (Optional)</label>
                    <textarea id="side_hustle" name="side_hustle"><?= htmlspecialchars($side_hustle) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="preferred_department"><i class="fas fa-building"></i> Preferred Department</label>
                    <select id="preferred_department" name="preferred_department" required>
                        <option value="">Select Department</option>
                        <option value="hr" <?= $preferred_department === 'hr' ? 'selected' : '' ?>>Human Resources</option>
                        <option value="ict" <?= $preferred_department === 'ict' ? 'selected' : '' ?>>ICT Department</option>
                        <option value="registry" <?= $preferred_department === 'registry' ? 'selected' : '' ?>>Registry Department</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="attachment_start_date"><i class="fas fa-calendar-day"></i> Attachment Start Date</label>
                    <input type="date" id="attachment_start_date" name="attachment_start_date" 
                           value="<?= htmlspecialchars($attachment_start_date) ?>" 
                           onchange="calculateEndDate()" required>
                </div>
                <div class="form-group">
                    <label for="attachment_end_date"><i class="fas fa-calendar-week"></i> Attachment End Date</label>
                    <input type="date" id="attachment_end_date" name="attachment_end_date" readonly>
                </div>
            </div>

            <button type="submit" class="btn"><i class="fas fa-user-plus"></i> Register</button>
        </form>

        <div class="links">
            <p>Already have an account? <a href="index.php"><i class="fas fa-sign-in-alt"></i> Login here</a></p>
        </div>
    </div>
</body>
</html>