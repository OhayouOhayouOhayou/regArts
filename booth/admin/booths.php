<?php
session_start();
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    // User is not logged in, redirect to login page
    header("Location: index.php");
    exit;
}

// Get admin info
$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'];
$admin_name = $_SESSION['admin_name'];
$admin_role = $_SESSION['admin_role'];

// Initialize variables
$message = '';
$messageType = '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$direction = isset($_GET['direction']) ? $_GET['direction'] : 'asc';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20; // Items per page
$offset = ($page - 1) * $limit;

// Process actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add new booth
    if (isset($_POST['action']) && $_POST['action'] === 'add_booth') {
        $zone = $_POST['zone'];
        $floor = $_POST['floor'];
        $location = $_POST['location'];
        $boothNumber = $_POST['booth_number'];
        $price = $_POST['price'];
        
        // Check if booth already exists
        $checkStmt = $conn->prepare("SELECT id FROM booths WHERE zone = ? AND booth_number = ?");
        $checkStmt->bind_param("si", $zone, $boothNumber);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $message = "บูธหมายเลข $boothNumber ในโซน $zone มีอยู่แล้ว";
            $messageType = "danger";
        } else {
            $stmt = $conn->prepare("INSERT INTO booths (booth_number, zone, floor, location, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isisi", $boothNumber, $zone, $floor, $location, $price);
            
            if ($stmt->execute()) {
                $message = "เพิ่มบูธใหม่เรียบร้อยแล้ว";
                $messageType = "success";
            } else {
                $message = "เกิดข้อผิดพลาด: " . $conn->error;
                $messageType = "danger";
            }
        }
    }
    // Update booth
    elseif (isset($_POST['action']) && $_POST['action'] === 'update_booth') {
        $id = $_POST['id'];
        $zone = $_POST['zone'];
        $floor = $_POST['floor'];
        $location = $_POST['location'];
        $price = $_POST['price'];
        $status = $_POST['status'];
        
        // เริ่ม transaction
        $conn->begin_transaction();
        
        try {
            // ดึงข้อมูลบูธและคำสั่งซื้อที่เกี่ยวข้อง
            $getDataStmt = $conn->prepare("
                SELECT oi.order_id, b.status AS current_status
                FROM booths b
                LEFT JOIN order_items oi ON b.id = oi.booth_id
                WHERE b.id = ?
            ");
            $getDataStmt->bind_param("i", $id);
            $getDataStmt->execute();
            $result = $getDataStmt->get_result();
            $data = $result->fetch_assoc();
            
            // กำหนด payment_status ให้สอดคล้องกับ status
            $payment_status = ($status === 'paid') ? 'paid' : (($status === 'pending_payment') ? 'pending' : 'unpaid');
            
            // อัพเดตข้อมูลบูธ
            $stmt = $conn->prepare("UPDATE booths SET zone = ?, floor = ?, location = ?, price = ?, status = ?, payment_status = ? WHERE id = ?");
            $stmt->bind_param("sisisss", $zone, $floor, $location, $price, $status, $payment_status, $id);
            $stmt->execute();
            
            // ถ้ามีการเปลี่ยนแปลงสถานะและมีคำสั่งซื้อที่เกี่ยวข้อง
            if (!empty($data['order_id']) && $data['current_status'] !== $status) {
                $orderId = $data['order_id'];
                
                // อัพเดตสถานะคำสั่งซื้อ
                $updateOrderStmt = $conn->prepare("
                    UPDATE orders 
                    SET payment_status = ?, 
                        note = CONCAT(IFNULL(note, ''), '\nอัพเดตสถานะเป็น ', ? ,' โดยแอดมินเมื่อ ', NOW()) 
                    WHERE id = ?
                ");
                $updateOrderStmt->bind_param("ssi", $payment_status, $payment_status, $orderId);
                $updateOrderStmt->execute();
            }
            
            // ยืนยัน transaction
            $conn->commit();
            
            $message = "อัปเดตข้อมูลบูธเรียบร้อยแล้ว";
            $messageType = "success";
        } catch (Exception $e) {
            // ยกเลิก transaction หากเกิดข้อผิดพลาด
            $conn->rollback();
            
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
            $messageType = "danger";
        }
    }
    // Delete booth
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete_booth') {
        $id = $_POST['id'];
        
        // Check if booth is reserved
        $checkStmt = $conn->prepare("SELECT status FROM booths WHERE id = ?");
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $boothStatus = $checkResult->fetch_assoc()['status'];
        
        if ($boothStatus !== 'available') {
            $message = "ไม่สามารถลบบูธที่มีการจองแล้วได้";
            $messageType = "danger";
        } else {
            $stmt = $conn->prepare("DELETE FROM booths WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = "ลบบูธเรียบร้อยแล้ว";
                $messageType = "success";
            } else {
                $message = "เกิดข้อผิดพลาด: " . $conn->error;
                $messageType = "danger";
            }
        }
    }
    // Reset booth status (make available)
    elseif (isset($_POST['action']) && $_POST['action'] === 'reset_booth') {
        $id = $_POST['id'];
        
        // เริ่ม transaction
        $conn->begin_transaction();
        
        try {
            // ตรวจสอบว่ามีข้อมูลใน order_items หรือไม่
            $getOrderStmt = $conn->prepare("
                SELECT oi.order_id, b.booth_number, b.zone, b.floor, b.status,
                       o.customer_name, o.customer_phone, o.customer_email, o.created_at
                FROM booths b
                LEFT JOIN order_items oi ON b.id = oi.booth_id
                LEFT JOIN orders o ON oi.order_id = o.id
                WHERE b.id = ?
            ");
            $getOrderStmt->bind_param("i", $id);
            $getOrderStmt->execute();
            $result = $getOrderStmt->get_result();
            $boothData = $result->fetch_assoc();
            
            if ($boothData) {
                // บันทึกข้อมูลการยกเลิกไว้ในประวัติ
                if (!empty($boothData['customer_name'])) {
                    $cancelledBy = 'admin';
                    $reason = 'รีเซ็ตโดยผู้ดูแลระบบ';
                    
                    $logStmt = $conn->prepare("INSERT INTO cancellation_logs 
                        (booth_id, booth_number, zone, floor, customer_name, customer_phone, customer_email, reserved_at, cancelled_by, cancellation_reason) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $logStmt->bind_param("iississsss", 
                        $id, 
                        $boothData['booth_number'], 
                        $boothData['zone'], 
                        $boothData['floor'], 
                        $boothData['customer_name'], 
                        $boothData['customer_phone'], 
                        $boothData['customer_email'], 
                        $boothData['created_at'], 
                        $cancelledBy, 
                        $reason
                    );
                    $logStmt->execute();
                }
                
                // อัพเดตสถานะใน orders ถ้ามี
                if (!empty($boothData['order_id'])) {
                    $orderId = $boothData['order_id'];
                    
                    // ตรวจสอบจำนวนบูธในออเดอร์นี้
                    $countBoothsStmt = $conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE order_id = ?");
                    $countBoothsStmt->bind_param("i", $orderId);
                    $countBoothsStmt->execute();
                    $boothCount = $countBoothsStmt->get_result()->fetch_assoc()['count'];
                    
                    if ($boothCount <= 1) {
                        // ถ้าเป็นบูธเดียวในออเดอร์ ให้อัพเดตสถานะออเดอร์เป็น cancelled
                        $updateOrderStmt = $conn->prepare("
                            UPDATE orders 
                            SET payment_status = 'cancelled', 
                                note = CONCAT(IFNULL(note, ''), '\nยกเลิกโดยแอดมินเมื่อ ', NOW()) 
                            WHERE id = ?
                        ");
                        $updateOrderStmt->bind_param("i", $orderId);
                        $updateOrderStmt->execute();
                        
                        // ลบรายการใน order_items
                        $deleteItemsStmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ? AND booth_id = ?");
                        $deleteItemsStmt->bind_param("ii", $orderId, $id);
                        $deleteItemsStmt->execute();
                    } else {
                        // ถ้ามีหลายบูธในออเดอร์เดียวกัน ให้ลบเฉพาะบูธที่ต้องการรีเซ็ต
                        $deleteItemStmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ? AND booth_id = ?");
                        $deleteItemStmt->bind_param("ii", $orderId, $id);
                        $deleteItemStmt->execute();
                    }
                }
            }
            
            // รีเซ็ตสถานะบูธ
            $resetBoothStmt = $conn->prepare("
                UPDATE booths 
                SET status = 'available', 
                    payment_status = 'unpaid',
                    reserved_by = NULL,
                    note = CONCAT(IFNULL(note, ''), '\nรีเซ็ตโดยผู้ดูแลระบบเมื่อ ', NOW()) 
                WHERE id = ?
            ");
            $resetBoothStmt->bind_param("i", $id);
            $resetBoothStmt->execute();
            
            // ยืนยัน transaction
            $conn->commit();
            
            $message = "รีเซ็ตสถานะบูธเรียบร้อยแล้ว";
            $messageType = "success";
        } catch (Exception $e) {
            // ยกเลิก transaction หากเกิดข้อผิดพลาด
            $conn->rollback();
            
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
            $messageType = "danger";
        }
    }// เพิ่มการประมวลผลสำหรับการอัพเดตสถานะการชำระเงิน
    elseif (isset($_POST['action']) && $_POST['action'] === 'update_payment_status') {
        $boothId = $_POST['booth_id'];
        $paymentStatus = $_POST['payment_status'];
        
        // เริ่ม transaction
        $conn->begin_transaction();
        
        try {
            // ดึงข้อมูลบูธและคำสั่งซื้อที่เกี่ยวข้อง
            $getDataStmt = $conn->prepare("
                SELECT oi.order_id, b.status
                FROM booths b
                LEFT JOIN order_items oi ON b.id = oi.booth_id
                WHERE b.id = ?
            ");
            $getDataStmt->bind_param("i", $boothId);
            $getDataStmt->execute();
            $result = $getDataStmt->get_result();
            $data = $result->fetch_assoc();
            
            if (!$data) {
                throw new Exception("ไม่พบข้อมูลบูธ");
            }
            
            // อัพเดตสถานะบูธ
            $boothStatus = ($paymentStatus === 'paid') ? 'paid' : (($paymentStatus === 'pending') ? 'pending_payment' : 'reserved');
            $updateBoothStmt = $conn->prepare("
                UPDATE booths 
                SET status = ?, 
                    payment_status = ?,
                    note = CONCAT(IFNULL(note, ''), '\nอัพเดตสถานะการชำระเงินเป็น ', ? ,' โดยแอดมินเมื่อ ', NOW()) 
                WHERE id = ?
            ");
            $updateBoothStmt->bind_param("sssi", $boothStatus, $paymentStatus, $paymentStatus, $boothId);
            $updateBoothStmt->execute();
            
            // อัพเดตสถานะคำสั่งซื้อถ้ามี
            if (!empty($data['order_id'])) {
                $orderId = $data['order_id'];
                
                $updateOrderStmt = $conn->prepare("
                    UPDATE orders 
                    SET payment_status = ?, 
                        note = CONCAT(IFNULL(note, ''), '\nอัพเดตสถานะการชำระเงินเป็น ', ? ,' โดยแอดมินเมื่อ ', NOW()) 
                    WHERE id = ?
                ");
                $updateOrderStmt->bind_param("ssi", $paymentStatus, $paymentStatus, $orderId);
                $updateOrderStmt->execute();
            }
            
            // ยืนยัน transaction
            $conn->commit();
            
            $message = "อัพเดตสถานะการชำระเงินเป็น " . ucfirst($paymentStatus) . " เรียบร้อยแล้ว";
            $messageType = "success";
        } catch (Exception $e) {
            // ยกเลิก transaction หากเกิดข้อผิดพลาด
            $conn->rollback();
            
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
            $messageType = "danger";
        }
    }
    // Bulk operations
    elseif (isset($_POST['action']) && $_POST['action'] === 'bulk_action') {
        if (isset($_POST['booth_ids']) && !empty($_POST['booth_ids'])) {
            $boothIds = $_POST['booth_ids'];
            $bulkAction = $_POST['bulk_action'];
            
            // Count successfully processed booths
            $successCount = 0;
            
            if ($bulkAction === 'delete') {
                foreach ($boothIds as $id) {
                    // Check if booth is reserved
                    $checkStmt = $conn->prepare("SELECT status FROM booths WHERE id = ?");
                    $checkStmt->bind_param("i", $id);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    $boothStatus = $checkResult->fetch_assoc()['status'];
                    
                    if ($boothStatus === 'available') {
                        $stmt = $conn->prepare("DELETE FROM booths WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        
                        if ($stmt->execute()) {
                            $successCount++;
                        }
                    }
                }
                
                $message = "ลบบูธจำนวน $successCount รายการเรียบร้อยแล้ว";
                $messageType = "success";
            }
            elseif ($bulkAction === 'reset') {
                foreach ($boothIds as $id) {
                    $stmt = $conn->prepare("UPDATE booths SET status = 'available', customer_name = NULL, customer_email = NULL, customer_phone = NULL, customer_company = NULL, reserved_at = NULL, payment_status = 'unpaid', payment_method = NULL, payment_reference = NULL, payment_amount = 0, payment_date = NULL, note = CONCAT(IFNULL(note, ''), '\nรีเซ็ตโดยผู้ดูแลระบบเมื่อ ', NOW()) WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    
                    if ($stmt->execute()) {
                        $successCount++;
                        
                        // Log cancellation
                        $getBoothStmt = $conn->prepare("SELECT booth_number, zone, floor, customer_name, customer_phone, customer_email, reserved_at FROM booths WHERE id = ?");
                        $getBoothStmt->bind_param("i", $id);
                        $getBoothStmt->execute();
                        $boothData = $getBoothStmt->get_result()->fetch_assoc();
                        
                        if (!empty($boothData['customer_name'])) {
                            $cancelledBy = 'admin';
                            $reason = 'รีเซ็ตโดยผู้ดูแลระบบ (การดำเนินการแบบกลุ่ม)';
                            
                            $logStmt = $conn->prepare("INSERT INTO cancellation_logs (booth_id, booth_number, zone, floor, customer_name, customer_phone, customer_email, reserved_at, cancelled_by, cancellation_reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $logStmt->bind_param("iississsss", $id, $boothData['booth_number'], $boothData['zone'], $boothData['floor'], $boothData['customer_name'], $boothData['customer_phone'], $boothData['customer_email'], $boothData['reserved_at'], $cancelledBy, $reason);
                            $logStmt->execute();
                        }
                    }
                }
                
                $message = "รีเซ็ตสถานะบูธจำนวน $successCount รายการเรียบร้อยแล้ว";
                $messageType = "success";
            }elseif ($bulkAction === 'update_price') {
                $newPrice = $_POST['bulk_price'];
                
                if (!empty($newPrice) && is_numeric($newPrice)) {
                    foreach ($boothIds as $id) {
                        $stmt = $conn->prepare("UPDATE booths SET price = ? WHERE id = ?");
                        $stmt->bind_param("di", $newPrice, $id);
                        
                        if ($stmt->execute()) {
                            $successCount++;
                        }
                    }
                    
                    $message = "อัปเดตราคาบูธจำนวน $successCount รายการเรียบร้อยแล้ว";
                    $messageType = "success";
                } else {
                    $message = "กรุณาระบุราคาที่ถูกต้อง";
                    $messageType = "danger";
                }
            }
        } else {
            $message = "กรุณาเลือกบูธที่ต้องการดำเนินการ";
            $messageType = "warning";
        }
    }
}

// Build query based on filters
$whereClause = "";
$params = [];
$types = "";

if ($filter === 'available') {
    $whereClause = "WHERE oi.booth_id IS NULL";
} elseif ($filter === 'reserved') {
    $whereClause = "WHERE oi.booth_id IS NOT NULL AND o.payment_status IN ('unpaid', 'pending')";
} elseif ($filter === 'paid') {
    $whereClause = "WHERE oi.booth_id IS NOT NULL AND o.payment_status = 'paid'";
} elseif ($filter === 'zone_a') {
    $whereClause = "WHERE b.zone = 'A'";
} elseif ($filter === 'zone_b') {
    $whereClause = "WHERE b.zone = 'B'";
} elseif ($filter === 'zone_c') {
    $whereClause = "WHERE b.zone = 'C'";
}

// Add search to where clause
if (!empty($search)) {
    $search = "%$search%";
    if (empty($whereClause)) {
        $whereClause = "WHERE (b.booth_number LIKE ? OR b.zone LIKE ? OR b.location LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)";
    } else {
        $whereClause .= " AND (b.booth_number LIKE ? OR b.zone LIKE ? OR b.location LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)";
    }
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sssss";
}

// Check valid sort columns
$validSortColumns = ['id', 'booth_number', 'zone', 'floor', 'price', 'status', 'reserved_at'];
if (!in_array($sort, $validSortColumns)) {
    $sort = 'id';
}

// Check valid directions
$validDirections = ['asc', 'desc'];
if (!in_array($direction, $validDirections)) {
    $direction = 'asc';
}

// Get total count for pagination - adjusted for join
$countQuery = "SELECT COUNT(DISTINCT b.id) as total 
               FROM booths b 
               LEFT JOIN order_items oi ON b.id = oi.booth_id 
               LEFT JOIN orders o ON oi.order_id = o.id 
               $whereClause";

if (!empty($params)) {
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalCount = $stmt->get_result()->fetch_assoc()['total'];
} else {
    $totalCount = $conn->query($countQuery)->fetch_assoc()['total'];
}

$totalPages = ceil($totalCount / $limit);

// Modified query to join with order_items and orders tables
$query = "SELECT b.*, oi.order_id, o.payment_status as order_payment_status, 
          o.customer_name, o.customer_email, o.customer_phone, o.customer_company, 
          o.created_at as order_created_at, o.payment_method, o.payment_reference, 
          o.total_amount, o.payment_date
          FROM booths b
          LEFT JOIN order_items oi ON b.id = oi.booth_id
          LEFT JOIN orders o ON oi.order_id = o.id
          $whereClause 
          ORDER BY b.$sort $direction 
          LIMIT $offset, $limit";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$booths = [];
while ($row = $result->fetch_assoc()) {
    $booths[] = $row;
}

// Helper function to format date/time
function formatDateTime($dateStr) {
    if (empty($dateStr)) return '-';
    $date = new DateTime($dateStr);
    return $date->format('d/m/Y H:i:s');
}

// Function to generate pagination URL
function paginationUrl($page, $filter, $sort, $direction, $search) {
    $url = "booths.php?page=$page";
    if ($filter !== 'all') $url .= "&filter=$filter";
    if ($sort !== 'id') $url .= "&sort=$sort";
    if ($direction !== 'asc') $url .= "&direction=$direction";
    if (!empty($search)) $url .= "&search=" . urlencode($search);
    return $url;
}

// Function to generate sort URL
function sortUrl($column, $currentSort, $currentDirection, $filter, $search) {
    $newDirection = ($column === $currentSort && $currentDirection === 'asc') ? 'desc' : 'asc';
    $url = "booths.php?sort=$column&direction=$newDirection";
    if ($filter !== 'all') $url .= "&filter=$filter";
    if (!empty($search)) $url .= "&search=" . urlencode($search);
    return $url;
}

// Updated function to get status badge based on order status
function getStatusBadge($boothStatus, $orderStatus, $hasOrder) {
    // If there's no order, show available status
    if (!$hasOrder) {
        return '<span class="badge bg-success">ว่าง</span>';
    }
    
    // Use order status to determine badge
    switch ($orderStatus) {
        case 'paid':
            return '<span class="badge bg-primary">ชำระแล้ว</span>';
        case 'pending':
            return '<span class="badge bg-warning">รอชำระเงิน</span>';
        case 'unpaid':
            return '<span class="badge bg-danger">จองแล้ว (ยังไม่ชำระ)</span>';
        case 'cancelled':
            return '<span class="badge bg-secondary">ยกเลิกแล้ว</span>';
        default:
            // Fallback to booth status if order status is not recognized
            switch ($boothStatus) {
                case 'available':
                    return '<span class="badge bg-success">ว่าง</span>';
                case 'reserved':
                    return '<span class="badge bg-danger">จองแล้ว</span>';
                case 'pending_payment':
                    return '<span class="badge bg-warning">รอชำระเงิน</span>';
                case 'paid':
                    return '<span class="badge bg-primary">ชำระแล้ว</span>';
                default:
                    return '<span class="badge bg-secondary">ไม่ทราบสถานะ</span>';
            }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการบูธ - ระบบจองบูธขายสินค้า</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f5f5f5;
        }
        
        .table-wrapper {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .booth-filters {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table th {
            font-weight: 500;
            white-space: nowrap;
        }
        
        .action-buttons {
            white-space: nowrap;
        }
        
        .sort-icon {
            display: inline-block;
            margin-left: 5px;
        }
        
        .form-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .pagination-container {
            margin-top: 20px;
        }
        
        .summary-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .stat-item {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            flex: 1;
            min-width: 150px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }
        .evidence-icon {
            color: #0d6efd;
            cursor: pointer;
            font-size: 1.5rem;
            transition: color 0.2s ease-in-out;
        }

        .evidence-icon:hover {
            color: #0a58ca;
            transform: scale(1.1);
        }

        .payment-modal-image {
            max-width: 100%;
            max-height: 70vh;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid #dee2e6;
        }

        #paymentEvidenceModal .modal-body {
            padding: 1.5rem;
        }

        #paymentEvidenceModal p {
            margin-bottom: 0.5rem;
        }

        #paymentEvidenceModal .row {
            margin-bottom: 1rem;
        }

        /* Add a loading spinner while image loads */
        #payment-evidence-image.loading {
            min-height: 300px;
            background: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzgiIGhlaWdodD0iMzgiIHZpZXdCb3g9IjAgMCAzOCAzOCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiBzdHJva2U9IiM5ZTllOWUiPiAgICA8ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPiAgICAgICAgPGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMSAxKSIgc3Ryb2tlLXdpZHRoPSIyIj4gICAgICAgICAgICA8Y2lyY2xlIHN0cm9rZS1vcGFjaXR5PSIuNSIgY3g9IjE4IiBjeT0iMTgiIHI9IjE4Ii8+ICAgICAgICAgICAgPHBhdGggZD0iTTM2IDE4YzAtOS45NC04LjA2LTE4LTE4LTE4Ij4gICAgICAgICAgICAgICAgPGFuaW1hdGVUcmFuc2Zvcm0gICAgICAgICAgICAgICAgICAgIGF0dHJpYnV0ZU5hbWU9InRyYW5zZm9ybSIgICAgICAgICAgICAgICAgICAgIHR5cGU9InJvdGF0ZSIgICAgICAgICAgICAgICAgICAgIGZyb209IjAgMTggMTgiICAgICAgICAgICAgICAgICAgICB0bz0iMzYwIDE4IDE4IiAgICAgICAgICAgICAgICAgICAgZHVyPSIxcyIgICAgICAgICAgICAgICAgICAgIHJlcGVhdENvdW50PSJpbmRlZmluaXRlIi8+ICAgICAgICAgICAgPC9wYXRoPiAgICAgICAgPC9nPiAgICA8L2c+PC9zdmc+') center center no-repeat;
        }

        /* Ensure modal appears on top */
        #paymentEvidenceModal {
            z-index: 1060;
        }

        /* Additional mobile responsive adjustments */
        @media (max-width: 576px) {
            .evidence-icon {
                font-size: 1.25rem;
            }
            
            #paymentEvidenceModal .col-md-6 {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-shop me-2"></i>ระบบจองบูธขายสินค้า
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>แดชบอร์ด
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="booths.php">
                            <i class="bi bi-grid me-1"></i>จัดการบูธ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="bi bi-receipt me-1"></i>คำสั่งซื้อ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="bi bi-bar-chart me-1"></i>รายงาน
                        </a>
                    </li>
                    <?php if ($admin_role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="bi bi-gear me-1"></i>ตั้งค่า
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex">
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($admin_name); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>โปรไฟล์</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-grid me-2"></i>
                จัดการบูธ
            </h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBoothModal">
                <i class="bi bi-plus-circle me-1"></i>เพิ่มบูธใหม่
            </button>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Summary statistics -->
        <div class="summary-stats">
            <?php
            // Get stats
            $totalQuery = "SELECT COUNT(*) as total FROM booths";
            $availableQuery = "SELECT COUNT(*) as count FROM booths b LEFT JOIN order_items oi ON b.id = oi.booth_id WHERE oi.booth_id IS NULL";
            $reservedQuery = "SELECT COUNT(*) as count FROM booths b JOIN order_items oi ON b.id = oi.booth_id JOIN orders o ON oi.order_id = o.id WHERE o.payment_status IN ('unpaid', 'pending')";
            $paidQuery = "SELECT COUNT(*) as count FROM booths b JOIN order_items oi ON b.id = oi.booth_id JOIN orders o ON oi.order_id = o.id WHERE o.payment_status = 'paid'";
            
            $totalStat = $conn->query($totalQuery)->fetch_assoc()['total'];
            $availableStat = $conn->query($availableQuery)->fetch_assoc()['count'];
            $reservedStat = $conn->query($reservedQuery)->fetch_assoc()['count'];
            $paidStat = $conn->query($paidQuery)->fetch_assoc()['count'];
            ?>
            <div class="stat-item">
                <div class="stat-value"><?php echo $totalStat; ?></div>
                <div class="stat-label">บูธทั้งหมด</div>
            </div>
            <div class="stat-item">
                <div class="stat-value text-success"><?php echo $availableStat; ?></div>
                <div class="stat-label">บูธว่าง</div>
            </div>
            <div class="stat-item">
                <div class="stat-value text-warning"><?php echo $reservedStat; ?></div>
                <div class="stat-label">บูธจองแล้ว</div>
            </div>
            <div class="stat-item">
                <div class="stat-value text-primary"><?php echo $paidStat; ?></div>
                <div class="stat-label">บูธชำระแล้ว</div>
            </div>
        </div>
        
        <div class="booth-filters">
            <div class="row">
                <div class="col-md-8">
                    <form method="get" class="row g-3">
                        <div class="col-md-4">
                            <label for="filter" class="form-label">กรองตามสถานะ</label>
                            <select class="form-select" id="filter" name="filter" onchange="this.form.submit()">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                                <option value="available" <?php echo $filter === 'available' ? 'selected' : ''; ?>>บูธว่าง</option>
                                <option value="reserved" <?php echo $filter === 'reserved' ? 'selected' : ''; ?>>บูธจองแล้ว</option>
                                <option value="paid" <?php echo $filter === 'paid' ? 'selected' : ''; ?>>บูธชำระแล้ว</option>
                                <option value="zone_a" <?php echo $filter === 'zone_a' ? 'selected' : ''; ?>>โซน A</option>
                                <option value="zone_b" <?php echo $filter === 'zone_b' ? 'selected' : ''; ?>>โซน B</option>
                                <option value="zone_c" <?php echo $filter === 'zone_c' ? 'selected' : ''; ?>>โซน C</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="search" class="form-label">ค้นหา</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" name="search" placeholder="ค้นหาตามหมายเลขบูธ, โซน, ชื่อลูกค้า..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                        <input type="hidden" name="direction" value="<?php echo $direction; ?>">
                    </form>
                </div>
                <div class="col-md-4">
                    <form id="bulkActionForm" method="post" class="row g-3">
                        <input type="hidden" name="action" value="bulk_action">
                        <div class="col-md-7">
                            <label for="bulk_action" class="form-label">การดำเนินการแบบกลุ่ม</label>
                            <select class="form-select" id="bulk_action" name="bulk_action">
                                <option value="">เลือกการดำเนินการ...</option>
                                <option value="reset">รีเซ็ตสถานะบูธเป็นว่าง</option>
                                <option value="update_price">อัปเดตราคา</option>
                                <option value="delete">ลบบูธ (เฉพาะที่ว่างเท่านั้น)</option>
                            </select>
                        </div>
                        <div class="col-md-5 price-field-container d-none">
                            <label for="bulk_price" class="form-label">ราคาใหม่</label>
                            <input type="number" class="form-control" id="bulk_price" name="bulk_price" step="0.01" min="0">
                        </div>
                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-outline-primary" id="applyBulkAction" disabled>
                                <i class="bi bi-check-circle me-1"></i>ดำเนินการ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="table-wrapper">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="5%">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th width="5%">
                                <a href="<?php echo sortUrl('id', $sort, $direction, $filter, $search); ?>" class="text-dark text-decoration-none">
                                    ID
                                    <?php if ($sort === 'id'): ?>
                                    <i class="bi bi-arrow-<?php echo $direction === 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th width="8%">
                                <a href="<?php echo sortUrl('zone', $sort, $direction, $filter, $search); ?>" class="text-dark text-decoration-none">
                                    โซน
                                    <?php if ($sort === 'zone'): ?>
                                    <i class="bi bi-arrow-<?php echo $direction === 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th width="10%">
                                <a href="<?php echo sortUrl('booth_number', $sort, $direction, $filter, $search); ?>" class="text-dark text-decoration-none">
                                    หมายเลขบูธ
                                    <?php if ($sort === 'booth_number'): ?>
                                    <i class="bi bi-arrow-<?php echo $direction === 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th width="8%">
                                <a href="<?php echo sortUrl('floor', $sort, $direction, $filter, $search); ?>" class="text-dark text-decoration-none">
                                    ชั้น
                                    <?php if ($sort === 'floor'): ?>
                                    <i class="bi bi-arrow-<?php echo $direction === 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th width="12%">
                                ตำแหน่ง
                            </th>
                            <th width="10%">
                                <a href="<?php echo sortUrl('price', $sort, $direction, $filter, $search); ?>" class="text-dark text-decoration-none">
                                    ราคา
                                    <?php if ($sort === 'price'): ?>
                                    <i class="bi bi-arrow-<?php echo $direction === 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th width="10%">
                                <a href="<?php echo sortUrl('status', $sort, $direction, $filter, $search); ?>" class="text-dark text-decoration-none">
                                    สถานะ
                                    <?php if ($sort === 'status'): ?>
                                    <i class="bi bi-arrow-<?php echo $direction === 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th width="12%">ข้อมูลการจอง</th>
                            <th width="10%">หลักฐานการชำระเงิน</th>
                            <th width="15%">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($booths)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-4">ไม่พบข้อมูลบูธ</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($booths as $booth): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input booth-checkbox" name="booth_ids[]" value="<?php echo $booth['id']; ?>" form="bulkActionForm">
                            </td>
                            <td><?php echo $booth['id']; ?></td>
                            <td><?php echo htmlspecialchars($booth['zone']); ?></td>
                            <td><?php echo htmlspecialchars($booth['booth_number']); ?></td>
                            <td><?php echo htmlspecialchars($booth['floor']); ?></td>
                            <td><?php echo htmlspecialchars($booth['location'] ?? '-'); ?></td>
                            <td><?php echo formatCurrency($booth['price']); ?></td>
                            <td>
                                <?php 
                                // Determine if booth has an order
                                $hasOrder = !empty($booth['order_id']);
                                // Display badge based on order status if it exists
                                echo getStatusBadge($booth['status'], $booth['order_payment_status'], $hasOrder); 
                                ?>
                            </td>
                            <td>
                                <?php if ($hasOrder): ?>
                                <small>
                                    <div><strong><?php echo htmlspecialchars($booth['customer_name'] ?? '-'); ?></strong></div>
                                    <div><?php echo htmlspecialchars($booth['customer_phone'] ?? '-'); ?></div>
                                    <div class="text-muted"><?php echo formatDateTime($booth['order_created_at']); ?></div>
                                </small>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($hasOrder && $booth['order_payment_status'] === 'paid' && !empty($booth['payment_reference'])): ?>
                                    <i class="bi bi-image evidence-icon" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#paymentEvidenceModal" 
                                    data-payment-reference="../<?php echo htmlspecialchars($booth['payment_reference']); ?>"
                                    data-booth-number="โซน <?php echo htmlspecialchars($booth['zone']); ?> หมายเลข <?php echo htmlspecialchars($booth['booth_number']); ?>"
                                    data-customer-name="<?php echo htmlspecialchars($booth['customer_name']); ?>"
                                    data-payment-date="<?php echo formatDateTime($booth['payment_date']); ?>"
                                    data-payment-amount="<?php echo formatCurrency($booth['total_amount']); ?>"
                                    data-payment-method="<?php echo htmlspecialchars($booth['payment_method']); ?>"
                                    title="คลิกเพื่อดูหลักฐานการชำระเงิน">
                                    </i>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-buttons">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editBoothModal" 
                                        data-id="<?php echo $booth['id']; ?>"
                                        data-zone="<?php echo htmlspecialchars($booth['zone']); ?>"
                                        data-number="<?php echo htmlspecialchars($booth['booth_number']); ?>"
                                        data-floor="<?php echo htmlspecialchars($booth['floor']); ?>"
                                        data-location="<?php echo htmlspecialchars($booth['location'] ?? ''); ?>"
                                        data-price="<?php echo htmlspecialchars($booth['price']); ?>"
                                        data-status="<?php echo htmlspecialchars($booth['status']); ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                
                                <?php if ($hasOrder): ?>
                                <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetBoothModal" data-id="<?php echo $booth['id']; ?>">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php if (!$hasOrder): ?>
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteBoothModal" data-id="<?php echo $booth['id']; ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination-container d-flex justify-content-between align-items-center">
                <div>
                    แสดง <?php echo $offset + 1; ?> ถึง <?php echo min($offset + $limit, $totalCount); ?> จากทั้งหมด <?php echo $totalCount; ?> รายการ
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination mb-0">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo paginationUrl(1, $filter, $sort, $direction, $search); ?>" aria-label="First">
                                <span aria-hidden="true">&laquo;&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo paginationUrl($page - 1, $filter, $sort, $direction, $search); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($startPage + 4, $totalPages);
                        
                        if ($endPage - $startPage < 4 && $startPage > 1) {
                            $startPage = max(1, $endPage - 4);
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo paginationUrl($i, $filter, $sort, $direction, $search); ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo paginationUrl($page + 1, $filter, $sort, $direction, $search); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo paginationUrl($totalPages, $filter, $sort, $direction, $search); ?>" aria-label="Last">
                                <span aria-hidden="true">&raquo;&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Payment Evidence Modal -->
    <div class="modal fade" id="paymentEvidenceModal" tabindex="-1" aria-labelledby="paymentEvidenceModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="paymentEvidenceModalLabel">หลักฐานการชำระเงิน</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row mb-3">
              <div class="col-md-8 mx-auto text-center">
                <img id="payment-evidence-image" src="" class="img-fluid rounded payment-modal-image" alt="หลักฐานการชำระเงิน">
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <p><strong>บูธ:</strong> <span id="evidence-booth-number"></span></p>
                <p><strong>ชื่อลูกค้า:</strong> <span id="evidence-customer-name"></span></p>
              </div>
              <div class="col-md-6">
                <p><strong>วันที่ชำระ:</strong> <span id="evidence-payment-date"></span></p>
                <p><strong>จำนวนเงิน:</strong> <span id="evidence-payment-amount"></span></p>
                <p><strong>ช่องทางชำระเงิน:</strong> <span id="evidence-payment-method"></span></p>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Add Booth Modal -->
    <div class="modal fade" id="addBoothModal" tabindex="-1" aria-labelledby="addBoothModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBoothModalLabel">เพิ่มบูธใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addBoothForm" method="post">
                        <input type="hidden" name="action" value="add_booth">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="zone" class="form-label">โซน</label>
                                <select class="form-select" id="zone" name="zone" required>
                                    <option value="A">โซน A</option>
                                    <option value="B">โซน B</option>
                                    <option value="C">โซน C</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="booth_number" class="form-label">หมายเลขบูธ</label>
                                <input type="number" class="form-control" id="booth_number" name="booth_number" required min="1">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="floor" class="form-label">ชั้น</label>
                                <input type="number" class="form-control" id="floor" name="floor" value="1" min="1">
                            </div>
                            <div class="col-md-6">
                                <label for="price" class="form-label">ราคา</label>
                                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="location" class="form-label">ตำแหน่ง/รายละเอียด</label>
                            <input type="text" class="form-control" id="location" name="location">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" form="addBoothForm" class="btn btn-primary">บันทึก</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Booth Modal -->
    <div class="modal fade" id="editBoothModal" tabindex="-1" aria-labelledby="editBoothModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBoothModalLabel">แก้ไขข้อมูลบูธ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editBoothForm" method="post">
                        <input type="hidden" name="action" value="update_booth">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_zone" class="form-label">โซน</label>
                                <select class="form-select" id="edit_zone" name="zone" required>
                                    <option value="A">โซน A</option>
                                    <option value="B">โซน B</option>
                                    <option value="C">โซน C</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_booth_number" class="form-label">หมายเลขบูธ</label>
                                <input type="number" class="form-control" id="edit_booth_number" name="booth_number" required min="1" readonly>
                                <small class="form-text text-muted">ไม่สามารถแก้ไขหมายเลขบูธได้</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_floor" class="form-label">ชั้น</label>
                                <input type="number" class="form-control" id="edit_floor" name="floor" value="1" min="1">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_price" class="form-label">ราคา</label>
                                <input type="number" class="form-control" id="edit_price" name="price" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_location" class="form-label">ตำแหน่ง/รายละเอียด</label>
                            <input type="text" class="form-control" id="edit_location" name="location">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">สถานะ</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="available">ว่าง</option>
                                <option value="reserved">จองแล้ว</option>
                                <option value="pending_payment">รอชำระเงิน</option>
                                <option value="paid">ชำระแล้ว</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" form="editBoothForm" class="btn btn-primary">บันทึก</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Booth Modal -->
    <div class="modal fade" id="deleteBoothModal" tabindex="-1" aria-labelledby="deleteBoothModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteBoothModalLabel">ยืนยันการลบบูธ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>คำเตือน:</strong> การลบบูธจะไม่สามารถกู้คืนได้ และสามารถลบได้เฉพาะบูธที่ว่างเท่านั้น
                    </div>
                    <p>คุณต้องการลบบูธนี้ใช่หรือไม่?</p>
                    <form id="deleteBoothForm" method="post">
                        <input type="hidden" name="action" value="delete_booth">
                        <input type="hidden" name="id" id="delete_id">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" form="deleteBoothForm" class="btn btn-danger">ยืนยันการลบ</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reset Booth Modal -->
    <div class="modal fade" id="resetBoothModal" tabindex="-1" aria-labelledby="resetBoothModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetBoothModalLabel">ยืนยันการรีเซ็ตบูธ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>คำเตือน:</strong> การรีเซ็ตบูธจะล้างข้อมูลการจองและทำให้บูธกลับมาว่างพร้อมจองใหม่
                    </div>
                    <p>คุณต้องการรีเซ็ตบูธนี้ใช่หรือไม่?</p>
                    <form id="resetBoothForm" method="post">
                        <input type="hidden" name="action" value="reset_booth">
                        <input type="hidden" name="id" id="reset_id">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" form="resetBoothForm" class="btn btn-warning">ยืนยันการรีเซ็ต</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Edit Booth Modal
            const editBoothModal = document.getElementById('editBoothModal');
            if (editBoothModal) {
                editBoothModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const id = button.getAttribute('data-id');
                    const zone = button.getAttribute('data-zone');
                    const number = button.getAttribute('data-number');
                    const floor = button.getAttribute('data-floor');
                    const location = button.getAttribute('data-location');
                    const price = button.getAttribute('data-price');
                    const status = button.getAttribute('data-status');
                    
                    const modalId = editBoothModal.querySelector('#edit_id');
                    const modalZone = editBoothModal.querySelector('#edit_zone');
                    const modalNumber = editBoothModal.querySelector('#edit_booth_number');
                    const modalFloor = editBoothModal.querySelector('#edit_floor');
                    const modalLocation = editBoothModal.querySelector('#edit_location');
                    const modalPrice = editBoothModal.querySelector('#edit_price');
                    const modalStatus = editBoothModal.querySelector('#edit_status');
                    
                    modalId.value = id;
                    modalZone.value = zone;
                    modalNumber.value = number;
                    modalFloor.value = floor;
                    modalLocation.value = location;
                    modalPrice.value = price;
                    modalStatus.value = status;
                });
            }
            
            // Delete Booth Modal
            const deleteBoothModal = document.getElementById('deleteBoothModal');
            if (deleteBoothModal) {
                deleteBoothModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const id = button.getAttribute('data-id');
                    
                    const modalId = deleteBoothModal.querySelector('#delete_id');
                    modalId.value = id;
                });
            }
            
            // Reset Booth Modal
            const resetBoothModal = document.getElementById('resetBoothModal');
            if (resetBoothModal) {
                resetBoothModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const id = button.getAttribute('data-id');
                    
                    const modalId = resetBoothModal.querySelector('#reset_id');
                    modalId.value = id;
                });
            }
            
            // Select All Checkboxes
            const selectAllCheckbox = document.getElementById('selectAll');
            const boothCheckboxes = document.querySelectorAll('.booth-checkbox');
            
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    boothCheckboxes.forEach(checkbox => {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                    
                    updateBulkActionButton();
                });
            }
            
            if (boothCheckboxes.length > 0) {
                boothCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        updateBulkActionButton();
                        
                        // Check if all checkboxes are checked
                        const allChecked = [...boothCheckboxes].every(box => box.checked);
                        if (selectAllCheckbox) {
                            selectAllCheckbox.checked = allChecked;
                        }
                    });
                });
            }
            
            // Bulk Action Button
            const applyBulkButton = document.getElementById('applyBulkAction');
            const bulkActionSelect = document.getElementById('bulk_action');
            const priceFieldContainer = document.querySelector('.price-field-container');
            
            if (bulkActionSelect) {
                bulkActionSelect.addEventListener('change', function() {
                    updateBulkActionButton();
                    
                    // Show/hide price field
                    if (bulkActionSelect.value === 'update_price') {
                        priceFieldContainer.classList.remove('d-none');
                    } else {
                        priceFieldContainer.classList.add('d-none');
                    }
                });
            }
            
            // Update Bulk Action Button state
            function updateBulkActionButton() {
                if (applyBulkButton) {
                    const hasCheckedBoxes = [...boothCheckboxes].some(box => box.checked);
                    const hasSelectedAction = bulkActionSelect && bulkActionSelect.value !== '';
                    
                    applyBulkButton.disabled = !(hasCheckedBoxes && hasSelectedAction);
                }
            }
            
            // Payment Evidence Modal
            const paymentEvidenceModal = document.getElementById('paymentEvidenceModal');
            if (paymentEvidenceModal) {
                paymentEvidenceModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    
                    // Get data attributes
                    const paymentReference = button.getAttribute('data-payment-reference');
                    const boothNumber = button.getAttribute('data-booth-number');
                    const customerName = button.getAttribute('data-customer-name');
                    const paymentDate = button.getAttribute('data-payment-date');
                    const paymentAmount = button.getAttribute('data-payment-amount');
                    const paymentMethod = button.getAttribute('data-payment-method');
                    
                    // Translate payment method to Thai
                    let paymentMethodThai = paymentMethod;
                    if (paymentMethod === 'bank_transfer') {
                        paymentMethodThai = 'โอนเงินผ่านธนาคาร';
                    } else if (paymentMethod === 'credit_card') {
                        paymentMethodThai = 'บัตรเครดิต';
                    } else if (paymentMethod === 'qr_code') {
                        paymentMethodThai = 'แสกน QR Code';
                    } else if (paymentMethod === 'prompt_pay') {
                        paymentMethodThai = 'พร้อมเพย์';
                    }
                    
                    // Set values in the modal
                    document.getElementById('payment-evidence-image').src = paymentReference;
                    document.getElementById('evidence-booth-number').textContent = boothNumber;
                    document.getElementById('evidence-customer-name').textContent = customerName;
                    document.getElementById('evidence-payment-date').textContent = paymentDate;
                    document.getElementById('evidence-payment-amount').textContent = paymentAmount;
                    document.getElementById('evidence-payment-method').textContent = paymentMethodThai;
                });
            }
            
            // Image loading and error handling for payment evidence
            const paymentImage = document.getElementById('payment-evidence-image');
            
            if (paymentImage && paymentEvidenceModal) {
                // Add loading class when image starts loading
                paymentEvidenceModal.addEventListener('show.bs.modal', function() {
                    paymentImage.classList.add('loading');
                });
                
                // Remove loading class when image is loaded
                paymentImage.addEventListener('load', function() {
                    paymentImage.classList.remove('loading');
                });
                
                // Handle image loading errors
                paymentImage.addEventListener('error', function() {
                    paymentImage.classList.remove('loading');
                    paymentImage.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNMTAgMTRMMTIgMTJNMTIgMTJMMTQgMTBNMTIgMTJMMTAgMTBNMTIgMTJMMTQgMTRNMjEgMTJDMjEgMTYuOTcwNiAxNi45NzA2IDIxIDEyIDIxQzcuMDI5NDQgMjEgMyAxNi45NzA2IDMgMTJDMyA3LjAyOTQ0IDcuMDI5NDQgMyAxMiAzQzE2Ljk3MDYgMyAyMSA3LjAyOTQ0IDIxIDEyWiIgc3Ryb2tlPSIjZDMzYzNjIiBzdHJva2Utd2lkdGg9IjIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIvPjwvc3ZnPg==';
                    console.error('Image failed to load');
                });
            }
        });
    </script>
</body>
</html>