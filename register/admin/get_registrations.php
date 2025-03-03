<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'check_auth.php';
require_once '../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // รับค่าและทำความสะอาดข้อมูล
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // สร้างเงื่อนไขการค้นหา
    $whereClause = '';
    $params = [];
    
    if (!empty($search)) {
        $whereClause = "WHERE fullname LIKE ? OR organization LIKE ? OR phone LIKE ? OR email LIKE ?";
        $searchTerm = "%{$search}%";
        $params = array_fill(0, 4, $searchTerm);
    }
    
    // นับจำนวนทั้งหมด
    $countQuery = "SELECT COUNT(*) FROM registrations " . $whereClause;
    $stmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $totalRecords = $stmt->fetchColumn();
    
    // ดึงข้อมูล
    $query = "
        SELECT r.*, 
               CASE 
                   WHEN r.payment_status = 'paid' AND r.is_approved = 1 THEN 'approved'
                   WHEN r.payment_status = 'paid' THEN 'pending_approval'
                   ELSE 'not_paid'
               END as status
        FROM registrations r 
        {$whereClause}
        ORDER BY r.created_at DESC 
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key + 1, $value);
        }
    }
    
    $stmt->execute();
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'registrations' => $registrations,
            'totalPages' => ceil($totalRecords / $perPage),
            'currentPage' => $page
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get_registrations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage()
    ]);
}