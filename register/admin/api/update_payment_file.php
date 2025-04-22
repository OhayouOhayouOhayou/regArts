<?php
require_once '../check_auth.php';
require_once '../../config/database.php';

// Create database connection
$database = new Database();
$pdo = $database->getConnection();

// Initialize response
$response = [
    'success' => false,
    'message' => 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ'
];

// Check if user is authenticated
if (!isset($_SESSION['admin_id'])) {
    $response['message'] = 'ไม่ได้รับอนุญาต กรุณาเข้าสู่ระบบ';
    echo json_encode($response);
    exit;
}

// Get action type
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Process based on action
if ($action === 'delete') {
    // Delete file
    if (!isset($_POST['file_id']) || !is_numeric($_POST['file_id'])) {
        $response['message'] = 'ไม่พบรหัสไฟล์';
        echo json_encode($response);
        exit;
    }
    
    $file_id = intval($_POST['file_id']);
    
    // Get file path before deleting
    $stmt = $pdo->prepare("SELECT file_path FROM registration_files WHERE id = ?");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        $response['message'] = 'ไม่พบไฟล์ที่ต้องการลบ';
        echo json_encode($response);
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM registration_files WHERE id = ?");
        $result = $stmt->execute([$file_id]);
        
        if ($result) {
            // Delete physical file
            $file_path = "../../" . $file['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            $pdo->commit();
            
            $response['success'] = true;
            $response['message'] = 'ลบไฟล์เรียบร้อยแล้ว';
        } else {
            throw new Exception("ไม่สามารถลบข้อมูลไฟล์ได้");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $response['message'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
    
} elseif ($action === 'update' || $action === 'upload') {
    // Update or upload file
    if (!isset($_POST['registration_id']) || !is_numeric($_POST['registration_id'])) {
        $response['message'] = 'ไม่พบรหัสการลงทะเบียน';
        echo json_encode($response);
        exit;
    }
    
    $registration_id = intval($_POST['registration_id']);
    $file_id = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;
    
    // Check if file was uploaded
    if (!isset($_FILES['payment_file']) || $_FILES['payment_file']['error'] != 0) {
        $response['message'] = 'ไม่พบไฟล์ที่อัพโหลดหรือเกิดข้อผิดพลาดในการอัพโหลด';
        echo json_encode($response);
        exit;
    }
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $file = $_FILES['payment_file'];
    
    if (!in_array($file['type'], $allowed_types)) {
        $response['message'] = 'ประเภทไฟล์ไม่ถูกต้อง รองรับเฉพาะ JPG, JPEG, PNG, PDF เท่านั้น';
        echo json_encode($response);
        exit;
    }
    
    if ($file['size'] > $max_size) {
        $response['message'] = 'ขนาดไฟล์เกิน 5MB';
        echo json_encode($response);
        exit;
    }
    
    // Generate unique filename
    $timestamp = time();
    $unique_id = uniqid();
    $original_name = $file['name'];
    $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
    
    // Keep original filename if provided by removing any potentially harmful characters
    $safe_original_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $original_name);
    $filename = $timestamp . '_' . $unique_id . ($safe_original_name ? '_' . $safe_original_name : '.' . $file_ext);
    
    // Set upload path
    $upload_dir = '../../uploads/payment_slips/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_path = $upload_dir . $filename;
    $db_file_path = 'uploads/payment_slips/' . $filename;
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // If updating, get old file path and delete
        $old_file_path = null;
        if ($action === 'update' && $file_id > 0) {
            $stmt = $pdo->prepare("SELECT file_path FROM registration_files WHERE id = ?");
            $stmt->execute([$file_id]);
            $old_file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($old_file) {
                $old_file_path = "../../" . $old_file['file_path'];
            }
        }
        
        // Upload new file
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Update database
            if ($action === 'update' && $file_id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE registration_files 
                    SET file_name = ?, file_path = ?, file_type = ?, file_size = ?, uploaded_at = NOW()
                    WHERE id = ?
                ");
                $result = $stmt->execute([$original_name, $db_file_path, $file['type'], $file['size'], $file_id]);
            } else {
                // Insert new file
                $stmt = $pdo->prepare("
                    INSERT INTO registration_files (registration_id, file_name, file_path, file_type, file_size)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $result = $stmt->execute([$registration_id, $original_name, $db_file_path, $file['type'], $file['size']]);
            }
            
            if ($result) {
                // Delete old file if updating
                if ($old_file_path && file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
                
                $pdo->commit();
                
                $response['success'] = true;
                $response['message'] = ($action === 'update') ? 'อัพเดทไฟล์เรียบร้อยแล้ว' : 'อัพโหลดไฟล์เรียบร้อยแล้ว';
                $response['file_id'] = ($action === 'update') ? $file_id : $pdo->lastInsertId();
            } else {
                throw new Exception("ไม่สามารถบันทึกข้อมูลไฟล์ได้");
            }
        } else {
            throw new Exception("ไม่สามารถอัพโหลดไฟล์ได้");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        // Delete the newly uploaded file if exists
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $response['message'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'คำสั่งไม่ถูกต้อง';
}

// Return response
header('Content-Type: application/json');
echo json_encode($response);
?>