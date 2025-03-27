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

            // สร้าง registration_group_id สำหรับผู้ลงทะเบียนทั้งหมดในครั้งนี้
            $groupId = uniqid('group_');
            $registrationIds = [];
            
            // หาจำนวนผู้ลงทะเบียน
            $registrantCount = 1; // มีอย่างน้อย 1 คน
            
            // ตรวจสอบว่ามีผู้ลงทะเบียนกี่คน โดยนับจากฟิลด์ fullname_X
            foreach ($postData as $key => $value) {
                if (preg_match('/^fullname_(\d+)$/', $key, $matches)) {
                    $index = (int)$matches[1];
                    if ($index >= $registrantCount) {
                        $registrantCount = $index + 1;
                    }
                }
            }
            
            // ประมวลผลแต่ละผู้ลงทะเบียน
            for ($i = 0; $i < $registrantCount; $i++) {
                $registrantData = [];
                
                // จัดการข้อมูลสำหรับคนแรก (ไม่มี suffix)
                if ($i === 0) {
                    $registrantData = [
                        'title' => $postData['title'],
                        'title_other' => $postData['title_other'] ?? null,
                        'fullname' => $postData['fullname'],
                        'organization' => $postData['organization'],
                        'position' => $postData['position'],
                        'phone' => $postData['phone'],
                        'email' => $postData['email'],
                        'line_id' => $postData['line_id'] ?? null
                    ];
                } else {
                    // จัดการข้อมูลสำหรับคนที่ 2 เป็นต้นไป (มี suffix _X)
                    $registrantData = [
                        'title' => $postData["title_{$i}"] ?? '',
                        'title_other' => $postData["title_other_{$i}"] ?? null,
                        'fullname' => $postData["fullname_{$i}"] ?? '',
                        'organization' => $postData["organization_{$i}"] ?? '',
                        'position' => $postData["position_{$i}"] ?? '',
                        'phone' => $postData['phone'], // ใช้เบอร์โทรศัพท์เดียวกับคนแรก
                        'email' => $postData["email_{$i}"] ?? '',
                        'line_id' => $postData["line_id_{$i}"] ?? null
                    ];
                }
                
                // ตรวจสอบว่ามีข้อมูลที่จำเป็นครบหรือไม่
                if (empty($registrantData['fullname'])) {
                    // ข้ามผู้ลงทะเบียนที่ไม่มีข้อมูล
                    continue;
                }
                
                // ตรวจสอบข้อมูลที่จำเป็น
                foreach (['title', 'fullname', 'organization', 'position', 'email'] as $field) {
                    if (empty($registrantData[$field])) {
                        throw new Exception("กรุณากรอก{$field}ให้ครบถ้วน");
                    }
                }
                
                // บันทึกข้อมูลการลงทะเบียน
                $registrationId = $this->saveRegistrationWithGroup($conn, $registrantData, $groupId);
                $registrationIds[] = $registrationId;
                
                // บันทึกที่อยู่ (เฉพาะผู้ลงทะเบียนคนแรกเท่านั้น)
                if ($i === 0) {
                    $this->saveAddresses($conn, $registrationId, $postData);
                }
            }
            
            // จัดการไฟล์เอกสารประกอบ (ถ้ามี) - เชื่อมโยงกับผู้ลงทะเบียนคนแรก
            if (isset($files['documents']) && $this->isValidDocumentsUpload($files['documents'])) {
                $this->handleDocuments($conn, $registrationIds[0], $files['documents'], $postData);
            }
            
            // จัดการไฟล์หลักฐานการชำระเงินสำหรับทุกคน (ถ้ามี)
            $hasPaymentSlip = false;
            if (isset($files['payment_slip']) && $files['payment_slip']['error'] === UPLOAD_ERR_OK) {
                $fileId = $this->handleGroupPaymentSlip($conn, $registrationIds[0], $files['payment_slip']);
                
                if ($fileId) {
                    $hasPaymentSlip = true;
                    
                    // อัพเดตสถานะการชำระเงินสำหรับทุกคนในกลุ่ม
                    foreach ($registrationIds as $regId) {
                        $this->updatePaymentStatus($conn, $regId, $fileId, $postData['payment_date'] ?? null);
                    }
                }
            }
            
            $conn->commit();
            
            return [
                'success' => true,
                'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว',
                'registration_ids' => $registrationIds,
                'registration_count' => count($registrationIds),
                'payment_uploaded' => $hasPaymentSlip
            ];
            
        } catch (Exception $e) {
            if (isset($conn)) {
                $conn->rollBack();
            }
            error_log("เกิดข้อผิดพลาด: " . $e->getMessage());
            throw new Exception($e->getMessage());
        }
    }
    
    // เพิ่มฟังก์ชันใหม่สำหรับบันทึกการลงทะเบียนพร้อมกับ group_id
    private function saveRegistrationWithGroup($conn, $data, $groupId) {
        $sql = "INSERT INTO registrations (
                    title, title_other, fullname, organization, position,
                    phone, email, line_id, registration_group, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $data['title'],
            $data['title'] === 'other' ? $data['title_other'] : null,
            $data['fullname'],
            $data['organization'],
            $data['position'],
            $data['phone'],
            $data['email'],
            $data['line_id'] ?? null,
            $groupId
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
                if ($type === 'invoice') {
                    // สำหรับ invoice address จำเป็นต้องมีข้อมูลครบถ้วน
                    throw new Exception("กรุณากรอกข้อมูลที่อยู่สำหรับออกใบเสร็จให้ครบถ้วน");
                } else {
                    // สำหรับที่อยู่อื่นๆ ใช้ที่อยู่เดียวกับ invoice
                    continue;
                }
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
    
    // เพิ่มฟังก์ชันสำหรับอัพเดตสถานะการชำระเงิน
    private function updatePaymentStatus($conn, $registrationId, $fileId, $paymentDate) {
        $sql = "UPDATE registrations 
                SET payment_status = 'paid',
                    payment_date = ?,
                    payment_updated_at = NOW(),
                    payment_slip_id = ?
                WHERE id = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $paymentDate ?: date('Y-m-d H:i:s'),
            $fileId,
            $registrationId
        ]);
    }
    
    private function isValidDocumentsUpload($files) {
        // ตรวจสอบว่า documents มีข้อมูลและไม่มี error
        if (!isset($files['name']) || !is_array($files['name'])) {
            return false;
        }
        
        // ตรวจสอบว่ามีอย่างน้อย 1 ไฟล์ที่อัพโหลดได้สำเร็จ
        foreach ($files['error'] as $error) {
            if ($error === UPLOAD_ERR_OK) {
                return true;
            }
        }
        
        return false;
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

    // แก้ไขฟังก์ชัน handlePaymentSlip เป็น handleGroupPaymentSlip
    private function handleGroupPaymentSlip($conn, $registrationId, $file) {
        // ตรวจสอบว่ามีการอัพโหลดไฟล์หรือไม่
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0) {
            error_log("ไม่สามารถอัพโหลดหลักฐานการชำระเงินได้: " . $file['error']);
            return false;
        }
        
        // ตรวจสอบวันที่ชำระเงิน
        $paymentDate = isset($_POST['payment_date']) && !empty($_POST['payment_date']) 
            ? $_POST['payment_date'] 
            : date('Y-m-d H:i:s'); // ใช้เวลาปัจจุบันถ้าไม่ได้ระบุ
        
        if ($file['size'] > 5242880) { // 5MB
            error_log("ขนาดไฟล์หลักฐานการชำระเงินใหญ่เกินไป: " . $file['size'] . " bytes");
            return false;
        }
    
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        if (!in_array($file['type'], $allowedTypes)) {
            error_log("ประเภทไฟล์หลักฐานการชำระเงินไม่ถูกต้อง: " . $file['type']);
            return false;
        }
    
        // ตรวจสอบและสร้างโฟลเดอร์
        $uploadDir = 'uploads/payment_slips/';
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                error_log("ไม่สามารถสร้างโฟลเดอร์: " . $uploadDir);
                return false;
            }
        }
        
        // สร้างชื่อไฟล์
        $fileName = time() . '_' . uniqid() . '_' . basename($file['name']);
        $filePath = $uploadDir . $fileName;
        
        // อัพโหลดไฟล์
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            error_log("ไม่สามารถย้ายไฟล์อัพโหลดได้: " . $file['name']);
            return false;
        }
        
        try {
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
            
            return $conn->lastInsertId(); // คืนค่า ID ของไฟล์ที่อัพโหลด
        } catch (Exception $e) {
            error_log("เกิดข้อผิดพลาดในการบันทึกข้อมูลหลักฐานการชำระเงิน: " . $e->getMessage());
            return false;
        }
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