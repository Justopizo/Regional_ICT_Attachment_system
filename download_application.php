<?php
require_once 'db_connect.php';
require_once 'functions.php';

check_role(['ict']);

if (isset($_GET['app_id'])) {
    $app_id = intval($_GET['app_id']);
    try {
        $stmt = $pdo->prepare("
            SELECT application_letter_path, cv_path, insurance_path, introduction_letter_path
            FROM applications
            WHERE id = ? AND forwarded_to = 'ict' AND status = 'accepted'
        ");
        $stmt->execute([$app_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($application) {
            // Example: Serve the application letter (modify for other documents or zip them)
            $file_path = $application['application_letter_path'];
            if (file_exists($file_path)) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
                readfile($file_path);
                exit;
            } else {
                set_alert("File not found.", 'error');
                redirect('ict_dashboard.php');
            }
        } else {
            set_alert("Invalid application or not accepted.", 'error');
            redirect('ict_dashboard.php');
        }
    } catch (PDOException $e) {
        set_alert("Error fetching application: " . $e->getMessage(), 'error');
        redirect('ict_dashboard.php');
    }
} else {
    set_alert("No application ID provided.", 'error');
    redirect('ict_dashboard.php');
}
?>