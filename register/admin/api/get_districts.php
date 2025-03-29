<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $provinceId = $_GET['province_id'] ?? null;
    if (!$provinceId) {
        throw new Exception('Province ID is required');
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT id, name_in_thai FROM districts WHERE province_id = ? ORDER BY name_in_thai");
    $stmt->execute([$provinceId]);
    $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($districts);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>