<?php
require_once '../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    $province = $_GET['province'] ?? '';
    $district = $_GET['district'] ?? '';
    $firstName = $_GET['firstName'] ?? '';
    $lastName = $_GET['lastName'] ?? '';
    $phone = $_GET['phone'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $whereClauses = [];
    $params = [];
    
    if ($province) {
        $whereClauses[] = "ra.province_id = ?";
        $params[] = $province;
    }
    if ($district) {
        $whereClauses[] = "ra.district_id = ?";
        $params[] = $district;
    }
    if ($firstName) {
        $whereClauses[] = "r.fullname LIKE ?";
        $params[] = "%$firstName%";
    }
    if ($lastName) {
        $whereClauses[] = "r.fullname LIKE ?";
        $params[] = "%$lastName%";
    }
    if ($phone) {
        $whereClauses[] = "r.phone LIKE ?";
        $params[] = "%$phone%";
    }
    if ($status) {
        switch ($status) {
            case 'approved':
                $whereClauses[] = "r.is_approved = 1";
                break;
            case 'pending':
                $whereClauses[] = "r.is_approved = 0";
                break;
            case 'paid':
                $whereClauses[] = "r.payment_status = 'paid'";
                break;
            case 'not_paid':
                $whereClauses[] = "r.payment_status = 'not_paid'";
                break;
        }
    }
    if ($search) {
        $whereClauses[] = "(r.fullname LIKE ? OR r.email LIKE ? OR r.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereSql = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);
    
    $stmt = $conn->prepare("
        SELECT r.*, ra.address, p.name_in_thai as province_name, d.name_in_thai as district_name, s.name_in_thai as subdistrict_name, ra.zipcode
        FROM registrations r
        LEFT JOIN registration_addresses ra ON r.id = ra.registration_id
        LEFT JOIN provinces p ON ra.province_id = p.id
        LEFT JOIN districts d ON ra.district_id = d.id
        LEFT JOIN subdistricts s ON ra.subdistrict_id = s.id
        $whereSql
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM registrations r
        LEFT JOIN registration_addresses ra ON r.id = ra.registration_id
        $whereSql
    ");
    $countStmt->execute(array_slice($params, 0, -2));
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'registrations' => $registrations,
            'total' => $total
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}