<?php
require_once '../../config/database.php';
require_once '../check_auth.php';

header('Content-Type: application/json');

// Create database connection using the Database class
$database = new Database();
$pdo = $database->getConnection();

// Get province ID from request
$province_id = isset($_GET['province_id']) ? intval($_GET['province_id']) : 0;

if ($province_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // Get districts for the selected province
    $stmt = $pdo->prepare("
        SELECT id, code, name_in_thai, name_in_english
        FROM districts
        WHERE province_id = ?
        ORDER BY name_in_thai
    ");
    
    $stmt->execute([$province_id]);
    $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($districts);
    
} catch (Exception $e) {
    // Return empty array on error
    echo json_encode([]);
}