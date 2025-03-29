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

// รับพารามิเตอร์จาก URL
$province_id = isset($_GET['province_id']) ? (int)$_GET['province_id'] : 0;

if (!$province_id) {
    echo json_encode(['status' => 'error', 'message' => 'ต้องระบุรหัสจังหวัด']);
    exit;
}

try {
    // ดึงข้อมูลอำเภอตามรหัสจังหวัด
    $stmt = $conn->prepare("SELECT id, name_in_thai, name_in_english FROM districts WHERE province_id = :province_id ORDER BY name_in_thai");
    $stmt->bindValue(':province_id', $province_id, PDO::PARAM_INT);
    $stmt->execute();
    $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $districts
    ]);
} catch(PDOException $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>