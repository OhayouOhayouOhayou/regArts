<?php

class RegistrationForm {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function register($data, $files) {
        try {
            $this->db->beginTransaction();
            
            // Insert registration
            $query = "INSERT INTO registrations (title, title_other, fullname, organization, 
                     phone, email, line_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $data['title'],
                $data['title_other'],
                $data['fullname'],
                $data['organization'],
                $data['phone'],
                $data['email'],
                $data['line_id']
            ]);
            
            $registrationId = $this->db->lastInsertId();
            
            // Insert addresses
            $this->insertAddress($registrationId, 'invoice', $data['invoice_address']);
            $this->insertAddress($registrationId, 'house', $data['house_address']);
            $this->insertAddress($registrationId, 'current', $data['current_address']);
            
            // Handle payment slip upload
            if (isset($files['payment_slip'])) {
                $this->uploadPaymentSlip($registrationId, $files['payment_slip']);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    private function insertAddress($registrationId, $type, $addressData) {
        $query = "INSERT INTO registration_addresses (registration_id, address_type, 
                 address, province_id, district_id, subdistrict_id, zipcode) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $registrationId,
            $type,
            $addressData['address'],
            $addressData['province_id'],
            $addressData['district_id'],
            $addressData['subdistrict_id'],
            $addressData['zipcode']
        ]);
    }
    
    private function uploadPaymentSlip($registrationId, $file) {
        $uploadDir = 'uploads/payment_slips/';
        $fileName = time() . '_' . $file['name'];
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $query = "INSERT INTO registration_files (registration_id, file_name, 
                     file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $registrationId,
                $fileName,
                $filePath,
                $file['type'],
                $file['size']
            ]);
        }
    }
}
?>