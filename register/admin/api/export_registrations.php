<?php
require_once '../../config/database.php';
require_once '../check_auth.php';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="registrations_export_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Get search parameter
$search = isset($_GET['search']) ? $_GET['search'] : '';

try {
    // Create database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Build query
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
    
    // Get registrations
    $query = "SELECT 
                r.id,
                r.title,
                r.title_other,
                r.fullname,
                r.organization,
                r.phone,
                r.email,
                r.line_id,
                DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as registration_date,
                r.payment_status,
                r.is_approved,
                DATE_FORMAT(r.approved_at, '%d/%m/%Y %H:%i') as approval_date
              FROM registrations r
              $whereClause
              ORDER BY r.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Output Excel content
    echo '<table border="1">';
    
    // Header row
    echo '<tr>';
    echo '<th>รหัส</th>';
    echo '<th>วันที่ลงทะเบียน</th>';
    echo '<th>คำนำหน้า</th>';
    echo '<th>ชื่อ-นามสกุล</th>';
    echo '<th>หน่วยงาน</th>';
    echo '<th>เบอร์โทร</th>';
    echo '<th>อีเมล</th>';
    echo '<th>Line ID</th>';
    echo '<th>การชำระเงิน</th>';
    echo '<th>สถานะการอนุมัติ</th>';
    echo '<th>วันที่อนุมัติ</th>';
    echo '</tr>';
    
    // Data rows
    foreach ($registrations as $row) {
        echo '<tr>';
        echo '<td>' . $row['id'] . '</td>';
        echo '<td>' . $row['registration_date'] . '</td>';
        echo '<td>' . ($row['title'] == 'อื่นๆ' ? $row['title_other'] : $row['title']) . '</td>';
        echo '<td>' . $row['fullname'] . '</td>';
        echo '<td>' . $row['organization'] . '</td>';
        echo '<td>' . $row['phone'] . '</td>';
        echo '<td>' . $row['email'] . '</td>';
        echo '<td>' . $row['line_id'] . '</td>';
        echo '<td>' . ($row['payment_status'] == 'paid' ? 'ชำระแล้ว' : 'ยังไม่ชำระ') . '</td>';
        echo '<td>' . ($row['is_approved'] ? 'อนุมัติแล้ว' : 'รอการอนุมัติ') . '</td>';
        echo '<td>' . $row['approval_date'] . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    
} catch (Exception $e) {
    // Output error as plain text
    header('Content-Type: text/plain');
    echo 'Error exporting data: ' . $e->getMessage();
}