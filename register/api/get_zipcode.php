<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

try {
    if (!isset($_GET['subdistrict_id'])) {
        throw new Exception('ไม่พบรหัสตำบล/แขวง');
    }

    $subdistrictId = filter_var($_GET['subdistrict_id'], FILTER_VALIDATE_INT);
    if ($subdistrictId === false) {
        throw new Exception('รหัสตำบล/แขวงไม่ถูกต้อง');
    }

    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT zip_code 
        FROM subdistricts 
        WHERE id = ?
    ");
    
    $stmt->execute([$subdistrictId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        throw new Exception('ไม่พบข้อมูลรหัสไปรษณีย์');
    }
    
    echo json_encode([
        'success' => true,
        'zip_code' => $result['zip_code']
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}