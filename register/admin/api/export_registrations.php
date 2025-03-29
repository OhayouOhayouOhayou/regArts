<?php
// ตั้งค่า headers สำหรับการดาวน์โหลดไฟล์ CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="registrations_export.csv"');

// สร้างการเชื่อมต่อฐานข้อมูล
try {
    $conn = new PDO(
        "mysql:host=mysql;port=3306;dbname=shared_db",
        "dbuser", 
        "dbpassword",
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection error: " . $e->getMessage());
}

// รับพารามิเตอร์สำหรับฟิลเตอร์
$province = $_GET['province'] ?? '';
$district = $_GET['district'] ?? '';
$firstName = $_GET['firstName'] ?? '';
$lastName = $_GET['lastName'] ?? '';
$phone = $_GET['phone'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// สร้าง SQL หลัก
$sql = "SELECT r.*, 
               a.address, a.province_id, a.district_id, a.subdistrict_id, a.zipcode,
               p.name_in_thai AS province_name, 
               d.name_in_thai AS district_name, 
               s.name_in_thai AS subdistrict_name
        FROM registrations r 
        LEFT JOIN registration_addresses a ON r.id = a.registration_id
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
if ($district) {
    $conditions[] = "a.district_id = :district";
    $params[':district'] = $district;
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
} else if ($status === 'paid') {
    $conditions[] = "r.payment_status = 'paid'";
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

$sql .= " ORDER BY r.created_at DESC";

try {
    // รัน SQL ด้วย Prepared Statement
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    
    // เปิดไฟล์สำหรับเขียน
    $output = fopen('php://output', 'w');
    
    // เขียนหัวคอลัมน์ UTF-8 BOM (ช่วยให้ Excel อ่านภาษาไทยได้ถูกต้อง)
    fputs($output, "\xEF\xBB\xBF");
    
    // กำหนดหัวคอลัมน์
    fputcsv($output, [
        'วันที่ลงทะเบียน', 'ชื่อ-นามสกุล', 'หน่วยงาน', 'ตำแหน่ง', 'เบอร์โทร', 'อีเมล', 'ไลน์ไอดี', 
        'ที่อยู่', 'จังหวัด', 'อำเภอ', 'ตำบล', 'รหัสไปรษณีย์', 'สถานะการอนุมัติ', 'สถานะการชำระเงิน', 'วันที่ชำระเงิน'
    ]);
    
    // เขียนข้อมูล
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['created_at'],
            $row['fullname'],
            $row['organization'],
            $row['position'],
            $row['phone'],
            $row['email'],
            $row['line_id'],
            $row['address'] ?? '',
            $row['province_name'] ?? '',
            $row['district_name'] ?? '',
            $row['subdistrict_name'] ?? '',
            $row['zipcode'] ?? '',
            $row['is_approved'] == 1 ? 'อนุมัติแล้ว' : 'รอการอนุมัติ',
            $row['payment_status'] === 'paid' ? 'ชำระแล้ว' : 'ยังไม่ชำระ',
            $row['payment_date'] ?? ''
        ]);
    }
    
    // ปิดไฟล์
    fclose($output);
    exit;
} catch(Exception $e) {
    die("Error: " . $e->getMessage());
}
?>