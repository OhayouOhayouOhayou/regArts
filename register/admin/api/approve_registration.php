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
    
    // Check if registration exists and is not already approved
    $checkStmt = $pdo->prepare("SELECT id, is_approved FROM registrations WHERE id = ?");
    $checkStmt->execute([$registration_id]);
    $registration = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registration) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Registration not found'
        ]);
        exit;
    }
    
    if ($registration['is_approved'] == 1) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Registration already approved'
        ]);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update registration status
    $updateStmt = $pdo->prepare("
        UPDATE registrations 
        SET is_approved = 1,
            approved_at = NOW(),
            approved_by = ?
        WHERE id = ?
    ");
    
    $user_id = $_SESSION['user_id'];
    $updateStmt->execute([$user_id, $registration_id]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Registration approved successfully'
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