<?php
require_once '../../config/database.php';
require_once '../check_auth.php';

header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'เมธอดไม่ถูกต้อง กรุณาใช้ POST'
    ]);
    exit;
}

// Get registration ID
$registration_id = isset($_POST['registration_id']) ? (int)$_POST['registration_id'] : 0;

if ($registration_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'รหัสการลงทะเบียนไม่ถูกต้อง'
    ]);
    exit;
}

try {
    // Check if registration exists and is not already approved
    $checkStmt = $conn->prepare("
        SELECT id, is_approved 
        FROM registrations 
        WHERE id = ?
    ");
    $checkStmt->bind_param("i", $registration_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่พบข้อมูลการลงทะเบียน'
        ]);
        exit;
    }
    
    $registration = $result->fetch_assoc();
    
    if ($registration['is_approved'] == 1) {
        echo json_encode([
            'status' => 'error',
            'message' => 'การลงทะเบียนนี้ได้รับการอนุมัติไปแล้ว'
        ]);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Update registration status
    $updateStmt = $conn->prepare("
        UPDATE registrations 
        SET is_approved = 1,
            approved_at = NOW(),
            approved_by = ?
        WHERE id = ?
    ");
    
    $user_id = $_SESSION['user_id'];
    $updateStmt->bind_param("ii", $user_id, $registration_id);
    $updateStmt->execute();
    
    // Check if update was successful
    if ($conn->affected_rows === 0) {
        throw new Exception('การอัปเดตสถานะล้มเหลว');
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'อนุมัติการลงทะเบียนเรียบร้อยแล้ว'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}