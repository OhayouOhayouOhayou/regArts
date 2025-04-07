<?php
session_start();

// Verify this is accessed only after authentication with password change required
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['password_change_required'])) {
    header('Location: login.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/database.php';
    
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (strlen($new_password) < 8) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
    } elseif ($new_password !== $confirm_password) {
        $error = 'รหัสผ่านไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง';
    } else {
        // Initialize database connection
        $db = new Database();
        if ($db->error) {
            $error = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้';
        } else {
            $conn = $db->getConnection();
            
            try {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update user record
                $stmt = $conn->prepare("UPDATE admin_users SET password = :password, password_change_required = 0 
                                       WHERE id = :user_id");
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':user_id', $_SESSION['temp_user_id']);
                $stmt->execute();
                
                // Log the password change
                $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action_type, details, ip_address, user_agent) 
                                         VALUES (:admin_id, 'update', 'เปลี่ยนรหัสผ่าน', :ip_address, :user_agent)");
                $logStmt->bindParam(':admin_id', $_SESSION['temp_user_id']);
                $logStmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
                $logStmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
                $logStmt->execute();
                
                // Get complete user data
                $userStmt = $conn->prepare("SELECT * FROM admin_users WHERE id = :user_id");
                $userStmt->bindParam(':user_id', $_SESSION['temp_user_id']);
                $userStmt->execute();
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                
                // Update session
                unset($_SESSION['temp_user_id']);
                unset($_SESSION['temp_username']);
                unset($_SESSION['password_change_required']);
                
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_display_name'] = $user['display_name'];
                $_SESSION['admin_role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Success message and redirect
                $_SESSION['success_message'] = 'เปลี่ยนรหัสผ่านสำเร็จ';
                header('Location: dashboard.php');
                exit;
            } catch (PDOException $e) {
                error_log("Password change error: " . $e->getMessage());
                $error = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน กรุณาลองใหม่อีกครั้ง';
            }
        }
    }
}
?>
<!DOCTYPE html>
<!-- [HTML content remains unchanged] -->
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เปลี่ยนรหัสผ่าน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Sarabun", serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2C3E50, #3498DB);
        }
        .password-card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 450px;
        }
        .password-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .form-floating {
            margin-bottom: 1rem;
        }
        .btn-submit {
            background: #3498DB;
            border: none;
            padding: 0.8rem;
            font-size: 1.1rem;
        }
        .password-info {
            margin: 1.5rem 0;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            border-left: 4px solid #3498DB;
        }
    </style>
</head>
<body>
    <div class="password-card">
        <div class="password-header">
            <h4>เปลี่ยนรหัสผ่าน</h4>
            <p class="text-muted">กรุณาเปลี่ยนรหัสผ่านเพื่อความปลอดภัย</p>
        </div>
        
        <div class="password-info">
            <p class="mb-0"><i class="fas fa-info-circle me-2"></i> รหัสผ่านของคุณต้องเปลี่ยนก่อนเข้าใช้งานระบบ</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="change_password.php" method="POST">
            <div class="form-floating">
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['temp_username']); ?>" disabled>
                <label>ชื่อผู้ใช้</label>
            </div>
            
            <div class="form-floating">
                <input type="password" class="form-control" id="new_password" name="new_password" 
                       placeholder="รหัสผ่านใหม่" required>
                <label for="new_password">รหัสผ่านใหม่</label>
            </div>
            
            <div class="form-floating">
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                       placeholder="ยืนยันรหัสผ่านใหม่" required>
                <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
            </div>
            
            <div class="mb-3">
                <ul class="text-muted small">
                    <li>รหัสผ่านควรมีความยาวอย่างน้อย 8 ตัวอักษร</li>
                    <li>ควรประกอบด้วยตัวอักษรพิมพ์ใหญ่ พิมพ์เล็ก ตัวเลข และอักขระพิเศษ</li>
                </ul>
            </div>

            <button type="submit" class="btn btn-primary btn-submit w-100">
                <i class="fas fa-key me-2"></i>
                เปลี่ยนรหัสผ่าน
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Basic password strength validation
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            // Add password strength indicator functionality here if desired
        });
    </script>
</body>
</html>