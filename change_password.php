
<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password)) $errors['current_password'] = 'Current password is required';
    if (empty($new_password)) $errors['new_password'] = 'New password is required';
    elseif (strlen($new_password) < 6) $errors['new_password'] = 'Password must be at least 6 characters';
    if ($new_password !== $confirm_password) $errors['confirm_password'] = 'Passwords do not match';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($current_password, $user['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);

            if ($stmt->rowCount() > 0) {
                $success = 'Password changed successfully!';
                $_POST = [];
            } else {
                $errors['general'] = 'Failed to change password.';
            }
        } else {
            $errors['current_password'] = 'Current password is incorrect';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Kakamega ICT Authority</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .sidebar.active { display: block; }
        @media (min-width: 768px) { .sidebar { display: block; } }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <button id="mobile-menu-toggle" class="md:hidden p-2 bg-gray-800 text-white rounded"><i class="fas fa-bars"></i></button>
    <div class="flex flex-col md:flex-row">
        <aside class="sidebar hidden md:block w-full md:w-64 bg-gray-800 text-white p-4 shadow-md">
            <div class="text-center mb-6">
                <img src="ictlogo.jpeg" alt="Kakamega ICT Logo" class="h-16 mx-auto">
                <h3 class="text-lg font-semibold mt-2"><?php echo ucfirst($_SESSION['role']); ?> Dashboard</h3>
            </div>
            <nav class="space-y-2">
                <a href="<?php echo $_SESSION['role'] === 'student' ? 'student_dashboard.php' : $_SESSION['role'] . '_dashboard.php'; ?>" class="block p-2 hover:bg-gray-700 rounded"><i class="fas fa-home mr-2"></i>Home</a>
                <a href="update_profile.php" class="block p-2 hover:bg-gray-700 rounded"><i class="fas fa-user-edit mr-2"></i>Update Profile</a>
                <a href="change_password.php" class="block p-2 bg-gray-700 rounded"><i class="fas fa-key mr-2"></i>Change Password</a>
                <a href="logout.php" class="block p-2 hover:bg-gray-700 rounded"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
            </nav>
            <div class="mt-4 text-center text-sm">
                Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
            </div>
        </aside>

        <main class="flex-1 p-6">
            <header class="mb-6">
                <h1 class="text-2xl font-bold">Change Password</h1>
                <div class="mt-2 text-sm text-gray-600">
                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <span class="ml-4"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></span>
                </div>
            </header>

            <?php if (!empty($errors['general'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 p-4 rounded mb-4"><?php echo $errors['general']; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 p-4 rounded mb-4"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="bg-white p-6 rounded shadow-md">
                <form action="change_password.php" method="POST" class="space-y-4" id="passwordForm">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700"><i class="fas fa-lock"></i> Current Password</label>
                        <div class="relative">
                            <input type="password" id="current_password" name="current_password" required class="w-full p-2 border rounded">
                            <span class="password-toggle absolute right-3 top-2 text-gray-400"><i class="fas fa-eye"></i></span>
                        </div>
                        <?php if (!empty($errors['current_password'])): ?>
                            <p class="text-red-500 text-sm"><?php echo $errors['current_password']; ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700"><i class="fas fa-lock"></i> New Password</label>
                        <div class="relative">
                            <input type="password" id="new_password" name="new_password" required class="w-full p-2 border rounded">
                            <span class="password-toggle absolute right-3 top-2 text-gray-400"><i class="fas fa-eye"></i></span>
                        </div>
                        <?php if (!empty($errors['new_password'])): ?>
                            <p class="text-red-500 text-sm"><?php echo $errors['new_password']; ?></p>
                        <?php endif; ?>
                        <small class="text-gray-500">Minimum 6 characters</small>
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700"><i class="fas fa-lock"></i> Confirm New Password</label>
                        <div class="relative">
                            <input type="password" id="confirm_password" name="confirm_password" required class="w-full p-2 border rounded">
                            <span class="password-toggle absolute right-3 top-2 text-gray-400"><i class="fas fa-eye"></i></span>
                        </div>
                        <?php if (!empty($errors['confirm_password'])): ?>
                            <p class="text-red-500 text-sm"><?php echo $errors['confirm_password']; ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="flex space-x-2">
                        <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">Change Password</button>
                        <a href="<?php echo $_SESSION['role'] === 'student' ? 'student_dashboard.php' : $_SESSION['role'] . '_dashboard.php'; ?>" class="bg-gray-500 text-white py-2 px-4 rounded hover:bg-gray-600">Cancel</a>
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
