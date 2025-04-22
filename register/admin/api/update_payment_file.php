<?php
// Enable error reporting
ini_set('display_errors', 0); // Set to 0 in production
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Log request details
error_log("API update_payment_file called");
error_log("POST: " . print_r($_POST, true));
error_log("FILES: " . print_r($_FILES, true));

// Log PHP configuration
error_log("upload_max_filesize: " . ini_get('upload_max_filesize'));
error_log("post_max_size: " . ini_get('post_max_size'));
error_log("upload_tmp_dir: " . sys_get_temp_dir());

require_once '../check_auth.php';
require_once '../../config/database.php';

// Create database connection
$database = new Database();
$pdo = $database->getConnection();

// Define upload directory
$upload_dir = dirname(__FILE__) . '/../../Uploads/payment_slips/';
error_log("Upload directory: " . $upload_dir);

// Ensure upload directory exists and is writable
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        error_log("Failed to create directory: " . $upload_dir);
        header("Location: ../registration_detail.php?id=" . (int)$_POST['registration_id'] . "&upload_error=Cannot create upload directory");
        exit;
    }
    error_log("Created directory: " . $upload_dir);
}

if (!is_writable($upload_dir)) {
    error_log("Directory is not writable: " . $upload_dir);
    header("Location: ../registration_detail.php?id=" . (int)$_POST['registration_id'] . "&upload_error=Upload directory is not writable");
    exit;
}
error_log("Directory is writable: " . $upload_dir);

// Check temporary directory
$temp_dir = sys_get_temp_dir();
if (!is_writable($temp_dir)) {
    error_log("Temporary directory is not writable: " . $temp_dir);
    header("Location: ../registration_detail.php?id=" . (int)$_POST['registration_id'] . "&upload_error=Temporary directory is not writable");
    exit;
}

// Get action type
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$registration_id = isset($_POST['registration_id']) && is_numeric($_POST['registration_id']) ? (int)$_POST['registration_id'] : 0;
error_log("Action: " . $action . ", Registration ID: " . $registration_id);

if (!$registration_id) {
    error_log("Invalid registration ID");
    header("Location: ../registrations.php");
    exit;
}

if ($action === 'delete') {
    $file_id = isset($_POST['file_id']) && is_numeric($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
    if (!$file_id) {
        error_log("Invalid file ID");
        header("Location: ../registration_detail.php?id=$registration_id&upload_error=Invalid file ID");
        exit;
    }

    $stmt = $pdo->prepare("SELECT file_path FROM registration_files WHERE id = ?");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        error_log("File not found: ID $file_id");
        header("Location: ../registration_detail.php?id=$registration_id&upload_error=File not found");
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
                    error_log("File deleted: " . $file_path);
                } else {
                    error_log("Failed to delete file: " . $file_path);
                }
            } else {
                error_log("File does not exist: " . $file_path);
            }

            $pdo->commit();
            header("Location: ../registration_detail.php?id=$registration_id&upload_success=1");
            exit;
        } else {
            throw new Exception("Failed to delete file record");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Exception during delete: " . $e->getMessage());
        header("Location: ../registration_detail.php?id=$registration_id&upload_error=Error: " . urlencode($e->getMessage()));
        exit;
    }
} elseif ($action === 'upload' || $action === 'update') {
    if (!isset($_FILES['payment_file']) || empty($_FILES['payment_file']['name'])) {
        error_log("No file uploaded");
        header("Location: ../registration_detail.php?id=$registration_id&upload_error=No file uploaded");
        exit;
    }

    $file = $_FILES['payment_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $error_code = $file['error'];
        error_log("Upload error code: " . $error_code);
        $message = 'Upload error: ' . ($upload_errors[$error_code] ?? 'Unknown error');
        header("Location: ../registration_detail.php?id=$registration_id&upload_error=" . urlencode($message));
        exit;
    }

    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed_types)) {
        error_log("Invalid file type: " . $file['type']);
        header("Location: ../registration_detail.php?id=$registration_id&upload_error=Invalid file type. Allowed: JPG, JPEG, PNG, PDF");
        exit;
    }

    if ($file['size'] > $max_size) {
        error_log("File too large: " . $file['size'] . " bytes");
        header("Location: ../registration_detail.php?id=$registration_id&upload_error=File size exceeds 5MB");
        exit;
    }

    if (!is_uploaded_file($file['tmp_name']) || !is_readable($file['tmp_name'])) {
        error_log("Invalid or unreadable temporary file: " . $file['tmp_name']);
        header("Location: ../registration_detail.php?id=$registration_id&upload_error=Invalid or inaccessible uploaded file");
        exit;
    }

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

    error_log("Generated file path: " . $file_path);

    $pdo->beginTransaction();
    try {
        $file_id = ($action === 'update' && isset($_POST['file_id']) && is_numeric($_POST['file_id'])) ? (int)$_POST['file_id'] : 0;
        $old_file_path = null;
        if ($action === 'update' && $file_id > 0) {
            $stmt = $pdo->prepare("SELECT file_path FROM registration_files WHERE id = ?");
            $stmt->execute([$file_id]);
            $old_file = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old_file) {
                throw new Exception("File to update not found");
            }
            $old_file_path = realpath(dirname(__FILE__) . '/../../' . $old_file['file_path']);
            error_log("Old file path to be replaced: " . ($old_file_path ?: 'not found'));
        }

        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            error_log("Failed to move uploaded file to: " . $file_path);
            throw new Exception("Failed to save uploaded file");
        }

        if (!file_exists($file_path)) {
            error_log("File not found after move: " . $file_path);
            throw new Exception("File not created after upload");
        }

        error_log("File uploaded successfully: " . $file_path);

        if ($action === 'update' && $file_id > 0) {
            $stmt = $pdo->prepare("
                UPDATE registration_files 
                SET file_name = ?, file_path = ?, file_type = ?, file_size = ?, uploaded_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$file['name'], $db_file_path, $file['type'], $file['size'], $file_id]);
            error_log("Database update result: " . ($result ? "success" : "failed"));
        } else {
            $stmt = $pdo->prepare("
            INSERT INTO registration_files (registration_id, file_name, file_path, file_type, file_size)
            VALUES (?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([$registration_id, $file['name'], $db_file_path, $file['type'], $file['size']]);
            error_log("Database insert result: " . ($result ? "success" : "failed"));
        }

        if (!$result) {
            throw new Exception("Failed to save file record to database");
        }

        if ($old_file_path && file_exists($old_file_path)) {
            if (unlink($old_file_path)) {
                error_log("Old file deleted: " . $old_file_path);
            } else {
                error_log("Failed to delete old file: " . $old_file_path);
            }
        }

        $pdo->commit();
        header("Location: ../registration_detail.php?id=$registration_id&upload_success=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Exception during $action: " . $e->getMessage());
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                error_log("Cleaned up failed upload file: " . $file_path);
            } else {
                error_log("Failed to clean up failed upload file: " . $file_path);
            }
        }
        header("Location: ../registration_detail.php?id=$registration_id&upload_error=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    error_log("Invalid action: " . $action);
    header("Location: ../registration_detail.php?id=$registration_id&upload_error=Invalid action");
    exit;
}
?>