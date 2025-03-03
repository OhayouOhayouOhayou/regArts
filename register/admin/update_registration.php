<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'check_auth.php';
require_once '../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // เริ่ม transaction
    $conn->beginTransaction();

    // อัพเดทข้อมูลหลัก
    $stmt = $conn->prepare("
        UPDATE registrations 
        SET fullname = :fullname,
            organization = :organization,
            phone = :phone,
            email = :email,
            payment_status = :payment_status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $_POST['id'],
        ':fullname' => $_POST['fullname'],
        ':organization' => $_POST['organization'],
        ':phone' => $_POST['phone'],
        ':email' => $_POST['email'],
        ':payment_status' => $_POST['payment_status']
    ]);

    // จัดการไฟล์แนบถ้ามีการอัพโหลดใหม่
    if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/payment_slips/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . '_' . basename($_FILES['payment_slip']['name']);
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $filePath)) {
            // บันทึกข้อมูลไฟล์
            $stmt = $conn->prepare("
                INSERT INTO registration_files (
                    registration_id, file_name, file_path, file_type, file_size
                ) VALUES (
                    :registration_id, :file_name, :file_path, :file_type, :file_size
                )
            ");

            $stmt->execute([
                ':registration_id' => $_POST['id'],
                ':file_name' => $fileName,
                ':file_path' => $filePath,
                ':file_type' => $_FILES['payment_slip']['type'],
                ':file_size' => $_FILES['payment_slip']['size']
            ]);
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'อัพเดทข้อมูลเรียบร้อยแล้ว'
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    error_log("Error in update_registration.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'ไม่สามารถอัพเดทข้อมูลได้: ' . $e->getMessage()
    ]);
}