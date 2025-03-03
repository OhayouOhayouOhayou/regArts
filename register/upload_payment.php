<?php
header('Content-Type: application/json');
require_once 'config/database.php';

class PaymentUploader {
    private $db;
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    private $maxFileSize = 5242880; // 5MB in bytes
    private $uploadDir = 'uploads/payment_slips/';

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function upload($registrationId, $file) {
        try {
            $this->validateRequest($registrationId, $file);
            
            // Begin transaction
            $this->db->beginTransaction();
            
            // Check registration exists and status
            $registration = $this->checkRegistration($registrationId);
            
            // Process file upload
            $uploadResult = $this->processFileUpload($file);
            
            // Update database records
            $this->updateDatabase($registrationId, $uploadResult);
            
            // Commit transaction
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'อัพโหลดหลักฐานการชำระเงินสำเร็จ',
                'file_path' => $uploadResult['file_path']
            ];
            
        } catch (Exception $e) {
            if (isset($this->db)) {
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

    private function validateRequest($registrationId, $file) {
        if (!$registrationId) {
            throw new Exception('ไม่พบรหัสการลงทะเบียน');
        }

        if (!isset($file) || !is_array($file) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('ไม่พบไฟล์ที่อัพโหลดหรือเกิดข้อผิดพลาด');
        }

        if (!in_array($file['type'], $this->allowedTypes)) {
            throw new Exception('ประเภทไฟล์ไม่ถูกต้อง กรุณาอัพโหลดไฟล์ภาพ (JPG, PNG, GIF) หรือ PDF เท่านั้น');
        }

        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('ขนาดไฟล์ใหญ่เกินไป กรุณาอัพโหลดไฟล์ขนาดไม่เกิน 5MB');
        }
    }

    private function checkRegistration($registrationId) {
        $stmt = $this->db->prepare("
            SELECT * FROM registrations 
            WHERE id = ? AND payment_status = 'not_paid'
        ");
        
        $stmt->execute([$registrationId]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$registration) {
            throw new Exception('ไม่พบข้อมูลการลงทะเบียนหรือชำระเงินไปแล้ว');
        }
        
        return $registration;
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
            throw new Exception('ไม่สามารถอัพโหลดไฟล์ได้');
        }

        return [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_type' => $file['type'],
            'file_size' => $file['size']
        ];
    }

    private function updateDatabase($registrationId, $uploadResult) {
        // Insert file record
        $stmt = $this->db->prepare("
            INSERT INTO registration_files (
                registration_id, file_name, file_path, 
                file_type, file_size, uploaded_at
            ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([
            $registrationId,
            $uploadResult['file_name'],
            $uploadResult['file_path'],
            $uploadResult['file_type'],
            $uploadResult['file_size']
        ]);

        // Update registration status
        $stmt = $this->db->prepare("
            UPDATE registrations 
            SET payment_status = 'paid', 
                payment_updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        
        $stmt->execute([$registrationId]);
    }
}

// Process upload request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploader = new PaymentUploader();
    $result = $uploader->upload(
        $_POST['registration_id'] ?? null,
        $_FILES['payment_slip'] ?? null
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