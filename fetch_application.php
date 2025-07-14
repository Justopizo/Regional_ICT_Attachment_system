<?php
require_once 'db_connect.php';
require_once 'functions.php';
check_role(['ict']);

if (isset($_GET['id'])) {
    $app_id = intval($_GET['id']);

    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.full_name, u.email, u.phone, s.institution, s.course, s.year_of_study, s.side_hustle
            FROM applications a
            JOIN students s ON a.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE a.id = ? AND a.forwarded_to = 'ict'
        ");
        $stmt->execute([$app_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($data);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
