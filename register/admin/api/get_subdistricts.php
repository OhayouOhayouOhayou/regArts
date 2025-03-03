<?php
require_once '../../config/database.php';
require_once '../check_auth.php';

header('Content-Type: application/json');

// Create database connection using the Database class
$database = new Database();
$pdo = $database->getConnection();

// Get district ID from request
$district_id = isset($_GET['district_id']) ? intval($_GET['district_id']) : 0;

if ($district_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // Get subdistricts for the selected district
    $stmt = $pdo->prepare("
        SELECT id, code, name_in_thai, name_in_english, zip_code
        FROM subdistricts
        WHERE district_id = ?
        ORDER BY name_in_thai
    ");
    
    $stmt->execute([$district_id]);
    $subdistricts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($subdistricts);
    
} catch (Exception $e) {
    // Return empty array on error
    echo json_encode([]);
}