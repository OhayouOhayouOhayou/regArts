<?php
session_start();

// ตรวจสอบการส่งข้อมูล
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// ตรวจสอบความถูกต้องของข้อมูล
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// ตรวจสอบ username และ password
if ($username === 'admin' && $password === 'admin') {
    // สร้าง session สำหรับผู้ดูแลระบบ
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_name'] = 'ผู้ดูแลระบบ';
    $_SESSION['admin_role'] = 'administrator';
    
    // บันทึกเวลาเข้าสู่ระบบ
    $_SESSION['login_time'] = time();
    
    // Redirect ไปยังหน้า Dashboard
    header('Location: dashboard.php');
    exit;
} else {
    // กรณีข้อมูลไม่ถูกต้อง
    $_SESSION['login_error'] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    header('Location: login.php');
    exit;
}