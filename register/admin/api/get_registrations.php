<?php
require_once '../../config/database.php';
require_once '../check_auth.php';

header('Content-Type: application/json');

// Create database connection using the Database class
$database = new Database();
$pdo = $database->getConnection();

// Get request parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Calculate offset
$offset = ($page - 1) * $limit;

try {
    // Build where clause for search
    $whereClause = '';
    $params = [];
    
    if (!empty($search)) {
        $whereClause = " WHERE 
            r.fullname LIKE ? OR 
            r.organization LIKE ? OR 
            r.phone LIKE ? OR 
            r.email LIKE ? OR 
            r.line_id LIKE ?";
        
        $searchParam = "%{$search}%";
        $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM registrations r $whereClause";
    $countStmt = $pdo->prepare($countQuery);
    
    if (!empty($params)) {
        $countStmt->execute($params);
    } else {
        $countStmt->execute();
    }
    
    $total = $countStmt->fetchColumn();
    
    // Get registrations
    $query = "SELECT 
                r.*, 
                DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as formatted_date
              FROM registrations r
              $whereClause
              ORDER BY r.created_at DESC
              LIMIT :offset, :limit";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    
    if (!empty($params)) {
        $i = 1;
        foreach ($params as $param) {
            $stmt->bindValue($i, $param);
            $i++;
        }
    }
    
    $stmt->execute();
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'data' => [
            'registrations' => $registrations,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]
    ]);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}