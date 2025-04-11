<?php
session_start();
require_once 'check_auth.php'; // ไฟล์ตรวจสอบการล็อกอิน
require_once '../config/database.php'; // ไฟล์เชื่อมต่อฐานข้อมูล
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการลงทะเบียนทั้งหมด - ระบบจัดการการลงทะเบียน</title>
    <!-- External CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- jQuery -->
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

        /* Responsive Adjustments */
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

        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 0.8rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .filter-section .form-label {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .filter-section .form-control,
        .filter-section .form-select {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
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
                        <a href="registrations.php" class="nav-link active">
                            <i class="fas fa-users"></i>
                            <span>รายการลงทะเบียน</span>
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
                        <h4>รายการลงทะเบียนทั้งหมด</h4>
                        <p class="text-muted">จัดการข้อมูลการลงทะเบียนทั้งหมดในระบบ</p>
                    </div>
                    <div class="d-flex gap-2">
                        <div class="input-group">
                            <input type="text" class="form-control" id="searchInput" 
                                   placeholder="ค้นหาชื่อ, อีเมล, เบอร์โทร...">
                            <button class="btn btn-primary" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <button class="btn btn-success" onclick="exportToExcel()">
                            <i class="fas fa-file-excel me-2"></i>
                            ส่งออก Excel
                        </button>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="provinceFilter" class="form-label">จังหวัด</label>
                            <select id="provinceFilter" class="form-select">
                                <option value="">เลือกจังหวัด</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="districtFilter" class="form-label">อำเภอ</label>
                            <select id="districtFilter" class="form-select" disabled>
                                <option value="">เลือกอำเภอ</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="firstNameFilter" class="form-label">ชื่อ</label>
                            <input type="text" id="firstNameFilter" class="form-control" placeholder="กรอกชื่อ">
                        </div>
                        <div class="col-md-3">
                            <label for="lastNameFilter" class="form-label">นามสกุล</label>
                            <input type="text" id="lastNameFilter" class="form-control" placeholder="กรอกนามสกุล">
                        </div>
                        <div class="col-md-3">
                            <label for="phoneFilter" class="form-label">เบอร์โทร</label>
                            <input type="text" id="phoneFilter" class="form-control" placeholder="กรอกเบอร์โทร">
                        </div>
                        <div class="col-md-3">
                            <label for="statusFilter" class="form-label">สถานะ</label>
                            <select id="statusFilter" class="form-select">
                                <option value="">ทุกสถานะ</option>
                                <option value="approved">อนุมัติแล้ว</option>
                                <option value="pending">รอการอนุมัติ</option>
                                <option value="paid_approved">ชำระแล้ว (อนุมัติแล้ว)</option>
                                <option value="paid">ชำระแล้ว (รอตรวจสอบจากเจ้าหน้าที่)</option>
                                <option value="not_paid">ยังไม่ชำระ</option>
                            </select>
                        </div>
                        <div class="col-md-12 mt-3">
                        <button class="btn btn-primary" id="filterButton">กรองข้อมูล</button>
                            <button class="btn btn-outline-secondary ms-2" onclick="resetFilters()">รีเซ็ต</button>
                        </div>
                    </div>
                </div>

                <!-- Registrations Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="title">
                            <i class="fas fa-list"></i>
                            รายการลงทะเบียนทั้งหมด
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>วันที่</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>หน่วยงาน</th>
                                        <th>เบอร์โทร</th>
                                        <th>อีเมล</th>
                                        <th>ที่อยู่</th>
                                        <th>สถานะ</th>
                                        <th>การชำระเงิน</th>
                                        <th>การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody id="registrationsList">
                                    <!-- ข้อมูลจะถูกเติมด้วย JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center" id="pagination">
                                <!-- จะถูกเติมด้วย JavaScript -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- External JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="registrations.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // เพิ่ม Event Listener สำหรับปุ่มค้นหา
            document.getElementById('filterButton').addEventListener('click', function() {
                // ตรวจสอบว่าฟังก์ชัน applyFilters มีอยู่หรือไม่
                if (typeof applyFilters === 'function') {
                    applyFilters();
                } else {
                    alert('ฟังก์ชัน applyFilters ไม่ถูกโหลด กรุณาตรวจสอบไฟล์ JavaScript');
                }
            });
        });
</script>
</body>
</html>