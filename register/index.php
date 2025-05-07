<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนสัมมนา | เพิ่มศักยภาพท้องถิ่นเพื่อการขับเคลื่อนอนาคตไทยอย่างยั่งยืน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2C3E50;
            --secondary-color: #34495E;
            --accent-color: #3498DB;
            --success-color: #27AE60;
            --warning-color: #F39C12;
            --error-color: #E74C3C;
        }

        body {
            font-family: "Sarabun", serif;
            background-color: #f8f9fa;
            color: var(--primary-color);
            font-family: 'Sarabun', sans-serif;
        }

        .navbar-brand img {
            height: 40px;
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }

        .form-section {
            display: none;
            animation: fadeIn 0.5s ease-in-out;
        }

        .form-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            background-color: var(--primary-color) !important;
            color: white;
            padding: 1rem 1.5rem;
            border-bottom: none;
        }

        .form-label.required::after {
            content: " *";
            color: var(--error-color);
        }

        .progress-stepper {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
            padding: 0 1rem;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
            padding: 1rem 0;
        }

        .step::before {
            content: '';
            position: absolute;
            top: 50%;
            left: -50%;
            width: 100%;
            height: 2px;
            background-color: #dee2e6;
            z-index: -1;
        }

        .step:first-child::before {
            display: none;
        }

        .step.active {
            color: var(--accent-color);
        }

        .step.completed {
            color: var(--success-color);
        }

        .step-icon {
            width: 40px;
            height: 40px;
            background-color: white;
            border: 2px solid #dee2e6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            margin-bottom:-5px;
        }

        .floating-help {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background-color: var(--accent-color);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        @media (max-width: 768px) {
            .step-text {
                display: none;
            }
            
            .step-icon {
                margin-bottom: 0;
            }
        }
        
        .timeline-container {
            max-width: 600px;
            margin: 0 auto;
        }

        .timeline {
            position: relative;
            padding: 20px 0;
        }

        .timeline-item {
            padding: 15px 0;
            position: relative;
            border-left: 2px solid #dee2e6;
            margin-left: 20px;
        }

        .timeline-icon {
            position: absolute;
            left: -31px;
            top: 16px;
            width: 30px;
            height: 30px;
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .timeline-content {
            margin-left: 30px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        /* สถานะต่างๆ */
        .timeline-item.completed .timeline-icon {
            background: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }

        .timeline-item.current .timeline-icon {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .timeline-item.waiting .timeline-icon {
            background: #f8f9fa;
            color: #dee2e6;
        }

        .timeline-status {
            display: flex;
            align-items: center;
            margin-top: 10px;
            color: var(--primary-color);
        }

        /* เพิ่ม CSS สำหรับ Timeline ที่ปรับปรุงใหม่ */
        .timeline-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .timeline {
            position: relative;
            padding: 20px 0;
        }

        .timeline-item {
            padding: 15px 0;
            position: relative;
            border-left: 3px solid #dee2e6;
            margin-left: 20px;
            margin-bottom: 15px;
        }

        .timeline-icon {
            position: absolute;
            left: -35px;
            top: 15px;
            width: 35px;
            height: 35px;
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .timeline-content {
            margin-left: 30px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .timeline-content h5 {
            margin-bottom: 8px;
            font-weight: 600;
        }

        /* สถานะต่างๆ */
        .timeline-item.completed .timeline-icon {
            background: var(--success-color);
            color: white;
            border-color: var(--success-color);
            transform: scale(1.1);
        }

        .timeline-item.completed .timeline-content {
            border-left: 4px solid var(--success-color);
        }

        .timeline-item.current .timeline-icon {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: scale(1.1);
        }

        .timeline-item.current .timeline-content {
            border-left: 4px solid var(--primary-color);
            background: rgba(52, 152, 219, 0.1);
        }

        .timeline-item.waiting .timeline-icon {
            background: #f8f9fa;
            color: #adb5bd;
        }

        .timeline-status {
            display: flex;
            align-items: center;
            margin-top: 10px;
            color: var(--primary-color);
            font-weight: 500;
        }

        /* Card styling for bank info */
        .card-header.bg-primary {
            background-color: var(--primary-color) !important;
        }

        .text-primary {
            color: var(--primary-color) !important;
        }

        /* Input group styling */
        .input-group-text.bg-primary {
            background-color: var(--primary-color) !important;
        }

        /* Button icons */
        .btn i {
            margin-right: 5px;
        }

        /* Animation for timeline items */
        .timeline-item {
            opacity: 0;
            transform: translateY(10px);
            animation: fadeInUp 0.5s forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .timeline-item:nth-child(1) { animation-delay: 0.1s; }
        .timeline-item:nth-child(2) { animation-delay: 0.2s; }
        .timeline-item:nth-child(3) { animation-delay: 0.3s; }
        .timeline-item:nth-child(4) { animation-delay: 0.4s; }

        /* CSS สำหรับส่วนผู้สมัคร */
        .registrant-item {
            position: relative;
            border-bottom: 1px dashed #ccc;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
        }

        .registrant-counter {
            position: absolute;
            top: -10px;
            left: -10px;
            background-color: var(--primary-color);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        /* ซ่อนส่วนที่อยู่ที่ไม่ต้องการแสดง */
        .hidden-address-section {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary-color);">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="https://arts.rmutsb.ac.th/image/logo_art_2019.png" alt="Logo" class="me-2">
                
            </a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="mb-3">การสัมมนาเพิ่มศักยภาพท้องถิ่น</h1>
            <p class="lead mb-0">เพื่อการขับเคลื่อนอนาคตไทยอย่างยั่งยืน</p>
            <p class="mb-0">วันที่ 13-15 พฤษภาคม พ.ศ. 2568</p>
        </div>
    </section>

    <div class="container py-4">
        <!-- Progress Stepper -->
        <div class="progress-stepper">
            <div class="step active">
                <div class="step-icon">
                    <i class="fas fa-phone"></i>
                </div>
                <div class="step-text">ตรวจสอบ</div>
            </div>
            <div class="step">
                <div class="step-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="step-text">ข้อมูลส่วนตัว</div>
            </div>
            <div class="step">
                <div class="step-icon">
                    <i class="fas fa-money-bill"></i>
                </div>
                <div class="step-text">ชำระเงิน</div>
            </div>
            <div class="step">
                <div class="step-icon">
                    <i class="fas fa-check"></i>
                </div>
                <div class="step-text">เสร็จสิ้น</div>
            </div>
        </div>

        <!-- Phone Check Section -->
        <div id="phoneCheck" class="form-section active">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-search me-2"></i>
                        ตรวจสอบสถานะการลงทะเบียน
                    </h3>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label for="checkPhone" class="form-label required">เบอร์โทรศัพท์</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-phone"></i>
                            </span>
                            <input type="tel" class="form-control form-control-lg" 
                                   id="checkPhone" placeholder="กรอกเบอร์โทรศัพท์" required>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button class="btn btn-primary btn-lg" onclick="checkRegistration()">
                            <i class="fas fa-search me-2"></i>
                            ตรวจสอบ
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Registration Form -->
        <div id="registrationForm" class="form-section">
            <form id="seminarRegistration" enctype="multipart/form-data">
                <!-- ส่วนผู้สมัคร -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-users me-2"></i>
                            ข้อมูลผู้สมัคร
                        </h3>
                    </div>
                    <div class="card-body">
                        <div id="registrants-container">
                            <!-- ผู้สมัครคนแรก -->
                            <div class="registrant-item">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">ผู้สมัครคนที่ 1</h5>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label required">คำนำหน้าชื่อ</label>
                                        <select class="form-select" name="title" required>
                                            <option value="">เลือกคำนำหน้าชื่อ</option>
                                            <option value="นาย">นาย</option>
                                            <option value="นาง">นาง</option>
                                            <option value="นางสาว">นางสาว</option>
                                            <option value="other">อื่นๆ</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label required">ชื่อ-นามสกุล</label>
                                        <input type="text" class="form-control" name="fullname" required>
                                    </div>
                                    <div class="col-md-3 mb-3 title-other-container d-none">
                                        <label class="form-label required">ระบุคำนำหน้าชื่อ</label>
                                        <input type="text" class="form-control" name="title_other">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label required">หน่วยงาน</label>
                                        <input type="text" class="form-control" name="organization" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label required">ตำแหน่ง</label>
                                        <input type="text" class="form-control" name="position" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label required">เบอร์โทรศัพท์</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-phone"></i>
                                            </span>
                                            <input type="tel" class="form-control" name="phone" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label required">อีเมล <span style="color:red">(ใช้สำหรับรับวุฒิบัตร)</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-envelope"></i>
                                            </span>
                                            <input type="email" class="form-control" name="email" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">LINE ID</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fab fa-line"></i>
                                            </span>
                                            <input type="text" class="form-control" name="line_id">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- ปุ่มเพิ่มผู้สมัคร -->
                        <div class="mt-3">
                            <button type="button" class="btn btn-primary" id="add-registrant-btn">
                                <i class="fas fa-plus me-2"></i>เพิ่มผู้สมัคร
                            </button>
                            <small class="text-muted ms-2">ท่านสามารถลงทะเบียนได้สูงสุด 15 คนต่อเบอร์โทรศัพท์</small>
                        </div>
                    </div>
                </div>
                
                <!-- Address Sections -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            ที่อยู่สำหรับออกใบเสร็จ
                        </h3>
                    </div>
                    <div class="card-body">
                        <div id="invoiceAddress"></div>
                    </div>
                </div>

                <!-- ที่อยู่ตามทะเบียนบ้าน (ซ่อนไว้) -->
                <div class="card hidden-address-section">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-home me-2"></i>
                            ที่อยู่ตามทะเบียนบ้าน
                        </h3>
                    </div>
                    <div class="card-body">
                        <div id="houseAddress"></div>
                    </div>
                </div>

                <!-- ที่อยู่ปัจจุบัน (ซ่อนไว้) -->
                <div class="card hidden-address-section">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-map-pin me-2"></i>
                            ที่อยู่ปัจจุบัน
                        </h3>
                    </div>
                    <div class="card-body">
                        <div id="currentAddress"></div>
                    </div>
                </div>

                <!-- Documents Section (Before Payment) -->
                <div class="card" style="display:none">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-file-alt me-2"></i>
                            เอกสารประกอบ
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            ท่านสามารถอัพโหลดเอกสารประกอบการลงทะเบียนได้ เช่น สำเนาบัตรประชาชน หรือเอกสารอื่นๆ ที่เกี่ยวข้อง
                        </div>
                        
                        <div id="documents-container">
                            <div class="document-item mb-3 p-3 border rounded">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">ประเภทเอกสาร</label>
                                        <select class="form-select" name="document_type[]">
                                            <option value="identification">บัตรประชาชน/บัตรข้าราชการ</option>
                                            <option value="certificate">วุฒิบัตร/ประกาศนียบัตร</option>
                                            <option value="professional">เอกสารรับรองทางวิชาชีพ</option>
                                            <option value="other">อื่นๆ</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">รายละเอียด</label>
                                        <input type="text" class="form-control" name="document_description[]" placeholder="อธิบายเอกสาร (ถ้ามี)">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="form-label">เอกสาร</label>
                                        <input type="file" class="form-control" name="documents[]" accept="image/*,.pdf">
                                        <small class="text-muted">รองรับไฟล์ภาพ (JPG, PNG, GIF) และ PDF ขนาดไม่เกิน 5MB</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-primary" id="add-document-btn">
                                <i class="fas fa-plus me-2"></i>เพิ่มเอกสาร
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-money-bill me-2"></i>
                            ข้อมูลการชำระเงิน
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="payment-info">
                            <h4 class="mb-3">รายละเอียดการชำระเงิน</h4>
                            
                            <!-- เพิ่ม QR Code -->
                            <div class="text-center mb-4">
                                <img src="QR.jpg" alt="QR Code สำหรับชำระเงิน" style="max-width: 600px;" class="img-fluid border p-2">
                                <p class="text-muted mt-2">สแกน QR Code เพื่อชำระเงิน</p>
                            </div>
                            
                            <p class="mb-3 fs-5 fw-bold text-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                กรุณาชำระเงินค่าลงทะเบียน ก่อนวันเข้ารับการฝึกอบรม 7 วัน
                            </p>
                            <p class="mb-2">
                                <i class="fas fa-university"></i>
                                โอนเงินเข้าบัญชี ธนาคารกรุงไทย สาขาโรจนะ
                            </p>
                            <p class="mb-2">
                                <i class="fas fa-file-invoice"></i>
                                ชื่อบัญชี "มทร.สุวรรณภูมิ เงินรายได้"
                            </p>
                            <p class="mb-2">
                                <i class="fas fa-money-check"></i>
                                เลขที่บัญชี 128-028939-2
                            </p>
                            <p class="mb-0 text-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>*ไม่รับชำระด้วยเช็ค*</strong>
                            </p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">วันที่และเวลาที่ชำระเงิน (ถ้ามี)</label>
                            <input type="datetime-local" class="form-control" name="payment_date">
                            <small class="text-muted">กรอกเฉพาะกรณีที่มีการอัพโหลดหลักฐานการชำระเงิน</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">หลักฐานการชำระเงิน (ถ้ามี)</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-upload"></i>
                                </span>
                                <input type="file" class="form-control" 
                                    name="payment_slip" accept="image/*,.pdf">
                            </div>
                            <small class="text-muted">รองรับไฟล์ภาพ (JPG, PNG, GIF) และ PDF ขนาดไม่เกิน 5MB</small>
                            <small class="d-block text-info mt-1">
                                <i class="fas fa-info-circle"></i>
                                คุณสามารถลงทะเบียนโดยไม่อัพโหลดหลักฐานการชำระเงินได้ และสามารถอัพโหลดภายหลังได้
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="fas fa-check-circle me-2"></i>
                        ลงทะเบียน
                    </button>
                </div>
            </form>
        </div>

        <!-- Floating Help Button -->
        <div class="floating-help" onclick="showHelp()">
            <i class="fas fa-question-circle fa-2x"></i>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="regarts.js"></script>
    </body>
    </html>