<?php
require_once 'config.php';
require_once 'functions.php';

// Only allow logged in users to update profile
if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $full_name = sanitizeInput($_POST['full_name']);
    
    // Validate inputs
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    } elseif (strlen($username) < 4) {
        $errors['username'] = 'Username must be at least 4 characters';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required';
    } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors['phone'] = 'Invalid phone number';
    }
    
    if (empty($full_name)) {
        $errors['full_name'] = 'Full name is required';
    }
    
    // Check if username or email already exists (excluding current user)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $stmt->execute([$username, $email, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        $errors['general'] = 'Username or email already exists';
    }
    
    // If no errors, update the profile
    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone = ?, full_name = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$username, $email, $phone, $full_name, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            // Update session variables
            $_SESSION['username'] = $username;
            $_SESSION['full_name'] = $full_name;
            
            $success = 'Profile updated successfully!';
            $user = getUserById($user_id); // Refresh user data
        } else {
            $errors['general'] = 'Failed to update profile. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile - Kakamega ICT Authority</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex flex-col md:flex-row">
        <aside class="w-full md:w-64 bg-gray-800 text-white p-4 shadow-md">
            <div class="text-center mb-6">
                <img src="ictlogo.jpeg" alt="Kakamega ICT Logo" class="h-16 mx-auto">
                <h3 class="text-lg font-semibold mt-2"><?php echo ucfirst(getUserRole()); ?> Dashboard</h3>
            </div>
            <nav class="space-y-2">
                <a href="<?php echo getUserRole() === 'student' ? 'student_dashboard.php' : getUserRole() . '_dashboard.php'; ?>" class="block p-2 text-white hover:bg-gray-700 rounded"><i class="fas fa-home mr-2"></i>Home</a>
                <a href="update_profile.php" class="block p-2 text-white bg-gray-700 rounded"><i class="fas fa-user-edit mr-2"></i>Update Profile</a>
                <a href="change_password.php" class="block p-2 text-white hover:bg-gray-700 rounded"><i class="fas fa-key mr-2"></i>Change Password</a>
                <a href="logout.php" class="block p-2 text-white hover:bg-gray-700 rounded"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
            </nav>
            <div class="mt-4 text-center text-sm">
                Logged in as: <strong><?php echo $_SESSION['username']; ?></strong>
            </div>
        </aside>

        <main class="flex-1 p-6">
            <header class="mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Update Profile</h1>
                <div class="mt-2 text-sm text-gray-600">
                    <span><i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?></span>
                    <span class="ml-4"><i class="fas fa-envelope"></i> <?php echo $user['email']; ?></span>
                </div>
            </header>

            <?php if (!empty($errors['general'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $errors['general']; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="bg-white p-6 rounded shadow-md">
                <form action="update_profile.php" method="POST" class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="username" name="username" value="<?php echo $user['username']; ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php if (!empty($errors['username'])): ?>
                            <p class="text-red-500 text-sm"><?php echo $errors['username']; ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="email" name="email" value="<?php echo $user['email']; ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php if (!empty($errors['email'])): ?>
                            <p class="text-red-500 text-sm"><?php echo $errors['email']; ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-gray-700"><i class="fas fa-id-card"></i> Full Name</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo $user['full_name']; ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php if (!empty($errors['full_name'])): ?>
                            <p class="text-red-500 text-sm"><?php echo $errors['full_name']; ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700"><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo $user['phone']; ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php if (!empty($errors['phone'])): ?>
                            <p class="text-red-500 text-sm"><?php echo $errors['phone']; ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex space-x-2">
                        <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">Update Profile</button>
                        <a href="<?php echo getUserRole() === 'student' ? 'student_dashboard.php' : getUserRole() . '_dashboard.php'; ?>" class="bg-gray-500 text-white py-2 px-4 rounded hover:bg-gray-600">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <footer class="mt-6 text-center text-gray-600 text-sm">
        <p>© 2025 Kakamega Regional ICT Authority | Developed by Justin Ratemo - 0793031269</p>
    </footer>

    <script src="script.js"></script>
</body>
</html>