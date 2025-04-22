<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log request details
error_log("API update_payment_file called");
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

// Log PHP configuration
error_log("PHP upload_max_filesize: " . ini_get('upload_max_filesize'));
error_log("PHP post_max_size: " . ini_get('post_max_size'));
error_log("PHP max_execution_time: " . ini_get('max_execution_time'));
error_log("PHP memory_limit: " . ini_get('memory_limit'));

require_once '../check_auth.php';
require_once '../../config/database.php';

// Create database connection
$database = new Database();
$pdo = $database->getConnection();

// Initialize response
$response = [
    'success' => false,
    'message' => 'An unknown error occurred'
];

// Define upload directory (use absolute path)
$upload_dir = dirname(__FILE__) . '/../../Uploads/payment_slips/';
error_log("Upload directory: " . $upload_dir);

// Ensure upload directory exists and is writable
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        error_log("Failed to create directory: " . $upload_dir);
        $response['message'] = 'Cannot create upload directory';
        echo json_encode($response);
        exit;
    }
    error_log("Created directory: " . $upload_dir);
}

if (!is_writable($upload_dir)) {
    error_log("Directory is not writable: " . $upload_dir);
    $response['message'] = 'Upload directory is not writable';
    echo json_encode($response);
    exit;
}
error_log("Directory is writable: " . $upload_dir);

// Get action type
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
error_log("Action requested: " . $action);

// Process based on action
if ($action === 'delete') {
    // Delete file
    if (!isset($_POST['file_id']) || !is_numeric($_POST['file_id'])) {
        $response['message'] = 'Invalid file ID';
        echo json_encode($response);
        exit;
    }

    $file_id = intval($_POST['file_id']);

    // Get file path before deleting
    $stmt = $pdo->prepare("SELECT file_path FROM registration_files WHERE id = ?");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        $response['message'] = 'File not found';
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
            $file_path = realpath(dirname(__FILE__) . '/../../' . $file['file_path']);
            if ($file_path && file_exists($file_path)) {
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
            $response['message'] = 'File deleted successfully';
        } else {
            throw new Exception("Failed to delete file record from database");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Exception during delete: " . $e->getMessage());
        $response['message'] = 'Error: ' . $e->getMessage();
    }

} elseif ($action === 'upload' || $action === 'update') {
    // Upload or update file
    if (!isset($_POST['registration_id']) || !is_numeric($_POST['registration_id'])) {
        $response['message'] = 'Invalid registration ID';
        echo json_encode($response);
        exit;
    }

    $registration_id = intval($_POST['registration_id']);
    $file_id = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;

    error_log("Processing " . $action . " for registration_id: " . $registration_id . " and file_id: " . $file_id);

    // Check if file was uploaded
    if (!isset($_FILES['payment_file']) || empty($_FILES['payment_file']['name'])) {
        error_log("No file uploaded - payment_file missing or empty");
        $response['message'] = 'No file uploaded';
        echo json_encode($response);
        exit;
    }

    $file = $_FILES['payment_file'];

    // Handle upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds PHP upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $error_code = $file['error'];
        error_log("Upload error code: " . $error_code);
        $response['message'] = 'Upload error: ' . ($upload_errors[$error_code] ?? 'Unknown error');
        echo json_encode($response);
        exit;
    }

    // Validate file
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed_types)) {
        error_log("Invalid file type: " . $file['type']);
        $response['message'] = 'Invalid file type. Allowed: JPG, JPEG, PNG, PDF';
        echo json_encode($response);
        exit;
    }

    if ($file['size'] > $max_size) {
        error_log("File too large: " . $file['size'] . " bytes");
        $response['message'] = 'File size exceeds 5MB';
        echo json_encode($response);
        exit;
    }

    // Validate temporary file
    if (!is_uploaded_file($file['tmp_name']) || !is_readable($file['tmp_name'])) {
        error_log("Invalid or unreadable temporary file: " . $file['tmp_name']);
        $response['message'] = 'Invalid or inaccessible uploaded file';
        echo json_encode($response);
        exit;
    }

    // Generate unique filename
    $timestamp = time();
    $unique_id = uniqid();
    $original_name = pathinfo($file['name'], PATHINFO_FILENAME);
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Sanitize filename
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $original_name);
    if (empty($safe_name)) {
        $safe_name = 'file';
    }
    $filename = $timestamp . '_' . $unique_id . '_' . substr($safe_name, 0, 50) . '.' . $file_ext;
    $file_path = $upload_dir . $filename;
    $db_file_path = 'Uploads/payment_slips/' . $filename;

    error_log("Generated file path: " . $file_path);

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // If updating, get old file path
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

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            error_log("Failed to move uploaded file to: " . $file_path);
            throw new Exception("Failed to save uploaded file");
        }

        // Verify file was saved
        if (!file_exists($file_path)) {
            error_log("File not found after move: " . $file_path);
            throw new Exception("File not created after upload");
        }

        error_log("File uploaded successfully: " . $file_path);

        // Update or insert database record
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
            $result = $stmt->execute($registration_id, $file['name'], $db_file_path, $file['type'], $file['size']);
            error_log("Database insert result: " . ($result ? "success" : "failed"));
            $file_id = $pdo->lastInsertId();
        }

        if (!$result) {
            throw new Exception("Failed to save file record to database");
        }

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
        $response['message'] = ($action === 'update') ? 'File updated successfully' : 'File uploaded successfully';
        $response['file_id'] = $file_id;
        $response['file_path'] = $db_file_path;
        error_log("Operation successful: " . $response['message']);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Exception during " . $action . ": " . $e->getMessage());

        // Delete uploaded file if it exists
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                error_log("Cleaned up failed upload file: " . $file_path);
            } else {
                error_log("Failed to clean up failed upload file: " . $file_path);
            }
        }

        $response['message'] = 'Error: ' . $e->getMessage();
    }
} else {
    error_log("Invalid action: " . $action);
    $response['message'] = 'Invalid action';
}

// Handle response (redirect or JSON)
if (isset($_GET['return_id']) && is_numeric($_GET['return_id']) && $response['success'] && in_array($action, ['upload', 'update'])) {
    $return_id = intval($_GET['return_id']);
    error_log("Redirecting to registration_detail.php?id=" . $return_id);
    header("Location: ../registration_detail.php?id=$return_id&success=1");
    exit;
}

// Return JSON response
header('Content-Type: application/json');
error_log("Final response: " . json_encode($response));
echo json_encode($response);
?>