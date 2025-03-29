<?php
session_start();
require_once 'includes/check_auth.php';

// ตรวจสอบว่ามีการบังคับเปลี่ยนรหัสผ่านหรือไม่
$forceChange = isset($_SESSION['password_change_required']) && $_SESSION['password_change_required'];

// หากไม่ได้บังคับเปลี่ยนรหัสผ่านและไม่ได้เลือกที่จะเปลี่ยนจากเมนู ให้ redirect ไปหน้า dashboard
if (!$forceChange && !isset($_GET['change'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เปลี่ยนรหัสผ่าน - ระบบจัดการการลงทะเบียน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ใช้สไตล์เดียวกับหน้า login */
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f5f7fa;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .form-control:focus, .form-select:focus {
            border-color: #3498DB;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        .change-password-container {
            max-width: 500px;
            margin: 5rem auto;
        }
        .password-requirements {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .password-requirements ul {
            padding-left: 1rem;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <?php if ($forceChange): ?>
    <div class="container change-password-container">
    <?php else: ?>
    <!-- Header and Sidebar should be included here for non-forced change -->
    <?php include 'includes/header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <div class="col-lg-10 main-content">
                <div class="page-header">
                    <h4>เปลี่ยนรหัสผ่าน</h4>
                    <p class="text-muted">กำหนดรหัสผ่านใหม่สำหรับบัญชีของคุณ</p>
                </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-key me-2"></i>
                <?php echo $forceChange ? 'กรุณาเปลี่ยนรหัสผ่านของคุณ' : 'เปลี่ยนรหัสผ่าน'; ?>
            </h5>
        </div>
        <div class="card-body">
            <?php if ($forceChange): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    นี่เป็นการเข้าสู่ระบบครั้งแรกของคุณหรือรหัสผ่านของคุณถูกรีเซ็ต กรุณาเปลี่ยนรหัสผ่านเพื่อความปลอดภัย
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['password_error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                        echo $_SESSION['password_error'];
                        unset($_SESSION['password_error']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['password_success'])): ?>
                <div class="alert alert-success">
                    <?php 
                        echo $_SESSION['password_success'];
                        unset($_SESSION['password_success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <form action="process_change_password.php" method="post">
                <?php if (!$forceChange): ?>
                <div class="mb-3">
                    <label for="current_password" class="form-label">รหัสผ่านปัจจุบัน</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <label for="new_password" class="form-label">รหัสผ่านใหม่</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="password-requirements mb-4">
                    <p class="mb-1">รหัสผ่านต้อง:</p>
                    <ul>
                        <li>มีความยาวอย่างน้อย 8 ตัวอักษร</li>
                        <li>ประกอบด้วยตัวอักษรภาษาอังกฤษและตัวเลขอย่างน้อย 1 ตัว</li>
                        <li>ไม่เหมือนกับรหัสผ่านเดิม</li>
                    </ul>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>
                        บันทึกรหัสผ่านใหม่
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($forceChange): ?>
    </div> <!-- .container -->
    <?php else: ?>
            </div> <!-- .main-content -->
        </div> <!-- .row -->
    </div> <!-- .container-fluid -->
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>