

<?php
/**
 * Script to insert data into district table
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

echo "Connected to database successfully\n";

// เตรียมคำสั่ง SQL สำหรับการเพิ่มข้อมูล
$sql = "INSERT INTO `district` VALUES ('โพนสว่าง', 460, 31, 3)";

// ดำเนินการ query
if ($conn->query($sql) === TRUE) {
    echo "เพิ่มข้อมูลลงในตาราง district เรียบร้อยแล้ว";
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>