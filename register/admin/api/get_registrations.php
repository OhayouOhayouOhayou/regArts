<?php

require_once 'check_auth.php'; // ตรวจสอบสิทธิ์ผู้ใช้ (ถ้ามี)
require_once '../../config/database.php'; // ดึงการเชื่อมต่อฐานข้อมูล ($conn)

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
$types = '';

if ($province) {
    $conditions[] = "r.province_id = ?";
    $params[] = $province;
    $types .= 's'; // string
}
if ($firstName) {
    $conditions[] = "r.fullname LIKE ?";
    $params[] = "%$firstName%";
    $types .= 's';
}
if ($status) {
    $conditions[] = "r.payment_status = ?";
    $params[] = $status;
    $types .= 's';
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
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$total = $countResult->fetch_assoc()['total'];

// เพิ่มการแบ่งหน้า
$sql .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types .= 'ii'; // integer for offset and limit

// รัน SQL ด้วย Prepared Statement
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Query failed: ' . $conn->error]);
    exit;
}

$registrations = [];
while ($row = $result->fetch_assoc()) {
    $registrations[] = $row;
}

// ส่งผลลัพธ์กลับในรูปแบบ JSON
echo json_encode([
    'status' => 'success',
    'data' => [
        'registrations' => $registrations,
        'total' => (int)$total
    ]
]);

// ปิดการเชื่อมต่อ
$conn->close();
?>