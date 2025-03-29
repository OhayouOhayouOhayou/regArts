<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// ตรวจสอบการส่งข้อมูล
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// ตรวจสอบความถูกต้องของข้อมูล
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

try {
    $db = new Database();
    
    // ตรวจสอบว่ามีข้อผิดพลาดในการเชื่อมต่อหรือไม่
    if (isset($db->error)) {
        $_SESSION['login_error'] = 'เกิดข้อผิดพลาดในการเชื่อมต่อกับฐานข้อมูล: ' . $db->error;
        header('Location: login.php');
        exit;
    }
    
    $conn = $db->getConnection();
    
    // ตรวจสอบว่ามีตาราง admin_users หรือไม่
    $tableExists = false;
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE 'admin_users'");
        $tableExists = ($checkTable->rowCount() > 0);
    } catch (PDOException $e) {
        // ข้ามข้อผิดพลาดและยอมรับว่าตารางอาจจะไม่มีอยู่
    }
    
    if (!$tableExists) {
        $_SESSION['login_error'] = 'ระบบยังไม่ได้ตั้งค่าฐานข้อมูล กรุณาติดต่อผู้ดูแลระบบ';
        header('Location: login.php');
        exit;
    }
    
    // ตรวจสอบว่ามีคอลัมน์ที่จำเป็นหรือไม่
    $requiredColumns = ['id', 'username', 'password', 'display_name', 'role', 'password_change_required', 'status'];
    $missingColumns = [];
    
    try {
        $columnsResult = $conn->query("SHOW COLUMNS FROM admin_users");
        $existingColumns = [];
        
        while ($column = $columnsResult->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[] = $column['Field'];
        }
        
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $existingColumns)) {
                $missingColumns[] = $column;
            }
        }
    } catch (PDOException $e) {
        // ข้ามข้อผิดพลาด
    }
    
    if (!empty($missingColumns)) {
        $_SESSION['login_error'] = 'พบข้อผิดพลาดในโครงสร้างฐานข้อมูล: คอลัมน์ที่จำเป็นไม่มีในตาราง admin_users (' . implode(', ', $missingColumns) . ')';
        header('Location: login.php');
        exit;
    }
    
    // ค้นหาผู้ใช้จากฐานข้อมูล
    $query = "SELECT * FROM admin_users WHERE username = ?";
    
    // เพิ่มเงื่อนไข status ถ้ามีคอลัมน์นี้
    if (in_array('status', $existingColumns)) {
        $query .= " AND status = 1";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ตรวจสอบว่าพบผู้ใช้หรือไม่ และรหัสผ่านตรงกันหรือไม่
    if ($user && password_verify($password, $user['password'])) {
        // สร้าง session สำหรับผู้ดูแลระบบ
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        
        // เพิ่มข้อมูลที่ตรวจสอบว่ามีคอลัมน์อยู่จริง
        if (isset($user['display_name'])) {
            $_SESSION['admin_name'] = $user['display_name'];
        } else {
            $_SESSION['admin_name'] = $user['username']; // ใช้ username แทนถ้าไม่มี display_name
        }
        
        if (isset($user['role'])) {
            $_SESSION['admin_role'] = $user['role'];
        } else {
            $_SESSION['admin_role'] = 'staff'; // กำหนดค่าเริ่มต้น
        }
        
        // บันทึกเวลาเข้าสู่ระบบ
        $_SESSION['login_time'] = time();
        
        // บันทึกการเข้าสู่ระบบลงใน logs ถ้ามีฟังก์ชันนี้
        if (function_exists('logActivity')) {
            try {
                logActivity($conn, $user['id'], 'login', null, null, 'User logged in');
            } catch (Exception $e) {
                // ข้ามข้อผิดพลาดในการบันทึกกิจกรรม
            }
        }
        
        // อัปเดตเวลาล็อกอินล่าสุด ถ้ามีคอลัมน์ last_login
        if (in_array('last_login', $existingColumns)) {
            try {
                $stmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
            } catch (PDOException $e) {
                // ข้ามข้อผิดพลาดในการอัปเดต last_login
            }
        }
        
        // ตรวจสอบว่าต้องเปลี่ยนรหัสผ่านหรือไม่
        if (isset($user['password_change_required']) && $user['password_change_required']) {
            $_SESSION['password_change_required'] = true;
            header('Location: change_password.php');
            exit;
        }
        
        // Redirect ไปยังหน้า Dashboard
        header('Location: dashboard.php');
        exit;
    } else {
        // กรณีข้อมูลไม่ถูกต้อง
        $_SESSION['login_error'] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    // บันทึกข้อผิดพลาดที่เกิดขึ้นลงใน error log เพื่อการแก้ไขภายหลัง
    error_log('Login Error: ' . $e->getMessage());
    
    $_SESSION['login_error'] = 'เกิดข้อผิดพลาดในการเชื่อมต่อกับฐานข้อมูล';
    header('Location: login.php');
    exit;
}
?>