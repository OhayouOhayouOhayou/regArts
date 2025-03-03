<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'check_auth.php';
require_once '../config/database.php';

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ไม่พบรหัสการลงทะเบียน');
    }

    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT r.*, rf.file_path as payment_slip
        FROM registrations r
        LEFT JOIN registration_files rf ON r.id = rf.registration_id
        WHERE r.id = ?
    ");

    $stmt->execute([$_GET['id']]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        throw new Exception('ไม่พบข้อมูลการลงทะเบียน');
    }

    echo json_encode([
        'success' => true,
        'data' => $registration
    ]);

} catch (Exception $e) {
    error_log("Error in get_registration_details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'ไม่สามารถดึงข้อมูลได้: ' . $e->getMessage()
    ]);
}