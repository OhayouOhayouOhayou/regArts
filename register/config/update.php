<?php
/**
 * Script to insert data into subdistricts table
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
$sql = "INSERT INTO `subdistricts` (code, name_in_thai, name_in_english, latitude, longitude, district_id, zip_code) 
        VALUES (610509, 'หลุมเข้า', 'Lom Kung', 17.858, 102.700, 423, 61130)";

// ดำเนินการ query
if ($conn->query($sql) === TRUE) {
    echo "เพิ่มข้อมูลลงในตาราง subdistricts เรียบร้อยแล้ว";
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>