<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->query("
        SELECT id, name_in_thai 
        FROM provinces 
        ORDER BY name_in_thai ASC
    ");
    
    $provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);
    

    $cleanProvinces = array_map(function($province) {
        return [
            'id' => (int)$province['id'],
            'name_in_thai' => trim($province['name_in_thai'])
        ];
    }, $provinces);
    
    echo json_encode($cleanProvinces, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'ไม่สามารถโหลดข้อมูลจังหวัดได้'
    ], JSON_UNESCAPED_UNICODE);
}