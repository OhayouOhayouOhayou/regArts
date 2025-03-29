<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// ตรวจสอบว่ามีการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: change_password.php');
    exit;
}

$admin_id = $_SESSION['admin_id'];
$forceChange = isset($_SESSION['password_change_required']) && $_SESSION['password_change_required'];

// รับข้อมูลจากฟอร์ม
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// ถ้าไม่ใช่การบังคับเปลี่ยนรหัสผ่าน ต้องตรวจสอบรหัสผ่านปัจจุบัน
if (!$forceChange && empty($current_password)) {
    $_SESSION['password_error'] = 'กรุณากรอกรหัสผ่านปัจจุบัน';
    header('Location: change_password.php');
    exit;
}

// ตรวจสอบรหัสผ่านใหม่
if (empty($new_password)) {
    $_SESSION['password_error'] = 'กรุณากรอกรหัสผ่านใหม่';
    header('Location: change_password.php');
    exit;
}

// ตรวจสอบการยืนยันรหัสผ่าน
if ($new_password !== $confirm_password) {
    $_SESSION['password_error'] = 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน';
    header('Location: change_password.php');
    exit;
}

// ตรวจสอบความซับซ้อนของรหัสผ่าน
if (strlen($new_password) < 8) {
    $_SESSION['password_error'] = 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
    header('Location: change_password.php');
    exit;
}

// ตรวจสอบว่ามีตัวเลขและตัวอักษรผสมกัน
if (!preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
    $_SESSION['password_error'] = 'รหัสผ่านต้องประกอบด้วยตัวอักษรและตัวเลขอย่างน้อย 1 ตัว';
    header('Location: change_password.php');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // ดึงข้อมูลผู้ใช้
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('ไม่พบข้อมูลผู้ใช้');
    }
    
    // ถ้าไม่ใช่การบังคับเปลี่ยนรหัสผ่าน ต้องตรวจสอบรหัสผ่านปัจจุบัน
    if (!$forceChange && !password_verify($current_password, $user['password'])) {
        $_SESSION['password_error'] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
        header('Location: change_password.php');
        exit;
    }
    
    // ตรวจสอบว่ารหัสผ่านใหม่ไม่ซ้ำกับรหัสผ่านเดิม
    if (!$forceChange && password_verify($new_password, $user['password'])) {
        $_SESSION['password_error'] = 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม';
        header('Location: change_password.php');
        exit;
    }
    
    // เข้ารหัสรหัสผ่านใหม่
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // อัปเดตรหัสผ่านในฐานข้อมูล
    $stmt = $conn->prepare("UPDATE admin_users SET password = ?, password_change_required = 0, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$hashed_password, $admin_id]);
    
    // บันทึกกิจกรรมการเปลี่ยนรหัสผ่าน
    logActivity($conn, $admin_id, 'update', 'admin_users', $admin_id, 'Changed password');
    
    // ลบค่า session สำหรับการเปลี่ยนรหัสผ่าน
    unset($_SESSION['password_change_required']);
    
    $_SESSION['password_success'] = 'เปลี่ยนรหัสผ่านสำเร็จ';
    
    // หากเป็นการบังคับเปลี่ยนรหัสผ่าน ให้ redirect ไปหน้า dashboard
    if ($forceChange) {
        header('Location: dashboard.php');
    } else {
        header('Location: change_password.php');
    }
    exit;
} catch (Exception $e) {
    $_SESSION['password_error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: change_password.php');
    exit;
}
?>