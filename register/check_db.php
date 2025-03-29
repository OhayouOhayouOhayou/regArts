<?php
// แสดงข้อผิดพลาดทั้งหมด
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ตั้งค่า header ให้แสดงผลเป็นข้อความ
header('Content-Type: text/plain');

echo "=== ตรวจสอบการเชื่อมต่อฐานข้อมูล ===\n\n";

// ทดลองเชื่อมต่อด้วย PDO
try {
    // แสดงค่าคงที่หรือตัวแปรที่ใช้ในการเชื่อมต่อ
    echo "กำลังพยายามเชื่อมต่อกับฐานข้อมูล (mysql)...\n";
    
    // ลองเชื่อมต่อด้วยชื่อบริการ Docker
    $conn = new PDO(
        "mysql:host=mysql;port=3306;dbname=shared_db",
        "dbuser", 
        "dbpassword",
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "เชื่อมต่อสำเร็จ!\n\n";
    
    echo "=== รายชื่อตารางทั้งหมด ===\n";
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    // เลือกตารางที่มีอยู่จริงและดูโครงสร้าง
    if (in_array('registrations', $tables)) {
        echo "\n=== โครงสร้างตาราง registrations ===\n";
        $stmt = $conn->query("DESCRIBE registrations");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "- {$column['Field']} ({$column['Type']})\n";
        }
        
        // ดูข้อมูลตัวอย่าง
        echo "\n=== ข้อมูลตัวอย่างในตาราง registrations (5 รายการแรก) ===\n";
        $stmt = $conn->query("SELECT * FROM registrations LIMIT 5");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($data as $index => $row) {
            echo "\nรายการที่ " . ($index + 1) . ":\n";
            foreach ($row as $key => $value) {
                echo "  $key: $value\n";
            }
        }
    }
    
    if (in_array('provinces', $tables)) {
        echo "\n=== โครงสร้างตาราง provinces ===\n";
        $stmt = $conn->query("DESCRIBE provinces");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "- {$column['Field']} ({$column['Type']})\n";
        }
    }
    
    if (in_array('districts', $tables)) {
        echo "\n=== โครงสร้างตาราง districts ===\n";
        $stmt = $conn->query("DESCRIBE districts");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "- {$column['Field']} ({$column['Type']})\n";
        }
    }
    
    if (in_array('subdistricts', $tables)) {
        echo "\n=== โครงสร้างตาราง subdistricts ===\n";
        $stmt = $conn->query("DESCRIBE subdistricts");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "- {$column['Field']} ({$column['Type']})\n";
        }
    }
    
} catch(PDOException $e) {
    echo "ข้อผิดพลาดในการเชื่อมต่อ: " . $e->getMessage() . "\n";
    
    // แสดงข้อมูลเพิ่มเติมเพื่อการแก้ไขปัญหา
    echo "\nลองเชื่อมต่อด้วยพารามิเตอร์อื่น:\n";
    
    // ลองเชื่อมต่อกับ mysql (ชื่อบริการ Docker)
    try {
        echo "ทดสอบเชื่อมต่อกับ host=mysql... ";
        new PDO("mysql:host=mysql;dbname=shared_db", "dbuser", "dbpassword");
        echo "สำเร็จ!\n";
    } catch(PDOException $e) {
        echo "ล้มเหลว: " . $e->getMessage() . "\n";
    }
    
    // ลองเชื่อมต่อกับ mysql-container (ชื่อคอนเทนเนอร์)
    try {
        echo "ทดสอบเชื่อมต่อกับ host=shared-mysql... ";
        new PDO("mysql:host=shared-mysql;dbname=shared_db", "dbuser", "dbpassword");
        echo "สำเร็จ!\n";
    } catch(PDOException $e) {
        echo "ล้มเหลว: " . $e->getMessage() . "\n";
    }
    
    // ลองเชื่อมต่อกับพอร์ตที่แมปออกมา
    try {
        echo "ทดสอบเชื่อมต่อกับ host=localhost;port=3307... ";
        new PDO("mysql:host=localhost;port=3307;dbname=shared_db", "dbuser", "dbpassword");
        echo "สำเร็จ!\n";
    } catch(PDOException $e) {
        echo "ล้มเหลว: " . $e->getMessage() . "\n";
    }
}
?>