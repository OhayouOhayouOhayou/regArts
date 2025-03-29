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
    $conn = $db->getConnection();
    
    // ค้นหาผู้ใช้จากฐานข้อมูล
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ? AND status = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ตรวจสอบว่าพบผู้ใช้หรือไม่ และรหัสผ่านตรงกันหรือไม่
    if ($user && password_verify($password, $user['password'])) {
        // สร้าง session สำหรับผู้ดูแลระบบ
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_name'] = $user['display_name'];
        $_SESSION['admin_role'] = $user['role'];
        $_SESSION['admin_username'] = $user['username'];
        
        // บันทึกเวลาเข้าสู่ระบบ
        $_SESSION['login_time'] = time();
        
        // บันทึกการเข้าสู่ระบบลงใน logs
        logActivity($conn, $user['id'], 'login', null, null, 'User logged in');
        
        // อัปเดตเวลาล็อกอินล่าสุด
        $stmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // ตรวจสอบว่าต้องเปลี่ยนรหัสผ่านหรือไม่
        if ($user['password_change_required']) {
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
    $_SESSION['login_error'] = 'เกิดข้อผิดพลาดในการเชื่อมต่อกับฐานข้อมูล';
    header('Location: login.php');
    exit;
}
?>