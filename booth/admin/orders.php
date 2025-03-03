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
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$direction = isset($_GET['direction']) ? $_GET['direction'] : 'desc';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20; // Items per page
$offset = ($page - 1) * $limit;

// Process actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Order actions can be added here
}

// Build query based on filters
$whereClause = "";
$params = [];
$types = "";

if ($filter === 'unpaid') {
    $whereClause = "WHERE payment_status = 'unpaid'";
} elseif ($filter === 'pending') {
    $whereClause = "WHERE payment_status = 'pending'";
} elseif ($filter === 'paid') {
    $whereClause = "WHERE payment_status = 'paid'";
} elseif ($filter === 'today') {
    $today = date('Y-m-d');
    $whereClause = "WHERE DATE(created_at) = ?";
    $params[] = $today;
    $types .= "s";
} elseif ($filter === 'this_month') {
    $firstDayOfMonth = date('Y-m-01');
    $whereClause = "WHERE created_at >= ?";
    $params[] = $firstDayOfMonth;
    $types .= "s";
}

// Add search to where clause
if (!empty($search)) {
    $search = "%$search%";
    if (empty($whereClause)) {
        $whereClause = "WHERE (order_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ? OR customer_email LIKE ?)";
    } else {
        $whereClause .= " AND (order_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ? OR customer_email LIKE ?)";
    }
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "ssss";
}

// Check valid sort columns
$validSortColumns = ['id', 'order_number', 'customer_name', 'total_amount', 'payment_status', 'created_at'];
if (!in_array($sort, $validSortColumns)) {
    $sort = 'created_at';
}

// Check valid directions
$validDirections = ['asc', 'desc'];
if (!in_array($direction, $validDirections)) {
    $direction = 'desc';
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM orders $whereClause";
if (!empty($params)) {
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalCount = $stmt->get_result()->fetch_assoc()['total'];
} else {
    $totalCount = $conn->query($countQuery)->fetch_assoc()['total'];
}

$totalPages = ceil($totalCount / $limit);

// Get orders with booth count
$query = "SELECT o.*, COUNT(oi.id) as booth_count, 
          (SELECT SUM(oi2.price) FROM order_items oi2 WHERE oi2.order_id = o.id) as calculated_total 
          FROM orders o 
          LEFT JOIN order_items oi ON o.id = oi.order_id 
          $whereClause 
          GROUP BY o.id 
          ORDER BY $sort $direction 
          LIMIT $offset, $limit";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

// Helper function to format date/time
function formatDateTime($dateStr) {
    if (empty($dateStr)) return '-';
    $date = new DateTime($dateStr);
    return $date->format('d/m/Y H:i:s');
}


// Function to generate pagination URL
function paginationUrl($page, $filter, $sort, $direction, $search) {
    $url = "orders.php?page=$page";
    if ($filter !== 'all') $url .= "&filter=$filter";
    if ($sort !== 'created_at') $url .= "&sort=$sort";
    if ($direction !== 'desc') $url .= "&direction=$direction";
    if (!empty($search)) $url .= "&search=" . urlencode($search);
    return $url;
}

// Function to generate sort URL
function sortUrl($column, $currentSort, $currentDirection, $filter, $search) {
    $newDirection = ($column === $currentSort && $currentDirection === 'asc') ? 'desc' : 'asc';
    $url = "orders.php?sort=$column&direction=$newDirection";
    if ($filter !== 'all') $url .= "&filter=$filter";
    if (!empty($search)) $url .= "&search=" . urlencode($search);
    return $url;
}

// Function to get payment status badge
function getPaymentStatusBadge($status) {
    switch ($status) {
        case 'paid':
            return '<span class="badge bg-success">ชำระแล้ว</span>';
        case 'pending':
            return '<span class="badge bg-warning">รอตรวจสอบ</span>';
        case 'unpaid':
            return '<span class="badge bg-danger">ยังไม่ชำระเงิน</span>';
        case 'cancelled':
            return '<span class="badge bg-secondary">ยกเลิกแล้ว</span>';
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
    <title>คำสั่งซื้อ - ระบบจองบูธขายสินค้า</title>
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
        
        .order-filters {
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
        
        .customer-info {
            white-space: nowrap;
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
                คำสั่งซื้อ
            </h2>
            <a href="../index.php" class="btn btn-outline-primary" target="_blank">
                <i class="bi bi-plus-circle me-1"></i>สร้างคำสั่งซื้อใหม่
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
            <?php
            // Get stats
            $totalQuery = "SELECT COUNT(*) as total FROM orders";
            $unpaidQuery = "SELECT COUNT(*) as count FROM orders WHERE payment_status = 'unpaid'";
            $pendingQuery = "SELECT COUNT(*) as count FROM orders WHERE payment_status = 'pending'";
            $paidQuery = "SELECT COUNT(*) as count FROM orders WHERE payment_status = 'paid'";
            $incomeQuery = "SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid'";
            
            $totalStat = $conn->query($totalQuery)->fetch_assoc()['total'];
            $unpaidStat = $conn->query($unpaidQuery)->fetch_assoc()['count'];
            $pendingStat = $conn->query($pendingQuery)->fetch_assoc()['count'];
            $paidStat = $conn->query($paidQuery)->fetch_assoc()['count'];
            $incomeStat = $conn->query($incomeQuery)->fetch_assoc()['total'] ?? 0;
            ?>
            <div class="stat-item">
                <div class="stat-value"><?php echo $totalStat; ?></div>
                <div class="stat-label">คำสั่งซื้อทั้งหมด</div>
            </div>
            <div class="stat-item">
                <div class="stat-value text-danger"><?php echo $unpaidStat; ?></div>
                <div class="stat-label">ยังไม่ชำระเงิน</div>
            </div>
            <div class="stat-item">
                <div class="stat-value text-warning"><?php echo $pendingStat; ?></div>
                <div class="stat-label">รอตรวจสอบ</div>
            </div>
            <div class="stat-item">
                <div class="stat-value text-success"><?php echo $paidStat; ?></div>
                <div class="stat-label">ชำระแล้ว</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo formatCurrency($incomeStat); ?></div>
                <div class="stat-label">รายได้ทั้งหมด</div>
            </div>
        </div>
        
        <div class="order-filters">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="filter" class="form-label">กรองตามสถานะ</label>
                    <select class="form-select" id="filter" name="filter" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                        <option value="unpaid" <?php echo $filter === 'unpaid' ? 'selected' : ''; ?>>ยังไม่ชำระเงิน</option>
                        <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>รอตรวจสอบ</option>
                        <option value="paid" <?php echo $filter === 'paid' ? 'selected' : ''; ?>>ชำระแล้ว</option>
                        <option value="today" <?php echo $filter === 'today' ? 'selected' : ''; ?>>วันนี้</option>
                        <option value="this_month" <?php echo $filter === 'this_month' ? 'selected' : ''; ?>>เดือนนี้</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="search" class="form-label">ค้นหา</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" placeholder="ค้นหาตามหมายเลขคำสั่งซื้อ, ชื่อลูกค้า, เบอร์โทรศัพท์..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
                <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                <input type="hidden" name="direction" value="<?php echo $direction; ?>">
            </form>
        </div>

        <div class="table-wrapper">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>
                                <a href="<?php echo sortUrl('id', $sort, $direction, $filter, $search); ?>" class="text-dark text-decoration-none">
                                    ID
                                    <?php if ($sort === 'id'): ?>
                                    <i class="bi bi-arrow-<?php echo $direction === 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo sortUrl('order_number', $sort, $direction, $filter, $search); ?>" class="text-dark text-decoration-none">
                                    หมายเลขคำสั่งซื้อ
                                    <?php if ($sort === 'order_number'): ?>
                                    <i class="bi bi-arrow-<?php echo $direction === 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo sortUrl('customer_name', $sort, $direction, $filter, $search); ?>" class="text-dark text-decoration-none">
                                    ลูกค้า
                                    <?php if ($sort === 'customer_name'): ?>
                                    <i class="bi bi-arrow-<?php echo $direction === 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>บูธ</th>
                            <th>
                                <a href="<?php echo sortUrl('total_amount', $sort, $direction, $filter, $search); ?>" class="text-dark text-decoration-none">
                                    ยอดรวม
                                    <?php if ($sort === 'total_amount'): ?>
                                    <i class="bi bi-arrow-<?php echo $direction === 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo sortUrl('payment_status', $sort, $direction, $filter, $search); ?>" class="text-dark text-decoration-none">
                                    สถานะ
                                    <?php if ($sort === 'payment_status'): ?>
                                    <i class="bi bi-arrow-<?php echo $direction === 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo sortUrl('created_at', $sort, $direction, $filter, $search); ?>" class="text-dark text-decoration-none">
                                    วันที่
                                    <?php if ($sort === 'created_at'): ?>
                                    <i class="bi bi-arrow-<?php echo $direction === 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">ไม่พบข้อมูลคำสั่งซื้อ</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo $order['id']; ?></td>
                            <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                            <td class="customer-info">
                                <div><strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></div>
                                <div><?php echo htmlspecialchars($order['customer_phone']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                            </td>
                            <td><?php echo $order['booth_count']; ?> บูธ</td>
                            <td><?php echo formatCurrency($order['total_amount']); ?></td>
                            <td><?php echo getPaymentStatusBadge($order['payment_status']); ?></td>
                            <td><?php echo formatDateTime($order['created_at']); ?></td>
                            <td class="action-buttons">
                                <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-secondary" onclick="printOrder(<?php echo $order['id']; ?>)">
                                    <i class="bi bi-printer"></i>
                                </button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printOrder(orderId) {
            window.open('print-order.php?id=' + orderId, '_blank');
        }
    </script>
</body>
</html>