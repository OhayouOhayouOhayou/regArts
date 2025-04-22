<?php
// เพิ่มการเปิดใช้งานการแสดงข้อผิดพลาดทั้งหมด
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// บันทึกข้อมูลที่ได้รับทั้งหมดลง error_log
error_log("API update_payment_file was called");
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

// ตรวจสอบการตั้งค่า PHP
error_log("PHP upload_max_filesize: " . ini_get('upload_max_filesize'));
error_log("PHP post_max_size: " . ini_get('post_max_size'));
error_log("PHP max_execution_time: " . ini_get('max_execution_time'));
error_log("PHP memory_limit: " . ini_get('memory_limit'));

require_once '../check_auth.php';
require_once '../../config/database.php';

// ตรวจสอบว่าโฟลเดอร์สำหรับอัพโหลดมีอยู่จริงหรือไม่
$upload_dir = '../../uploads/payment_slips/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        error_log("Failed to create directory: " . $upload_dir);
    } else {
        error_log("Created directory: " . $upload_dir);
    }
} else {
    error_log("Directory exists: " . $upload_dir);
    // ตรวจสอบสิทธิ์การเขียน
    if (is_writable($upload_dir)) {
        error_log("Directory is writable");
    } else {
        error_log("Directory is NOT writable");
    }
}

// Create database connection
$database = new Database();
$pdo = $database->getConnection();

// Initialize response
$response = [
    'success' => false,
    'message' => 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ'
];

// Get action type
$action = isset($_POST['action']) ? $_POST['action'] : '';
error_log("Action requested: " . $action);

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
                if (unlink($file_path)) {
                    error_log("File deleted successfully: " . $file_path);
                } else {
                    error_log("Failed to delete file: " . $file_path);
                }
            } else {
                error_log("File does not exist: " . $file_path);
            }
            
            $pdo->commit();
            
            $response['success'] = true;
            $response['message'] = 'ลบไฟล์เรียบร้อยแล้ว';
        } else {
            throw new Exception("ไม่สามารถลบข้อมูลไฟล์ได้");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Exception during delete: " . $e->getMessage());
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
    
    error_log("Processing " . $action . " for registration_id: " . $registration_id . " and file_id: " . $file_id);
    
    // Check if file was uploaded
    if (!isset($_FILES['payment_file'])) {
        error_log("No file uploaded - payment_file not found in FILES array");
        $response['message'] = 'ไม่พบไฟล์ที่อัพโหลด';
        echo json_encode($response);
        exit;
    }
    
    if ($_FILES['payment_file']['error'] != 0) {
        $upload_errors = array(
            UPLOAD_ERR_INI_SIZE => 'ไฟล์มีขนาดใหญ่เกินกว่าที่กำหนดในไฟล์ php.ini',
            UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินกว่าที่กำหนดในฟอร์ม',
            UPLOAD_ERR_PARTIAL => 'ไฟล์ถูกอัพโหลดเพียงบางส่วน',
            UPLOAD_ERR_NO_FILE => 'ไม่มีไฟล์ถูกอัพโหลด',
            UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราวสำหรับอัพโหลด',
            UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ลงดิสก์ได้',
            UPLOAD_ERR_EXTENSION => 'การอัพโหลดถูกหยุดโดย PHP extension'
        );
        
        $error_code = $_FILES['payment_file']['error'];
        error_log("Upload error code: " . $error_code);
        $error_message = isset($upload_errors[$error_code]) ? $upload_errors[$error_code] : 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุในการอัพโหลด';
        
        $response['message'] = 'เกิดข้อผิดพลาดในการอัพโหลด: ' . $error_message;
        echo json_encode($response);
        exit;
    }
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $file = $_FILES['payment_file'];
    
    if (!in_array($file['type'], $allowed_types)) {
        error_log("Invalid file type: " . $file['type']);
        $response['message'] = 'ประเภทไฟล์ไม่ถูกต้อง รองรับเฉพาะ JPG, JPEG, PNG, PDF เท่านั้น';
        echo json_encode($response);
        exit;
    }
    
    if ($file['size'] > $max_size) {
        error_log("File too large: " . $file['size'] . " bytes");
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
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Failed to create directory: " . $upload_dir);
            $response['message'] = 'ไม่สามารถสร้างโฟลเดอร์สำหรับเก็บไฟล์ได้';
            echo json_encode($response);
            exit;
        }
    }
    
    $file_path = $upload_dir . $filename;
    $db_file_path = 'uploads/payment_slips/' . $filename;
    
    error_log("Generated file path: " . $file_path);
    
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
                error_log("Old file path to be replaced: " . $old_file_path);
            }
        }
        
        // Check if tmp_name exists and is a valid uploaded file
        if (!is_uploaded_file($file['tmp_name'])) {
            error_log("Not a valid uploaded file: " . $file['tmp_name']);
            throw new Exception("ไม่พบไฟล์ที่อัพโหลดหรือไม่ใช่ไฟล์ที่อัพโหลดอย่างถูกต้อง");
        }
        
        // Upload new file - with additional error checking
        $upload_success = false;
        
        // Try to use move_uploaded_file first
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            error_log("File uploaded successfully using move_uploaded_file");
            $upload_success = true;
        } else {
            error_log("move_uploaded_file failed, trying file_put_contents");
            
            // If move_uploaded_file fails, try with file_put_contents
            if (is_readable($file['tmp_name'])) {
                $content = file_get_contents($file['tmp_name']);
                if ($content !== false && file_put_contents($file_path, $content) !== false) {
                    error_log("File uploaded successfully using file_put_contents");
                    $upload_success = true;
                } else {
                    error_log("file_put_contents failed");
                }
            } else {
                error_log("Cannot read temporary file: " . $file['tmp_name']);
            }
        }
        
        if (!$upload_success) {
            throw new Exception("ไม่สามารถอัพโหลดไฟล์ได้");
        }
        
        // Check if the file was actually written
        if (!file_exists($file_path)) {
            error_log("File does not exist after upload attempt: " . $file_path);
            throw new Exception("ไฟล์ไม่ถูกสร้างหลังจากอัพโหลด");
        }
        
        // Update database
        if ($action === 'update' && $file_id > 0) {
            $stmt = $pdo->prepare("
                UPDATE registration_files 
                SET file_name = ?, file_path = ?, file_type = ?, file_size = ?, uploaded_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$original_name, $db_file_path, $file['type'], $file['size'], $file_id]);
            error_log("Database update result: " . ($result ? "success" : "failed"));
        } else {
            // Insert new file
            $stmt = $pdo->prepare("
                INSERT INTO registration_files (registration_id, file_name, file_path, file_type, file_size)
                VALUES (?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([$registration_id, $original_name, $db_file_path, $file['type'], $file['size']]);
            error_log("Database insert result: " . ($result ? "success" : "failed"));
        }
        
        if ($result) {
            // Delete old file if updating
            if ($old_file_path && file_exists($old_file_path)) {
                if (unlink($old_file_path)) {
                    error_log("Old file deleted successfully: " . $old_file_path);
                } else {
                    error_log("Failed to delete old file: " . $old_file_path);
                }
            }
            
            $pdo->commit();
            
            $response['success'] = true;
            $response['message'] = ($action === 'update') ? 'อัพเดทไฟล์เรียบร้อยแล้ว' : 'อัพโหลดไฟล์เรียบร้อยแล้ว';
            $response['file_id'] = ($action === 'update') ? $file_id : $pdo->lastInsertId();
            error_log("Operation successful: " . $response['message']);
        } else {
            throw new Exception("ไม่สามารถบันทึกข้อมูลไฟล์ได้");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Exception during " . $action . ": " . $e->getMessage());
        // Delete the newly uploaded file if exists
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                error_log("Temporary file deleted after exception: " . $file_path);
            } else {
                error_log("Failed to delete temporary file after exception: " . $file_path);
            }
        }
        $response['message'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
} else {
    error_log("Invalid action requested: " . $action);
    $response['message'] = 'คำสั่งไม่ถูกต้อง';
}

// Return response
header('Content-Type: application/json');
error_log("Final response: " . json_encode($response));
echo json_encode($response);
?>