<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'check_registration.log');

header('Content-Type: application/json; charset=utf-8');
require_once 'config/database.php';

// ตรวจสอบว่าเป็นการเรียกแบบ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ตรวจสอบว่ามีการส่ง JSON มาหรือไม่
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// ถ้าไม่สามารถแปลง JSON ได้ ให้ใช้ $_POST
if (json_last_error() !== JSON_ERROR_NONE) {
    $data = $_POST;
}

// ตรวจสอบว่ามีเบอร์โทรศัพท์หรือไม่
if (empty($data['phone'])) {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาระบุเบอร์โทรศัพท์'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$phone = $data['phone'];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // ค้นหาข้อมูลจากเบอร์โทรศัพท์แบบไม่ซ้ำ
    $sql = "SELECT DISTINCT r.* 
            FROM registrations r
            WHERE r.phone = ?
            ORDER BY r.created_at ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$phone]);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ถ้าไม่พบข้อมูล
    if (empty($registrations)) {
        echo json_encode([
            'success' => true,
            'status' => 'not_registered',
            'message' => 'ไม่พบข้อมูลการลงทะเบียน'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ดึงรายการแรกที่พบ (ซึ่งเป็นรายการเก่าสุด) เพื่อส่งกลับไปแสดงสถานะ
    $firstReg = $registrations[0];
    
    // ตรวจสอบว่ามีผู้สมัครทั้งหมดกี่คนในกลุ่มนี้
    $sql = "SELECT COUNT(DISTINCT id) FROM registrations WHERE registration_group = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$firstReg['registration_group']]);
    $totalRegistrants = $stmt->fetchColumn();
    
    // ตรวจสอบสถานะการชำระเงิน
    $paymentStatus = $firstReg['payment_status'] ?? 'not_paid';
    $isApproved = $firstReg['is_approved'] ?? 0;
    
    // ส่งข้อมูลกลับไป
    echo json_encode([
        'success' => true,
        'status' => $paymentStatus === 'paid' ? 'paid' : 'not_paid',
        'message' => $paymentStatus === 'paid' 
            ? ($isApproved ? 'ลงทะเบียนและชำระเงินเรียบร้อยแล้ว' : 'ชำระเงินแล้ว รอการตรวจสอบ') 
            : 'ลงทะเบียนแล้ว รอชำระเงิน',
        'data' => [
            'registration_id' => $firstReg['id'],
            'registration_group' => $firstReg['registration_group'],
            'payment_status' => $paymentStatus,
            'is_approved' => $isApproved,
            'total_registrants' => $totalRegistrants
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("เกิดข้อผิดพลาดในการตรวจสอบข้อมูล: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการตรวจสอบข้อมูล: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}