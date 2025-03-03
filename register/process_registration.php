<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'debug.log');

// Log ข้อมูลที่ได้รับ
error_log("ข้อมูลที่ได้รับจากฟอร์ม: " . print_r($_POST, true));
error_log("ไฟล์ที่อัพโหลด: " . print_r($_FILES, true));


header('Content-Type: application/json; charset=utf-8');
require_once 'config/database.php';

class RegistrationProcessor {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function processRegistration($postData, $files) {
        try {
            $conn = $this->db->getConnection();
            $conn->beginTransaction();

            // ตรวจสอบข้อมูลที่จำเป็น
            $this->validateData($postData);
            
            // บันทึกข้อมูลการลงทะเบียน
            $registrationId = $this->saveRegistration($conn, $postData);
            
            // บันทึกที่อยู่
            $this->saveAddresses($conn, $registrationId, $postData);
            
            // จัดการไฟล์เอกสารประกอบ (ใหม่)
            if (isset($files['documents'])) {
                $this->handleDocuments($conn, $registrationId, $files['documents'], $postData);
            }
            
            // จัดการไฟล์หลักฐานการชำระเงิน
            if (isset($files['payment_slip'])) {
                $this->handlePaymentSlip($conn, $registrationId, $files['payment_slip']);
            }
            
            $conn->commit();
            
            return [
                'success' => true,
                'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว',
                'registration_id' => $registrationId
            ];
            
        } catch (Exception $e) {
            if (isset($conn)) {
                $conn->rollBack();
            }
            throw new Exception($e->getMessage());
        }
    }
    
    private function validateData($data) {
        $requiredFields = [
            'title' => 'คำนำหน้าชื่อ',
            'fullname' => 'ชื่อ-นามสกุล',
            'organization' => 'หน่วยงาน',
            'phone' => 'เบอร์โทรศัพท์',
            'email' => 'อีเมล'
        ];
        
        foreach ($requiredFields as $field => $label) {
            if (empty($data[$field])) {
                throw new Exception("กรุณากรอก{$label}");
            }
        }
    }
    
    private function saveRegistration($conn, $data) {
        $sql = "INSERT INTO registrations (
                    title, title_other, fullname, organization,
                    phone, email, line_id, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $data['title'],
            $data['title'] === 'other' ? $data['title_other'] : null,
            $data['fullname'],
            $data['organization'],
            $data['phone'],
            $data['email'],
            $data['line_id'] ?? null
        ]);
        
        return $conn->lastInsertId();
    }
    
    private function saveAddresses($conn, $registrationId, $data) {
        $addressMapping = [
            'invoice' => [
                'address' => 'invoiceAddress_address',
                'province' => 'invoiceAddress_province',
                'district' => 'invoiceAddress_district',
                'subdistrict' => 'invoiceAddress_subdistrict',
                'zipcode' => 'invoiceAddress_zipcode'
            ],
            'house' => [
                'address' => 'houseAddress_address',
                'province' => 'houseAddress_province',
                'district' => 'houseAddress_district',
                'subdistrict' => 'houseAddress_subdistrict',
                'zipcode' => 'houseAddress_zipcode'
            ],
            'current' => [
                'address' => 'currentAddress_address',
                'province' => 'currentAddress_province',
                'district' => 'currentAddress_district',
                'subdistrict' => 'currentAddress_subdistrict',
                'zipcode' => 'currentAddress_zipcode'
            ]
        ];
    
        foreach ($addressMapping as $type => $fields) {
            // ตรวจสอบข้อมูลก่อนบันทึก
            if (empty($data[$fields['address']]) || 
                empty($data[$fields['province']]) || 
                empty($data[$fields['district']]) || 
                empty($data[$fields['subdistrict']]) || 
                empty($data[$fields['zipcode']])) {
                throw new Exception("กรุณากรอกข้อมูลที่อยู่ให้ครบถ้วน");
            }
    
            $sql = "INSERT INTO registration_addresses (
                        registration_id, address_type, address,
                        province_id, district_id, subdistrict_id, zipcode
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $registrationId,
                $type,
                $data[$fields['address']],
                $data[$fields['province']],
                $data[$fields['district']],
                $data[$fields['subdistrict']],
                $data[$fields['zipcode']]
            ]);
        }
    }
    
    private function handleDocuments($conn, $registrationId, $files, $postData) {

        if (!isset($files['name']) || !is_array($files['name'])) {

            $documents = [];
            foreach ($files as $key => $value) {
                $documents[$key] = [$value];
            }
        } else {
            $documents = $files;
        }
        
        $documentTypes = isset($postData['document_type']) ? (array)$postData['document_type'] : [];
        $documentDescriptions = isset($postData['document_description']) ? (array)$postData['document_description'] : [];
        
        $uploadDir = 'uploads/documents/';
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception('ไม่สามารถสร้างโฟลเดอร์สำหรับเก็บเอกสารได้');
            }
        }
        
        for ($i = 0; $i < count($documents['name']); $i++) {
            if ($documents['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            if ($documents['error'][$i] !== UPLOAD_ERR_OK) {
                error_log("เกิดข้อผิดพลาดในการอัพโหลดเอกสาร: " . $documents['error'][$i]);
                continue;
            }
            if ($documents['size'][$i] > 5242880) {
                error_log("ขนาดไฟล์เอกสารใหญ่เกิน 5MB: " . $documents['name'][$i]);
                continue;
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            if (!in_array($documents['type'][$i], $allowedTypes)) {
                error_log("ประเภทไฟล์เอกสารไม่ถูกต้อง: " . $documents['type'][$i]);
                continue;
            }
            

            $fileName = time() . '_' . uniqid() . '_' . basename($documents['name'][$i]);
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($documents['tmp_name'][$i], $filePath)) {
                $documentType = isset($documentTypes[$i]) ? $documentTypes[$i] : 'general';
                $description = isset($documentDescriptions[$i]) ? $documentDescriptions[$i] : null;
                
                $sql = "INSERT INTO registration_documents (
                            registration_id, file_name, file_path, file_type, 
                            file_size, document_type, description, uploaded_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $registrationId,
                    $fileName,
                    $filePath,
                    $documents['type'][$i],
                    $documents['size'][$i],
                    $documentType,
                    $description
                ]);
                
                error_log("อัพโหลดเอกสารสำเร็จ: " . $filePath);
            } else {
                error_log("ไม่สามารถย้ายไฟล์อัพโหลดได้: " . $documents['name'][$i]);
            }
        }
    }

    private function handlePaymentSlip($conn, $registrationId, $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('เกิดข้อผิดพลาดในการอัพโหลดไฟล์');
        }
    
        if ($file['size'] > 5242880) { // 5MB
            throw new Exception('ขนาดไฟล์ใหญ่เกินไป กรุณาอัพโหลดไฟล์ขนาดไม่เกิน 5MB');
        }
    
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('ประเภทไฟล์ไม่ถูกต้อง กรุณาอัพโหลดไฟล์ภาพหรือ PDF เท่านั้น');
        }
    
        // ตรวจสอบและสร้างโฟลเดอร์
        $uploadDir = 'uploads/payment_slips/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // สร้างชื่อไฟล์
        $fileName = time() . '_' . uniqid() . '_' . $file['name'];
        $filePath = $uploadDir . $fileName;
        
        // อัพโหลดไฟล์
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('ไม่สามารถอัพโหลดไฟล์ได้');
        }
        
        // บันทึกข้อมูลไฟล์
        $sql = "INSERT INTO registration_files (
                    registration_id, file_name, file_path,
                    file_type, file_size, uploaded_at
                ) VALUES (?, ?, ?, ?, ?, NOW())";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $registrationId,
            $fileName,
            $filePath,
            $file['type'],
            $file['size']
        ]);
        
        // อัพเดตสถานะการชำระเงิน
        $sql = "UPDATE registrations 
                SET payment_status = 'paid',
                    updated_at = NOW()
                WHERE id = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([$registrationId]);
    }
}

try {
    $processor = new RegistrationProcessor();
    $result = $processor->processRegistration($_POST, $_FILES);
    error_log("ผลการประมวลผล: " . print_r($result, true));
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("เกิดข้อผิดพลาด: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}