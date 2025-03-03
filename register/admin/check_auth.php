<?php
// Buffer all output to prevent headers issue
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['admin_id'])) {
    // กรณีไม่ได้ล็อกอิน ให้ redirect ไปหน้า login
    header('Location: login.php');
    exit;
}

// ตรวจสอบ session timeout (ตั้งไว้ที่ 30 นาที)
$timeout = 30 * 60; // 30 นาที
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $timeout)) {
    // ลบ session ทั้งหมด
    session_destroy();
    header('Location: login.php');
    exit;
}

// อัพเดทเวลาล่าสุดที่มีการใช้งาน
$_SESSION['login_time'] = time();


ob_end_flush();
?>