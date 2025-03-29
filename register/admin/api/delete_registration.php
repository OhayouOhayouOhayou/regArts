<?php
require_once 'check_auth.php'; // ตรวจสอบการล็อกอิน
require_once '../../config/database.php'; // ไฟล์เชื่อมต่อฐานข้อมูล

header('Content-Type: application/json');

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสการลงทะเบียน']);
    exit;
}

$id = intval($_POST['id']);

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