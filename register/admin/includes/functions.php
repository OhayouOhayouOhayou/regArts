<?php
/**
 * บันทึกกิจกรรมของผู้ใช้ลงในตาราง admin_logs
 * 
 * @param PDO $conn การเชื่อมต่อฐานข้อมูล
 * @param int $admin_id รหัสผู้ดูแลระบบ
 * @param string $action_type ประเภทการกระทำ ('login', 'logout', 'insert', 'update', 'delete', 'view')
 * @param string|null $target_table ชื่อตารางที่ถูกกระทำ (ถ้ามี)
 * @param string|int|null $record_id รหัสของรายการที่ถูกกระทำ (ถ้ามี)
 * @param string|null $details รายละเอียดเพิ่มเติม (ถ้ามี)
 * @return bool สถานะการบันทึก
 */
function logActivity($conn, $admin_id, $action_type, $target_table = null, $record_id = null, $details = null) {
    try {
        // ตรวจสอบว่า $admin_id เป็นตัวเลขหรือไม่
        if (!is_numeric($admin_id)) {
            throw new Exception('Invalid admin_id');
        }
        
        // ตรวจสอบว่า $action_type เป็นค่าที่ถูกต้องหรือไม่
        $valid_actions = ['login', 'logout', 'insert', 'update', 'delete', 'view'];
        if (!in_array($action_type, $valid_actions)) {
            throw new Exception('Invalid action_type');
        }
        
        // รับ IP Address ของผู้ใช้
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // รับ User Agent ของผู้ใช้
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // เตรียม SQL สำหรับบันทึก log
        $sql = "INSERT INTO admin_logs (admin_id, action_type, target_table, record_id, details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        return $stmt->execute([
            $admin_id, 
            $action_type, 
            $target_table, 
            $record_id, 
            $details, 
            $ip_address, 
            $user_agent
        ]);
    } catch (Exception $e) {
        error_log('Error logging activity: ' . $e->getMessage());
        return false;
    }
}

/**
 * บันทึกการเพิ่มข้อมูลในตาราง
 * 
 * @param PDO $conn การเชื่อมต่อฐานข้อมูล
 * @param string $table ชื่อตารางที่เพิ่มข้อมูล
 * @param int|string $record_id รหัสของรายการที่เพิ่ม
 * @param string $details รายละเอียดการเพิ่มข้อมูล
 * @return bool สถานะการบันทึก
 */
function logInsert($conn, $table, $record_id, $details = null) {
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    return logActivity($conn, $_SESSION['admin_id'], 'insert', $table, $record_id, $details);
}

/**
 * บันทึกการแก้ไขข้อมูลในตาราง
 * 
 * @param PDO $conn การเชื่อมต่อฐานข้อมูล
 * @param string $table ชื่อตารางที่แก้ไขข้อมูล
 * @param int|string $record_id รหัสของรายการที่แก้ไข
 * @param string $details รายละเอียดการแก้ไขข้อมูล
 * @return bool สถานะการบันทึก
 */
function logUpdate($conn, $table, $record_id, $details = null) {
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    return logActivity($conn, $_SESSION['admin_id'], 'update', $table, $record_id, $details);
}

/**
 * บันทึกการลบข้อมูลในตาราง
 * 
 * @param PDO $conn การเชื่อมต่อฐานข้อมูล
 * @param string $table ชื่อตารางที่ลบข้อมูล
 * @param int|string $record_id รหัสของรายการที่ลบ
 * @param string $details รายละเอียดการลบข้อมูล
 * @return bool สถานะการบันทึก
 */
function logDelete($conn, $table, $record_id, $details = null) {
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    return logActivity($conn, $_SESSION['admin_id'], 'delete', $table, $record_id, $details);
}

/**
 * บันทึกการดูข้อมูลในตาราง
 * 
 * @param PDO $conn การเชื่อมต่อฐานข้อมูล
 * @param string $table ชื่อตารางที่ดูข้อมูล
 * @param int|string $record_id รหัสของรายการที่ดู (ถ้ามี)
 * @param string $details รายละเอียดการดูข้อมูล
 * @return bool สถานะการบันทึก
 */
function logView($conn, $table, $record_id = null, $details = null) {
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    return logActivity($conn, $_SESSION['admin_id'], 'view', $table, $record_id, $details);
}
?>