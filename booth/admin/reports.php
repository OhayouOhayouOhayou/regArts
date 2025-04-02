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
$zone_filter = isset($_GET['zone_filter']) ? $_GET['zone_filter'] : 'all';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 50; // Show more items per page for reports
$offset = ($page - 1) * $limit;
$export = isset($_GET['export']) && $_GET['export'] === 'csv';

// Process export to CSV if requested
if ($export) {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="booth_report_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add CSV headers
    fputcsv($output, [
        'ID',
        'โซน',
        'หมายเลขบูธ',
        'ชั้น',
        'ตำแหน่ง',
        'ราคา',
        'สถานะ',
        'ชื่อลูกค้า',
        'เบอร์โทร',
        'อีเมล',
        'บริษัท',
        'วันที่จอง',
        'จำนวนเงิน',
        'วันที่ชำระเงิน',
        'ช่องทางชำระเงิน'
    ]);
    
    // Skip pagination for export - get all data
    $limit = 10000;
    $offset = 0;
}

// Build query based on filters
$whereClause = "";
$params = [];
$types = "";

if ($zone_filter !== 'all') {
    $whereClause = "WHERE b.zone = ?";
    $params[] = $zone_filter;
    $types .= "s";
}

// Add status filter
if ($status_filter !== 'all') {
    if ($status_filter === 'available') {
        if (empty($whereClause)) {
            $whereClause = "WHERE oi.booth_id IS NULL";
        } else {
            $whereClause .= " AND oi.booth_id IS NULL";
        }
    } elseif ($status_filter === 'unpaid') {
        if (empty($whereClause)) {
            $whereClause = "WHERE oi.booth_id IS NOT NULL AND o.payment_status = 'unpaid'";
        } else {
            $whereClause .= " AND oi.booth_id IS NOT NULL AND o.payment_status = 'unpaid'";
        }
    } elseif ($status_filter === 'pending') {
        if (empty($whereClause)) {
            $whereClause = "WHERE oi.booth_id IS NOT NULL AND o.payment_status = 'pending'";
        } else {
            $whereClause .= " AND oi.booth_id IS NOT NULL AND o.payment_status = 'pending'";
        }
    } elseif ($status_filter === 'paid') {
        if (empty($whereClause)) {
            $whereClause = "WHERE oi.booth_id IS NOT NULL AND o.payment_status = 'paid'";
        } else {
            $whereClause .= " AND oi.booth_id IS NOT NULL AND o.payment_status = 'paid'";
        }
    }
}

// Add search to where clause
if (!empty($search)) {
    $search = "%$search%";
    if (empty($whereClause)) {
        $whereClause = "WHERE (b.booth_number LIKE ? OR b.zone LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.customer_company LIKE ?)";
    } else {
        $whereClause .= " AND (b.booth_number LIKE ? OR b.zone LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.customer_company LIKE ?)";
    }
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sssss";
}

// Get total count for pagination
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

// Get report data
$query = "SELECT b.id, b.zone, b.booth_number, b.floor, b.location, b.price, 
          oi.order_id, o.payment_status, o.customer_name, o.customer_phone, 
          o.customer_email, o.customer_company, o.created_at as order_date, 
          o.total_amount, o.payment_date, o.payment_method
          FROM booths b
          LEFT JOIN order_items oi ON b.id = oi.booth_id
          LEFT JOIN orders o ON oi.order_id = o.id
          $whereClause 
          ORDER BY b.zone, b.booth_number 
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

// Process CSV export
if ($export && !empty($booths)) {
    foreach ($booths as $booth) {
        // Format data for CSV
        $hasOrder = !empty($booth['order_id']);
        $status = $hasOrder ? 
            ($booth['payment_status'] === 'paid' ? 'ชำระแล้ว' : 
            ($booth['payment_status'] === 'pending' ? 'รอชำระเงิน' : 
            ($booth['payment_status'] === 'unpaid' ? 'จองแล้ว (ยังไม่ชำระ)' : 'จองแล้ว'))) : 
            'ว่าง';
        
        // Format payment method in Thai
        $paymentMethod = '';
        if (!empty($booth['payment_method'])) {
            switch ($booth['payment_method']) {
                case 'bank_transfer':
                    $paymentMethod = 'โอนเงินผ่านธนาคาร';
                    break;
                case 'credit_card':
                    $paymentMethod = 'บัตรเครดิต';
                    break;
                case 'qr_code':
                    $paymentMethod = 'แสกน QR Code';
                    break;
                case 'prompt_pay':
                    $paymentMethod = 'พร้อมเพย์';
                    break;
                default:
                    $paymentMethod = $booth['payment_method'];
            }
        }
        
        // Format dates
        $orderDate = !empty($booth['order_date']) ? date('d/m/Y H:i:s', strtotime($booth['order_date'])) : '';
        $paymentDate = !empty($booth['payment_date']) ? date('d/m/Y H:i:s', strtotime($booth['payment_date'])) : '';
        
        fputcsv($output, [
            $booth['id'],
            $booth['zone'],
            $booth['booth_number'],
            $booth['floor'],
            $booth['location'],
            $booth['price'],
            $status,
            $booth['customer_name'] ?? '',
            $booth['customer_phone'] ?? '',
            $booth['customer_email'] ?? '',
            $booth['customer_company'] ?? '',
            $orderDate,
            $booth['total_amount'] ?? '',
            $paymentDate,
            $paymentMethod
        ]);
    }
    
    // Close the output stream
    fclose($output);
    exit;
}

// Helper function to format date/time
function formatDateTime($dateStr) {
    if (empty($dateStr)) return '-';
    $date = new DateTime($dateStr);
    return $date->format('d/m/Y H:i:s');
}

// Function to generate pagination URL
function paginationUrl($page, $zone_filter, $status_filter, $search) {
    $url = "reports.php?page=$page";
    if ($zone_filter !== 'all') $url .= "&zone_filter=$zone_filter";
    if ($status_filter !== 'all') $url .= "&status_filter=$status_filter";
    if (!empty($search)) $url .= "&search=" . urlencode($search);
    return $url;
}

// Function to generate export URL
function exportUrl($zone_filter, $status_filter, $search) {
    $url = "reports.php?export=csv";
    if ($zone_filter !== 'all') $url .= "&zone_filter=$zone_filter";
    if ($status_filter !== 'all') $url .= "&status_filter=$status_filter";
    if (!empty($search)) $url .= "&search=" . urlencode($search);
    return $url;
}

// Function to get status badge based on order status
function getStatusBadge($orderStatus, $hasOrder) {
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
            return '<span class="badge bg-secondary">ไม่ทราบสถานะ</span>';
    }
}

// Calculate summary stats
$totalStat = $totalCount;
$availableStat = 0;
$unpaidStat = 0;
$pendingStat = 0;
$paidStat = 0;
$totalRevenue = 0;
$actualRevenue = 0;

// Get stats with separate queries to be more efficient
$availableQuery = "SELECT COUNT(*) as count FROM booths b LEFT JOIN order_items oi ON b.id = oi.booth_id WHERE oi.booth_id IS NULL";
if ($zone_filter !== 'all') {
    $availableQuery .= " AND b.zone = '$zone_filter'";
}
$availableStat = $conn->query($availableQuery)->fetch_assoc()['count'];

$unpaidQuery = "SELECT COUNT(*) as count FROM booths b JOIN order_items oi ON b.id = oi.booth_id JOIN orders o ON oi.order_id = o.id WHERE o.payment_status = 'unpaid'";
if ($zone_filter !== 'all') {
    $unpaidQuery .= " AND b.zone = '$zone_filter'";
}
$unpaidStat = $conn->query($unpaidQuery)->fetch_assoc()['count'];

$pendingQuery = "SELECT COUNT(*) as count FROM booths b JOIN order_items oi ON b.id = oi.booth_id JOIN orders o ON oi.order_id = o.id WHERE o.payment_status = 'pending'";
if ($zone_filter !== 'all') {
    $pendingQuery .= " AND b.zone = '$zone_filter'";
}
$pendingStat = $conn->query($pendingQuery)->fetch_assoc()['count'];

$paidQuery = "SELECT COUNT(*) as count FROM booths b JOIN order_items oi ON b.id = oi.booth_id JOIN orders o ON oi.order_id = o.id WHERE o.payment_status = 'paid'";
if ($zone_filter !== 'all') {
    $paidQuery .= " AND b.zone = '$zone_filter'";
}
$paidStat = $conn->query($paidQuery)->fetch_assoc()['count'];

// Calculate potential revenue (all booked booths) and actual revenue (paid booths)
$revenueQuery = "SELECT 
    SUM(CASE WHEN o.id IS NOT NULL THEN b.price ELSE 0 END) as potential_revenue,
    SUM(CASE WHEN o.payment_status = 'paid' THEN b.price ELSE 0 END) as actual_revenue
    FROM booths b
    LEFT JOIN order_items oi ON b.id = oi.booth_id
    LEFT JOIN orders o ON oi.order_id = o.id";
if ($zone_filter !== 'all') {
    $revenueQuery .= " WHERE b.zone = '$zone_filter'";
}
$revenueResult = $conn->query($revenueQuery)->fetch_assoc();
$potentialRevenue = $revenueResult['potential_revenue'];
$actualRevenue = $revenueResult['actual_revenue'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงาน - ระบบจองบูธขายสินค้า</title>
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
        
        .report-filters {
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
        
        .revenue-summary {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .revenue-item {
            margin-bottom: 10px;
        }
        
        .revenue-label {
            font-weight: 500;
        }
        
        .revenue-value {
            font-size: 18px;
            font-weight: 700;
        }
        
        .export-btn {
            margin-left: 10px;
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
                        <a class="nav-link" href="orders.php">
                            <i class="bi bi-receipt me-1"></i>คำสั่งซื้อ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="reports.php">
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
                <i class="bi bi-bar-chart me-2"></i>
                รายงาน
            </h2>
            <a href="<?php echo exportUrl($zone_filter, $status_filter, $search); ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-excel me-1"></i>ส่งออก CSV
            </a>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Summary statistics -->
        <div class="summary-stats">
            <div class="stat-item">
                <div class="stat-value"><?php echo $totalStat; ?></div>
                <div class="stat-label">บูธทั้งหมด</div>
            </div>
            <div class="stat-item">
                <div class="stat-value text-success"><?php echo $availableStat; ?></div>
                <div class="stat-label">บูธว่าง</div>
            </div>
            <div class="stat-item">
                <div class="stat-value text-danger"><?php echo $unpaidStat; ?></div>
                <div class="stat-label">จองแล้ว (ยังไม่ชำระ)</div>
            </div>
            <div class="stat-item">
                <div class="stat-value text-warning"><?php echo $pendingStat; ?></div>
                <div class="stat-label">รอชำระเงิน</div>
            </div>
            <div class="stat-item">
                <div class="stat-value text-primary"><?php echo $paidStat; ?></div>
                <div class="stat-label">ชำระแล้ว</div>
            </div>
        </div>
        
        <!-- Revenue summary -->
        <div class="revenue-summary">
            <h4 class="mb-3">สรุปรายได้</h4>
            <div class="row">
                <div class="col-md-6">
                    <div class="revenue-item">
                        <div class="revenue-label">รายได้ที่เป็นไปได้ (จองแล้วทั้งหมด):</div>
                        <div class="revenue-value text-primary"><?php echo formatCurrency($potentialRevenue); ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="revenue-item">
                        <div class="revenue-label">รายได้จริง (ชำระแล้ว):</div>
                        <div class="revenue-value text-success"><?php echo formatCurrency($actualRevenue); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="report-filters">
            <form method="get" id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label for="zone_filter" class="form-label">กรองตามโซน</label>
                    <select class="form-select" id="zone_filter" name="zone_filter">
                        <option value="all" <?php echo $zone_filter === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                        <option value="A" <?php echo $zone_filter === 'A' ? 'selected' : ''; ?>>โซน A</option>
                        <option value="B" <?php echo $zone_filter === 'B' ? 'selected' : ''; ?>>โซน B</option>
                        <option value="C" <?php echo $zone_filter === 'C' ? 'selected' : ''; ?>>โซน C</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status_filter" class="form-label">กรองตามสถานะ</label>
                    <select class="form-select" id="status_filter" name="status_filter">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                        <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>บูธว่าง</option>
                        <option value="unpaid" <?php echo $status_filter === 'unpaid' ? 'selected' : ''; ?>>จองแล้ว (ยังไม่ชำระ)</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>รอชำระเงิน</option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>ชำระแล้ว</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">ค้นหา</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" placeholder="ค้นหาตามหมายเลขบูธ, โซน, ชื่อลูกค้า..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i>กรอง
                    </button>
                </div>
            </form>
        </div>

        <div class="table-wrapper">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>โซน</th>
                            <th>หมายเลขบูธ</th>
                            <th>ชั้น</th>
                            <th>ราคา</th>
                            <th>สถานะ</th>
                            <th>ชื่อลูกค้า</th>
                            <th>เบอร์โทร</th>
                            <th>บริษัท</th>
                            <th>วันที่จอง</th>
                            <th>วันที่ชำระเงิน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($booths)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-4">ไม่พบข้อมูล</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($booths as $booth): ?>
                        <tr>
                            <td><?php echo $booth['id']; ?></td>
                            <td><?php echo htmlspecialchars($booth['zone']); ?></td>
                            <td><?php echo htmlspecialchars($booth['booth_number']); ?></td>
                            <td><?php echo htmlspecialchars($booth['floor']); ?></td>
                            <td><?php echo formatCurrency($booth['price']); ?></td>
                            <td>
                                <?php 
                                // Determine if booth has an order
                                $hasOrder = !empty($booth['order_id']);
                                // Display badge based on order status if it exists
                                echo getStatusBadge($booth['payment_status'], $hasOrder); 
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($booth['customer_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($booth['customer_phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($booth['customer_company'] ?? '-'); ?></td>
                            <td><?php echo $hasOrder ? formatDateTime($booth['order_date']) : '-'; ?></td>
                            <td><?php echo !empty($booth['payment_date']) ? formatDateTime($booth['payment_date']) : '-'; ?></td>
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
                            <a class="page-link" href="<?php echo paginationUrl(1, $zone_filter, $status_filter, $search); ?>" aria-label="First">
                                <span aria-hidden="true">&laquo;&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo paginationUrl($page - 1, $zone_filter, $status_filter, $search); ?>" aria-label="Previous">
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
                            <a class="page-link" href="<?php echo paginationUrl($i, $zone_filter, $status_filter, $search); ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo paginationUrl($page + 1, $zone_filter, $status_filter, $search); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo paginationUrl($totalPages, $zone_filter, $status_filter, $search); ?>" aria-label="Last">
                                <span aria-hidden="true">&raquo;&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit form when select filters change
            const zoneFilter = document.getElementById('zone_filter');
            const statusFilter = document.getElementById('status_filter');
            
            if (zoneFilter) {
                zoneFilter.addEventListener('change', function() {
                    document.getElementById('filterForm').submit();
                });
            }
            
            if (statusFilter) {
                statusFilter.addEventListener('change', function() {
                    document.getElementById('filterForm').submit();
                });
            }
        });
    </script>
</body>
</html>