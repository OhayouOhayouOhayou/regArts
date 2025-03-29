<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'registration_details.log');

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

// รับข้อมูล JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// ถ้าไม่สามารถแปลง JSON ได้ ให้ใช้ $_POST
if (json_last_error() !== JSON_ERROR_NONE) {
    $data = $_POST;
}

// ตรวจสอบว่ามี registration_id หรือไม่
if (empty($data['registration_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาระบุรหัสการลงทะเบียน'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$registrationId = $data['registration_id'];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // ดึงข้อมูลการลงทะเบียน
    $sql = "SELECT * FROM registrations WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$registrationId]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registration) {
        echo json_encode([
            'success' => false,
            'message' => 'ไม่พบข้อมูลการลงทะเบียน'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ดึงข้อมูลกลุ่มการลงทะเบียน
    $group = $registration['registration_group'];
    
    // ดึงข้อมูลผู้ลงทะเบียนทั้งหมดในกลุ่มเดียวกัน (ไม่ซ้ำกัน)
    $sql = "SELECT DISTINCT id, title, title_other, fullname, organization, position, phone, email, line_id, 
                   payment_status, is_approved, created_at
            FROM registrations 
            WHERE registration_group = ?
            ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$group]);
    $groupRegistrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get first member ID for document retrieval
    $firstMemberId = $groupRegistrations[0]['id'] ?? $registrationId;
    
    // ดึงข้อมูลที่อยู่
    $sql = "SELECT a.*, 
                  p.name_in_thai as province_name, 
                  d.name_in_thai as district_name, 
                  s.name_in_thai as subdistrict_name
           FROM registration_addresses a
           LEFT JOIN provinces p ON a.province_id = p.id
           LEFT JOIN districts d ON a.district_id = d.id
           LEFT JOIN subdistricts s ON a.subdistrict_id = s.id
           WHERE a.registration_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$registrationId]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ดึงข้อมูลเอกสารจากสมาชิกคนแรกของกลุ่ม (ที่มีเอกสารครบถ้วนที่สุด)
    $sql = "SELECT * FROM registration_documents WHERE registration_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$firstMemberId]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log for debugging
    error_log("Fetching documents for first member ID: " . $firstMemberId);
    error_log("Documents found: " . count($documents));
    
    // ดึงข้อมูลหลักฐานการชำระเงิน
    $paymentSlip = null;
    if ($registration['payment_slip_id']) {
        $sql = "SELECT * FROM registration_files WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$registration['payment_slip_id']]);
        $paymentSlip = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ส่งข้อมูลกลับไป
    echo json_encode([
        'success' => true,
        'registration' => $registration,
        'group_registrations' => $groupRegistrations,
        'addresses' => $addresses,
        'documents' => $documents,
        'payment_slip' => $paymentSlip
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}