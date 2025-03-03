<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $districtId = $_GET['district_id'] ?? null;
    if (!$districtId) {
        throw new Exception('District ID is required');
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT id, name_in_thai FROM subdistricts WHERE district_id = ? ORDER BY name_in_thai");
    $stmt->execute([$districtId]);
    $subdistricts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($subdistricts);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>