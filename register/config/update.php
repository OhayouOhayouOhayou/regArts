<?php
/**
 * Script to insert data into districts table
 */

$servername = "shared-mysql";  // ชื่อ container MySQL
$username = "dbuser";
$password = "dbpassword";
$dbname = "shared_db";

// ทำการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to database successfully<br>";

// เตรียมคำสั่ง SQL สำหรับการเพิ่มข้อมูล
// ต้องระบุให้ถูกต้องตามโครงสร้างตาราง (ไม่รวม id ที่เป็น auto_increment)
$sql = "INSERT INTO `districts` (code, name_in_thai, name_in_english, province_id) 
        VALUES (460, 'โพนสว่าง', 'Phon Sawang', 31)";

// ดำเนินการ query
if ($conn->query($sql) === TRUE) {
    echo "เพิ่มข้อมูลลงในตาราง districts เรียบร้อยแล้ว";
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>