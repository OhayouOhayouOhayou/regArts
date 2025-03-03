<?php
session_start();

// ลบ session ทั้งหมด
session_destroy();

// Redirect ไปหน้า login
header('Location: login.php');
exit;