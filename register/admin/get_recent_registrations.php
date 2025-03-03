<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'check_auth.php';
require_once '../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // ดึงข้อมูลการลงทะเบียนล่าสุดโดยใช้เฉพาะคอลัมน์ที่มีอยู่
    $query = "
        SELECT 
            r.id,
            r.fullname,
            r.organization,
            r.created_at,
            r.payment_status,
            CASE
                WHEN r.payment_status = 'paid' THEN 'pending_approval'
                ELSE 'not_paid'
            END as status
        FROM registrations r
        ORDER BY r.created_at DESC
        LIMIT 10
    ";
    
    $registrations = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $registrations
    ]);

} catch (Exception $e) {
    error_log("Error in get_recent_registrations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล'
    ]);
}