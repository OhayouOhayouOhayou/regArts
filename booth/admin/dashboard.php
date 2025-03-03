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

// Get dashboard statistics
// Total booths
$totalBoothsQuery = "SELECT COUNT(*) as total FROM booths";
$totalBoothsResult = $conn->query($totalBoothsQuery);
$totalBooths = $totalBoothsResult->fetch_assoc()['total'];

// Available booths
$availableBoothsQuery = "SELECT COUNT(*) as available FROM booths WHERE status = 'available'";
$availableBoothsResult = $conn->query($availableBoothsQuery);
$availableBooths = $availableBoothsResult->fetch_assoc()['available'];

// Reserved booths
$reservedBoothsQuery = "SELECT COUNT(*) as reserved FROM booths WHERE status IN ('reserved', 'pending_payment')";
$reservedBoothsResult = $conn->query($reservedBoothsQuery);
$reservedBooths = $reservedBoothsResult->fetch_assoc()['reserved'];

// Paid booths
$paidBoothsQuery = "SELECT COUNT(*) as paid FROM booths WHERE status = 'paid'";
$paidBoothsResult = $conn->query($paidBoothsQuery);
$paidBooths = $paidBoothsResult->fetch_assoc()['paid'];

// Total income
$totalIncomeQuery = "SELECT SUM(oi.price) as total FROM order_items oi 
                     JOIN orders o ON oi.order_id = o.id 
                     WHERE o.payment_status = 'paid'";
$totalIncomeResult = $conn->query($totalIncomeQuery);
$totalIncome = $totalIncomeResult->fetch_assoc()['total'] ?? 0;

// Pending payments
$pendingPaymentsQuery = "SELECT COUNT(*) as pending FROM orders WHERE payment_status = 'pending'";
$pendingPaymentsResult = $conn->query($pendingPaymentsQuery);
$pendingPayments = $pendingPaymentsResult->fetch_assoc()['pending'];

// Latest orders
$latestOrdersQuery = "SELECT o.id, o.order_number, o.customer_name, o.customer_phone, 
                      o.total_amount, o.payment_status, o.created_at, 
                      COUNT(oi.id) as total_booths
                      FROM orders o
                      LEFT JOIN order_items oi ON o.id = oi.order_id
                      GROUP BY o.id
                      ORDER BY o.created_at DESC
                      LIMIT 5";
$latestOrdersResult = $conn->query($latestOrdersQuery);
$latestOrders = [];
while ($row = $latestOrdersResult->fetch_assoc()) {
    $latestOrders[] = $row;
}

// Recent payment uploads
// แก้ไขเพื่อใช้ booth_id แทน order_id ตามโครงสร้างจริงของตาราง payment_files
$recentPaymentsQuery = "SELECT pf.*, b.booth_number, b.zone, b.customer_name
                       FROM payment_files pf
                       LEFT JOIN booths b ON pf.booth_id = b.id
                       ORDER BY pf.uploaded_at DESC
                       LIMIT 5";
try {
    $recentPaymentsResult = $conn->query($recentPaymentsQuery);
    $recentPayments = [];
    if ($recentPaymentsResult) {
        while ($row = $recentPaymentsResult->fetch_assoc()) {
            $recentPayments[] = $row;
        }
    }
} catch (Exception $e) {
    // กรณีเกิด error ให้กำหนดเป็นอาร์เรย์ว่าง
    $recentPayments = [];
}

// Booth availability by zone
$zoneStatsQuery = "SELECT zone, 
                  SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                  SUM(CASE WHEN status IN ('reserved', 'pending_payment') THEN 1 ELSE 0 END) as reserved,
                  SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
                  COUNT(*) as total
                  FROM booths
                  GROUP BY zone
                  ORDER BY zone";
$zoneStatsResult = $conn->query($zoneStatsQuery);
$zoneStats = [];
while ($row = $zoneStatsResult->fetch_assoc()) {
    $zoneStats[] = $row;
}

// Helper function to format date/time
function formatDateTime($dateStr) {
    $date = new DateTime($dateStr);
    return $date->format('d/m/Y H:i:s');
}

// Helper function to format currency
function formatCurrencyDisplay($amount) {
    return number_format($amount, 2) . ' บาท';
}

function getPaymentStatusLabel($status) {
    switch ($status) {
        case 'paid':
            return '<span class="badge bg-success">ชำระแล้ว</span>';
        case 'pending':
            return '<span class="badge bg-warning">รอตรวจสอบ</span>';
        case 'unpaid':
            return '<span class="badge bg-danger">ยังไม่ชำระเงิน</span>';
        default:
            return '<span class="badge bg-secondary">ไม่ทราบสถานะ</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ด - ระบบจองบูธขายสินค้า</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f5f5f5;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            display: inline-flex;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
            color: white;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .bg-primary-light {
            background-color: #e6f2ff;
        }
        
        .bg-success-light {
            background-color: #e6fff2;
        }
        
        .bg-warning-light {
            background-color: #fffbe6;
        }
        
        .bg-danger-light {
            background-color: #ffe6e6;
        }
        
        .content-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .recent-payment-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .progress-thin {
            height: 8px;
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
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>แดชบอร์ด
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="booths.php">
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
            <h2><i class="bi bi-speedometer2 me-2"></i>แดชบอร์ด</h2>
            <div class="text-muted">
                วันที่ปัจจุบัน: <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card bg-primary-light">
                    <div class="stat-icon bg-primary">
                        <i class="bi bi-shop"></i>
                    </div>
                    <div class="stat-value"><?php echo $totalBooths; ?></div>
                    <div class="stat-label">บูธทั้งหมด</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-success-light">
                    <div class="stat-icon bg-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $paidBooths; ?></div>
                    <div class="stat-label">บูธที่ชำระเงินแล้ว</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-warning-light">
                    <div class="stat-icon bg-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stat-value"><?php echo $reservedBooths; ?></div>
                    <div class="stat-label">บูธที่จองแล้ว</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-danger-light">
                    <div class="stat-icon bg-danger">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div class="stat-value"><?php echo formatCurrencyDisplay($totalIncome); ?></div>
                    <div class="stat-label">รายได้ทั้งหมด</div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-lg-8">
                <div class="content-card">
                    <h3 class="card-title">
                        <i class="bi bi-bar-chart-fill me-2"></i>สถานะบูธตามโซน
                    </h3>
                    
                    <?php foreach ($zoneStats as $zone): ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <div>
                                <strong>โซน <?php echo $zone['zone']; ?></strong>
                            </div>
                            <div>
                                <span class="badge bg-secondary"><?php echo $zone['total']; ?> บูธ</span>
                            </div>
                        </div>
                        <div class="progress progress-thin">
                            <?php
                            $availablePercent = ($zone['available'] / $zone['total']) * 100;
                            $reservedPercent = ($zone['reserved'] / $zone['total']) * 100;
                            $paidPercent = ($zone['paid'] / $zone['total']) * 100;
                            ?>
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $paidPercent; ?>%" aria-valuenow="<?php echo $paidPercent; ?>" aria-valuemin="0" aria-valuemax="100" title="ชำระแล้ว: <?php echo $zone['paid']; ?> บูธ"></div>
                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $reservedPercent; ?>%" aria-valuenow="<?php echo $reservedPercent; ?>" aria-valuemin="0" aria-valuemax="100" title="จองแล้ว: <?php echo $zone['reserved']; ?> บูธ"></div>
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $availablePercent; ?>%" aria-valuenow="<?php echo $availablePercent; ?>" aria-valuemin="0" aria-valuemax="100" title="ว่าง: <?php echo $zone['available']; ?> บูธ"></div>
                        </div>
                        <div class="row text-center mt-2 small">
                            <div class="col-4">
                                <span class="badge bg-primary">ว่าง: <?php echo $zone['available']; ?> บูธ</span>
                            </div>
                            <div class="col-4">
                                <span class="badge bg-warning">จองแล้ว: <?php echo $zone['reserved']; ?> บูธ</span>
                            </div>
                            <div class="col-4">
                                <span class="badge bg-success">ชำระแล้ว: <?php echo $zone['paid']; ?> บูธ</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-4">
                        <a href="booths.php" class="btn btn-outline-primary">
                            <i class="bi bi-grid me-1"></i>จัดการบูธทั้งหมด
                        </a>
                    </div>
                </div>
                
                <div class="content-card mt-4">
                    <h3 class="card-title">
                        <i class="bi bi-receipt me-2"></i>คำสั่งซื้อล่าสุด
                    </h3>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>หมายเลขคำสั่งซื้อ</th>
                                    <th>ลูกค้า</th>
                                    <th>จำนวนบูธ</th>
                                    <th>ยอดรวม</th>
                                    <th>สถานะ</th>
                                    <th>วันที่</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($latestOrders)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">ไม่มีคำสั่งซื้อล่าสุด</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($latestOrders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['customer_phone']); ?></small>
                                    </td>
                                    <td><?php echo $order['total_booths']; ?></td>
                                    <td><?php echo formatCurrencyDisplay($order['total_amount']); ?></td>
                                    <td><?php echo getPaymentStatusLabel($order['payment_status']); ?></td>
                                    <td><?php echo formatDateTime($order['created_at']); ?></td>
                                    <td>
                                        <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="orders.php" class="btn btn-outline-primary">
                            <i class="bi bi-receipt me-1"></i>ดูคำสั่งซื้อทั้งหมด
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="content-card">
                    <h3 class="card-title">
                        <i class="bi bi-bell me-2"></i>การแจ้งเตือน
                    </h3>
                    
                    <?php if ($pendingPayments > 0): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong><?php echo $pendingPayments; ?> รายการ</strong> รอการตรวจสอบชำระเงิน
                        <div class="mt-2">
                            <a href="orders.php?filter=pending" class="btn btn-sm btn-warning">ตรวจสอบ</a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (empty($recentPayments)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        ไม่มีหลักฐานการชำระเงินใหม่
                    </div>
                    <?php else: ?>
                    <h5 class="mb-3">หลักฐานการชำระเงินล่าสุด</h5>
                    <?php foreach ($recentPayments as $payment): ?>
                    <div class="d-flex align-items-center mb-3 p-2 border rounded">
                        <?php if (in_array($payment['file_type'], ['image/jpeg', 'image/png', 'image/jpg'])): ?>
                        <img src="../<?php echo htmlspecialchars($payment['file_path']); ?>" alt="Payment slip" class="recent-payment-img me-3">
                        <?php else: ?>
                        <div class="recent-payment-img me-3 d-flex justify-content-center align-items-center bg-light">
                            <i class="bi bi-file-earmark-text fs-4"></i>
                        </div>
                        <?php endif; ?>
                        <div>
                            <div><strong><?php echo htmlspecialchars($payment['customer_name'] ?? 'ไม่ระบุชื่อ'); ?></strong></div>
                            <div class="small text-muted">บูธ: โซน <?php echo htmlspecialchars($payment['zone'] ?? '-'); ?> #<?php echo htmlspecialchars($payment['booth_number'] ?? '-'); ?></div>
                            <div class="small text-muted"><?php echo isset($payment['uploaded_at']) ? formatDateTime($payment['uploaded_at']) : '-'; ?></div>
                        </div>
                        <div class="ms-auto">
                            <a href="booth-detail.php?id=<?php echo $payment['booth_id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="content-card mt-4">
                    <h3 class="card-title">
                        <i class="bi bi-link-45deg me-2"></i>ลิงก์ด่วน
                    </h3>
                    
                    <div class="d-grid gap-2">
                        <a href="booths.php?action=create" class="btn btn-outline-primary">
                            <i class="bi bi-plus-circle me-2"></i>เพิ่มบูธใหม่
                        </a>
                        <a href="reports.php?type=income" class="btn btn-outline-success">
                            <i class="bi bi-cash-stack me-2"></i>รายงานรายได้
                        </a>
                        <a href="../index.php" target="_blank" class="btn btn-outline-secondary">
                            <i class="bi bi-box-arrow-up-right me-2"></i>ไปยังหน้าเว็บไซต์
                        </a>
                        <?php if ($admin_role === 'admin'): ?>
                        <a href="settings.php" class="btn btn-outline-dark">
                            <i class="bi bi-gear me-2"></i>ตั้งค่าระบบ
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-light py-3 mt-5">
        <div class="container text-center">
            <p class="text-muted mb-0">ระบบจองบูธขายสินค้า &copy; <?php echo date('Y'); ?></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>