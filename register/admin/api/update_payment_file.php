<?php
// Enable error reporting
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Custom logging function to error.txt
function log_to_file($message) {
    $log_file = '/home/artss/regArts/error.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Log request details
log_to_file("API update_payment_file called");
log_to_file("POST: " . print_r($_POST, true));
log_to_file("FILES: " . print_r($_FILES, true));

// Log PHP configuration
log_to_file("upload_max_filesize: " . ini_get('upload_max_filesize'));
log_to_file("post_max_size: " . ini_get('post_max_size'));
log_to_file("upload_tmp_dir: " . sys_get_temp_dir());

require_once '../check_auth.php';
require_once '../../config/database.php';

// Create database connection
$database = new Database();
$pdo = $database->getConnection();

// Define upload directory
$upload_dir = dirname(__FILE__) . '/../../Uploads/payment_slips/';
log_to_file("Upload directory: " . $upload_dir);

// Ensure upload directory exists and is writable
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        log_to_file("Failed to create directory: " . $upload_dir);
        show_alert('error', 'เกิดข้อผิดพลาด', 'ไม่สามารถสร้างโฟลเดอร์สำหรับอัพโหลดได้', "../registration_detail.php?id=" . (int)$_POST['registration_id']);
        exit;
    }
    log_to_file("Created directory: " . $upload_dir);
}

if (!is_writable($upload_dir)) {
    log_to_file("Directory is not writable: " . $upload_dir);
    show_alert('error', 'เกิดข้อผิดพลาด', 'ไม่มีสิทธิ์เขียนไฟล์ในโฟลเดอร์อัพโหลด', "../registration_detail.php?id=" . (int)$_POST['registration_id']);
    exit;
}
log_to_file("Directory is writable: " . $upload_dir);

// Check temporary directory
$temp_dir = sys_get_temp_dir();
if (!is_writable($temp_dir)) {
    log_to_file("Temporary directory is not writable: " . $temp_dir);
    show_alert('error', 'เกิดข้อผิดพลาด', 'โฟลเดอร์ชั่วคราวไม่สามารถเขียนได้', "../registration_detail.php?id=" . (int)$_POST['registration_id']);
    exit;
}

// Get action type
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$registration_id = isset($_POST['registration_id']) && is_numeric($_POST['registration_id']) ? (int)$_POST['registration_id'] : 0;
log_to_file("Action: " . $action . ", Registration ID: " . $registration_id);

if (!$registration_id) {
    log_to_file("Invalid registration ID");
    show_alert('error', 'เกิดข้อผิดพลาด', 'ไม่พบรหัสการลงทะเบียน', '../registrations.php');
    exit;
}

// Function to show SweetAlert2 popup
function show_alert($type, $title, $message, $redirect_url) {
    log_to_file("Showing alert: Type=$type, Title=$title, Message=$message, Redirect=$redirect_url");
    echo <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>ผลการดำเนินการ</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: '$type',
                title: '$title',
                text: '$message',
                confirmButtonText: 'ตกลง'
            }).then(() => {
                window.location.href = '$redirect_url';
            });
        </script>
    </body>
    </html>
    HTML;
}

if ($action === 'delete') {
    $file_id = isset($_POST['file_id']) && is_numeric($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
    if (!$file_id) {
        log_to_file("Invalid file ID");
        show_alert('error', 'เกิดข้อผิดพลาด', 'ไม่พบรหัสไฟล์', "../registration_detail.php?id=$registration_id");
        exit;
    }

    $stmt = $pdo->prepare("SELECT file_path FROM registration_files WHERE id = ?");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        log_to_file("File not found: ID $file_id");
        show_alert('error', 'เกิดข้อผิดพลาด', 'ไม่พบไฟล์ที่ต้องการลบ', "../registration_detail.php?id=$registration_id");
        exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM registration_files WHERE id = ?");
        $result = $stmt->execute([$file_id]);

        if ($result) {
            $file_path = realpath(dirname(__FILE__) . '/../../' . $file['file_path']);
            if ($file_path && file_exists($file_path)) {
                if (unlink($file_path)) {
                    log_to_file("File deleted: " . $file_path);
                } else {
                    log_to_file("Failed to delete file: " . $file_path);
                }
            } else {
                log_to_file("File does not exist: " . $file_path);
            }

            $pdo->commit();
            show_alert('success', 'สำเร็จ', 'ลบไฟล์เรียบร้อยแล้ว', "../registration_detail.php?id=$registration_id&upload_success=1");
            exit;
        } else {
            throw new Exception("Failed to delete file record");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        log_to_file("Exception during delete: " . $e->getMessage());
        show_alert('error', 'เกิดข้อผิดพลาด', 'ไม่สามารถลบไฟล์ได้: ' . $e->getMessage(), "../registration_detail.php?id=$registration_id");
        exit;
    }
} elseif ($action === 'upload' || $action === 'update') {
    if (!isset($_FILES['payment_file']) || empty($_FILES['payment_file']['name'])) {
        log_to_file("No file uploaded");
        show_alert('error', 'เกิดข้อผิดพลาด', 'ไม่พบไฟล์ที่อัพโหลด', "../registration_detail.php?id=$registration_id");
        exit;
    }

    $file = $_FILES['payment_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'ไฟล์มีขนาดใหญ่เกินกว่าที่กำหนดใน php.ini',
            UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินกว่าที่กำหนดในฟอร์ม',
            UPLOAD_ERR_PARTIAL => 'ไฟล์ถูกอัพโหลดเพียงบางส่วน',
            UPLOAD_ERR_NO_FILE => 'ไม่มีไฟล์ถูกอัพโหลด',
            UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราว',
            UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ลงดิสก์ได้',
            UPLOAD_ERR_EXTENSION => 'การอัพโหลดถูกหยุดโดย PHP extension'
        ];
        $error_code = $file['error'];
        log_to_file("Upload error code: " . $error_code);
        $message = $upload_errors[$error_code] ?? 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ';
        show_alert('error', 'เกิดข้อผิดพลาด', $message, "../registration_detail.php?id=$registration_id");
        exit;
    }

    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed_types)) {
        log_to_file("Invalid file type: " . $file['type']);
        show_alert('error', 'เกิดข้อผิดพลาด', 'ประเภทไฟล์ไม่ถูกต้อง รองรับเฉพาะ JPG, JPEG, PNG, PDF', "../registration_detail.php?id=$registration_id");
        exit;
    }

    if ($file['size'] > $max_size || $file['size'] <= 0) {
        log_to_file("Invalid file size: " . $file['size'] . " bytes");
        show_alert('error', 'เกิดข้อผิดพลาด', 'ขนาดไฟล์ต้องอยู่ระหว่าง 0 ถึง 5MB', "../registration_detail.php?id=$registration_id");
        exit;
    }

    if (!is_uploaded_file($file['tmp_name']) || !is_readable($file['tmp_name'])) {
        log_to_file("Invalid or unreadable temporary file: " . $file['tmp_name']);
        show_alert('error', 'เกิดข้อผิดพลาด', 'ไฟล์ที่อัพโหลดไม่ถูกต้องหรือไม่สามารถเข้าถึงได้', "../registration_detail.php?id=$registration_id");
        exit;
    }

    // Validate parameters
    if (empty($file['name']) || empty($file['type'])) {
        log_to_file("Missing file name or type");
        show_alert('error', 'เกิดข้อผิดพลาด', 'ชื่อไฟล์หรือประเภทไฟล์ขาดหาย', "../registration_detail.php?id=$registration_id");
        exit;
    }

    // Generate unique filename
    $timestamp = time();
    $unique_id = uniqid();
    $original_name = pathinfo($file['name'], PATHINFO_FILENAME);
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $original_name);
    if (empty($safe_name)) {
        $safe_name = 'file';
    }
    $filename = $timestamp . '_' . $unique_id . '_' . substr($safe_name, 0, 50) . '.' . $file_ext;
    $file_path = $upload_dir . $filename;
    $db_file_path = 'Uploads/payment_slips/' . $filename;

    log_to_file("Generated file path: " . $file_path);

    $pdo->beginTransaction();
    try {
        $file_id = ($action === 'update' && isset($_POST['file_id']) && is_numeric($_POST['file_id'])) ? (int)$_POST['file_id'] : 0;
        $old_file_path = null;
        if ($action === 'update' && $file_id > 0) {
            $stmt = $pdo->prepare("SELECT file_path FROM registration_files WHERE id = ?");
            $stmt->execute([$file_id]);
            $old_file = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old_file) {
                throw new Exception("ไฟล์ที่ต้องการอัพเดทไม่พบ");
            }
            $old_file_path = realpath(dirname(__FILE__) . '/../../' . $old_file['file_path']);
            log_to_file("Old file path to be replaced: " . ($old_file_path ?: 'not found'));
        }

        // Move file and verify
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            log_to_file("Failed to move uploaded file to: " . $file_path);
            throw new Exception("ไม่สามารถบันทึกไฟล์ที่อัพโหลดได้");
        }

        if (!file_exists($file_path)) {
            log_to_file("File not found after move: " . $file_path);
            throw new Exception("ไฟล์ไม่ถูกสร้างหลังจากการอัพโหลด");
        }

        log_to_file("File uploaded successfully: " . $file_path);

        // Log parameters before execute
        log_to_file("Execute parameters: registration_id=$registration_id, file_name=" . $file['name'] . ", file_path=$db_file_path, file_type=" . $file['type'] . ", file_size=" . (int)$file['size']);

        if ($action === 'update' && $file_id > 0) {
            $stmt = $pdo->prepare("
                UPDATE registration_files 
                SET file_name = ?, file_path = ?, file_type = ?, file_size = ?, uploaded_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$file['name'], $db_file_path, $file['type'], (int)$file['size'], $file_id]);
            log_to_file("Database update result: " . ($result ? "success" : "failed"));
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO registration_files (registration_id, file_name, file_path, file_type, file_size, uploaded_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $result = $stmt->execute([$registration_id, $file['name'], $db_file_path, $file['type'], (int)$file['size']]);
            log_to_file("Database insert result: " . ($result ? "success" : "failed"));
        }

        if (!$result) {
            throw new Exception("ไม่สามารถบันทึกข้อมูลไฟล์ลงฐานข้อมูลได้");
        }

        if ($old_file_path && file_exists($old_file_path)) {
            if (unlink($old_file_path)) {
                log_to_file("Old file deleted: " . $old_file_path);
            } else {
                log_to_file("Failed to delete old file: " . $old_file_path);
            }
        }

        $pdo->commit();
        show_alert('success', 'สำเร็จ', 'อัพโหลดไฟล์เรียบร้อยแล้ว', "../registration_detail.php?id=$registration_id&upload_success=1");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        log_to_file("PDOException during $action: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                log_to_file("Cleaned up failed upload file: " . $file_path);
            } else {
                log_to_file("Failed to clean up failed upload file: " . $file_path);
            }
        }
        show_alert('error', 'เกิดข้อผิดพลาด', 'ข้อผิดพลาดฐานข้อมูล: ' . $e->getMessage(), "../registration_detail.php?id=$registration_id");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        log_to_file("Exception during $action: " . $e->getMessage());
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                log_to_file("Cleaned up failed upload file: " . $file_path);
            } else {
                log_to_file("Failed to clean up failed upload file: " . $file_path);
            }
        }
        show_alert('error', 'เกิดข้อผิดพลาด', $e->getMessage(), "../registration_detail.php?id=$registration_id");
        exit;
    }
} else {
    log_to_file("Invalid action: " . $action);
    show_alert('error', 'เกิดข้อผิดพลาด', 'คำสั่งไม่ถูกต้อง', "../registration_detail.php?id=$registration_id");
    exit;
}
?>