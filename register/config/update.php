<?php
/**
 * Script to update NULL registration_group values in the shared_db database
 * 
 * This script identifies records where registration_group is NULL and
 * assigns groups based on email addresses.
 */

// สร้าง connection ไปยังฐานข้อมูล (ปรับข้อมูลตามที่ใช้จริง)
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

// 1. ดึงรายการกลุ่มที่มีอยู่แล้วทั้งหมดเพื่อป้องกันการซ้ำซ้อน
$existing_groups = [];
$sql_get_groups = "SELECT DISTINCT registration_group FROM registrations WHERE registration_group IS NOT NULL";
$result = $conn->query($sql_get_groups);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $existing_groups[] = $row["registration_group"];
    }
}

echo "Found " . count($existing_groups) . " existing groups.\n";

// 2. ดึงข้อมูลกลุ่มที่มีอยู่แล้วของแต่ละอีเมล - แก้ไขคำสั่ง SQL เพื่อแก้ปัญหา only_full_group_by
$email_to_group = [];

// ใช้ subquery เพื่อหาค่า registration_group แรกของแต่ละอีเมล
$sql_find_groups = "
    SELECT email, 
           (SELECT registration_group 
            FROM registrations r2 
            WHERE r2.email = r1.email AND r2.registration_group IS NOT NULL 
            LIMIT 1) AS registration_group
    FROM registrations r1
    WHERE EXISTS (SELECT 1 
                 FROM registrations r3 
                 WHERE r3.email = r1.email AND r3.registration_group IS NOT NULL)
    GROUP BY email
";

// อีกวิธีหนึ่งที่อาจใช้ได้ (ถ้าวิธีบนไม่ทำงาน):
/*
$sql_find_groups = "
    SELECT DISTINCT r1.email, r1.registration_group
    FROM registrations r1
    INNER JOIN (
        SELECT email, MIN(id) AS min_id
        FROM registrations
        WHERE registration_group IS NOT NULL
        GROUP BY email
    ) r2 ON r1.email = r2.email AND r1.id = r2.min_id
    WHERE r1.registration_group IS NOT NULL
";
*/

$result = $conn->query($sql_find_groups);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $email = $row["email"];
        $group = $row["registration_group"];
        
        // กรณีที่ group อาจเป็น NULL จากการใช้ subquery
        if (!is_null($group)) {
            $email_to_group[$email] = $group;
        }
    }
}

echo "Found " . count($email_to_group) . " emails with existing groups.\n";

// 3. ค้นหาอีเมลที่มีค่า registration_group เป็น NULL
$emails_with_null = [];
$sql_null_emails = "
    SELECT DISTINCT email 
    FROM registrations 
    WHERE registration_group IS NULL
";

$result = $conn->query($sql_null_emails);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $emails_with_null[] = $row["email"];
    }
}

echo "Found " . count($emails_with_null) . " unique emails with NULL registration_group.\n";

// 4. ประมวลผลแต่ละอีเมลที่มีค่า NULL
$updates = 0;

foreach ($emails_with_null as $email) {
    // ตรวจสอบว่าอีเมลนี้มีกลุ่มอยู่แล้วหรือไม่
    if (isset($email_to_group[$email])) {
        $group = $email_to_group[$email];
        echo "Using existing group '{$group}' for email '{$email}'\n";
    } else {
        // สร้างกลุ่มใหม่ที่ไม่ซ้ำกับกลุ่มที่มีอยู่แล้ว
        do {
            $group = "group_" . bin2hex(random_bytes(6));
        } while (in_array($group, $existing_groups));
        
        // เพิ่มกลุ่มใหม่ลงในรายการกลุ่มที่มีอยู่แล้ว
        $existing_groups[] = $group;
        echo "Created new group '{$group}' for email '{$email}'\n";
    }
    
    // อัปเดตทุกรายการสำหรับอีเมลนี้
    $update_sql = "
        UPDATE registrations 
        SET registration_group = ? 
        WHERE email = ? AND registration_group IS NULL
    ";
    
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ss", $group, $email);
    $stmt->execute();
    
    $affected = $stmt->affected_rows;
    if ($affected > 0) {
        $updates += $affected;
        echo "Updated {$affected} records for email '{$email}' with group '{$group}'\n";
    }
    
    $stmt->close();
}

echo "Total updates: {$updates}\n";

// 5. ตรวจสอบว่ายังมี NULL registration_group เหลืออยู่หรือไม่
$sql_verify = "SELECT COUNT(*) as count FROM registrations WHERE registration_group IS NULL";
$result = $conn->query($sql_verify);
$row = $result->fetch_assoc();

if ($row["count"] > 0) {
    echo "\nWarning: {$row["count"]} records still have NULL registration_group values.\n";
} else {
    echo "\nSuccess: All registration_group values have been updated.\n";
}

$conn->close();
?>