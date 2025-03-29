<?php
// ตั้งค่า header
header('Content-Type: application/json');

// สร้างการเชื่อมต่อฐานข้อมูลโดยตรง
try {
    $conn = new PDO(
        "mysql:host=mysql;dbname=shared_db",
        "dbuser", 
        "dbpassword",
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// รับพารามิเตอร์จากคำขอ
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

// รับพารามิเตอร์สำหรับฟิลเตอร์
$province = $_GET['province'] ?? '';
$firstName = $_GET['firstName'] ?? '';
$lastName = $_GET['lastName'] ?? '';
$phone = $_GET['phone'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// สร้าง SQL หลักโดยไม่มี JOIN กับ subdistricts ที่มีปัญหา
$sql = "SELECT r.*, p.name_in_thai AS province_name, d.name_in_thai AS district_name
        FROM registrations r 
        LEFT JOIN provinces p ON r.province_id = p.id 
        LEFT JOIN districts d ON r.district_id = d.id 
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
if ($lastName) {
    $conditions[] = "r.fullname LIKE :lastName";
    $params[':lastName'] = "%$lastName%";
}
if ($phone) {
    $conditions[] = "r.phone LIKE :phone";
    $params[':phone'] = "%$phone%";
}
if ($status) {
    $conditions[] = "r.payment_status = :status";
    $params[':status'] = $status;
}
if ($search) {
    $conditions[] = "(r.fullname LIKE :search OR r.email LIKE :search OR r.phone LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

try {
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
    $total = $countStmt->fetchColumn();

    // เพิ่มการแบ่งหน้า
    $sql .= " ORDER BY r.created_at DESC LIMIT :offset, :limit";
    $params[':offset'] = (int)$offset;
    $params[':limit'] = (int)$limit;

    // รัน SQL ด้วย Prepared Statement
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ส่งผลลัพธ์กลับในรูปแบบ JSON
    echo json_encode([
        'status' => 'success',
        'data' => [
            'registrations' => $registrations,
            'total' => (int)$total
        ]
    ]);
} catch(PDOException $e) {
    // ส่งข้อความผิดพลาดกลับในรูปแบบ JSON
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>