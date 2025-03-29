<?php
// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// ตรวจสอบเวลาที่ล็อกอิน (session timeout)
$session_lifetime = 7200; // 2 ชั่วโมง
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $session_lifetime)) {
    // ล้าง session
    session_unset();
    session_destroy();
    
    // สร้าง session ใหม่เพื่อเก็บข้อความแจ้งเตือน
    session_start();
    $_SESSION['login_error'] = 'เซสชันหมดอายุ กรุณาเข้าสู่ระบบอีกครั้ง';
    
    header('Location: login.php');
    exit;
}

// อัปเดตเวลาล็อกอิน
$_SESSION['login_time'] = time();

// ตรวจสอบว่าต้องเปลี่ยนรหัสผ่านหรือไม่ และไม่ใช่หน้าเปลี่ยนรหัสผ่าน
if (isset($_SESSION['password_change_required']) && $_SESSION['password_change_required'] && basename($_SERVER['PHP_SELF']) !== 'change_password.php') {
    header('Location: change_password.php');
    exit;
}
?>