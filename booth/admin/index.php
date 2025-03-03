<?php
session_start();
require_once '../config.php';

// Check if already logged in
if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_username'])) {
    // User is already logged in, redirect to dashboard
    header("Location: dashboard.php");
    exit;
}

// Process login form
$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    } else {
        // Check credentials against database
        $stmt = $conn->prepare("SELECT id, username, password, name, role FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            // Option 1: Use direct comparison with fixed values for admin account
            if ($username === 'admin' && $password === 'admin123') {
                // Set session variables
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_name'] = $user['name'];
                $_SESSION['admin_role'] = $user['role'];
                
                // Redirect to dashboard
                header("Location: dashboard.php");
                exit;
            }
            // Option 2: Use password_verify for other accounts
            else if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_name'] = $user['name'];
                $_SESSION['admin_role'] = $user['role'];
                
                // Redirect to dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "รหัสผ่านไม่ถูกต้อง";
            }
        } else {
            $error = "ไม่พบชื่อผู้ใช้นี้ในระบบ";
        }
    }
}

// Get site title from settings
$siteTitle = "ระบบจองบูธขายสินค้า - หน้าผู้ดูแลระบบ";
$query = "SELECT setting_value FROM settings WHERE setting_key = 'site_title'";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $siteTitle = $row['setting_value'] . " - หน้าผู้ดูแลระบบ";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $siteTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f5f5f5;
            padding-top: 40px;
            padding-bottom: 40px;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .form-signin {
            width: 100%;
            max-width: 360px;
            padding: 15px;
            margin: auto;
        }
        
        .form-signin .form-floating:focus-within {
            z-index: 2;
        }
        
        .form-signin input[type="text"] {
            margin-bottom: -1px;
            border-bottom-right-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        .form-signin input[type="password"] {
            margin-bottom: 10px;
            border-top-left-radius: 0;
            border-top-right-radius: 0;
        }
        
        .login-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .admin-logo {
            width: 80px;
            height: 80px;
            background-color: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .admin-logo i {
            font-size: 40px;
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <main class="form-signin">
        <div class="login-card">
            <div class="admin-logo">
                <i class="bi bi-person-lock"></i>
            </div>
            <h1 class="h4 mb-3 fw-normal text-center">เข้าสู่ระบบผู้ดูแล</h1>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" placeholder="ชื่อผู้ใช้" required>
                    <label for="username">ชื่อผู้ใช้</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password" name="password" placeholder="รหัสผ่าน" required>
                    <label for="password">รหัสผ่าน</label>
                </div>
                
                <button class="w-100 btn btn-lg btn-primary" type="submit">เข้าสู่ระบบ</button>
            </form>
            
            <p class="mt-4 mb-0 text-muted text-center">
                <a href="../index.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left"></i> กลับไปหน้าหลัก
                </a>
            </p>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>