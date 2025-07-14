<?php
require_once 'db_connect.php';
require_once 'functions.php';

// Ensure the user is logged in and has the 'registry' role
check_role(['registry']);

header('Content-Type: application/json');

$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($application_id === 0) {
    echo json_encode(['error' => 'Invalid application ID.']);
    exit;
}

try {
    // Fetch application details along with student and user information, including attachment dates
    $stmt = $pdo->prepare("
        SELECT
            a.*,
            u.full_name, u.email, u.phone_number,
            s.institution, s.course, s.student_id_number, s.year_of_study,
            s.attachment_start_date, s.attachment_end_date,
            s.cv_path, s.application_letter_path, s.id_card_path, s.academic_transcript_path
        FROM applications a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE a.id = ? AND a.forwarded_to = 'registry'
    ");
    $stmt->execute([$application_id]);
    $application_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($application_details) {
        // Format dates for display
        if (!empty($application_details['created_at'])) {
            $application_details['created_at_formatted'] = date('M j, Y H:i', strtotime($application_details['created_at']));
        }
        if (!empty($application_details['updated_at'])) {
            $application_details['updated_at_formatted'] = date('M j, Y H:i', strtotime($application_details['updated_at']));
        }
        if (!empty($application_details['attachment_start_date'])) {
            $application_details['attachment_start_date_formatted'] = date('M j, Y', strtotime($application_details['attachment_start_date']));
        }
        if (!empty($application_details['attachment_end_date'])) {
            $application_details['attachment_end_date_formatted'] = date('M j, Y', strtotime($application_details['attachment_end_date']));
        }

        echo json_encode(['success' => true, 'application' => $application_details]);
    } else {
        echo json_encode(['error' => 'Application not found or not forwarded to registry.']);
    }

} catch (PDOException $e) {
    error_log("Error fetching application details: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
