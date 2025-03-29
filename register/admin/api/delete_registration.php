<?php
// ตั้งค่า header ให้แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../check_auth.php'; // ตรวจสอบการล็อกอิน
require_once '../../config/database.php'; // ไฟล์เชื่อมต่อฐานข้อมูล

header('Content-Type: application/json');

// ดึงข้อมูล ID จาก POST
$raw_data = file_get_contents('php://input');
parse_str($raw_data, $post_data);

// ตรวจสอบทั้ง $_POST และข้อมูลที่เข้ามาในรูปแบบของ raw data
$id = isset($_POST['id']) ? intval($_POST['id']) : (isset($post_data['id']) ? intval($post_data['id']) : 0);

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสการลงทะเบียน']);
    exit;
}

// สร้างการเชื่อมต่อฐานข้อมูล
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // เริ่ม Transaction
    $conn->beginTransaction();
    
    // ลบข้อมูลที่เกี่ยวข้องก่อน
    // 1. ลบเอกสาร
    $stmt = $conn->prepare("DELETE FROM registration_documents WHERE registration_id = ?");
    $stmt->execute([$id]);
    
    // 2. ลบไฟล์การชำระเงิน
    $stmt = $conn->prepare("DELETE FROM registration_files WHERE registration_id = ?");
    $stmt->execute([$id]);
    
    // 3. ลบที่อยู่
    $stmt = $conn->prepare("DELETE FROM registration_addresses WHERE registration_id = ?");
    $stmt->execute([$id]);
    
    // 4. ลบข้อมูลหลักการลงทะเบียน
    $stmt = $conn->prepare("DELETE FROM registrations WHERE id = ?");
    $stmt->execute([$id]);
    
    // ยืนยัน Transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'ลบข้อมูลสำเร็จ']);
} catch (Exception $e) {
    // ยกเลิก Transaction ในกรณีเกิดข้อผิดพลาด
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>