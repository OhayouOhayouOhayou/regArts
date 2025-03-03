<?php
require_once '../../config/database.php';
require_once '../check_auth.php';

header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get registration ID
$registration_id = isset($_POST['registration_id']) ? (int)$_POST['registration_id'] : 0;

if ($registration_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid registration ID'
    ]);
    exit;
}

try {
    // Create database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Delete related records from registration_addresses
    $stmt = $pdo->prepare("DELETE FROM registration_addresses WHERE registration_id = ?");
    $stmt->execute([$registration_id]);
    
    // Delete related records from registration_documents
    $stmt = $pdo->prepare("DELETE FROM registration_documents WHERE registration_id = ?");
    $stmt->execute([$registration_id]);
    
    // Delete related records from registration_files
    $stmt = $pdo->prepare("DELETE FROM registration_files WHERE registration_id = ?");
    $stmt->execute([$registration_id]);
    
    // Delete the registration
    $stmt = $pdo->prepare("DELETE FROM registrations WHERE id = ?");
    $stmt->execute([$registration_id]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Registration deleted successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}