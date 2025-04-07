<?php
session_start();
require_once 'config.php'; // Ensure you have a config file with DB connection details

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Sanitize input data
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    header('Location: login.php');
    exit;
}

try {
    // Connect to database
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Prepare and execute query
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // Authentication successful
        
        // Log the login action
        $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action_type, ip_address, user_agent) 
                                   VALUES (:admin_id, 'login', :ip_address, :user_agent)");
        $logStmt->bindParam(':admin_id', $user['id']);
        $logStmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
        $logStmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
        $logStmt->execute();
        
        // Check if password change is required
        if ($user['password_change_required'] == 1) {
            // Store minimal user data for password change
            $_SESSION['temp_user_id'] = $user['id'];
            $_SESSION['temp_username'] = $user['username'];
            $_SESSION['password_change_required'] = true;
            
            // Redirect to password change page
            header('Location: change_password.php');
            exit;
        }
        
        // Normal login - set session variables
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_display_name'] = $user['display_name'];
        $_SESSION['admin_role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        // Redirect to dashboard
        header('Location: dashboard.php');
        exit;
    } else {
        // Authentication failed
        $_SESSION['login_error'] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    // Log the error (but don't show database details to users)
    error_log("Login error: " . $e->getMessage());
    $_SESSION['login_error'] = 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ กรุณาลองใหม่อีกครั้ง';
    header('Location: login.php');
    exit;
}