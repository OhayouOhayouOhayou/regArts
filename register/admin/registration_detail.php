<?php
require_once 'check_auth.php';
require_once '../config/database.php';

// Create database connection using the Database class
$database = new Database();
$pdo = $database->getConnection();

// Get registration ID from URL parameter
$registration_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If no valid ID provided, redirect to registrations list
if ($registration_id <= 0) {
    header('Location: registrations.php');
    exit;
}

// Fetch registration data
$stmt = $pdo->prepare("
    SELECT r.*, 
           DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as formatted_date,
           DATE_FORMAT(r.approved_at, '%d/%m/%Y %H:%i') as formatted_approved_date
    FROM registrations r
    WHERE r.id = ?
");
$stmt->execute([$registration_id]);

if ($stmt->rowCount() === 0) {
    // Registration not found, redirect
    header('Location: registrations.php');
    exit;
}

$registration = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch addresses
$addresses = [];
$stmt = $pdo->prepare("
    SELECT ra.*, 
           p.name_in_thai as province_name, 
           d.name_in_thai as district_name, 
           sd.name_in_thai as subdistrict_name
    FROM registration_addresses ra
    LEFT JOIN provinces p ON ra.province_id = p.id
    LEFT JOIN districts d ON ra.district_id = d.id
    LEFT JOIN subdistricts sd ON ra.subdistrict_id = sd.id
    WHERE ra.registration_id = ?
");
$stmt->execute([$registration_id]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $addresses[$row['address_type']] = $row;
}

// Debug address data
error_log("Address data for registration ID $registration_id: " . print_r($addresses, true));

// Fetch documents
$stmt = $pdo->prepare("
    SELECT * FROM registration_documents
    WHERE registration_id = ?
");
$stmt->execute([$registration_id]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch payment files
$stmt = $pdo->prepare("
    SELECT * FROM registration_files
    WHERE registration_id = ?
");
$stmt->execute([$registration_id]);
$payment_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get the registration_group of the current registration
$registration_group = $registration['registration_group'];

// Initialize array for group members' files
$group_payment_files = [];
$group_members = [];

// If the current registration has a registration_group
if (!empty($registration_group)) {
    // Query to get all other registrations in the same group that have made payments
    $group_stmt = $pdo->prepare("
        SELECT id, fullname 
        FROM registrations 
        WHERE registration_group = ? 
        AND id != ? 
        AND payment_status IN ('paid', 'paid_approved', 'paid_onsite')
    ");
    $group_stmt->execute([$registration_group, $registration_id]);
    $group_members = $group_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all members in the group regardless of payment status
    $all_members_stmt = $pdo->prepare("
        SELECT id, fullname, payment_status, is_approved
        FROM registrations 
        WHERE registration_group = ? 
        AND id != ?
    ");
    $all_members_stmt->execute([$registration_group, $registration_id]);
    $all_group_members = $all_members_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If there are other registrations in the same group that have paid
    if (!empty($group_members)) {
        // Get all member IDs
        $member_ids = array_column($group_members, 'id');
        
        // Create placeholders for SQL query
        $placeholders = implode(',', array_fill(0, count($member_ids), '?'));
        
        // Fetch all payment files for these members in one query
        $files_stmt = $pdo->prepare("
            SELECT rf.*, r.fullname as member_name
            FROM registration_files rf
            JOIN registrations r ON rf.registration_id = r.id
            WHERE rf.registration_id IN ($placeholders)
        ");
        $files_stmt->execute($member_ids);
        $group_payment_files = $files_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get all provinces for dropdown
$stmt = $pdo->query("SELECT * FROM provinces ORDER BY name_in_thai");
$provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // For debugging
    error_log("POST data received: " . print_r($_POST, true));
    
    if (isset($_POST['update_registration'])) {
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            $payment_status = $_POST['payment_status'];
            $update_group = isset($_POST['update_group']) && $_POST['update_group'] == '1';
            
            // Update registration table for current registration
            $update_stmt = $pdo->prepare("
                UPDATE registrations 
                SET title = ?, 
                    title_other = ?, 
                    fullname = ?, 
                    organization = ?, 
                    position = ?,
                    phone = ?, 
                    email = ?, 
                    line_id = ?,
                    payment_status = ?
                WHERE id = ?
            ");
            
            $title = $_POST['title'];
            $title_other = !empty($_POST['title_other']) ? $_POST['title_other'] : null;
            $fullname = $_POST['fullname'];
            $organization = $_POST['organization'];
            $position = $_POST['position'];
            $phone = $_POST['phone'];
            $email = $_POST['email'];
            $line_id = $_POST['line_id'];
            
            $update_stmt->execute([
                $title,
                $title_other,
                $fullname,
                $organization,
                $position,
                $phone,
                $email,
                $line_id,
                $payment_status,
                $registration_id
            ]);
            
            // อัพเดตทั้งกลุ่มหรือไม่
            $affected_rows = 0;
            if ($update_group && !empty($registration['registration_group'])) {
                $group_id = $registration['registration_group'];
                
                // บันทึกจำนวนที่อัพเดต
                $group_update = $pdo->prepare("
                    UPDATE registrations 
                    SET payment_status = ?
                    WHERE registration_group = ? AND id != ?
                ");
                $group_update->execute([$payment_status, $group_id, $registration_id]);
                $affected_rows = $group_update->rowCount();
                
                // เพิ่มข้อความแจ้งว่าอัพเดตทั้งกลุ่มแล้ว
                $success_message_group = "อัพเดตสถานะการชำระเงินให้กับสมาชิกในกลุ่มอีก {$affected_rows} คนเรียบร้อยแล้ว";
            }
            
            // Update addresses
            foreach (['invoice', 'house', 'current'] as $address_type) {
                if (isset($_POST['address'][$address_type])) {
                    $address_update = $pdo->prepare("
                        UPDATE registration_addresses 
                        SET address = ?,
                            province_id = ?,
                            district_id = ?,
                            subdistrict_id = ?,
                            zipcode = ?
                        WHERE registration_id = ? AND address_type = ?
                    ");
                    
                    $address = $_POST['address'][$address_type];
                    $province_id = !empty($_POST['province_id'][$address_type]) ? $_POST['province_id'][$address_type] : null;
                    $district_id = !empty($_POST['district_id'][$address_type]) ? $_POST['district_id'][$address_type] : null;
                    $subdistrict_id = !empty($_POST['subdistrict_id'][$address_type]) ? $_POST['subdistrict_id'][$address_type] : null;
                    $zipcode = $_POST['zipcode'][$address_type];
                    
                    $address_update->execute([
                        $address,
                        $province_id,
                        $district_id,
                        $subdistrict_id,
                        $zipcode,
                        $registration_id,
                        $address_type
                    ]);
                }
            }
            
            // Handle approval
            if (isset($_POST['is_approved']) && $_POST['is_approved'] != $registration['is_approved']) {
                $is_approved = ($_POST['is_approved'] == '1') ? 1 : 0;
                $approved_at = ($is_approved) ? date('Y-m-d H:i:s') : null;
                
                $admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 1;
                $approved_by = ($is_approved) ? $admin_id : null;
                
                $approval_update = $pdo->prepare("
                    UPDATE registrations 
                    SET is_approved = ?,
                        approved_at = ?,
                        approved_by = ?
                    WHERE id = ?
                ");
                
                $approval_update->execute([
                    $is_approved,
                    $approved_at,
                    $approved_by,
                    $registration_id
                ]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            $success_message = "บันทึกข้อมูลเรียบร้อยแล้ว";
            if (isset($success_message_group)) {
                $success_message .= "<br>" . $success_message_group;
            }
            
            // Refresh data
            header("Location: registration_detail.php?id=$registration_id&success=1" . (!empty($affected_rows) ? "&group_updated=1&count={$affected_rows}" : ""));
            exit;
            
        } catch (Exception $e) {
            // Rollback on error
            $pdo->rollBack();
            $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
            error_log("Error updating registration: " . $e->getMessage());
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "บันทึกข้อมูลเรียบร้อยแล้ว";
    
    // ถ้ามีการอัพเดตทั้งกลุ่ม
    if (isset($_GET['group_updated']) && $_GET['group_updated'] == '1') {
        $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
        if ($count > 0) {
            $success_message .= "<br>อัพเดตสถานะการชำระเงินให้กับสมาชิกในกลุ่มอีก {$count} คนเรียบร้อยแล้ว";
        } else {
            $success_message .= "<br>อัพเดตสถานะการชำระเงินให้กับสมาชิกในกลุ่มเรียบร้อยแล้ว";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดการลงทะเบียน - ระบบจัดการการลงทะเบียน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- เพิ่ม Light Gallery สำหรับขยายรูปภาพ -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.1/css/lightgallery.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* คงไว้ตามเดิม */
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

        .nav-tabs {
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 0;
            color: var(--text-primary);
            font-weight: 500;
            padding: 0.8rem 1.5rem;
            transition: all 0.2s;
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
            border-color: transparent;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: transparent;
            border-bottom: 2px solid var(--primary-color);
        }

        .file-preview {
            border: 1px solid rgba(0,0,0,0.1);
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            background-color: white;
        }

        .file-preview img {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
            border-radius: 0.5rem;
            cursor: pointer; /* เพิ่มเคอร์เซอร์เป็นรูปมือชี้เพื่อบอกว่าคลิกได้ */
        }

        .file-preview-pdf {
            height: 200px;
            border: 1px solid rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border-radius: 0.5rem;
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

        .alert-success {
            background-color: rgba(76, 175, 80, 0.1);
            color: #2e7d32;
        }

        .alert-danger {
            background-color: rgba(244, 67, 54, 0.1);
            color: #d32f2f;
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
        
        /* Custom timeline styles */
        .approval-timeline {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 1rem;
        }
        
        .approval-timeline::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: rgba(0,0,0,0.1);
        }
        
        .timeline-point {
            position: absolute;
            left: 0;
            top: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: var(--primary-color);
        }
        
        .timeline-content {
            padding-bottom: 1rem;
        }
        
        .timeline-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .timeline-date {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        /* เพิ่มสไตล์สำหรับ light gallery */
        .gallery-item {
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .gallery-item:hover {
            opacity: 0.9;
        }
        
        /* สไตล์เพิ่มเติมสำหรับแสดงสถานะสมาชิกกลุ่ม */
        .group-members-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .group-member-item {
            padding: 0.5rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            background-color: rgba(0,0,0,0.02);
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
                        <a href="registrations.php" class="nav-link active">
                            <i class="fas fa-users"></i>
                            <span>รายการลงทะเบียน</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-clock"></i>
                            <span>รอการอนุมัติ</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
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
                        <h4>รายละเอียดการลงทะเบียน</h4>
                        <p class="text-muted">ID: <?php echo $registration_id; ?> | วันที่ลงทะเบียน: <?php echo $registration['formatted_date']; ?></p>
                    </div>
                    <div>
                        <a href="registrations.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left me-2"></i>
                            กลับไปหน้ารายการ
                        </a>
                        <button type="submit" form="registrationForm" name="update_registration" value="1" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            บันทึกการเปลี่ยนแปลง
                        </button>
                    </div>
                </div>

                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                <form method="post" action="" id="registrationForm">
                    <input type="hidden" name="update_registration" value="1">
                    <!-- เพิ่มฟิลด์เก็บข้อมูลว่าต้องการอัพเดตทั้งกลุ่มหรือไม่ -->
                    <input type="hidden" name="update_group" id="updateGroupField" value="0">
                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-lg-8">
                            <!-- ข้อมูลส่วนตัว -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="title">
                                        <i class="fas fa-user"></i>
                                        ข้อมูลส่วนตัว
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <label class="col-lg-3 col-form-label">คำนำหน้า</label>
                                        <div class="col-lg-9">
                                            <select class="form-select" name="title" id="title">
                                                <option value="นาย" <?php echo ($registration['title'] == 'นาย') ? 'selected' : ''; ?>>นาย</option>
                                                <option value="นาง" <?php echo ($registration['title'] == 'นาง') ? 'selected' : ''; ?>>นาง</option>
                                                <option value="นางสาว" <?php echo ($registration['title'] == 'นางสาว') ? 'selected' : ''; ?>>นางสาว</option>
                                                <option value="อื่นๆ" <?php echo ($registration['title'] == 'อื่นๆ') ? 'selected' : ''; ?>>อื่นๆ</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3" id="titleOtherWrapper" style="<?php echo ($registration['title'] == 'อื่นๆ') ? '' : 'display: none;'; ?>">
                                        <label class="col-lg-3 col-form-label">คำนำหน้าอื่นๆ</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="title_other" value="<?php echo $registration['title_other']; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <label class="col-lg-3 col-form-label">ชื่อ-นามสกุล</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="fullname" value="<?php echo $registration['fullname']; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <label class="col-lg-3 col-form-label">หน่วยงาน</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="organization" value="<?php echo $registration['organization']; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <label class="col-lg-3 col-form-label">ตำแหน่ง</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="position" value="<?php echo $registration['position']; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div class="row mb-3">
                                                <label class="col-lg-6 col-form-label">เบอร์โทร</label>
                                                <div class="col-lg-6">
                                                    <input type="text" class="form-control" name="phone" value="<?php echo $registration['phone']; ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="row mb-3">
                                                <label class="col-lg-3 col-form-label">Line ID</label>
                                                <div class="col-lg-9">
                                                    <input type="text" class="form-control" name="line_id" value="<?php echo $registration['line_id']; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <label class="col-lg-3 col-form-label">อีเมล</label>
                                        <div class="col-lg-9">
                                            <input type="email" class="form-control" name="email" value="<?php echo $registration['email']; ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="title">
                                        <i class="fas fa-map-marker-alt"></i>
                                        ข้อมูลที่อยู่
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <!-- Nav tabs -->
                                    <ul class="nav nav-tabs mb-4" id="addressTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="invoice-tab" data-bs-toggle="tab" data-bs-target="#invoice" type="button" role="tab">
                                                <i class="fas fa-file-invoice me-1"></i> ที่อยู่ออกใบเสร็จ
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="house-tab" data-bs-toggle="tab" data-bs-target="#house" type="button" role="tab">
                                            <i class="fas fa-home me-1"></i> ที่อยู่ตามทะเบียนบ้าน
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="current-tab" data-bs-toggle="tab" data-bs-target="#current" type="button" role="tab">
                                                <i class="fas fa-map-pin me-1"></i> ที่อยู่ปัจจุบัน
                                            </button>
                                        </li>
                                    </ul>
                                    
                                    <!-- Tab content -->
                                    <div class="tab-content" id="addressTabContent">
                                        <?php foreach(['invoice', 'house', 'current'] as $i => $address_type): 
                                            $tab_class = ($i == 0) ? 'show active' : '';
                                            $address_data = isset($addresses[$address_type]) ? $addresses[$address_type] : [
                                                'address' => '', 
                                                'province_id' => 0, 
                                                'district_id' => 0, 
                                                'subdistrict_id' => 0, 
                                                'zipcode' => ''
                                            ];
                                            
                                            // Convert NULL values to empty strings for data attributes
                                            $province_id = isset($address_data['province_id']) && !is_null($address_data['province_id']) ? $address_data['province_id'] : '';
                                            $district_id = isset($address_data['district_id']) && !is_null($address_data['district_id']) ? $address_data['district_id'] : '';
                                            $subdistrict_id = isset($address_data['subdistrict_id']) && !is_null($address_data['subdistrict_id']) ? $address_data['subdistrict_id'] : '';
                                        ?>
                                        <div class="tab-pane fade <?php echo $tab_class; ?>" id="<?php echo $address_type; ?>" role="tabpanel">
                                            <div class="row mb-3">
                                                <label class="col-lg-3 col-form-label">ที่อยู่</label>
                                                <div class="col-lg-9">
                                                    <textarea class="form-control" name="address[<?php echo $address_type; ?>]" rows="3"><?php echo $address_data['address']; ?></textarea>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <label class="col-lg-3 col-form-label">จังหวัด</label>
                                                <div class="col-lg-9">
                                                    <select class="form-select province-select" name="province_id[<?php echo $address_type; ?>]" data-address-type="<?php echo $address_type; ?>">
                                                        <option value="">--- เลือกจังหวัด ---</option>
                                                        <?php foreach($provinces as $province): ?>
                                                            <option value="<?php echo $province['id']; ?>" <?php echo ($province_id == $province['id']) ? 'selected' : ''; ?>>
                                                                <?php echo $province['name_in_thai']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="hidden" id="saved_province_<?php echo $address_type; ?>" value="<?php echo $province_id; ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <label class="col-lg-3 col-form-label">อำเภอ/เขต</label>
                                                <div class="col-lg-9">
                                                    <select class="form-select district-select" name="district_id[<?php echo $address_type; ?>]" data-address-type="<?php echo $address_type; ?>">
                                                        <option value="">--- เลือกอำเภอ/เขต ---</option>
                                                        <!-- จะโหลดด้วย JavaScript -->
                                                    </select>
                                                    <input type="hidden" id="saved_district_<?php echo $address_type; ?>" value="<?php echo $district_id; ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <label class="col-lg-3 col-form-label">ตำบล/แขวง</label>
                                                <div class="col-lg-9">
                                                    <select class="form-select subdistrict-select" name="subdistrict_id[<?php echo $address_type; ?>]" data-address-type="<?php echo $address_type; ?>">
                                                        <option value="">--- เลือกตำบล/แขวง ---</option>
                                                        <!-- จะโหลดด้วย JavaScript -->
                                                    </select>
                                                    <input type="hidden" id="saved_subdistrict_<?php echo $address_type; ?>" value="<?php echo $subdistrict_id; ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <label class="col-lg-3 col-form-label">รหัสไปรษณีย์</label>
                                                <div class="col-lg-9">
                                                    <input type="text" class="form-control" name="zipcode[<?php echo $address_type; ?>]" value="<?php echo $address_data['zipcode']; ?>" maxlength="5">
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- เอกสารเพิ่มเติม -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="title">
                                        <i class="fas fa-file-alt"></i>
                                        เอกสารเพิ่มเติม
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row" id="documents-gallery">
                                        <?php if (count($documents) > 0): ?>
                                            <?php foreach($documents as $document): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="file-preview h-100">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <strong>
                                                                <?php 
                                                                    $doc_type_name = 'เอกสารทั่วไป';
                                                                    $badge_class = 'bg-secondary';
                                                                    if ($document['document_type'] == 'identification') {
                                                                        $doc_type_name = 'เอกสารยืนยันตัวตน';
                                                                        $badge_class = 'bg-primary';
                                                                    } elseif ($document['document_type'] == 'other') {
                                                                        $doc_type_name = 'เอกสารอื่นๆ';
                                                                        $badge_class = 'bg-info';
                                                                    }
                                                                ?>
                                                                <span class="badge <?php echo $badge_class; ?>"><?php echo $doc_type_name; ?></span>
                                                            </strong>
                                                            <span class="badge bg-light text-dark"><?php echo strtoupper(pathinfo($document['file_name'], PATHINFO_EXTENSION)); ?></span>
                                                        </div>
                                                        
                                                        <?php if (strpos($document['file_type'], 'image') !== false): ?>
                                                            <div class="text-center mb-2">
                                                                <div class="gallery-item" data-src="../<?php echo $document['file_path']; ?>">
                                                                    <img src="../<?php echo $document['file_path']; ?>" alt="Document image">
                                                                </div>
                                                            </div>
                                                        <?php elseif (strpos($document['file_type'], 'pdf') !== false): ?>
                                                            <div class="file-preview-pdf mb-2">
                                                                <a href="../<?php echo $document['file_path']; ?>" target="_blank" class="btn btn-outline-primary">
                                                                    <i class="fas fa-file-pdf me-2"></i>
                                                                    เปิดไฟล์ PDF
                                                                </a>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="file-preview-pdf mb-2">
                                                                <a href="../<?php echo $document['file_path']; ?>" target="_blank" class="btn btn-outline-primary">
                                                                    <i class="fas fa-file me-2"></i>
                                                                    เปิดไฟล์
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted">
                                                                <?php echo $document['file_name']; ?><br>
                                                                (<?php echo round($document['file_size']/1024, 2); ?> KB)
                                                            </small>
                                                            <a href="../<?php echo $document['file_path']; ?>" class="btn btn-sm btn-outline-secondary" download>
                                                                <i class="fas fa-download"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="col-12">
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    ไม่พบเอกสารเพิ่มเติม
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="col-lg-4">
                            <!-- สถานะการลงทะเบียน -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="title">
                                        <i class="fas fa-info-circle"></i>
                                        สถานะการลงทะเบียน
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-4 text-center p-3" style="background-color: rgba(0,0,0,0.03); border-radius: 0.5rem;">
                                        <?php if($registration['is_approved'] == 1): ?>
                                            <div class="mb-2"><i class="fas fa-check-circle fa-3x text-success"></i></div>
                                            <h5 class="mb-1">อนุมัติแล้ว</h5>
                                            <p class="mb-0 text-muted">วันที่ <?php echo $registration['formatted_approved_date']; ?></p>
                                        <?php else: ?>
                                            <div class="mb-2"><i class="fas fa-clock fa-3x text-warning"></i></div>
                                            <h5 class="mb-1">รอการอนุมัติ</h5>
                                            <p class="mb-0 text-muted">กรุณาตรวจสอบข้อมูล</p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">เปลี่ยนสถานะการอนุมัติ</label>
                                        <select class="form-select" name="is_approved">
                                            <option value="0" <?php echo ($registration['is_approved'] == 0) ? 'selected' : ''; ?>>รอการอนุมัติ</option>
                                            <option value="1" <?php echo ($registration['is_approved'] == 1) ? 'selected' : ''; ?>>อนุมัติการลงทะเบียน</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Timeline -->
                                    <div class="approval-timeline">
                                        <div class="timeline-point"></div>
                                        <div class="timeline-content">
                                            <p class="timeline-title">ลงทะเบียน</p>
                                            <p class="timeline-date"><?php echo $registration['formatted_date']; ?></p>
                                        </div>
                                    </div>
                                    
                                    <?php if($registration['is_approved'] == 1 && $registration['payment_status'] == 'paid_approved'): ?>
                                    <div class="approval-timeline">
                                        <div class="timeline-point bg-success"></div>
                                        <div class="timeline-content">
                                            <p class="timeline-title">อนุมัติแล้ว</p>
                                            <p class="timeline-date"><?php echo $registration['formatted_approved_date']; ?></p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                           <!-- สถานะการชำระเงิน -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="title">
                                        <i class="fas fa-money-bill-wave"></i>
                                        สถานะการชำระเงิน
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-4 text-center p-3" style="background-color: rgba(0,0,0,0.03); border-radius: 0.5rem;">
                                        <?php if($registration['payment_status'] == 'paid_approved'): ?>
                                            <div class="mb-2"><i class="fas fa-check-circle fa-3x text-success"></i></div>
                                            <h5 class="mb-1">ชำระแล้ว (อนุมัติแล้ว)</h5>
                                        <?php elseif($registration['payment_status'] == 'paid'): ?>
                                            <div class="mb-2"><i class="fas fa-clock fa-3x text-warning"></i></div>
                                            <h5 class="mb-1">ชำระแล้ว (รอตรวจสอบจากเจ้าหน้าที่)</h5>
                                        <?php elseif($registration['payment_status'] == 'paid_onsite'): ?>
                                            <div class="mb-2"><i class="fas fa-money-bill fa-3x text-info"></i></div>
                                            <h5 class="mb-1">ชำระเงินที่หน้างาน</h5>
                                        <?php else: ?>
                                            <div class="mb-2"><i class="fas fa-times-circle fa-3x text-danger"></i></div>
                                            <h5 class="mb-1">ยังไม่ชำระ</h5>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- แสดงข้อมูลกลุ่ม (ถ้ามี) -->
                                    <?php if (!empty($registration_group) && count($all_group_members) > 0): ?>
                                    <div class="mt-3 mb-3 p-3 bg-light rounded">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-users text-primary me-2"></i>
                                            <h6 class="mb-0">การลงทะเบียนแบบกลุ่ม (<?php echo count($all_group_members) + 1; ?> คน)</h6>
                                        </div>
                                        <p class="text-muted small mb-2">
                                            เมื่อคุณเปลี่ยนสถานะการชำระเงิน ระบบจะถามว่าคุณต้องการอัพเดตสถานะ
                                            ของสมาชิกทุกคนในกลุ่มด้วยหรือไม่
                                        </p>
                                        
                                        <!-- แสดงรายชื่อสมาชิกในกลุ่มและสถานะ -->
                                        <div class="mt-2">
                                            <div class="group-member-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <i class="fas fa-user me-2 text-primary"></i>
                                                        <strong><?php echo $registration['fullname']; ?></strong>
                                                        <span class="badge bg-secondary ms-2">ปัจจุบัน</span>
                                                    </div>
                                                    <div>
                                                        <?php if($registration['payment_status'] == 'paid_approved'): ?>
                                                            <span class="badge bg-success">ชำระแล้ว (อนุมัติแล้ว)</span>
                                                        <?php elseif($registration['payment_status'] == 'paid'): ?>
                                                            <span class="badge bg-warning text-dark">ชำระแล้ว (รอตรวจสอบ)</span>
                                                        <?php elseif($registration['payment_status'] == 'paid_onsite'): ?>
                                                            <span class="badge bg-info">ชำระเงินที่หน้างาน</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">ยังไม่ชำระ</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php foreach($all_group_members as $member): ?>
                                                <div class="group-member-item">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <i class="fas fa-user me-2"></i>
                                                            <?php echo $member['fullname']; ?>
                                                        </div>
                                                        <div>
                                                            <?php if($member['payment_status'] == 'paid_approved'): ?>
                                                                <span class="badge bg-success">ชำระแล้ว (อนุมัติแล้ว)</span>
                                                            <?php elseif($member['payment_status'] == 'paid'): ?>
                                                                <span class="badge bg-warning text-dark">ชำระแล้ว (รอตรวจสอบ)</span>
                                                            <?php elseif($member['payment_status'] == 'paid_onsite'): ?>
                                                                <span class="badge bg-info">ชำระเงินที่หน้างาน</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">ยังไม่ชำระ</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">เปลี่ยนสถานะการชำระเงิน</label>
                                        <select class="form-select" name="payment_status">
                                            <option value="not_paid" <?php echo ($registration['payment_status'] == 'not_paid') ? 'selected' : ''; ?>>ยังไม่ชำระ</option>
                                            <option value="paid" <?php echo ($registration['payment_status'] == 'paid') ? 'selected' : ''; ?>>ชำระแล้ว (รอตรวจสอบจากเจ้าหน้าที่)</option>
                                            <option value="paid_approved" <?php echo ($registration['payment_status'] == 'paid_approved') ? 'selected' : ''; ?>>ชำระแล้ว (อนุมัติแล้ว)</option>
                                            <option value="paid_onsite" <?php echo ($registration['payment_status'] == 'paid_onsite') ? 'selected' : ''; ?>>ชำระเงินที่หน้างาน</option>
                                        </select>
                                    </div>
                                    
                                    <!-- หลักฐานการชำระเงิน -->
                                    <div class="mb-3">
                                        <label class="form-label">หลักฐานการชำระเงิน</label>
                                        <div id="payment-gallery">
                                            <!-- Current Registration's Files -->
                                            <?php if (count($payment_files) > 0): ?>
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <h6 class="text-primary mb-0"><i class="fas fa-user me-2"></i>หลักฐานการชำระเงินของคุณ</h6>
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                                        <i class="fas fa-upload me-1"></i> อัพโหลดเอกสารใหม่
                                                    </button>
                                                </div>
                                                <?php foreach($payment_files as $file): ?>
                                                    <div class="file-preview mb-3 border border-primary">
                                                        <?php if (strpos($file['file_type'], 'image') !== false): ?>
                                                            <div class="gallery-item" data-src="../<?php echo $file['file_path']; ?>">
                                                                <img src="../<?php echo $file['file_path']; ?>" alt="Payment proof" class="img-fluid mb-2">
                                                            </div>
                                                        <?php elseif (strpos($file['file_type'], 'pdf') !== false): ?>
                                                            <div class="file-preview-pdf mb-2">
                                                                <a href="../<?php echo $file['file_path']; ?>" target="_blank" class="btn btn-outline-primary">
                                                                    <i class="fas fa-file-pdf me-2"></i>
                                                                    เปิดไฟล์ PDF
                                                                </a>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="file-preview-pdf mb-2">
                                                                <a href="../<?php echo $file['file_path']; ?>" target="_blank" class="btn btn-outline-primary">
                                                                    <i class="fas fa-file me-2"></i>
                                                                    เปิดไฟล์
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted">
                                                                <?php echo $file['file_name']; ?><br>
                                                                (<?php echo round($file['file_size']/1024, 2); ?> KB)
                                                            </small>
                                                            <div>
                                                                <a href="../<?php echo $file['file_path']; ?>" class="btn btn-sm btn-outline-secondary" download>
                                                                    <i class="fas fa-download"></i>
                                                                </a>
                                                                <button type="button" class="btn btn-sm btn-outline-warning ms-1" 
                                                                        onclick="updateFile(<?php echo $file['id']; ?>, '<?php echo $file['file_name']; ?>')">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-danger ms-1" 
                                                                        onclick="deleteFile(<?php echo $file['id']; ?>, '<?php echo $file['file_name']; ?>')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <h6 class="text-primary mb-0"><i class="fas fa-user me-2"></i>หลักฐานการชำระเงินของคุณ</h6>
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                                        <i class="fas fa-upload me-1"></i> อัพโหลดเอกสาร
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Group Members' Files -->
                                            <?php if (count($group_payment_files) > 0): ?>
                                                <h6 class="text-success mt-4 mb-3"><i class="fas fa-users me-2"></i>หลักฐานการชำระเงินของสมาชิกในกลุ่ม</h6>
                                                <?php foreach($group_payment_files as $file): ?>
                                                    <div class="file-preview mb-3 border border-success">
                                                        <div class="bg-light p-2 mb-2 rounded">
                                                            <small class="text-success">
                                                                <i class="fas fa-user me-1"></i> 
                                                                <strong><?php echo $file['member_name']; ?></strong>
                                                            </small>
                                                        </div>
                                                    
                                                        <?php if (strpos($file['file_type'], 'image') !== false): ?>
                                                            <div class="gallery-item" data-src="../<?php echo $file['file_path']; ?>">
                                                                <img src="../<?php echo $file['file_path']; ?>" alt="Payment proof" class="img-fluid mb-2">
                                                            </div>
                                                        <?php elseif (strpos($file['file_type'], 'pdf') !== false): ?>
                                                            <div class="file-preview-pdf mb-2">
                                                                <a href="../<?php echo $file['file_path']; ?>" target="_blank" class="btn btn-outline-success">
                                                                    <i class="fas fa-file-pdf me-2"></i>
                                                                    เปิดไฟล์ PDF
                                                                </a>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="file-preview-pdf mb-2">
                                                                <a href="../<?php echo $file['file_path']; ?>" target="_blank" class="btn btn-outline-success">
                                                                    <i class="fas fa-file me-2"></i>
                                                                    เปิดไฟล์
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted">
                                                                <?php echo $file['file_name']; ?><br>
                                                                (<?php echo round($file['file_size']/1024, 2); ?> KB)
                                                            </small>
                                                            <a href="../<?php echo $file['file_path']; ?>" class="btn btn-sm btn-outline-secondary" download>
                                                                <i class="fas fa-download"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            
                                            <!-- No Files Message -->
                                            <?php if (count($payment_files) == 0 && count($group_payment_files) == 0): ?>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    ไม่พบหลักฐานการชำระเงินของคุณหรือสมาชิกในกลุ่มของคุณ
                                                    <button type="button" class="btn btn-sm btn-warning ms-3" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                                        <i class="fas fa-upload me-1"></i> อัพโหลดเอกสาร
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="title">
                                        <i class="fas fa-cogs"></i>
                                        การดำเนินการ
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-primary" onclick="printRegistration()">
                                            <i class="fas fa-print me-2"></i>
                                            พิมพ์ข้อมูลการลงทะเบียน
                                        </button>
                                        
                                        <button type="button" class="btn btn-outline-success" onclick="sendConfirmation()">
                                            <i class="fas fa-envelope me-2"></i>
                                            ส่งอีเมลยืนยันการลงทะเบียน
                                        </button>
                                        
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Upload/Update Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadModalLabel">อัพโหลดหลักฐานการชำระเงิน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="uploadForm" action="api/update_payment_file.php?return_id=<?php echo $registration_id; ?>" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="registration_id" value="<?php echo $registration_id; ?>">
                        <input type="hidden" name="file_id" id="file_id" value="0">
                        <input type="hidden" name="action" id="fileAction" value="upload">
                        
                        <div class="mb-3">
                            <label for="payment_file" class="form-label">เลือกไฟล์</label>
                            <input type="file" class="form-control" id="payment_file" name="payment_file">
                            <div class="form-text">รองรับไฟล์: JPG, JPEG, PNG, PDF (ขนาดไม่เกิน 5MB)</div>
                        </div>
                        
                        <div id="updateFileInfo" class="alert alert-info" style="display: none;">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="fileUpdateMessage"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i>
                            <span id="submitBtnText">อัพโหลด</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- เพิ่ม Light Gallery สำหรับขยายรูปภาพ -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.1/lightgallery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.1/plugins/zoom/lg-zoom.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.1/plugins/thumbnail/lg-thumbnail.min.js"></script>

    <!-- JavaScript สำหรับการจัดการฟอร์มและเหตุการณ์ -->
    <script>
      function updateFile(fileId, fileName) {
        // ตรวจสอบก่อนว่าฟอร์มมีอยู่จริง
        if ($('#uploadForm').length > 0) {
            $('#uploadForm')[0].reset();
        }
        
        // ส่วนที่เหลือของฟังก์ชัน
        $('#file_id').val(fileId);
        $('#fileAction').val('update');
        $('#submitBtnText').text('อัพเดทเอกสาร');
        $('#uploadModalLabel').text('อัพเดทหลักฐานการชำระเงิน');
        $('#fileUpdateMessage').text('กำลังแทนที่ไฟล์: ' + fileName);
        $('#updateFileInfo').show();
        $('#uploadModal').modal('show');
    }
    
    function deleteFile(fileId, fileName) {
        Swal.fire({
            title: 'ยืนยันการลบไฟล์',
            text: 'คุณต้องการลบไฟล์ "' + fileName + '" ใช่หรือไม่?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ใช่, ลบไฟล์',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#d33',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Send delete request
                $.ajax({
                    url: 'api/update_payment_file.php',
                    type: 'POST',
                    data: { 
                        file_id: fileId,
                        action: 'delete',
                        registration_id: <?php echo $registration_id; ?>
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'ลบไฟล์สำเร็จ',
                                text: 'ไฟล์ถูกลบเรียบร้อยแล้ว',
                                confirmButtonText: 'ตกลง'
                            }).then(() => {
                                // Reload the page to show the updated file list
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: response.message || 'ไม่สามารถลบไฟล์ได้ กรุณาลองใหม่อีกครั้ง'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: 'ไม่สามารถเชื่อมต่อกับระบบได้ กรุณาลองใหม่อีกครั้ง'
                        });
                    }
                });
            }
        });
    }
    
    $(document).ready(function() {
        // รีเซ็ตฟอร์มเมื่อปิด Modal
        $('#uploadModal').on('hidden.bs.modal', function() {
            $('#uploadForm')[0].reset();
            $('#file_id').val(0);
            $('#fileAction').val('upload');
            $('#submitBtnText').text('อัพโหลด');
            $('#uploadModalLabel').text('อัพโหลดหลักฐานการชำระเงิน');
            $('#updateFileInfo').hide();
        });
        
        // เพิ่ม Event Listener สำหรับการกดปุ่มบันทึก
        $("#registrationForm").on("submit", function(e) {
            // ตรวจสอบว่ามีการเปลี่ยนแปลงสถานะการชำระเงินหรือไม่
            const originalStatus = "<?php echo $registration['payment_status']; ?>";
            const newStatus = $("select[name='payment_status']").val();
            const hasGroup = "<?php echo !empty($registration_group) ? 'true' : 'false'; ?>" === 'true';
            const groupMemberCount = <?php echo isset($all_group_members) ? count($all_group_members) + 1 : 1; ?>;
            
            // ตรวจสอบว่ามีกลุ่มและมีการเปลี่ยนสถานะการชำระเงิน
            if (hasGroup && originalStatus !== newStatus && groupMemberCount > 1) {
                e.preventDefault(); // หยุดการส่งฟอร์มชั่วคราว
                
                Swal.fire({
                    title: 'ต้องการอัพเดตทั้งกลุ่ม?',
                    html: `<p>คุณกำลังเปลี่ยนสถานะการชำระเงินจาก 
                          <b>${getPaymentStatusText(originalStatus)}</b> เป็น 
                          <b>${getPaymentStatusText(newStatus)}</b></p>
                          <p>คุณต้องการอัพเดตสมาชิกทั้งหมดในกลุ่มนี้ด้วยหรือไม่?</p>
                          <p class="text-primary"><i class="fas fa-users me-2"></i>มีสมาชิกทั้งหมด ${groupMemberCount} คนในกลุ่มนี้</p>`,
                    icon: 'question',
                    showDenyButton: true,
                    confirmButtonText: 'ใช่, อัพเดตทั้งกลุ่ม',
                    denyButtonText: 'อัพเดตเฉพาะรายการนี้',
                    confirmButtonColor: '#4caf50',
                    denyButtonColor: '#ff9800',
                }).then((result) => {
                    if (result.isConfirmed) {
                        // อัพเดตทั้งกลุ่ม
                        $("#updateGroupField").val("1");
                    } else {
                        // อัพเดตเฉพาะรายการนี้
                        $("#updateGroupField").val("0");
                    }
                    // ส่งฟอร์ม
                    $("#registrationForm").off('submit').submit();
                });
            }
        });
        
        // แสดง/ซ่อน field คำนำหน้าอื่นๆ
        $('#title').change(function() {
            if ($(this).val() === 'อื่นๆ') {
                $('#titleOtherWrapper').show();
            } else {
                $('#titleOtherWrapper').hide();
            }
        });
        
        // เริ่มต้นโหลดข้อมูลที่อยู่
        initializeAddressData();
        
        // Event listener สำหรับการเปลี่ยนจังหวัด
        $('.province-select').change(function() {
            const provinceId = $(this).val();
            const addressType = $(this).data('address-type');
            
            if (provinceId) {
                loadDistricts(provinceId, addressType);
            } else {
                // ล้างข้อมูลอำเภอและตำบล
                resetDistrictAndSubdistrict(addressType);
            }
        });
        
        // Event listener สำหรับการเปลี่ยนอำเภอ
        $(document).on('change', '.district-select', function() {
            const districtId = $(this).val();
            const addressType = $(this).data('address-type');
            
            if (districtId) {
                loadSubdistricts(districtId, addressType);
            } else {
                // ล้างข้อมูลตำบล
                resetSubdistrict(addressType);
            }
        });
        
        // อัพเดทรหัสไปรษณีย์อัตโนมัติเมื่อเลือกตำบล
        $(document).on('change', '.subdistrict-select', function() {
            const addressType = $(this).data('address-type');
            const zipcode = $('option:selected', this).data('zipcode');
            
            if (zipcode) {
                $(`input[name="zipcode[${addressType}]"]`).val(zipcode);
            }
        });
        
        // เริ่มต้นการใช้งาน Light Gallery สำหรับรูปภาพทั้งหมด
        lightGallery(document.getElementById('documents-gallery'), {
            selector: '.gallery-item',
            plugins: [lgZoom, lgThumbnail],
            speed: 500,
            download: true,
            counter: true,
            thumbnail: true
        });
        
        // สำหรับหลักฐานการชำระเงิน
        lightGallery(document.getElementById('payment-gallery'), {
            selector: '.gallery-item',
            plugins: [lgZoom, lgThumbnail],
            speed: 500,
            download: true,
            counter: true,
            thumbnail: true
        });
    });

    // ฟังก์ชันแปลงรหัสสถานะเป็นข้อความ
    function getPaymentStatusText(status) {
        switch(status) {
            case 'paid_approved':
                return 'ชำระแล้ว (อนุมัติแล้ว)';
            case 'paid':
                return 'ชำระแล้ว (รอตรวจสอบจากเจ้าหน้าที่)';
            case 'paid_onsite':
                return 'ชำระเงินที่หน้างาน';
            case 'not_paid':
            default:
                return 'ยังไม่ชำระ';
        }
    }

    // เริ่มต้นโหลดข้อมูลที่อยู่
    function initializeAddressData() {
        console.log("Initializing address data...");
        // ทำซ้ำสำหรับแต่ละประเภทที่อยู่
        ['invoice', 'house', 'current'].forEach(function(addressType) {
            const provinceId = $(`#saved_province_${addressType}`).val();
            console.log(`Address type: ${addressType}, Province ID: ${provinceId}`);
            
            if (provinceId && provinceId > 0) {
                // โหลดข้อมูลอำเภอเมื่อเริ่มต้น
                loadDistricts(provinceId, addressType, true);
            }
        });
    }

    // ฟังก์ชันล้างข้อมูลอำเภอและตำบล
    function resetDistrictAndSubdistrict(addressType) {
        $(`select[name="district_id[${addressType}]"]`).html('<option value="">--- เลือกอำเภอ/เขต ---</option>');
        resetSubdistrict(addressType);
    }

    // ฟังก์ชันล้างข้อมูลตำบล
    function resetSubdistrict(addressType) {
        $(`select[name="subdistrict_id[${addressType}]"]`).html('<option value="">--- เลือกตำบล/แขวง ---</option>');
        $(`input[name="zipcode[${addressType}]"]`).val('');
    }

    // ฟังก์ชันโหลดข้อมูลอำเภอ
    function loadDistricts(provinceId, addressType, isInitial = false) {
        console.log(`Loading districts for province ${provinceId}, address type ${addressType}`);
        
        $.ajax({
            url: 'api/get_districts.php',
            type: 'GET',
            data: { province_id: provinceId },
            dataType: 'json',
            success: function(data) {
                let options = '<option value="">--- เลือกอำเภอ/เขต ---</option>';
                
                if (data && data.length > 0) {
                    // ดึงค่า district_id ที่บันทึกไว้
                    let savedDistrictId = $(`#saved_district_${addressType}`).val();
                    console.log(`Saved district ID for ${addressType}: ${savedDistrictId}`);
                    
                    $.each(data, function(index, district) {
                        const selected = (district.id == savedDistrictId) ? 'selected' : '';
                        options += `<option value="${district.id}" ${selected}>${district.name_in_thai}</option>`;
                    });
                    
                    $(`select[name="district_id[${addressType}]"]`).html(options);
                    
                    // โหลดข้อมูลตำบลหากมีการเลือกอำเภอ
                    const selectedDistrictId = $(`select[name="district_id[${addressType}]"]`).val();
                    
                    if (selectedDistrictId) {
                        loadSubdistricts(selectedDistrictId, addressType, isInitial);
                    } else if (savedDistrictId && isInitial) {
                        // หากไม่มีค่าที่เลือกแต่มีค่าที่บันทึกไว้และเป็นการโหลดครั้งแรก
                        loadSubdistricts(savedDistrictId, addressType, isInitial);
                    }
                } else {
                    resetSubdistrict(addressType);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading districts:', error);
                console.error('Server response:', xhr.responseText);
                resetDistrictAndSubdistrict(addressType);
            }
        });
    }

    // ฟังก์ชันโหลดข้อมูลตำบล
    function loadSubdistricts(districtId, addressType, isInitial = false) {
        console.log(`Loading subdistricts for district ${districtId}, address type ${addressType}`);
        
        $.ajax({
            url: 'api/get_subdistricts.php',
            type: 'GET',
            data: { district_id: districtId },
            dataType: 'json',
            success: function(data) {
                let options = '<option value="">--- เลือกตำบล/แขวง ---</option>';
                
                if (data && data.length > 0) {
                    // ดึงค่า subdistrict_id ที่บันทึกไว้
                    let savedSubdistrictId = $(`#saved_subdistrict_${addressType}`).val();
                    console.log(`Saved subdistrict ID for ${addressType}: ${savedSubdistrictId}`);
                    
                    $.each(data, function(index, subdistrict) {
                        const selected = (subdistrict.id == savedSubdistrictId) ? 'selected' : '';
                        options += `<option value="${subdistrict.id}" data-zipcode="${subdistrict.zip_code}" ${selected}>${subdistrict.name_in_thai}</option>`;
                    });
                    
                    $(`select[name="subdistrict_id[${addressType}]"]`).html(options);
                    
                    // อัพเดทรหัสไปรษณีย์อัตโนมัติหากมีการเลือกตำบล
                    const selectedSubdistrict = $(`select[name="subdistrict_id[${addressType}]"] option:selected`);
                    if (selectedSubdistrict.length > 0 && selectedSubdistrict.val() !== '') {
                        const zipcode = selectedSubdistrict.data('zipcode');
                        if (zipcode) {
                            $(`input[name="zipcode[${addressType}]"]`).val(zipcode);
                        }
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading subdistricts:', error);
                console.error('Server response:', xhr.responseText);
                resetSubdistrict(addressType);
            }
        });
    }

    // ฟังก์ชันสำหรับพิมพ์ข้อมูลการลงทะเบียน
    function printRegistration() {
        // เตรียมหน้าสำหรับพิมพ์
        let printWindow = window.open('', '_blank');
        let registrationId = <?php echo $registration_id; ?>;
        
        // โหลดข้อมูลและแสดงในรูปแบบที่เหมาะสำหรับการพิมพ์
        $.ajax({
            url: 'print_registration.php',
            type: 'GET',
            data: { id: registrationId },
            success: function(response) {
                printWindow.document.write(response);
                printWindow.document.close();
                printWindow.focus();
                // เริ่มการพิมพ์หลังจากโหลดเสร็จ
                setTimeout(function() {
                    printWindow.print();
                }, 500);
            },
            error: function() {
                printWindow.close();
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถพิมพ์ข้อมูลได้ กรุณาลองใหม่อีกครั้ง',
                });
            }
        });
    }

    // ฟังก์ชันสำหรับส่งอีเมลยืนยันการลงทะเบียน
    function sendConfirmation() {
        let registrationId = <?php echo $registration_id; ?>;
        let email = '<?php echo $registration['email']; ?>';
        let fullname = '<?php echo $registration['fullname']; ?>';
        let paymentStatus = '<?php echo $registration['payment_status']; ?>';
        
        Swal.fire({
            title: 'ยืนยันการส่งอีเมล',
            text: `ต้องการส่งอีเมลยืนยันไปยัง ${email} ใช่หรือไม่?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ใช่, ส่งอีเมล',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // แสดง loading
                Swal.fire({
                    title: 'กำลังส่งอีเมล...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // ส่งคำขอไปยัง API
                $.ajax({
                    url: 'api/send_confirmation_email.php',
                    type: 'POST',
                    data: { 
                        id: registrationId,
                        email: email,
                        fullname: fullname,
                        payment_status: paymentStatus
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'ส่งอีเมลสำเร็จ',
                                text: 'ระบบได้ส่งอีเมลยืนยันไปยังผู้ลงทะเบียนเรียบร้อยแล้ว'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: response.message || 'ไม่สามารถส่งอีเมลได้ กรุณาลองใหม่อีกครั้ง'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: 'ไม่สามารถเชื่อมต่อกับระบบได้ กรุณาลองใหม่อีกครั้ง'
                        });
                    }
                });
            }
        });
    }

    // ฟังก์ชันสำหรับลบข้อมูลการลงทะเบียน
    function deleteRegistration() {
        let registrationId = <?php echo $registration_id; ?>;
        
        Swal.fire({
            title: 'ยืนยันการลบ',
            text: 'คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลการลงทะเบียนนี้? การกระทำนี้ไม่สามารถเปลี่ยนกลับได้',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ใช่, ลบข้อมูล',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#d33',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // ขอยืนยันอีกครั้ง
                Swal.fire({
                    title: 'ยืนยันการลบอีกครั้ง',
                    text: 'ข้อมูลทั้งหมดรวมถึงเอกสารที่อัปโหลดจะถูกลบถาวร',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'ใช่, ลบถาวร',
                    cancelButtonText: 'ยกเลิก',
                    confirmButtonColor: '#d33',
                    reverseButtons: true
                }).then((innerResult) => {
                    if (innerResult.isConfirmed) {
                        // ส่งคำขอลบไปยัง API
                        $.ajax({
                            url: 'api/delete_registration.php',
                            type: 'POST',
                            data: { id: registrationId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'ลบข้อมูลสำเร็จ',
                                        text: 'ระบบได้ลบข้อมูลการลงทะเบียนเรียบร้อยแล้ว',
                                        confirmButtonText: 'ตกลง'
                                    }).then(() => {
                                        window.location.href = 'registrations.php';
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'เกิดข้อผิดพลาด',
                                        text: response.message || 'ไม่สามารถลบข้อมูลได้ กรุณาลองใหม่อีกครั้ง'
                                    });
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'เกิดข้อผิดพลาด',
                                    text: 'ไม่สามารถเชื่อมต่อกับระบบได้ กรุณาลองใหม่อีกครั้ง'
                                });
                            }
                        });
                    }
                });
            }
        });
    }
    </script>
</body>
</html>