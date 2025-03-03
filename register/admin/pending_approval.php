<?php
require_once 'check_auth.php';
require_once '../config/database.php';

// Create database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch pending registrations
$stmt = $pdo->prepare("
    SELECT r.*, 
           DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as formatted_date
    FROM registrations r
    WHERE r.is_approved = 0
    ORDER BY r.created_at DESC
");
$stmt->execute();
$pending_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$total_pending = count($pending_registrations);

// Get earliest pending registration
$earliest_date = null;
if ($total_pending > 0) {
    $earliest_stmt = $pdo->prepare("
        SELECT MIN(created_at) as earliest
        FROM registrations
        WHERE is_approved = 0
    ");
    $earliest_stmt->execute();
    $earliest_result = $earliest_stmt->fetch(PDO::FETCH_ASSOC);
    $earliest_date = $earliest_result['earliest'];
}

// Get pending by payment status
$payment_stmt = $pdo->prepare("
    SELECT payment_status, COUNT(*) as count
    FROM registrations
    WHERE is_approved = 0
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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รอการอนุมัติ - ระบบจัดการการลงทะเบียน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        .bg-pending {
            background-color: rgba(255, 152, 0, 0.1);
            color: var(--warning-color);
        }

        .bg-paid {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }

        .bg-unpaid {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--error-color);
        }

        .bg-time {
            background-color: rgba(33, 150, 243, 0.1);
            color: var(--accent-color);
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
                        <a href="pending_approval.php" class="nav-link active">
                            <i class="fas fa-clock"></i>
                            <span>รอการอนุมัติ</span>
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
                <div class="page-header">
                    <div>
                        <h4>รายการรอการอนุมัติ</h4>
                        <p class="text-muted">จัดการรายการลงทะเบียนที่รอการอนุมัติ</p>
                    </div>
                    <div class="d-flex gap-2">
                        <div class="input-group">
                            <input type="text" class="form-control" id="searchInput" 
                                placeholder="ค้นหาชื่อ, อีเมล, เบอร์โทร...">
                            <button class="btn btn-primary" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <button class="btn btn-success" onclick="approveSelected()">
                            <i class="fas fa-check-circle me-2"></i>
                            อนุมัติที่เลือก
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-sm-6 col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body stat-card bg-pending">
                                <div class="stat-icon" style="background-color: var(--warning-color);">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <div class="stat-title">รายการรอดำเนินการ</div>
                                    <div class="stat-value"><?php echo $total_pending; ?></div>
                                </div>
                                <div class="stat-desc">รายการที่รอการอนุมัติทั้งหมด</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body stat-card bg-paid">
                                <div class="stat-icon" style="background-color: var(--success-color);">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div>
                                    <div class="stat-title">ชำระเงินแล้ว</div>
                                    <div class="stat-value"><?php echo $paid_count; ?></div>
                                </div>
                                <div class="stat-desc">จำนวนที่ชำระเงินแล้ว</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body stat-card bg-unpaid">
                                <div class="stat-icon" style="background-color: var(--error-color);">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                                <div>
                                    <div class="stat-title">ยังไม่ชำระเงิน</div>
                                    <div class="stat-value"><?php echo $not_paid_count; ?></div>
                                </div>
                                <div class="stat-desc">จำนวนที่ยังไม่ชำระเงิน</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body stat-card bg-time">
                                <div class="stat-icon" style="background-color: var(--accent-color);">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div>
                                    <div class="stat-title">รอนานที่สุด</div>
                                    <div class="stat-value" style="font-size: 1.5rem;">
                                        <?php 
                                            if ($earliest_date) {
                                                $earliest = new DateTime($earliest_date);
                                                $now = new DateTime();
                                                $diff = $earliest->diff($now);
                                                echo $diff->days . ' วัน';
                                            } else {
                                                echo '-';
                                            }
                                        ?>
                                    </div>
                                </div>
                                <div class="stat-desc">ระยะเวลารอนานที่สุด</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="title">
                            <i class="fas fa-clock"></i>
                            รายการรอการอนุมัติ
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="40">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                            </div>
                                        </th>
                                        <th>วันที่</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>หน่วยงาน</th>
                                        <th>เบอร์โทร</th>
                                        <th>อีเมล</th>
                                        <th>การชำระเงิน</th>
                                        <th>การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($pending_registrations) > 0): ?>
                                        <?php foreach ($pending_registrations as $reg): ?>
                                            <tr>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input registration-checkbox" type="checkbox" value="<?php echo $reg['id']; ?>">
                                                    </div>
                                                </td>
                                                <td><?php echo $reg['formatted_date']; ?></td>
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
                                                    <button type="button" class="btn btn-sm btn-success approve-btn" data-id="<?php echo $reg['id']; ?>">
                                                        <i class="fas fa-check"></i>
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
                                                    ไม่พบรายการที่รอการอนุมัติ
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if (count($pending_registrations) > 0): ?>
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

  
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Toggle sidebar on mobile
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
        }
        
        // Select all checkbox
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.registration-checkbox');
        
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }
        
        // Individual approve buttons
        const approveButtons = document.querySelectorAll('.approve-btn');
        approveButtons.forEach(button => {
            button.addEventListener('click', function() {
                const regId = this.getAttribute('data-id');
                approveRegistration(regId);
            });
        });
        
        // Delete buttons
        const deleteButtons = document.querySelectorAll('.delete-btn');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const regId = this.getAttribute('data-id');
                deleteRegistration(regId);
            });
        });
    });
    
    // Approve single registration
    function approveRegistration(id) {
        Swal.fire({
            title: 'ยืนยันการอนุมัติ',
            text: 'คุณแน่ใจหรือไม่ว่าต้องการอนุมัติรายการนี้?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ใช่, อนุมัติ',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Send Ajax request to approve
                // This is a placeholder - replace with actual AJAX call
                Swal.fire({
                    title: 'อนุมัติสำเร็จ!',
                    text: 'รายการลงทะเบียนได้รับการอนุมัติแล้ว',
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
    
    // Approve selected registrations
    function approveSelected() {
        const selectedIds = [];
        document.querySelectorAll('.registration-checkbox:checked').forEach(checkbox => {
            selectedIds.push(checkbox.value);
        });
        
        if (selectedIds.length === 0) {
            Swal.fire({
                title: 'ไม่พบรายการที่เลือก',
                text: 'กรุณาเลือกรายการที่ต้องการอนุมัติอย่างน้อย 1 รายการ',
                icon: 'info'
            });
            return;
        }
        
        Swal.fire({
            title: 'ยืนยันการอนุมัติ',
            text: `คุณแน่ใจหรือไม่ว่าต้องการอนุมัติรายการที่เลือกทั้งหมด ${selectedIds.length} รายการ?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ใช่, อนุมัติทั้งหมด',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Send Ajax request to approve multiple
                // This is a placeholder - replace with actual AJAX call
                Swal.fire({
                    title: 'อนุมัติสำเร็จ!',
                    text: `อนุมัติรายการลงทะเบียนทั้งหมด ${selectedIds.length} รายการเรียบร้อยแล้ว`,
                    icon: 'success'
                }).then(() => {
                    location.reload();
                });
            }
        });
    }
</script>
</body>
</html>