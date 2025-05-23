<?php
header('Content-Type: application/json');
require_once 'config/database.php';

// Enable detailed logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'payment_upload.log');

// Log request details
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

class PaymentUploader {
    private $db;
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    private $maxFileSize = 5242880; // 5MB in bytes
    private $uploadDir = 'uploads/payment_slips/';

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function upload($registrationId, $paymentDate, $file, $registrationGroup = null) {
        try {
            error_log("Processing upload for registration ID: " . $registrationId);
            error_log("Registration group: " . $registrationGroup);
            error_log("Payment date: " . $paymentDate);
            
            // Validate registrationId
            if (!$registrationId) {
                throw new Exception('ไม่พบรหัสการลงทะเบียน');
            }
            
            // Validate payment date
            if (!$paymentDate) {
                throw new Exception('กรุณาระบุวันที่และเวลาที่ชำระเงิน');
            }
            
            // Begin transaction
            $this->db->beginTransaction();
            
            // Check registration exists
            $registration = $this->checkRegistration($registrationId);
            
            // Get group ID if not provided
            if (!$registrationGroup) {
                $registrationGroup = $registration['registration_group'];
            }
            
            error_log("Using registration group: " . $registrationGroup);
            
            // Validate and process file
            if (!isset($file) || !is_array($file) || $file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0) {
                throw new Exception('ไม่พบไฟล์หลักฐานการชำระเงิน');
            }
            
            $this->validateFile($file);
            
            $uploadResult = $this->processFileUpload($file);
            error_log("File uploaded to: " . $uploadResult['file_path']);
            
            // Update database records - save file record
            $fileId = $this->saveFileRecord($registrationId, $uploadResult);
            
            // Get all members of the group
            $groupMembers = $this->getGroupMembers($registrationGroup);
            error_log("Group members found: " . count($groupMembers));
            
            // Update payment status for all members
            foreach ($groupMembers as $memberId) {
                $this->updatePaymentStatus($memberId, $paymentDate, $fileId);
            }
            
            // Commit transaction
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'อัพโหลดหลักฐานการชำระเงินสำเร็จ',
                'file_path' => $uploadResult['file_path'],
                'updated_members' => count($groupMembers)
            ];
            
        } catch (Exception $e) {
            error_log("Error in upload: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            if (isset($this->db) && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            // Remove uploaded file if exists
            if (isset($uploadResult['file_path']) && file_exists($uploadResult['file_path'])) {
                unlink($uploadResult['file_path']);
            }
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function validateFile($file) {
        if (!in_array($file['type'], $this->allowedTypes)) {
            throw new Exception('ประเภทไฟล์ไม่ถูกต้อง กรุณาอัพโหลดไฟล์ภาพ (JPG, PNG, GIF) หรือ PDF เท่านั้น');
        }

        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('ขนาดไฟล์ใหญ่เกินไป กรุณาอัพโหลดไฟล์ขนาดไม่เกิน 5MB');
        }
    }

    private function checkRegistration($registrationId) {
        try {
            // Get table structure
            $this->logTableStructure('registrations');
            
            // Execute query
            $query = "SELECT * FROM registrations WHERE id = ?";
            error_log("Executing query: " . $query);
            error_log("With parameters: [" . $registrationId . "]");
            
            $stmt = $this->db->prepare($query);
            
            if (!$stmt) {
                error_log("PDO prepare error: " . print_r($this->db->errorInfo(), true));
                throw new Exception('เกิดข้อผิดพลาดในระบบฐานข้อมูล');
            }
            
            $result = $stmt->execute([$registrationId]);
            
            if (!$result) {
                error_log("PDO execute error: " . print_r($stmt->errorInfo(), true));
                throw new Exception('เกิดข้อผิดพลาดในการค้นหาข้อมูลการลงทะเบียน');
            }
            
            $registration = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$registration) {
                error_log("No registration found with ID: " . $registrationId);
                throw new Exception('ไม่พบข้อมูลการลงทะเบียน');
            }
            
            error_log("Found registration: " . print_r($registration, true));
            return $registration;
        } catch (PDOException $e) {
            error_log("PDO error: " . $e->getMessage());
            throw new Exception('เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล');
        }
    }

    private function processFileUpload($file) {
        // Create upload directory if not exists
        if (!file_exists($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0755, true)) {
                throw new Exception('ไม่สามารถสร้างโฟลเดอร์สำหรับเก็บไฟล์ได้');
            }
        }

        // Generate unique filename
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = time() . '_' . uniqid() . '.' . $fileExtension;
        $filePath = $this->uploadDir . $fileName;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            error_log("Failed to move uploaded file from {$file['tmp_name']} to {$filePath}");
            throw new Exception('ไม่สามารถอัพโหลดไฟล์ได้');
        }

        return [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_type' => $file['type'],
            'file_size' => $file['size']
        ];
    }

    private function saveFileRecord($registrationId, $uploadResult) {
        try {
            // Get table structure
            $this->logTableStructure('registration_files');
            
            // Insert file record
            $insertQuery = "
                INSERT INTO registration_files (
                    registration_id, file_name, file_path, 
                    file_type, file_size, uploaded_at
                ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ";
            
            error_log("Executing insert query: " . $insertQuery);
            error_log("With parameters: [" . $registrationId . ", " . 
                      $uploadResult['file_name'] . ", " . 
                      $uploadResult['file_path'] . ", " . 
                      $uploadResult['file_type'] . ", " . 
                      $uploadResult['file_size'] . "]");
            
            $stmt = $this->db->prepare($insertQuery);
            
            if (!$stmt) {
                error_log("PDO prepare error (insert): " . print_r($this->db->errorInfo(), true));
                throw new Exception('เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับบันทึกไฟล์');
            }
            
            $result = $stmt->execute([
                $registrationId,
                $uploadResult['file_name'],
                $uploadResult['file_path'],
                $uploadResult['file_type'],
                $uploadResult['file_size']
            ]);
            
            if (!$result) {
                error_log("PDO execute error (insert): " . print_r($stmt->errorInfo(), true));
                throw new Exception('เกิดข้อผิดพลาดในการบันทึกข้อมูลไฟล์');
            }
            
            $fileId = $this->db->lastInsertId();
            error_log("Inserted file record with ID: " . $fileId);
            
            return $fileId;
        } catch (PDOException $e) {
            error_log("Database error in saveFileRecord: " . $e->getMessage());
            throw new Exception('เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage());
        }
    }
    
    private function getGroupMembers($groupId) {
        try {
            $query = "SELECT id FROM registrations WHERE registration_group = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$groupId]);
            
            $members = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($members)) {
                error_log("No members found for group: " . $groupId);
                throw new Exception('ไม่พบข้อมูลกลุ่มการลงทะเบียน');
            }
            
            return $members;
        } catch (PDOException $e) {
            error_log("Database error in getGroupMembers: " . $e->getMessage());
            throw new Exception('เกิดข้อผิดพลาดในการดึงข้อมูลสมาชิกกลุ่ม: ' . $e->getMessage());
        }
    }
    
    private function updatePaymentStatus($registrationId, $paymentDate, $fileId) {
        try {
            // Check columns
            $columns = $this->getTableColumns('registrations');
            $hasPaymentDate = false;
            $hasPaymentSlipId = false;
            $hasPaymentUpdatedAt = false;
            
            foreach ($columns as $column) {
                if ($column['Field'] === 'payment_date') {
                    $hasPaymentDate = true;
                }
                if ($column['Field'] === 'payment_slip_id') {
                    $hasPaymentSlipId = true;
                }
                if ($column['Field'] === 'payment_updated_at') {
                    $hasPaymentUpdatedAt = true;
                }
            }
            
            // Construct update query
            $updateQuery = "UPDATE registrations SET payment_status = 'paid'";
            $params = [];
            
            if ($hasPaymentDate) {
                $updateQuery .= ", payment_date = ?";
                $params[] = $paymentDate;
            }
            
            if ($hasPaymentUpdatedAt) {
                $updateQuery .= ", payment_updated_at = CURRENT_TIMESTAMP";
            }
            
            if ($hasPaymentSlipId) {
                $updateQuery .= ", payment_slip_id = ?";
                $params[] = $fileId;
            }
            
            $updateQuery .= " WHERE id = ?";
            $params[] = $registrationId;
            
            error_log("Executing update query for member {$registrationId}: " . $updateQuery);
            
            $stmt = $this->db->prepare($updateQuery);
            
            if (!$stmt) {
                error_log("PDO prepare error (update): " . print_r($this->db->errorInfo(), true));
                throw new Exception('เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับอัพเดทสถานะ');
            }
            
            $updateResult = $stmt->execute($params);
            
            if (!$updateResult) {
                error_log("PDO execute error (update): " . print_r($stmt->errorInfo(), true));
                throw new Exception('เกิดข้อผิดพลาดในการอัพเดทสถานะการชำระเงิน');
            }
            
            $rowCount = $stmt->rowCount();
            error_log("Updated {$rowCount} row(s) in registrations table for member {$registrationId}");
            
            return $rowCount;
        } catch (PDOException $e) {
            error_log("Database error in updatePaymentStatus: " . $e->getMessage());
            throw new Exception('เกิดข้อผิดพลาดในการอัพเดทสถานะการชำระเงิน: ' . $e->getMessage());
        }
    }
    
    private function getTableColumns($tableName) {
        $stmt = $this->db->prepare("DESCRIBE " . $tableName);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function hasColumn($tableName, $columnName) {
        $columns = $this->getTableColumns($tableName);
        foreach ($columns as $column) {
            if ($column['Field'] === $columnName) {
                return true;
            }
        }
        return false;
    }
    
    private function logTableStructure($tableName) {
        try {
            $stmt = $this->db->prepare("DESCRIBE " . $tableName);
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Table structure for {$tableName}:");
            foreach ($columns as $column) {
                error_log("  {$column['Field']} - {$column['Type']} - Null: {$column['Null']} - Default: {$column['Default']}");
            }
        } catch (Exception $e) {
            error_log("Error getting table structure for {$tableName}: " . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploader = new PaymentUploader();
    
    // Debug registration ID and payment date
    $registrationId = $_POST['registration_id'] ?? null;
    $paymentDate = $_POST['payment_date'] ?? null;
    $registrationGroup = $_POST['registration_group'] ?? null;
    
    // ถ้าไม่มีวันที่ชำระเงิน ให้ใช้วันที่ปัจจุบัน
    if (empty($paymentDate)) {
        $paymentDate = date('Y-m-d H:i:s');
    }
    
    error_log("Received registration_id: " . $registrationId);
    error_log("Received registration_group: " . $registrationGroup);
    error_log("Received payment_date: " . $paymentDate);
    
    // Check if files are properly received
    if (isset($_FILES['payment_slip'])) {
        error_log("Payment slip file received: " . $_FILES['payment_slip']['name']);
    } else {
        error_log("No payment_slip file in request");
    }
    
    $result = $uploader->upload(
        $registrationId,
        $paymentDate,
        $_FILES['payment_slip'] ?? null,
        $registrationGroup
    );
    
    if (!$result['success']) {
        http_response_code(400);
    }
    
    echo json_encode($result);
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]);
}