<?php
// เพิ่มการเก็บ log
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'debug.log');

header('Content-Type: application/json; charset=utf-8');
require_once 'config/database.php';

try {
    // รับข้อมูลที่ส่งมา
    $data = json_decode(file_get_contents('php://input'), true);
    error_log("Received data: " . print_r($data, true));
    
    if (!isset($data['registration_id'])) {
        throw new Exception('กรุณาระบุรหัสการลงทะเบียน');
    }
    
    $registrationId = $data['registration_id'];
    
    $db = new Database();
    $conn = $db->getConnection();
    
    // ดึงข้อมูลการลงทะเบียน
    $stmt = $conn->prepare("
        SELECT * FROM registrations 
        WHERE id = ?
    ");
    $stmt->execute([$registrationId]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registration) {
        throw new Exception('ไม่พบข้อมูลการลงทะเบียน');
    }
    
    // ดึงข้อมูลผู้ลงทะเบียนในกลุ่มเดียวกัน
    $groupRegistrations = [];
    if (!empty($registration['registration_group'])) {
        $groupStmt = $conn->prepare("
            SELECT * FROM registrations
            WHERE registration_group = ?
            ORDER BY id ASC
        ");
        $groupStmt->execute([$registration['registration_group']]);
        $groupRegistrations = $groupStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ดึงข้อมูลที่อยู่
    $stmt = $conn->prepare("
        SELECT ra.*, 
               p.name_in_thai as province_name, 
               d.name_in_thai as district_name, 
               sd.name_in_thai as subdistrict_name
        FROM registration_addresses ra
        LEFT JOIN provinces p ON ra.province_id = p.id
        LEFT JOIN districts d ON ra.district_id = d.id
        LEFT JOIN subdistricts sd ON ra.subdistrict_id = sd.id
        WHERE ra.registration_id = ?
    ");
    $stmt->execute([$registrationId]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ดึงข้อมูลเอกสาร
    $stmt = $conn->prepare("
        SELECT * FROM registration_documents
        WHERE registration_id = ?
    ");
    $stmt->execute([$registrationId]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ดึงข้อมูลหลักฐานการชำระเงิน
    $stmt = $conn->prepare("
        SELECT * FROM registration_files
        WHERE registration_id = ?
    ");
    $stmt->execute([$registrationId]);
    $paymentFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ส่งข้อมูลกลับ
    echo json_encode([
        'success' => true,
        'registration' => $registration,
        'group_registrations' => $groupRegistrations,
        'addresses' => $addresses,
        'documents' => $documents,
        'payment_files' => $paymentFiles
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}