<?php
// เพิ่มการเก็บ log
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'debug.log');

header('Content-Type: application/json; charset=utf-8');
require_once 'config/database.php';

try {
    // บันทึก request ที่เข้ามา
    error_log("Request received: " . file_get_contents('php://input'));
    
    $data = json_decode(file_get_contents('php://input'), true);
    error_log("Decoded data: " . print_r($data, true));
    
    if (!isset($data['phone'])) {
        throw new Exception('กรุณาระบุเบอร์โทรศัพท์');
    }

    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT r.* 
        FROM registrations r 
        WHERE r.phone = ?
    ");
    
    error_log("Checking phone number: " . $data['phone']);
    $stmt->execute([$data['phone']]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Query result: " . print_r($registration, true));
    
    $response = [];
    if ($registration) {
        $response = [
            'success' => true,
            'status' => 'exists',
            'message' => 'เบอร์โทรศัพท์นี้ได้ลงทะเบียนแล้ว',
            'data' => [
                'registration_id' => $registration['id'],
                'payment_status' => $registration['payment_status']
            ]
        ];
        
        switch ($registration['payment_status']) {
            case 'not_paid':
                $response['status'] = 'registered_unpaid';
                break;
            case 'paid':
                if (isset($registration['is_approved']) && $registration['is_approved']) {
                    $response['status'] = 'registration_complete';
                } else {
                    $response['status'] = 'pending_approval';
                    $response['message'] = 'รอการตรวจสอบจากเจ้าหน้าที่';
                }
                break;
        }
    } else {
        $response = [
            'success' => true,
            'status' => 'not_registered',
            'message' => 'เบอร์โทรศัพท์นี้ยังไม่เคยลงทะเบียน'
        ];
    }
    
    error_log("Sending response: " . json_encode($response, JSON_UNESCAPED_UNICODE));
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Error occurred: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>