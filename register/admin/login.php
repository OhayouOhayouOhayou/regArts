<?php
session_start();

// หากมีการล็อกอินอยู่แล้ว ให้ redirect ไปหน้า dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบผู้ดูแล</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Sarabun", serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2C3E50, #3498DB);
            font-family: 'Sarabun', sans-serif;
        }
        .login-card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header img {
            height: 60px;
            margin-bottom: 1rem;
        }
        .form-floating {
            margin-bottom: 1rem;
        }
        .form-control:focus {
            border-color: #3498DB;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        .btn-login {
            background: #3498DB;
            border: none;
            padding: 0.8rem;
            font-size: 1.1rem;
        }
        .btn-login:hover {
            background: #2980B9;
        }
        .error-message {
            color: #E74C3C;
            margin-top: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <img src="https://arts.rmutsb.ac.th/image/logo_art_2019.png" alt="Logo">
            <h4>เข้าสู่ระบบผู้ดูแล</h4>
            <p class="text-muted">ระบบจัดการการลงทะเบียน</p>
        </div>
        
        <?php if (isset($_SESSION['login_error'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['login_error'];
                    unset($_SESSION['login_error']);
                ?>
            </div>
        <?php endif; ?>

        <form action="process_login.php" method="POST">
            <div class="form-floating">
                <input type="text" class="form-control" id="username" name="username" 
                       placeholder="ชื่อผู้ใช้" required>
                <label for="username">ชื่อผู้ใช้</label>
            </div>
            
            <div class="form-floating">
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="รหัสผ่าน" required>
                <label for="password">รหัสผ่าน</label>
            </div>

            <button type="submit" class="btn btn-primary btn-login w-100">
                <i class="fas fa-sign-in-alt me-2"></i>
                เข้าสู่ระบบ
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>