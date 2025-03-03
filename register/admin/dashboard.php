<?php
require_once 'check_auth.php';
require_once '../config/database.php';

// Create database connection
$database = new Database();
$pdo = $database->getConnection();

// Get dashboard statistics
try {
    // Total registrations
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM registrations");
    $total = $stmt->fetchColumn();
    
    // Pending approvals
    $stmt = $pdo->query("SELECT COUNT(*) AS pending FROM registrations WHERE is_approved = 0");
    $pending = $stmt->fetchColumn();
    
    // Approved registrations
    $stmt = $pdo->query("SELECT COUNT(*) AS approved FROM registrations WHERE is_approved = 1");
    $approved = $stmt->fetchColumn();
    
    // Unpaid registrations
    $stmt = $pdo->query("SELECT COUNT(*) AS unpaid FROM registrations WHERE payment_status = 'not_paid'");
    $unpaid = $stmt->fetchColumn();
    
    // Recent registrations
    $stmt = $pdo->query("
        SELECT 
            r.id, 
            r.fullname, 
            r.organization, 
            r.email,
            r.phone,
            r.is_approved, 
            r.payment_status, 
            DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as formatted_date,
            DATE_FORMAT(r.created_at, '%Y-%m-%d') as date_for_chart
        FROM registrations r 
        ORDER BY r.created_at DESC 
        LIMIT 5
    ");
    $recent_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Registration trends for chart (last 7 days)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m-%d') as date,
            COUNT(*) as count
        FROM registrations
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d')
        ORDER BY date
    ");
    $registration_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format for chart.js
    $trend_labels = [];
    $trend_data = [];
    
    foreach ($registration_trends as $trend) {
        $date = new DateTime($trend['date']);
        $trend_labels[] = $date->format('d/m/Y');
        $trend_data[] = $trend['count'];
    }
    
    // Payment status distribution
    $stmt = $pdo->query("
        SELECT 
            payment_status,
            COUNT(*) as count
        FROM registrations
        GROUP BY payment_status
    ");
    $payment_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format for chart.js
    $payment_labels = [];
    $payment_data = [];
    $payment_colors = [];
    
    foreach ($payment_distribution as $item) {
        if ($item['payment_status'] == 'paid') {
            $payment_labels[] = 'ชำระแล้ว';
            $payment_colors[] = '#27AE60';
        } else {
            $payment_labels[] = 'ยังไม่ชำระ';
            $payment_colors[] = '#E74C3C';
        }
        $payment_data[] = $item['count'];
    }
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการการลงทะเบียน - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a237e;
            --primary-light: #534bae;
            --primary-dark: #000051;
            --secondary-color: #0d47a1;
            --accent-color: #2196f3;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --error-color: #f44336;
            --background-light: #f5f7fa;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.08);
            --header-height: 60px;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--background-light);
            color: #333;
        }

        .header {
            height: var(--header-height);
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .sidebar {
            background: white;
            min-height: calc(100vh - var(--header-height));
            border-right: 1px solid rgba(0,0,0,0.08);
            padding-top: 1.5rem;
        }

        .sidebar .nav-link {
            color: #555;
            padding: 0.8rem 1.5rem;
            border-radius: 0.5rem;
            margin: 0.2rem 1rem;
            display: flex;
            align-items: center;
            font-weight: 500;
            transition: all 0.2s;
        }

        .sidebar .nav-link i {
            width: 24px;
            margin-right: 10px;
            font-size: 18px;
        }

        .sidebar .nav-link:hover {
            color: var(--primary-color);
            background: rgba(26, 35, 126, 0.05);
        }

        .sidebar .nav-link.active {
            color: var(--primary-color);
            background: rgba(26, 35, 126, 0.1);
            font-weight: 600;
        }

        .main-content {
            padding: 2rem;
            padding-top: 1.5rem;
        }

        .card {
            border: none;
            border-radius: 0.8rem;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s, box-shadow 0.2s;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header .title {
            font-size: 1.1rem;
            margin: 0;
            color: #333;
        }

        .card-body {
            padding: 1.5rem;
        }

        .stat-card {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            border-radius: 0.8rem;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 1rem;
            flex-shrink: 0;
            font-size: 1.5rem;
        }

        .stat-info h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
            color: #333;
        }

        .stat-info p {
            font-size: 0.9rem;
            margin: 0;
            color: #666;
        }

        .bg-primary-gradient {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
        }

        .bg-secondary-gradient {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            color: white;
        }

        .bg-success-gradient {
            background: linear-gradient(135deg, #2e7d32, var(--success-color));
            color: white;
        }

        .bg-warning-gradient {
            background: linear-gradient(135deg, #e65100, var(--warning-color));
            color: white;
        }

        .bg-danger-gradient {
            background: linear-gradient(135deg, #b71c1c, var(--error-color));
            color: white;
        }

        .table {
            width: 100%;
            border-spacing: 0 0.5rem;
            border-collapse: separate;
        }

        .table thead th {
            background-color: rgba(0,0,0,0.02);
            border: none;
            padding: 1rem;
            font-weight: 600;
            color: #555;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            background-color: white;
            border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .table tbody tr:first-child td:first-child {
            border-top-left-radius: 0.5rem;
        }

        .table tbody tr:first-child td:last-child {
            border-top-right-radius: 0.5rem;
        }

        .table tbody tr:last-child td:first-child {
            border-bottom-left-radius: 0.5rem;
        }

        .table tbody tr:last-child td:last-child {
            border-bottom-right-radius: 0.5rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .chart-container {
            position: relative;
            height: 280px;
            width: 100%;
        }

        .btn-custom {
            padding: 0.5rem 1.2rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .user-profile {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }

        .user-profile:hover {
            background-color: rgba(0,0,0,0.03);
        }

        .profile-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.8rem;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
        }

        .profile-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: #333;
        }

        .profile-role {
            font-size: 0.75rem;
            color: #666;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                left: -250px;
                top: var(--header-height);
                width: 250px;
                height: calc(100vh - var(--header-height));
                z-index: 1000;
                transition: all 0.3s;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .sidebar-toggler {
                display: block !important;
            }
        }

        @media (min-width: 993px) {
            .sidebar-toggler {
                display: none !important;
            }
        }

        .no-data-message {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .recent-activity-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .activity-details {
            flex-grow: 1;
        }

        .activity-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header d-flex align-items-center">
        <button class="btn sidebar-toggler me-2" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="d-flex align-items-center">
            <img src="https://arts.rmutsb.ac.th/image/logo_art_2019.png" alt="Logo" height="32" class="me-2">
            <h5 class="mb-0 d-none d-md-block">ระบบจัดการการลงทะเบียน</h5>
        </div>
        
        <div class="ms-auto d-flex align-items-center">
            <div class="dropdown">
                <div class="user-profile" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="profile-avatar">
                        <?php 
                        $initials = mb_substr($_SESSION['admin_name'] ?? 'A', 0, 1, 'UTF-8');
                        echo $initials;
                        ?>
                    </div>
                    <div class="profile-info d-none d-md-flex">
                        <span class="profile-name"><?php echo $_SESSION['admin_name'] ?? 'ผู้ดูแลระบบ'; ?></span>
                        <span class="profile-role">ผู้ดูแลระบบ</span>
                    </div>
                    <i class="fas fa-chevron-down ms-2"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>โปรไฟล์</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>ตั้งค่า</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 sidebar" id="sidebar">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link active">
                            <i class="fas fa-home"></i>
                            <span>หน้าหลัก</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="registrations.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>รายการลงทะเบียน</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="pending_approval.php" class="nav-link">
                            <i class="fas fa-clock"></i>
                            <span>รอการอนุมัติ</span>
                            <?php if ($pending > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-auto"><?php echo $pending; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                    <a href="approved.php" class="nav-link">
                            <i class="fas fa-check-circle"></i>
                            <span>อนุมัติแล้ว</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-file-alt"></i>
                            <span>รายงาน</span>
                        </a>
                    </li>
                  
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-1">ภาพรวม</h4>
                        <p class="text-muted mb-0">ข้อมูลสรุปการลงทะเบียนและกิจกรรมล่าสุด</p>
                    </div>
                    <div>
                        <button class="btn btn-outline-primary btn-custom me-2">
                            <i class="fas fa-calendar-alt me-2"></i>
                            วันที่: <?php echo date('d/m/Y'); ?>
                        </button>
                        <button class="btn btn-primary btn-custom">
                            <i class="fas fa-download me-2"></i>
                            ดาวน์โหลดรายงาน
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card bg-primary-gradient">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <h2><?php echo number_format($total); ?></h2>
                                <p>ลงทะเบียนทั้งหมด</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card bg-warning-gradient">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-info">
                                <h2><?php echo number_format($pending); ?></h2>
                                <p>รอการอนุมัติ</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card bg-success-gradient">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h2><?php echo number_format($approved); ?></h2>
                                <p>อนุมัติแล้ว</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card bg-danger-gradient">
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h2><?php echo number_format($unpaid); ?></h2>
                                <p>ยังไม่ชำระเงิน</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-lg-8 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="title">สถิติการลงทะเบียน</h5>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary active" id="weekBtn">รายสัปดาห์</button>
                                    <button class="btn btn-sm btn-outline-secondary" id="monthBtn">รายเดือน</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="registrationTrend"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="title">สถานะการชำระเงิน</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 240px;">
                                    <canvas id="paymentStatus"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Row -->
                <div class="row">
                    <div class="col-lg-8 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="title">การลงทะเบียนล่าสุด</h5>
                                <a href="registrations.php" class="btn btn-sm btn-outline-primary">ดูทั้งหมด</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table mb-0">
                                        <thead>
                                            <tr>
                                                <th>วันที่</th>
                                                <th>ชื่อ-นามสกุล</th>
                                                <th>หน่วยงาน</th>
                                                <th>สถานะ</th>
                                                <th>การชำระเงิน</th>
                                                <th>การจัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_registrations)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">ไม่พบข้อมูลการลงทะเบียน</td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_registrations as $reg): ?>
                                                <tr>
                                                    <td><?php echo $reg['formatted_date']; ?></td>
                                                    <td><?php echo htmlspecialchars($reg['fullname']); ?></td>
                                                    <td><?php echo htmlspecialchars($reg['organization']); ?></td>
                                                    <td>
                                                        <?php if ($reg['is_approved']): ?>
                                                        <span class="status-badge bg-success">อนุมัติแล้ว</span>
                                                        <?php else: ?>
                                                        <span class="status-badge bg-warning">รอการอนุมัติ</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($reg['payment_status'] == 'paid'): ?>
                                                        <span class="status-badge bg-success">ชำระแล้ว</span>
                                                        <?php else: ?>
                                                        <span class="status-badge bg-danger">ยังไม่ชำระ</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="registration_detail.php?id=<?php echo $reg['id']; ?>" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <?php if (!$reg['is_approved']): ?>
                                                            <button class="btn btn-sm btn-success" onclick="approveRegistration(<?php echo $reg['id']; ?>)">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="title">กิจกรรมล่าสุด</h5>
                            </div>
                            <div class="card-body p-3">
                                <?php if (empty($recent_registrations)): ?>
                                <div class="no-data-message">
                                    <i class="fas fa-info-circle mb-2 fa-2x"></i>
                                    <p>ไม่พบกิจกรรมล่าสุด</p>
                                </div>
                                <?php else: ?>
                                    <?php foreach ($recent_registrations as $reg): ?>
                                    <div class="recent-activity-item">
                                        <div class="activity-icon bg-<?php echo $reg['is_approved'] ? 'success' : 'warning'; ?>-gradient">
                                            <i class="fas fa-<?php echo $reg['is_approved'] ? 'check' : 'clock'; ?>"></i>
                                        </div>
                                        <div class="activity-details">
                                            <p class="activity-title"><?php echo htmlspecialchars($reg['fullname']); ?> ได้ลงทะเบียน</p>
                                            <p class="activity-time"><?php echo $reg['formatted_date']; ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Sidebar toggle for mobile
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Close sidebar when clicking outside of it
            document.addEventListener('click', function(event) {
                if (sidebar.classList.contains('show') && 
                    !sidebar.contains(event.target) && 
                    event.target !== sidebarToggle) {
                    sidebar.classList.remove('show');
                }
            });
            
            // Registration trend chart
            const trendCtx = document.getElementById('registrationTrend').getContext('2d');
            const registrationTrendChart = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($trend_labels); ?>,
                    datasets: [{
                        label: 'จำนวนการลงทะเบียน',
                        data: <?php echo json_encode($trend_data); ?>,
                        borderColor: '#1a237e',
                        backgroundColor: 'rgba(26, 35, 126, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: '#1a237e',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(26, 35, 126, 0.8)',
                            padding: 10,
                            titleFont: {
                                size: 14
                            },
                            bodyFont: {
                                size: 13
                            },
                            displayColors: false
                        }
                    }
                }
            });
            
            // Payment status chart
            const paymentCtx = document.getElementById('paymentStatus').getContext('2d');
            const paymentStatusChart = new Chart(paymentCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($payment_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($payment_data); ?>,
                        backgroundColor: <?php echo json_encode($payment_colors); ?>,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            padding: 10,
                            titleFont: {
                                size: 14
                            },
                            bodyFont: {
                                size: 13
                            },
                            displayColors: false
                        }
                    }
                }
            });
            
            // Toggle between weekly and monthly view
            document.getElementById('weekBtn').addEventListener('click', function() {
                this.classList.add('active');
                document.getElementById('monthBtn').classList.remove('active');
                // You would typically fetch new data here via AJAX
                // For demo purposes, we'll just update the chart with random data
                updateChartData(registrationTrendChart, 7);
            });
            
            document.getElementById('monthBtn').addEventListener('click', function() {
                this.classList.add('active');
                document.getElementById('weekBtn').classList.remove('active');
                // For demo purposes, we'll just update the chart with random data
                updateChartData(registrationTrendChart, 30);
            });
            
            function updateChartData(chart, days) {
                // In a real application, you would fetch data from the server
                // This is just for demonstration
                const labels = [];
                const data = [];
                
                const today = new Date();
                for (let i = days - 1; i >= 0; i--) {
                    const date = new Date();
                    date.setDate(today.getDate() - i);
                    labels.push(date.toLocaleDateString('th-TH', {day: '2-digit', month: '2-digit'}));
                    
                    // Random data between 1 and 10
                    data.push(Math.floor(Math.random() * 10) + 1);
                }
                
                chart.data.labels = labels;
                chart.data.datasets[0].data = data;
                chart.update();
            }
        });
        
        function approveRegistration(id) {
            Swal.fire({
                title: 'ยืนยันการอนุมัติ',
                text: 'คุณต้องการอนุมัติการลงทะเบียนนี้ใช่หรือไม่?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#4caf50',
                cancelButtonColor: '#f44336',
                confirmButtonText: 'ใช่, อนุมัติ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Here you would typically send an AJAX request to approve the registration
                    // For demo purposes, we'll just show a success message
                    Swal.fire({
                        title: 'อนุมัติเรียบร้อย!',
                        text: 'การลงทะเบียนได้รับการอนุมัติแล้ว',
                        icon: 'success'
                    }).then(() => {
                        // Reload the page to reflect the changes
                        location.reload();
                    });
                }
            });
        }
    </script>
</body>
</html>