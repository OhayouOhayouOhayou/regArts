<?php
// ตั้งค่า header
header('Content-Type: application/json');

// สร้างการเชื่อมต่อฐานข้อมูลโดยตรง
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

// สร้าง SQL หลัก - ใช้ DISTINCT เพื่อป้องกันการแสดงผลซ้ำซ้อน
// กำหนด address_type เป็น 'invoice' เพื่อให้แสดงเฉพาะที่อยู่สำหรับออกใบเสร็จ
$sql = "SELECT DISTINCT r.id, r.title, r.title_other, r.fullname, r.organization, r.position, 
               r.phone, r.email, r.line_id, r.payment_status, r.is_approved, r.created_at,
               a.address, a.province_id, a.district_id, a.subdistrict_id, a.zipcode,
               p.name_in_thai AS province_name, 
               d.name_in_thai AS district_name, 
               s.name_in_thai AS subdistrict_name
        FROM registrations r 
        LEFT JOIN registration_addresses a ON r.id = a.registration_id AND a.address_type = 'invoice'
        LEFT JOIN provinces p ON a.province_id = p.id 
        LEFT JOIN districts d ON a.district_id = d.id 
        LEFT JOIN subdistricts s ON a.subdistrict_id = s.id 
        WHERE 1=1";

// สร้างเงื่อนไขและพารามิเตอร์สำหรับ Prepared Statement
$conditions = [];
$params = [];

if ($province) {
    $conditions[] = "a.province_id = :province";
    $params[':province'] = $province;
}
if ($firstName) {
    $conditions[] = "r.fullname LIKE :firstName";
    $params[':firstName'] = "%$firstName%";
}
if ($phone) {
    $conditions[] = "r.phone LIKE :phone";
    $params[':phone'] = "%$phone%";
}
if ($status === 'approved') {
    $conditions[] = "r.is_approved = 1";
} else if ($status === 'pending') {
    $conditions[] = "r.is_approved = 0";
} else if ($status === 'paid_approved') {
    $conditions[] = "r.payment_status IN ('paid', 'paid_onsite') AND r.is_approved = 1";
} else if ($status === 'paid_pending') {
    $conditions[] = "r.payment_status IN ('paid', 'paid_onsite') AND r.is_approved = 0";
} else if ($status === 'paid') {
    $conditions[] = "r.payment_status IN ('paid', 'paid_onsite')";
} else if ($status === 'not_paid') {
    $conditions[] = "r.payment_status = 'not_paid'";
}
if ($search) {
    $conditions[] = "(r.fullname LIKE :search OR r.email LIKE :search OR r.phone LIKE :search OR r.organization LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

try {
    // นับจำนวนข้อมูลทั้งหมด - ปรับปรุงให้ตรงกับการค้นหาหลัก
    $countSql = "SELECT COUNT(DISTINCT r.id) as total FROM registrations r";
    
    // ต้องเพิ่ม JOIN ถ้ามีเงื่อนไขที่เกี่ยวข้องกับตารางอื่น
    if ($province || in_array($status, ['approved', 'pending'])) {
        $countSql .= " LEFT JOIN registration_addresses a ON r.id = a.registration_id AND a.address_type = 'invoice'";
        
        if ($province) {
            $countSql .= " LEFT JOIN provinces p ON a.province_id = p.id";
            $countSql .= " LEFT JOIN districts d ON a.district_id = d.id";
            $countSql .= " LEFT JOIN subdistricts s ON a.subdistrict_id = s.id";
        }
    }
    
    $countSql .= " WHERE 1=1";
    
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