<?php
require_once 'check_auth.php';
require_once '../config/database.php';

// Create database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch approved registrations
$stmt = $pdo->prepare("
    SELECT r.*, 
           DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as formatted_date,
           DATE_FORMAT(r.approved_at, '%d/%m/%Y %H:%i') as formatted_approved_date
    FROM registrations r
    WHERE r.is_approved = 1
    ORDER BY r.approved_at DESC
");
$stmt->execute();
$approved_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$total_approved = count($approved_registrations);

// Get registration counts by payment status
$payment_stmt = $pdo->prepare("
    SELECT payment_status, COUNT(*) as count
    FROM registrations
    WHERE is_approved = 1
    GROUP BY payment_status
");
$payment_stmt->execute();
$payment_stats = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);

$paid_count = 0;
$not_paid_count = 0;

foreach ($payment_stats as $stat) {
    if ($stat['payment_status'] == 'paid') {
        $paid_count = $stat['count'];
    } else {
        $not_paid_count = $stat['count'];
    }
}

// Get monthly approval statistics
$monthly_stmt = $pdo->prepare("
    SELECT DATE_FORMAT(approved_at, '%Y-%m') as month, COUNT(*) as count
    FROM registrations
    WHERE is_approved = 1
    GROUP BY DATE_FORMAT(approved_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$monthly_stmt->execute();
$monthly_stats = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);

// Format months for display
$monthly_data = [];
foreach ($monthly_stats as $stat) {
    $date = DateTime::createFromFormat('Y-m', $stat['month']);
    $monthly_data[] = [
        'month' => $date->format('m/Y'),
        'count' => $stat['count']
    ];
}
$monthly_data = array_reverse($monthly_data);

// ใช้ค่า approved_by จากตาราง registrations โดยตรง
$admin_stmt = $pdo->prepare("
    SELECT approved_by as id, COUNT(id) as count
    FROM registrations
    WHERE is_approved = 1
    GROUP BY approved_by
    ORDER BY count DESC
");
$admin_stmt->execute();
$admin_stats_raw = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);

// สร้างข้อมูลผู้อนุมัติแบบง่าย
$admin_stats = [];
foreach ($admin_stats_raw as $stat) {
    $admin_stats[] = [
        'name' => 'ผู้ดูแลระบบ ID: ' . $stat['id'],
        'count' => $stat['count']
    ];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อนุมัติแล้ว - ระบบจัดการการลงทะเบียน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --text-primary: #333;
            --text-secondary: #666;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.08);
            --header-height: 60px;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--background-light);
            color: var(--text-primary);
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
            color: var(--text-primary);
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

        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .card-header .title {
            font-size: 1.1rem;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .card-header .title i {
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-control, .form-select {
            border-radius: 0.5rem;
            padding: 0.6rem 1rem;
            border: 1px solid rgba(0,0,0,0.1);
            box-shadow: none;
            transition: all 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 0.25rem rgba(26, 35, 126, 0.1);
        }

        .btn {
            border-radius: 0.5rem;
            padding: 0.6rem 1.2rem;
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

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .page-header h4 {
            margin-bottom: 0.25rem;
            font-weight: 600;
            color: var(--primary-dark);
        }

        .page-header p {
            margin: 0;
            color: var(--text-secondary);
        }

        .alert {
            border-radius: 0.5rem;
            border: none;
            padding: 1rem 1.25rem;
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

        .table-responsive {
            background: white;
            border-radius: 0.5rem;
        }

        .pagination .page-link {
            color: var(--primary-color);
            border-radius: 0.5rem;
            margin: 0 0.2rem;
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .stat-card {
            border-radius: 0.8rem;
            padding: 1.5rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
        }

        .stat-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .stat-card .stat-icon i {
            font-size: 1.8rem;
            color: white;
        }

        .stat-card .stat-title {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-card .stat-desc {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .bg-approved {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }

        .bg-paid {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }

        .bg-unpaid {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--error-color);
        }

        .bg-chart {
            background-color: rgba(33, 150, 243, 0.1);
            color: var(--accent-color);
        }

        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }

        .admin-stat {
            padding: 0.8rem 1rem;
            background: rgba(26, 35, 126, 0.05);
            border-radius: 0.5rem;
            margin-bottom: 0.8rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-stat:last-child {
            margin-bottom: 0;
        }

        .admin-name {
            font-weight: 500;
        }

        .admin-count {
            font-weight: 600;
            color: var(--primary-color);
        }

        .btn-actions {
            white-space: nowrap;
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
            
            // Toggle sidebar on mobile
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Button actions
            const undoButtons = document.querySelectorAll('.undo-btn');
            undoButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const regId = this.getAttribute('data-id');
                    undoApproval(regId);
                });
            });
            
            const deleteButtons = document.querySelectorAll('.delete-btn');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const regId = this.getAttribute('data-id');
                    deleteRegistration(regId);
                });
            });
        });
        
        // Undo approval
        function undoApproval(id) {
            Swal.fire({
                title: 'ยกเลิกการอนุมัติ',
                text: 'คุณแน่ใจหรือไม่ว่าต้องการยกเลิกการอนุมัติรายการนี้?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ยกเลิกการอนุมัติ',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#f39c12',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Send Ajax request to undo approval
                    // This is a placeholder - replace with actual AJAX call
                    Swal.fire({
                        title: 'ยกเลิกการอนุมัติแล้ว!',
                        text: 'รายการลงทะเบียนถูกย้ายกลับไปยังรายการรอการอนุมัติแล้ว',
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                }
            });
        }
        
        // Delete registration
        function deleteRegistration(id) {
            Swal.fire({
                title: 'ยืนยันการลบ',
                text: 'คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้? การกระทำนี้ไม่สามารถยกเลิกได้',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ลบ',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#d33',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Send Ajax request to delete
                    // This is a placeholder - replace with actual AJAX call
                    Swal.fire({
                        title: 'ลบสำเร็จ!',
                        text: 'รายการลงทะเบียนถูกลบออกจากระบบแล้ว',
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                }
            });
        }
        
        // Export to Excel
        function exportToExcel() {
            Swal.fire({
                title: 'กำลังส่งออกข้อมูล',
                text: 'กำลังสร้างไฟล์ Excel โปรดรอสักครู่...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    // Simulate API call delay
                    setTimeout(() => {
                        Swal.close();
                        Swal.fire({
                            title: 'ส่งออกสำเร็จ!',
                            text: 'ไฟล์ Excel ถูกสร้างและดาวน์โหลดเรียบร้อยแล้ว',
                            icon: 'success'
                        });
                        // In real implementation, trigger the download here
                    }, 1500);
                }
            });
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
                        <a href="dashboard.php" class="nav-link">
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
                        <a href="approved.php" class="nav-link active">
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
                <div class="page-header">
                    <div>
                        <h4>รายการที่อนุมัติแล้ว</h4>
                        <p class="text-muted">ดูรายการลงทะเบียนที่ได้รับการอนุมัติแล้ว</p>
                    </div>
                    <div class="d-flex gap-2">
                        <div class="input-group">
                            <input type="text" class="form-control" id="searchInput" 
                                placeholder="ค้นหาชื่อ, อีเมล, เบอร์โทร...">
                            <button class="btn btn-primary" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                       
                    </div>
                </div>

                <!-- Statistics Row -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body stat-card bg-approved">
                <div class="stat-icon" style="background-color: var(--success-color);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <div class="stat-title">อนุมัติแล้ว</div>
                    <div class="stat-value"><?php echo $total_approved; ?></div>
                </div>
                <div class="stat-desc">จำนวนที่อนุมัติแล้วทั้งหมด</div>
            </div>
        </div>
    </div>
    
    <!-- สถานะการชำระเงิน (paid_approved) -->
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body stat-card bg-paid">
                <div class="stat-icon" style="background-color: var(--success-color);">
                    <i class="fas fa-money-check-alt"></i>
                </div>
                <div>
                    <div class="stat-title">ชำระแล้ว (อนุมัติแล้ว)</div>
                    <div class="stat-value">
                    <?php
                        // ใช้ PHP query เพื่อนับจำนวน
                        $paid_approved_stmt = $pdo->prepare("
                            SELECT COUNT(*) as count 
                            FROM registrations 
                            WHERE (payment_status = 'paid_approved' OR (payment_status = 'paid' AND is_approved = 1))
                        ");
                        $paid_approved_stmt->execute();
                        $paid_approved_result = $paid_approved_stmt->fetch(PDO::FETCH_ASSOC);
                        echo $paid_approved_result['count'];
                    ?>
                    </div>
                </div>
                <div class="stat-desc">ชำระเงินและอนุมัติแล้ว</div>
            </div>
        </div>
    </div>
    
    <!-- สถานะการชำระเงิน (paid - รอตรวจสอบ) -->
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body stat-card" style="background-color: rgba(255, 193, 7, 0.1); color: #ffc107;">
                <div class="stat-icon" style="background-color: #ffc107;">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div>
                    <div class="stat-title">ชำระแล้ว (รอตรวจสอบ)</div>
                    <div class="stat-value">
                    <?php
                        $paid_pending_stmt = $pdo->prepare("
                            SELECT COUNT(*) as count 
                            FROM registrations 
                            WHERE payment_status = 'paid' AND (is_approved = 0 OR is_approved = 1)  
                        ");
                        $paid_pending_stmt->execute();
                        $paid_pending_result = $paid_pending_stmt->fetch(PDO::FETCH_ASSOC);
                        echo $paid_pending_result['count'];
                    ?>
                    </div>
                </div>
                <div class="stat-desc">รอการตรวจสอบจากเจ้าหน้าที่</div>
            </div>
        </div>
    </div>
    
    

<!-- แถวที่ 2 สถิติเพิ่มเติม -->
<div class="row mb-4">
    <!-- สถานะการชำระเงิน (not_paid) -->
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body stat-card bg-unpaid">
                <div class="stat-icon" style="background-color: var(--error-color);">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div>
                    <div class="stat-title">ยังไม่ชำระเงิน</div>
                    <div class="stat-value">
                    <?php
                        $not_paid_stmt = $pdo->prepare("
                            SELECT COUNT(*) as count 
                            FROM registrations 
                            WHERE payment_status = 'not_paid'
                        ");
                        $not_paid_stmt->execute();
                        $not_paid_result = $not_paid_stmt->fetch(PDO::FETCH_ASSOC);
                        echo $not_paid_result['count'];
                    ?>
                    </div>
                </div>
                <div class="stat-desc">ยังไม่ได้ชำระเงิน</div>
            </div>
        </div>
    </div>
    
   
    
    <!-- จำนวนลงทะเบียนรวม -->
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body stat-card" style="background-color: rgba(108, 117, 125, 0.1); color: #6c757d;">
                <div class="stat-icon" style="background-color: #6c757d;">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div class="stat-title">ลงทะเบียนทั้งหมด</div>
                    <div class="stat-value">
                    <?php
                        $total_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM registrations");
                        $total_stmt->execute();
                        $total_result = $total_stmt->fetch(PDO::FETCH_ASSOC);
                        echo $total_result['count'];
                    ?>
                    </div>
                </div>
                <div class="stat-desc">จำนวนการลงทะเบียนทั้งหมด</div>
            </div>
        </div>
    </div>
    
   

                <!-- Charts and Stats Row -->
                <div class="row mb-4">
                    <div class="col-md-8 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="title">
                                    <i class="fas fa-chart-line"></i>
                                    แนวโน้มการอนุมัติรายเดือน
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="approvalChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="title">
                                    <i class="fas fa-user-check"></i>
                                    อนุมัติโดย
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($admin_stats) > 0): ?>
                                    <?php foreach ($admin_stats as $admin): ?>
                                        <div class="admin-stat">
                                            <div class="admin-name"><?php echo $admin['name']; ?></div>
                                            <div class="admin-count"><?php echo $admin['count']; ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-info-circle me-2"></i>
                                        ไม่พบข้อมูลผู้อนุมัติ
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Approved Registrations Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="title">
                            <i class="fas fa-check-circle"></i>
                            รายการที่อนุมัติแล้ว
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>วันที่ลงทะเบียน</th>
                                        <th>วันที่อนุมัติ</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>หน่วยงาน</th>
                                        <th>เบอร์โทร</th>
                                        <th>อีเมล</th>
                                        <th>การชำระเงิน</th>
                                        <th>การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($approved_registrations) > 0): ?>
                                        <?php foreach ($approved_registrations as $reg): ?>
                                            <tr>
                                                <td><?php echo $reg['formatted_date']; ?></td>
                                                <td><?php echo $reg['formatted_approved_date']; ?></td>
                                                <td>
                                                    <?php
                                                        $display_title = $reg['title'];
                                                        if ($reg['title'] == 'อื่นๆ' && !empty($reg['title_other'])) {
                                                            $display_title = $reg['title_other'];
                                                        }
                                                        echo $display_title . ' ' . $reg['fullname'];
                                                    ?>
                                                </td>
                                                <td><?php echo $reg['organization']; ?></td>
                                                <td><?php echo $reg['phone']; ?></td>
                                                <td><?php echo $reg['email']; ?></td>
                                                <td>
                                                    <?php if ($reg['payment_status'] == 'paid'): ?>
                                                        <span class="badge bg-success">ชำระเงินแล้ว</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">ยังไม่ชำระเงิน</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="btn-actions">
                                                    <a href="registration_detail.php?id=<?php echo $reg['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-warning undo-btn" data-id="<?php echo $reg['id']; ?>">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $reg['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    ไม่พบรายการที่อนุมัติแล้ว
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if (count($approved_registrations) > 0): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item disabled">
                                    <a class="page-link" href="#" tabindex="-1" aria-disabled="true">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                <li class="page-item">
                                    <a class="page-link" href="#">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Chart initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Monthly approvals chart
            const ctx = document.getElementById('approvalChart').getContext('2d');
            const monthlyData = <?php echo json_encode($monthly_data); ?>;
            
            if (monthlyData.length > 0) {
                const labels = monthlyData.map(item => item.month);
                const data = monthlyData.map(item => item.count);
                
                const chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'จำนวนการอนุมัติ',
                            data: data,
                            backgroundColor: 'rgba(33, 150, 243, 0.2)',
                            borderColor: 'rgba(33, 150, 243, 1)',
                            borderWidth: 2,
                            pointBackgroundColor: 'rgba(33, 150, 243, 1)',
                            pointRadius: 4,
                            tension: 0.2
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
                            }
                        }
                    }
                });
            } else {
                document.getElementById('approvalChart').parentNode.innerHTML = 
                    '<div class="text-center py-5 text-muted"><i class="fas fa-chart-line me-2"></i>ไม่มีข้อมูลเพียงพอสำหรับการแสดงกราฟ</div>';
            }
            
            // Toggle sidebar on mobile
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Button actions
            const undoButtons = document.querySelectorAll('.undo-btn');
            undoButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const regId = this.getAttribute('data-id');
                    undoApproval(regId);
                });
            });
            
            const deleteButtons = document.querySelectorAll('.delete-btn');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const regId = this.getAttribute('data-id');
                    deleteRegistration(regId);
                });
            });
        });
        
        // Undo approval
        function undoApproval(id) {
            Swal.fire({
                title: 'ยกเลิกการอนุมัติ',
                text: 'คุณแน่ใจหรือไม่ว่าต้องการยกเลิกการอนุมัติรายการนี้?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ยกเลิกการอนุมัติ',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#f39c12',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Send Ajax request to undo approval
                    // This is a placeholder - replace with actual AJAX call
                    Swal.fire({
                        title: 'ยกเลิกการอนุมัติแล้ว!',
                        text: 'รายการลงทะเบียนถูกย้ายกลับไปยังรายการรอการอนุมัติแล้ว',
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                }
            });
        }
        
        // Delete registration
        function deleteRegistration(id) {
            Swal.fire({
                title: 'ยืนยันการลบ',
                text: 'คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้? การกระทำนี้ไม่สามารถยกเลิกได้',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ลบ',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#d33',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Send Ajax request to delete
                    // This is a placeholder - replace with actual AJAX call
                    Swal.fire({
                        title: 'ลบสำเร็จ!',
                        text: 'รายการลงทะเบียนถูกลบออกจากระบบแล้ว',
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                }
            });
        }
        
        // Export to Excel
        function exportToExcel() {
            Swal.fire({
                title: 'กำลังส่งออกข้อมูล',
                text: 'กำลังสร้างไฟล์ Excel โปรดรอสักครู่...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    // Simulate API call delay
                    setTimeout(() => {
                        Swal.close();
                        Swal.fire({
                            title: 'ส่งออกสำเร็จ!',
                            text: 'ไฟล์ Excel ถูกสร้างและดาวน์โหลดเรียบร้อยแล้ว',
                            icon: 'success'
                        });
                        // In real implementation, trigger the download here
                    }, 1500);
                }
            });
        }
    </script>
</body>
</html>