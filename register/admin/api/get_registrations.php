<?php
require_once 'check_auth.php'; // ตรวจสอบสิทธิ์ผู้ใช้ (ถ้ามี)
require_once '../../config/database.php'; // ดึงไฟล์ config database

// สร้างอ็อบเจ็กต์ Database และเชื่อมต่อ
$database = new Database();
$conn = $database->getConnection();

// ตรวจสอบว่าเชื่อมต่อสำเร็จหรือไม่
if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $database->error]);
    exit;
}

header('Content-Type: application/json');

// รับพารามิเตอร์จากคำขอ
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

// รับพารามิเตอร์สำหรับฟิลเตอร์
$province = $_GET['province'] ?? '';
$firstName = $_GET['firstName'] ?? '';
$status = $_GET['status'] ?? '';

// สร้าง SQL หลัก
$sql = "SELECT r.*, p.name_in_thai AS province_name, d.name_in_thai AS district_name, s.name_in_thai AS subdistrict_name 
        FROM registrations r 
        LEFT JOIN provinces p ON r.province_id = p.id 
        LEFT JOIN districts d ON r.district_id = d.id 
        LEFT JOIN subdistricts s ON r.subdistrict_id = s.id 
        WHERE 1=1";

// สร้างเงื่อนไขและพารามิเตอร์สำหรับ Prepared Statement
$conditions = [];
$params = [];

if ($province) {
    $conditions[] = "r.province_id = :province";
    $params[':province'] = $province;
}
if ($firstName) {
    $conditions[] = "r.fullname LIKE :firstName";
    $params[':firstName'] = "%$firstName%";
}
if ($status) {
    $conditions[] = "r.payment_status = :status";
    $params[':status'] = $status;
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// นับจำนวนข้อมูลทั้งหมด
$countSql = "SELECT COUNT(*) as total FROM registrations r WHERE 1=1";
if (!empty($conditions)) {
    $countSql .= " AND " . implode(" AND ", $conditions);
}

$countStmt = $conn->prepare($countSql);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$total = $countStmt->fetchColumn(); // ใช้ fetchColumn แทน fetch_assoc

// เพิ่มการแบ่งหน้า
$sql .= " LIMIT :offset, :limit";
$params[':offset'] = $offset;
$params[':limit'] = $limit;

// รัน SQL ด้วย Prepared Statement
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC); // ใช้ fetchAll แทน fetch_assoc

// ส่งผลลัพธ์กลับในรูปแบบ JSON
echo json_encode([
    'status' => 'success',
    'data' => [
        'registrations' => $registrations,
        'total' => (int)$total
    ]
]);

// ไม่จำเป็นต้องปิดการเชื่อมต่อใน PDO
?>