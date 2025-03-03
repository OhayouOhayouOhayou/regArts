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

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: orders.php");
    exit;
}

$order_id = intval($_GET['id']);

// Get order details
$orderQuery = "SELECT o.*, 
                  COUNT(oi.id) AS total_booths,
                  SUM(oi.price) AS order_total 
               FROM orders o
               LEFT JOIN order_items oi ON o.id = oi.order_id
               WHERE o.id = ?
               GROUP BY o.id";

$stmt = $conn->prepare($orderQuery);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$orderResult = $stmt->get_result();

if ($orderResult->num_rows === 0) {
    // Order not found, redirect
    header("Location: orders.php");
    exit;
}

$order = $orderResult->fetch_assoc();

// Get ordered booths
$boothsQuery = "SELECT oi.*, b.booth_number, b.zone, b.floor, b.location, b.status
                FROM order_items oi
                JOIN booths b ON oi.booth_id = b.id
                WHERE oi.order_id = ?";

$stmt = $conn->prepare($boothsQuery);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$booths = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get payment files
$filesQuery = "SELECT pf.* 
              FROM payment_files pf
              WHERE pf.booth_id IN (
                SELECT oi.booth_id FROM order_items oi WHERE oi.order_id = ?
              )
              ORDER BY pf.uploaded_at DESC";

$stmt = $conn->prepare($filesQuery);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$paymentFiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        
        // Update payment status
        if ($_POST['action'] === 'update_payment') {
            $payment_status = $_POST['payment_status'];
            $note = $_POST['note'] ?? '';
            
            $updateQuery = "UPDATE orders SET 
                           payment_status = ?,
                           note = ?,
                           updated_at = NOW()
                           WHERE id = ?";
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ssi", $payment_status, $note, $order_id);
            
            if ($stmt->execute()) {
                // Also update booth statuses if needed
                if ($payment_status === 'paid') {
                    $updateBoothsQuery = "UPDATE booths b
                                         JOIN order_items oi ON b.id = oi.booth_id
                                         SET b.status = 'paid',
                                             b.payment_status = 'paid',
                                             b.payment_date = NOW(),
                                             b.updated_at = NOW()
                                         WHERE oi.order_id = ?";
                    
                    $stmt = $conn->prepare($updateBoothsQuery);
                    $stmt->bind_param("i", $order_id);
                    $stmt->execute();
                } elseif ($payment_status === 'pending') {
                    $updateBoothsQuery = "UPDATE booths b
                                         JOIN order_items oi ON b.id = oi.booth_id
                                         SET b.status = 'pending_payment',
                                             b.payment_status = 'pending',
                                             b.updated_at = NOW()
                                         WHERE oi.order_id = ?";
                    
                    $stmt = $conn->prepare($updateBoothsQuery);
                    $stmt->bind_param("i", $order_id);
                    $stmt->execute();
                }
                
                $message = "อัปเดตสถานะการชำระเงินเรียบร้อยแล้ว";
                $messageType = "success";
                
                // Refresh order data
                $stmt = $conn->prepare($orderQuery);
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $order = $stmt->get_result()->fetch_assoc();
            } else {
                $message = "เกิดข้อผิดพลาดในการอัปเดต: " . $conn->error;
                $messageType = "danger";
            }
        }
        
        // Cancel order
        elseif ($_POST['action'] === 'cancel_order') {
            $reason = $_POST['cancellation_reason'] ?? '';
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // First log cancellations for each booth
                $getBoothsQuery = "SELECT b.id, b.booth_number, b.zone, b.floor, b.customer_name, 
                                   b.customer_phone, b.customer_email, b.reserved_at
                                 FROM booths b
                                 JOIN order_items oi ON b.id = oi.booth_id
                                 WHERE oi.order_id = ?";
                
                $stmt = $conn->prepare($getBoothsQuery);
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $boothsToCancel = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                $cancelledBy = 'admin';
                
                foreach ($boothsToCancel as $booth) {
                    $logQuery = "INSERT INTO cancellation_logs 
                              (booth_id, order_id, booth_number, zone, floor, customer_name, 
                               customer_phone, customer_email, reserved_at, cancelled_by, cancellation_reason)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $conn->prepare($logQuery);
                    $stmt->bind_param("iisssssssss", 
                        $booth['id'], $order_id, $booth['booth_number'], $booth['zone'], $booth['floor'],
                        $booth['customer_name'], $booth['customer_phone'], $booth['customer_email'],
                        $booth['reserved_at'], $cancelledBy, $reason
                    );
                    $stmt->execute();
                }
                
                // Then update booth statuses
                $updateBoothsQuery = "UPDATE booths b
                                     JOIN order_items oi ON b.id = oi.booth_id
                                     SET b.status = 'available',
                                         b.customer_name = NULL,
                                         b.customer_email = NULL,
                                         b.customer_phone = NULL,
                                         b.customer_company = NULL,
                                         b.reserved_at = NULL,
                                         b.payment_status = 'unpaid',
                                         b.payment_method = NULL,
                                         b.payment_reference = NULL,
                                         b.payment_amount = 0,
                                         b.payment_date = NULL,
                                         b.note = CONCAT(IFNULL(b.note, ''), '\nยกเลิกการจองโดยผู้ดูแลระบบเมื่อ ', NOW()),
                                         b.updated_at = NOW()
                                     WHERE oi.order_id = ?";
                
                $stmt = $conn->prepare($updateBoothsQuery);
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                
                // Finally update order status
                $updateOrderQuery = "UPDATE orders 
                                   SET payment_status = 'unpaid',
                                       note = CONCAT(IFNULL(note, ''), '\nยกเลิกโดยผู้ดูแลระบบเมื่อ ', NOW(), '. เหตุผล: ', ?),
                                       updated_at = NOW()
                                   WHERE id = ?";
                
                $stmt = $conn->prepare($updateOrderQuery);
                $stmt->bind_param("si", $reason, $order_id);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $message = "ยกเลิกคำสั่งซื้อเรียบร้อยแล้ว";
                $messageType = "success";
                
                // Refresh order data
                $stmt = $conn->prepare($orderQuery);
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $order = $stmt->get_result()->fetch_assoc();
                
                // Refresh booths data
                $stmt = $conn->prepare($boothsQuery);
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $booths = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
            } catch (Exception $e) {
                // Roll back transaction on error
                $conn->rollback();
                $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
                $messageType = "danger";
            }
        }
    }
}

// Helper function to format date/time
function formatDateTime($dateStr) {
    if (empty($dateStr)) return '-';
    $date = new DateTime($dateStr);
    return $date->format('d/m/Y H:i:s');
}

// ลบฟังก์ชัน formatCurrency เพื่อแก้ปัญหาประกาศซ้ำซ้อน
// ใช้ฟังก์ชันจาก config.php แทน

// Get payment status label class
function getStatusClass($status) {
    switch ($status) {
        case 'paid':
            return 'success';
        case 'pending':
            return 'warning';
        case 'unpaid':
            return 'danger';
        default:
            return 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดคำสั่งซื้อ #<?php echo $order['order_number']; ?> - ระบบจองบูธขายสินค้า</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f5f5f5;
        }
        
        .content-wrapper {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .section-title {
            border-bottom: 2px solid #f5f5f5;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .order-meta {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .booth-item {
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 4px;
            background-color: #f8f9fa;
        }
        
        .payment-slip {
            max-width: 300px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
            margin-bottom: 15px;
        }
        
        .payment-slip img {
            max-width: 100%;
            height: auto;
        }
        
        .customer-info {
            background-color: #e9f5ff;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .action-buttons {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
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
                        <a class="nav-link" href="booths.php">
                            <i class="bi bi-grid me-1"></i>จัดการบูธ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="orders.php">
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
                <i class="bi bi-receipt me-2"></i>
                รายละเอียดคำสั่งซื้อ #<?php echo htmlspecialchars($order['order_number']); ?>
            </h2>
            <a href="orders.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>กลับไปหน้ารายการคำสั่งซื้อ
            </a>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="content-wrapper">
                    <h4 class="section-title">ข้อมูลคำสั่งซื้อ</h4>
                    
                    <div class="row order-meta">
                        <div class="col-md-6">
                            <p><strong>หมายเลขคำสั่งซื้อ:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                            <p><strong>วันที่สร้าง:</strong> <?php echo formatDateTime($order['created_at']); ?></p>
                            <p><strong>อัปเดตล่าสุด:</strong> <?php echo formatDateTime($order['updated_at']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p>
                                <strong>สถานะการชำระเงิน:</strong> 
                                <span class="badge bg-<?php echo getStatusClass($order['payment_status']); ?>">
                                    <?php echo $order['payment_status'] === 'paid' ? 'ชำระแล้ว' : 
                                          ($order['payment_status'] === 'pending' ? 'รอตรวจสอบ' : 'ยังไม่ชำระ'); ?>
                                </span>
                            </p>
                            <p><strong>วิธีการชำระเงิน:</strong> <?php echo empty($order['payment_method']) ? '-' : htmlspecialchars($order['payment_method']); ?></p>
                            <p><strong>วันที่ชำระเงิน:</strong> <?php echo empty($order['payment_date']) ? '-' : formatDateTime($order['payment_date']); ?></p>
                        </div>
                    </div>
                    
                    <h5 class="mt-4 mb-3">ข้อมูลลูกค้า</h5>
                    <div class="customer-info">
                        <p><strong>ชื่อ-นามสกุล:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                        <p><strong>อีเมล:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                        <p><strong>เบอร์โทรศัพท์:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                        <p><strong>บริษัท/ร้านค้า:</strong> <?php echo empty($order['customer_company']) ? '-' : htmlspecialchars($order['customer_company']); ?></p>
                    </div>
                    
                    <h5 class="mt-4 mb-3">บูธที่จอง</h5>
                    <?php foreach ($booths as $booth): ?>
                    <div class="booth-item">
                        <div class="row">
                            <div class="col-md-8">
                                <p class="mb-1">
                                    <strong>บูธ:</strong> 
                                    <?php echo htmlspecialchars("โซน {$booth['zone']} หมายเลข {$booth['booth_number']} (ชั้น {$booth['floor']})"); ?>
                                </p>
                                <p class="mb-1"><strong>ตำแหน่ง:</strong> <?php echo htmlspecialchars($booth['location']); ?></p>
                                <p class="mb-0">
                                    <strong>สถานะ:</strong>
                                    <span class="badge bg-<?php echo getStatusClass($booth['status'] === 'paid' ? 'paid' : ($booth['status'] === 'pending_payment' ? 'pending' : 'danger')); ?>">
                                        <?php echo $booth['status'] === 'paid' ? 'ชำระแล้ว' : 
                                              ($booth['status'] === 'pending_payment' ? 'รอชำระเงิน' : 'จองแล้ว'); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <p class="mb-0"><strong>ราคา:</strong> <?php echo formatCurrency($booth['price']); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="row mt-4">
                        <div class="col-md-8">
                            <p><strong>จำนวนบูธทั้งหมด:</strong> <?php echo count($booths); ?> บูธ</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <p><strong>ยอดรวมทั้งสิ้น:</strong> <?php echo formatCurrency($order['total_amount']); ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($order['note'])): ?>
                    <div class="mt-4">
                        <h5>หมายเหตุ</h5>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(htmlspecialchars($order['note'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="action-buttons">
                        <form id="updatePaymentForm" method="post" class="mb-3">
                            <input type="hidden" name="action" value="update_payment">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="payment_status" class="form-label">อัปเดตสถานะการชำระเงิน</label>
                                        <select class="form-select" id="payment_status" name="payment_status">
                                            <option value="unpaid" <?php echo $order['payment_status'] === 'unpaid' ? 'selected' : ''; ?>>ยังไม่ชำระเงิน</option>
                                            <option value="pending" <?php echo $order['payment_status'] === 'pending' ? 'selected' : ''; ?>>รอตรวจสอบการชำระเงิน</option>
                                            <option value="paid" <?php echo $order['payment_status'] === 'paid' ? 'selected' : ''; ?>>ชำระเงินแล้ว</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="note" class="form-label">หมายเหตุเพิ่มเติม</label>
                                        <textarea class="form-control" id="note" name="note" rows="3"><?php echo htmlspecialchars($order['note']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button>
                        </form>
                        
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                            <i class="bi bi-x-circle me-1"></i>ยกเลิกคำสั่งซื้อ
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="content-wrapper">
                    <h4 class="section-title">หลักฐานการชำระเงิน</h4>
                    
                    <?php if (empty($paymentFiles)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>ยังไม่มีการอัพโหลดหลักฐานการชำระเงิน
                    </div>
                    <?php else: ?>
                    
                    <?php foreach ($paymentFiles as $file): ?>
                    <div class="payment-slip">
                        <?php if (in_array($file['file_type'], ['image/jpeg', 'image/png', 'image/jpg'])): ?>
                            <a href="../<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank">
                                <img src="../<?php echo htmlspecialchars($file['file_path']); ?>" alt="หลักฐานการชำระเงิน">
                            </a>
                        <?php elseif ($file['file_type'] === 'application/pdf'): ?>
                            <div class="d-grid">
                                <a href="../<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="btn btn-outline-primary">
                                    <i class="bi bi-file-earmark-pdf me-2"></i>ดูไฟล์ PDF
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="d-grid">
                                <a href="../<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="btn btn-outline-secondary">
                                    <i class="bi bi-file-earmark me-2"></i>ดูไฟล์
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="text-center mt-2">
                            <small class="text-muted">อัพโหลดเมื่อ: <?php echo formatDateTime($file['uploaded_at']); ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php endif; ?>
                </div>
                
                <div class="content-wrapper mt-4">
                    <h4 class="section-title">การดำเนินการ</h4>
                    
                    <div class="d-grid gap-2">
                        <a href="print-order.php?id=<?php echo $order_id; ?>" target="_blank" class="btn btn-outline-primary">
                            <i class="bi bi-printer me-2"></i>พิมพ์ใบสั่งซื้อ
                        </a>
                        <a href="mailto:<?php echo $order['customer_email']; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-envelope me-2"></i>ส่งอีเมลถึงลูกค้า
                        </a>
                        <a href="tel:<?php echo $order['customer_phone']; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-telephone me-2"></i>โทรหาลูกค้า
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal ยกเลิกคำสั่งซื้อ -->
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelOrderModalLabel">ยืนยันการยกเลิกคำสั่งซื้อ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>คำเตือน:</strong> การยกเลิกคำสั่งซื้อจะทำให้การจองบูธทั้งหมดในคำสั่งซื้อนี้ถูกยกเลิกและบูธจะกลับมาว่างพร้อมจองใหม่
                    </div>
                    <form id="cancelOrderForm" method="post">
                        <input type="hidden" name="action" value="cancel_order">
                        <div class="mb-3">
                            <label for="cancellation_reason" class="form-label">เหตุผลในการยกเลิก:</label>
                            <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="3" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="submit" form="cancelOrderForm" class="btn btn-danger">ยืนยันการยกเลิก</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>