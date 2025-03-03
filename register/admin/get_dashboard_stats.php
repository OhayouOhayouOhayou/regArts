<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'check_auth.php';
require_once '../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // ดึงข้อมูลสถิติโดยไม่ใช้คอลัมน์ is_approved ก่อน
    $stats = [
        'total' => $conn->query("SELECT COUNT(*) FROM registrations")->fetchColumn(),
        'pending' => $conn->query("
            SELECT COUNT(*) 
            FROM registrations 
            WHERE payment_status = 'paid'
        ")->fetchColumn(),
        'approved' => 0, // ตั้งค่าเริ่มต้นเป็น 0
        'unpaid' => $conn->query("
            SELECT COUNT(*) 
            FROM registrations 
            WHERE payment_status = 'not_paid'
        ")->fetchColumn()
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);

} catch (Exception $e) {
    error_log("Error in get_dashboard_stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage()
    ]);
}