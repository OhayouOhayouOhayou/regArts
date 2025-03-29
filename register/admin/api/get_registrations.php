<?php
// ตั้งค่า header
header('Content-Type: application/json');

// ข้อมูลการเชื่อมต่อฐานข้อมูลที่ถูกต้องสำหรับ Docker
try {
    $conn = new PDO(
        "mysql:host=mysql;port=3306;dbname=shared_db",
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

// สร้าง SQL หลัก - ใช้แบบง่ายที่สุดก่อน
$sql = "SELECT * FROM registrations WHERE 1=1";

// สร้างเงื่อนไขและพารามิเตอร์สำหรับ Prepared Statement
$conditions = [];
$params = [];

if ($province) {
    $conditions[] = "province_id = :province";
    $params[':province'] = $province;
}
if ($firstName) {
    $conditions[] = "fullname LIKE :firstName";
    $params[':firstName'] = "%$firstName%";
}
if ($phone) {
    $conditions[] = "phone LIKE :phone";
    $params[':phone'] = "%$phone%";
}
if ($status) {
    $conditions[] = "payment_status = :status";
    $params[':status'] = $status;
}
if ($search) {
    $conditions[] = "(fullname LIKE :search OR email LIKE :search OR phone LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

try {
    // นับจำนวนข้อมูลทั้งหมด
    $countSql = "SELECT COUNT(*) as total FROM registrations WHERE 1=1";
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
    $sql .= " ORDER BY created_at DESC LIMIT :offset, :limit";
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