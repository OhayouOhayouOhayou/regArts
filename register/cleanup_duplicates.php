<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'cleanup.log');

require_once 'config/database.php';

/**
 * สคริปต์สำหรับทำความสะอาดข้อมูลลงทะเบียนที่ซ้ำซ้อน
 * ใช้ในกรณีที่มีข้อมูลซ้ำซ้อนในฐานข้อมูลแล้ว
 */
function cleanupDuplicates() {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        $conn->beginTransaction();
        
        echo "<h2>เริ่มต้นการทำความสะอาดข้อมูลที่ซ้ำซ้อน</h2>";
        error_log("เริ่มต้นการทำความสะอาดข้อมูลที่ซ้ำซ้อน");
        
        // 1. แสดงจำนวนรายการในตารางทั้งหมด (ก่อนลบ)
        $totalRegistrations = getTableCount($conn, 'registrations');
        $totalAddresses = getTableCount($conn, 'registration_addresses');
        
        echo "<p>จำนวนรายการก่อนทำความสะอาด:</p>";
        echo "<ul>";
        echo "<li>รายการลงทะเบียน: {$totalRegistrations} รายการ</li>";
        echo "<li>รายการที่อยู่: {$totalAddresses} รายการ</li>";
        echo "</ul>";
        
        // 2. ค้นหากลุ่มข้อมูลที่ซ้ำกัน
        echo "<h3>ค้นหารายการที่ซ้ำซ้อน...</h3>";
        
        $sql = "SELECT phone, fullname, COUNT(*) as count 
                FROM registrations 
                GROUP BY phone, fullname 
                HAVING COUNT(*) > 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>พบรายการซ้ำ: " . count($duplicates) . " กลุ่ม</p>";
        
        if (count($duplicates) === 0) {
            echo "<p>ไม่พบรายการซ้ำซ้อน ไม่จำเป็นต้องทำความสะอาด</p>";
            $conn->commit();
            return;
        }
        
        $removedRegistrations = 0;
        $removedAddresses = 0;
        
        // 3. แสดงและลบรายการซ้ำ
        echo "<h3>กำลังลบรายการซ้ำ...</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>เบอร์โทรศัพท์</th><th>ชื่อ-นามสกุล</th><th>จำนวนซ้ำ</th><th>ผลลัพธ์</th></tr>";
        
        foreach ($duplicates as $duplicate) {
            echo "<tr>";
            echo "<td>{$duplicate['phone']}</td>";
            echo "<td>{$duplicate['fullname']}</td>";
            echo "<td>{$duplicate['count']}</td>";
            
            // ดึงข้อมูลรายการซ้ำทั้งหมด
            $sql = "SELECT id FROM registrations 
                    WHERE phone = ? AND fullname = ? 
                    ORDER BY created_at ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$duplicate['phone'], $duplicate['fullname']]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // เก็บรายการแรกไว้ ลบรายการที่เหลือ
            $keepId = array_shift($ids);
            $deletedCount = 0;
            
            // ตรวจสอบว่ามีรายการเหลือให้ลบหรือไม่
            if (!empty($ids)) {
                // นับจำนวนที่อยู่ที่จะถูกลบ
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql = "SELECT COUNT(*) FROM registration_addresses WHERE registration_id IN ($placeholders)";
                $stmt = $conn->prepare($sql);
                $stmt->execute($ids);
                $addressesToDelete = $stmt->fetchColumn();
                $removedAddresses += $addressesToDelete;
                
                // ลบที่อยู่ที่เชื่อมโยงกับรายการที่จะลบ
                $sql = "DELETE FROM registration_addresses WHERE registration_id IN ($placeholders)";
                $stmt = $conn->prepare($sql);
                $stmt->execute($ids);
                
                // ลบรายการลงทะเบียนที่ซ้ำ
                $sql = "DELETE FROM registrations WHERE id IN ($placeholders)";
                $stmt = $conn->prepare($sql);
                $stmt->execute($ids);
                $deletedCount = $stmt->rowCount();
                $removedRegistrations += $deletedCount;
            }
            
            echo "<td>เก็บรายการ ID: {$keepId}, ลบ: {$deletedCount} รายการ</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // 4. แสดงสรุปผลการทำความสะอาด
        echo "<h3>ผลการทำความสะอาด</h3>";
        echo "<ul>";
        echo "<li>ลบรายการลงทะเบียนซ้ำ: {$removedRegistrations} รายการ</li>";
        echo "<li>ลบรายการที่อยู่: {$removedAddresses} รายการ</li>";
        echo "</ul>";
        
        // 5. แสดงจำนวนรายการที่เหลือหลังทำความสะอาด
        $remainingRegistrations = getTableCount($conn, 'registrations');
        $remainingAddresses = getTableCount($conn, 'registration_addresses');
        
        echo "<p>จำนวนรายการหลังทำความสะอาด:</p>";
        echo "<ul>";
        echo "<li>รายการลงทะเบียนคงเหลือ: {$remainingRegistrations} รายการ</li>";
        echo "<li>รายการที่อยู่คงเหลือ: {$remainingAddresses} รายการ</li>";
        echo "</ul>";
        
        // 6. ตรวจสอบรายการที่ไม่มีที่อยู่และแก้ไข
        fixRegistrationsWithoutAddress($conn);
        
        $conn->commit();
        
        echo "<h3>ทำความสะอาดข้อมูลเสร็จสมบูรณ์</h3>";
        echo "<p>ข้อมูลในฐานข้อมูลได้รับการทำความสะอาดเรียบร้อยแล้ว</p>";
        echo "<p><a href='index.php'>กลับไปยังหน้าหลัก</a></p>";
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo "<h3>เกิดข้อผิดพลาด</h3>";
        echo "<p>ข้อความผิดพลาด: " . $e->getMessage() . "</p>";
        error_log("เกิดข้อผิดพลาดในการทำความสะอาดข้อมูล: " . $e->getMessage());
    }
}

/**
 * ฟังก์ชันนับจำนวนรายการในตาราง
 */
function getTableCount($conn, $tableName) {
    $sql = "SELECT COUNT(*) FROM $tableName";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchColumn();
}

/**
 * ฟังก์ชันแก้ไขรายการที่ไม่มีที่อยู่
 */
function fixRegistrationsWithoutAddress($conn) {
    echo "<h3>ตรวจสอบและแก้ไขรายการที่ไม่มีที่อยู่...</h3>";
    
    // ค้นหารายการลงทะเบียนที่ไม่มีที่อยู่
    $sql = "SELECT r.id, r.registration_group
            FROM registrations r
            LEFT JOIN registration_addresses ra ON r.id = ra.registration_id
            WHERE ra.id IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $noAddressRegistrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($noAddressRegistrations) === 0) {
        echo "<p>ไม่พบรายการที่ไม่มีที่อยู่</p>";
        return;
    }
    
    echo "<p>พบรายการที่ไม่มีที่อยู่: " . count($noAddressRegistrations) . " รายการ</p>";
    
    // จัดกลุ่มตาม registration_group
    $groupedRegistrations = [];
    foreach ($noAddressRegistrations as $reg) {
        if (!isset($groupedRegistrations[$reg['registration_group']])) {
            $groupedRegistrations[$reg['registration_group']] = [];
        }
        $groupedRegistrations[$reg['registration_group']][] = $reg['id'];
    }
    
    $fixedCount = 0;
    
    // แก้ไขแต่ละกลุ่ม
    foreach ($groupedRegistrations as $group => $regIds) {
        // ค้นหาสมาชิกในกลุ่มที่มีที่อยู่
        $sql = "SELECT DISTINCT r.id
                FROM registrations r
                JOIN registration_addresses ra ON r.id = ra.registration_id
                WHERE r.registration_group = ?
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$group]);
        $sourceId = $stmt->fetchColumn();
        
        if ($sourceId) {
            // มีสมาชิกในกลุ่มที่มีที่อยู่ คัดลอกที่อยู่ให้กับรายการที่ไม่มี
            foreach ($regIds as $regId) {
                copyAddresses($conn, $sourceId, $regId);
                $fixedCount++;
            }
        } else {
            // ไม่มีสมาชิกในกลุ่มที่มีที่อยู่ ให้สร้างที่อยู่จากข้อมูลเริ่มต้น
            echo "<p class='warning'>ไม่สามารถคัดลอกที่อยู่ให้กับกลุ่ม {$group} เนื่องจากไม่มีสมาชิกใดในกลุ่มมีที่อยู่</p>";
        }
    }
    
    echo "<p>แก้ไขรายการที่ไม่มีที่อยู่แล้ว: {$fixedCount} รายการ</p>";
}

/**
 * ฟังก์ชันคัดลอกที่อยู่ระหว่างผู้สมัคร
 */
function copyAddresses($conn, $sourceRegId, $targetRegId) {
    // ดึงที่อยู่ของผู้สมัครต้นทาง
    $sql = "SELECT * FROM registration_addresses WHERE registration_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$sourceRegId]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ถ้าไม่มีที่อยู่ต้นทาง ไม่สามารถคัดลอกได้
    if (count($addresses) === 0) {
        return false;
    }
    
    // คัดลอกที่อยู่ให้กับผู้สมัครปลายทาง
    foreach ($addresses as $address) {
        $sql = "INSERT INTO registration_addresses (
                    registration_id, address_type, address,
                    province_id, district_id, subdistrict_id, zipcode
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $targetRegId,
            $address['address_type'],
            $address['address'],
            $address['province_id'],
            $address['district_id'],
            $address['subdistrict_id'],
            $address['zipcode']
        ]);
    }
    
    return true;
}

// HTML header for display
echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>ทำความสะอาดข้อมูลซ้ำซ้อน</title>
    <style>
        body { font-family: 'Sarabun', sans-serif; margin: 20px; line-height: 1.6; }
        h2 { color: #2C3E50; margin-top: 20px; }
        h3 { color: #3498DB; margin-top: 15px; }
        p { margin: 10px 0; }
        .warning { color: #E74C3C; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background-color: #2C3E50; color: white; padding: 10px; }
        td { padding: 8px; border: 1px solid #ddd; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        ul { margin: 10px 0; padding-left: 25px; }
        a { color: #3498DB; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>";

// Run cleanup
cleanupDuplicates();

// HTML footer
echo "</body></html>";