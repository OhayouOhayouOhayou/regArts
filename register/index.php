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
            background-color: var(--primary-color);
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
        <!-- Personal Information -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user me-2"></i>
                    ข้อมูลผู้สมัคร
                </h3>
            </div>
            <div class="card-body">
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
                        <label class="form-label required">อีเมล</label>
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

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-home me-2"></i>
                    ที่อยู่ตามทะเบียนบ้าน
                </h3>
            </div>
            <div class="card-body">
                 <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="copyToHouse">
                    <label class="form-check-label" for="copyToHouse">
                         ใช้ที่อยู่เดียวกับที่อยู่ออกใบเสร็จ
                        </label>
                </div>

                <div id="houseAddress"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-map-pin me-2"></i>
                    ที่อยู่ปัจจุบัน
                </h3>
            </div>
            <div class="card-body">

            <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="copyToCurrent">
                    <label class="form-check-label" for="copyToCurrent">
                        ใช้ที่อยู่เดียวกับที่อยู่ตามทะเบียนบ้าน
                    </label>
                </div>

                <div id="currentAddress"></div>
            </div>
        </div>


        <!-- Documents Section (Before Payment) -->
<div class="card">
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
<script>
// อัพเดทสถานะของ Progress Stepper
function updateProgress(step) {
    const steps = document.querySelectorAll('.step');
    steps.forEach((stepEl, index) => {
        if (index < step) {
            stepEl.classList.add('completed');
            stepEl.classList.remove('active');
        } else if (index === step) {
            stepEl.classList.add('active');
            stepEl.classList.remove('completed');
        } else {
            stepEl.classList.remove('active', 'completed');
        }
    });
}

// แสดงหน้าต่างช่วยเหลือ
function showHelp() {
    Swal.fire({
        title: 'ช่วยเหลือ',
        html: `
            <div class="text-start">
                <h5>ขั้นตอนการลงทะเบียน</h5>
                <p>1. กรอกเบอร์โทรศัพท์เพื่อตรวจสอบสถานะ</p>
                <p>2. กรอกข้อมูลส่วนตัวและที่อยู่</p>
                <p>3. อัพโหลดหลักฐานการชำระเงิน</p>
                <p>4. รอการตรวจสอบและยืนยัน</p>
                
                <h5 class="mt-4">การชำระเงิน</h5>
                <p>- โอนเงินเข้าบัญชีที่ระบุ</p>
                <p>- เก็บหลักฐานการโอนเงิน</p>
                <p>- อัพโหลดหลักฐานในระบบ</p>
                
                <h5 class="mt-4">ติดต่อสอบถาม</h5>
                <p>คุณชนิดาภา บุญเตี้ย (คุณนาว): 095-5439933</p>
                <p class="mt-3">
                    <a href="https://line.me/ti/g2/MVMMDL4KM05ML2EUDiuipEJaH6LtU6_6x-pfKw?utm_source=invitation&utm_medium=QR_code&utm_campaign=default" 
                       target="_blank" class="btn btn-success">
                        <i class="fab fa-line me-2"></i>เข้าร่วมกลุ่ม Line Open Chat
                    </a>
                </p>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'เข้าใจแล้ว'
    });
}
// จัดการการเลือกคำนำหน้าชื่อ
document.querySelector('select[name="title"]').addEventListener('change', function() {
    const titleOtherContainer = document.querySelector('.title-other-container');
    const titleOtherInput = document.querySelector('input[name="title_other"]');
    
    if (this.value === 'other') {
        titleOtherContainer.classList.remove('d-none');
        titleOtherInput.required = true;
    } else {
        titleOtherContainer.classList.add('d-none');
        titleOtherInput.required = false;
        titleOtherInput.value = '';
    }
});

// โหลดฟิลด์ที่อยู่
async function loadAddressFields() {
    const addressSections = ['invoiceAddress', 'houseAddress', 'currentAddress'];
    
    for (const section of addressSections) {
        document.getElementById(section).innerHTML = `
            <div class="mb-3">
                <label class="form-label required">ที่อยู่</label>
                <textarea class="form-control" name="${section}_address" 
                        rows="3" required placeholder="กรุณากรอกที่อยู่"></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">จังหวัด</label>
                    <select class="form-select" name="${section}_province" required 
                            onchange="loadDistricts(this.value, '${section}')">
                        <option value="">เลือกจังหวัด</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">อำเภอ/เขต</label>
                    <select class="form-select" name="${section}_district" required 
                            onchange="loadSubdistricts(this.value, '${section}')" disabled>
                        <option value="">เลือกอำเภอ/เขต</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">ตำบล/แขวง</label>
                    <select class="form-select" name="${section}_subdistrict" required 
                            onchange="updateZipcode(this.value, '${section}')" disabled>
                        <option value="">เลือกตำบล/แขวง</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">รหัสไปรษณีย์</label>
                    <input type="text" class="form-control" name="${section}_zipcode" 
                           required readonly>
                </div>
            </div>
        `;
    }
    
    await loadProvinces();
    updateProgress(1);
}

async function loadProvinces() {
    try {
        const response = await fetch('api/get_provinces.php');
        
        if (!response.ok) {
            throw new Error('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
        }

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('รูปแบบข้อมูลไม่ถูกต้อง');
        }

        const text = await response.text();
        const provinces = JSON.parse(text.trim());
        
        if (!Array.isArray(provinces)) {
            throw new Error('ข้อมูลจังหวัดไม่อยู่ในรูปแบบที่ถูกต้อง');
        }

        const provinceSelects = document.querySelectorAll('select[name$="_province"]');
        provinceSelects.forEach(select => {
            // ล้างตัวเลือกเดิม
            select.innerHTML = '<option value="">เลือกจังหวัด</option>';
            
            // เพิ่มตัวเลือกใหม่
            provinces.forEach(province => {
                const option = new Option(province.name_in_thai, province.id);
                select.add(option);
            });
        });

    } catch (error) {
        console.error('Error loading provinces:', error);
        showError('ไม่สามารถโหลดข้อมูลจังหวัดได้ กรุณาลองใหม่อีกครั้ง');
        throw error;
    }
}

// โหลดข้อมูลอำเภอ
async function loadDistricts(provinceId, section) {
    try {
        const response = await fetch(`api/get_districts.php?province_id=${provinceId}`);
        const districts = await response.json();
        
        const districtSelect = document.querySelector(`select[name="${section}_district"]`);
        const subdistrictSelect = document.querySelector(`select[name="${section}_subdistrict"]`);
        
        districtSelect.innerHTML = '<option value="">เลือกอำเภอ/เขต</option>';
        districtSelect.disabled = false;
        
        districts.forEach(district => {
            const option = new Option(district.name_in_thai, district.id);
            districtSelect.add(option);
        });
        
        // รีเซ็ตตำบลและรหัสไปรษณีย์
        subdistrictSelect.innerHTML = '<option value="">เลือกตำบล/แขวง</option>';
        subdistrictSelect.disabled = true;
        document.querySelector(`input[name="${section}_zipcode"]`).value = '';
        
    } catch (error) {
        console.error('Error loading districts:', error);
        showError('ไม่สามารถโหลดข้อมูลอำเภอได้');
    }
}

// โหลดข้อมูลตำบล
async function loadSubdistricts(districtId, section) {
    try {
        const response = await fetch(`api/get_subdistricts.php?district_id=${districtId}`);
        const subdistricts = await response.json();
        
        const subdistrictSelect = document.querySelector(`select[name="${section}_subdistrict"]`);
        subdistrictSelect.innerHTML = '<option value="">เลือกตำบล/แขวง</option>';
        subdistrictSelect.disabled = false;
        
        subdistricts.forEach(subdistrict => {
            const option = new Option(subdistrict.name_in_thai, subdistrict.id);
            subdistrictSelect.add(option);
        });
        
        // รีเซ็ตรหัสไปรษณีย์
        document.querySelector(`input[name="${section}_zipcode"]`).value = '';
        
    } catch (error) {
        console.error('Error loading subdistricts:', error);
        showError('ไม่สามารถโหลดข้อมูลตำบลได้');
    }
}

async function updateZipcode(subdistrictId, section) {
    try {
        const response = await fetch(`api/get_zipcode.php?subdistrict_id=${subdistrictId}`);
        
        if (!response.ok) {
            throw new Error('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
        }

        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'ไม่สามารถโหลดข้อมูลรหัสไปรษณีย์ได้');
        }

        const zipcodeInput = document.querySelector(`input[name="${section}_zipcode"]`);
        zipcodeInput.value = data.zip_code;

    } catch (error) {
        console.error('Error loading zipcode:', error);
        const zipcodeInput = document.querySelector(`input[name="${section}_zipcode"]`);
        zipcodeInput.value = '';
        showError('ไม่สามารถโหลดข้อมูลรหัสไปรษณีย์ได้ กรุณาลองใหม่อีกครั้ง');
    }
}
async function checkRegistration() {
    console.log('Starting registration check...');
    const phone = document.getElementById('checkPhone').value;
    console.log('Phone number:', phone);
    
    if (!phone) {
        console.log('No phone number provided');
        Swal.fire({
            icon: 'error',
            title: 'กรุณากรอกเบอร์โทรศัพท์',
            text: 'โปรดกรอกเบอร์โทรศัพท์เพื่อตรวจสอบสถานะ'
        });
        return;
    }

    try {
        console.log('Sending request to server...');
        const response = await fetch('check_registration.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ phone: phone })
        });

        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        const responseText = await response.text();
        console.log('Raw response:', responseText);

        try {
            const result = JSON.parse(responseText);
            console.log('Parsed response:', result);
            
            if (!result.success) {
                throw new Error(result.message);
            }

            handleRegistrationStatus(result);
            
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.log('Invalid JSON response:', responseText);
            throw new Error('ข้อมูลที่ได้รับจากเซิร์ฟเวอร์ไม่ถูกต้อง');
        }

    } catch (error) {
        console.error('Error details:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: error.message || 'ไม่สามารถตรวจสอบสถานะได้ กรุณาลองใหม่อีกครั้ง'
        });
    }
}

function handleRegistrationStatus(data) {
    const { status, message } = data;
    
    // Debug: แสดงข้อมูลที่ได้รับจาก API
    console.log("ข้อมูลสถานะการลงทะเบียน:", data);
    
    // กรณียังไม่ได้ลงทะเบียน ให้นำไปยังหน้าลงทะเบียน
    if (status === 'not_registered') {
        Swal.fire({
            title: 'ยังไม่ได้ลงทะเบียน',
            text: 'เบอร์โทรศัพท์นี้ยังไม่เคยลงทะเบียน คุณสามารถลงทะเบียนใหม่ได้ทันที',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'ลงทะเบียนใหม่',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                // เก็บเบอร์โทรศัพท์ไว้ใช้ในแบบฟอร์ม
                const phone = document.getElementById('checkPhone').value;
                
                // แสดงฟอร์มลงทะเบียน
                document.getElementById('phoneCheck').classList.remove('active');
                document.getElementById('registrationForm').classList.add('active');
                
                // โหลดฟิลด์ที่อยู่และข้อมูลจังหวัด
                loadAddressFields().then(() => {
                    // เติมเบอร์โทรศัพท์อัตโนมัติ
                    document.querySelector('input[name="phone"]').value = phone;
                });
                
                // อัพเดทสถานะ progress stepper
                updateProgress(1);
            }
        });
        return;
    }
    
    // อาจจำเป็นต้องโหลดข้อมูลรายละเอียดผู้ลงทะเบียนเพิ่มเติม
    // ทำการเรียก API เพื่อดึงข้อมูลรายละเอียดทั้งหมด
    fetchRegistrationDetails(data.data?.registration_id || data.registration_id);
}

// ฟังก์ชันใหม่สำหรับดึงข้อมูลผู้ลงทะเบียนทั้งหมด
function fetchRegistrationDetails(registrationId) {
    // แสดง loading
    Swal.fire({
        title: 'กำลังโหลดข้อมูล',
        text: 'กรุณารอสักครู่...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // เรียก API เพื่อดึงข้อมูลรายละเอียด
    fetch('get_registration_details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ registration_id: registrationId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // ปิด loading
            Swal.close();
            
            // แสดงหน้ารายละเอียดผู้ลงทะเบียน
            displayRegistrationDetails(data);
        } else {
            throw new Error(data.message || 'ไม่สามารถโหลดข้อมูลได้');
        }
    })
    .catch(error => {
        console.error('Error loading registration details:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: error.message || 'ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่อีกครั้ง'
        });
    });
}

// ฟังก์ชันสำหรับแสดงรายละเอียดผู้ลงทะเบียน
function displayRegistrationDetails(data) {
    const registration = data.registration;
    const addresses = data.addresses;
    const documents = data.documents;
    
    // กรณีที่ลงทะเบียนแล้ว ให้แสดงหน้าข้อมูลผู้ลงทะเบียน
    document.getElementById('phoneCheck').classList.remove('active');
    
    // สร้างหน้าแสดงข้อมูลผู้ลงทะเบียนและสถานะ
    const registrantInfoDiv = document.createElement('div');
    registrantInfoDiv.id = 'registrantInfo';
    registrantInfoDiv.className = 'form-section active';
    
    // สถานะการลงทะเบียน
    let statusText = '';
    let statusClass = '';
    let actionHtml = '';
    
    switch(registration.payment_status) {
        case 'not_paid':
            statusText = 'รอชำระเงิน';
            statusClass = 'text-warning';
            actionHtml = `
                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-upload me-2"></i>อัพโหลดหลักฐานการชำระเงิน</h5>
                    </div>
                    <div class="card-body">
                        <div class="payment-info mb-4">
                            <h5 class="mb-3">รายละเอียดการโอนเงิน</h5>
                            
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
                        </div>
                        
                        <form id="paymentForm">
                            <div class="mb-3">
                                <label class="form-label required">วันที่และเวลาที่ชำระเงิน</label>
                                <input type="datetime-local" class="form-control" name="payment_date" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required">หลักฐานการชำระเงิน</label>
                                <input type="file" class="form-control" name="payment_slip" accept="image/*,.pdf" required>
                                <small class="text-muted">รองรับไฟล์ภาพ (JPG, PNG, GIF) และ PDF ขนาดไม่เกิน 5MB</small>
                            </div>
                            <input type="hidden" name="registration_id" value="${registration.id}">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>อัพโหลดหลักฐาน
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            break;
        case 'paid':
            if (registration.is_approved) {
                statusText = 'ลงทะเบียนเสร็จสมบูรณ์';
                statusClass = 'text-success';
            } else {
                statusText = 'อัพโหลดหลักฐานแล้ว รอการตรวจสอบจากเจ้าหน้าที่';
                statusClass = 'text-info';
            }
            break;
    }
    
    // เตรียมข้อมูลที่อยู่
    const addressHTML = addresses.map(address => {
        let addressTitle = '';
        switch(address.address_type) {
            case 'invoice':
                addressTitle = 'ที่อยู่สำหรับออกใบเสร็จ';
                break;
            case 'house':
                addressTitle = 'ที่อยู่ตามทะเบียนบ้าน';
                break;
            case 'current':
                addressTitle = 'ที่อยู่ปัจจุบัน';
                break;
        }
        
        return `
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>${addressTitle}</h6>
                </div>
                <div class="card-body">
                    <p class="mb-1">${address.address}</p>
                    <p class="mb-0">
                        ตำบล/แขวง ${address.subdistrict_name} 
                        อำเภอ/เขต ${address.district_name} 
                        จังหวัด ${address.province_name} 
                        ${address.zipcode}
                    </p>
                </div>
            </div>
        `;
    }).join('');
    
    // เตรียมข้อมูลเอกสาร
    let documentsHTML = '';
    if (documents && documents.length > 0) {
        documentsHTML = `
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>เอกสารที่อัพโหลด</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        ${documents.map(doc => {
                            let docType = '';
                            switch(doc.document_type) {
                                case 'identification':
                                    docType = 'บัตรประชาชน/บัตรข้าราชการ';
                                    break;
                                case 'certificate':
                                    docType = 'วุฒิบัตร/ประกาศนียบัตร';
                                    break;
                                case 'professional':
                                    docType = 'เอกสารรับรองทางวิชาชีพ';
                                    break;
                                default:
                                    docType = 'เอกสารอื่นๆ';
                            }
                            
                            return `
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">${docType}</h6>
                                            <small class="text-muted">${doc.description || 'ไม่มีคำอธิบาย'}</small>
                                        </div>
                                        <a href="${doc.file_path}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>ดูเอกสาร
                                        </a>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            </div>
        `;
    }
    
    // สร้าง HTML สำหรับหน้าแสดงข้อมูล
    registrantInfoDiv.innerHTML = `
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-user-check me-2"></i>ข้อมูลการลงทะเบียน</h4>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-12">
                        <h5>สถานะการลงทะเบียน: <span class="${statusClass} fw-bold">${statusText}</span></h5>
                        <p class="mb-2">รหัสการลงทะเบียน: <strong>${registration.id}</strong></p>
                        <p class="mb-0">วันที่ลงทะเบียน: <strong>${new Date(registration.created_at).toLocaleString('th-TH')}</strong></p>
                    </div>
                </div>
                
                <div id="registrationTimeline" class="mb-4">
                    ${createTimelineHTML(getTimelineSteps(registration.payment_status, registration.is_approved), data)}
                </div>
                
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>ข้อมูลผู้ลงทะเบียน</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <p class="mb-1"><strong>ชื่อ-นามสกุล:</strong></p>
                                <p>${registration.title === 'other' ? registration.title_other : registration.title} ${registration.fullname}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="mb-1"><strong>หน่วยงาน:</strong></p>
                                <p>${registration.organization}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="mb-1"><strong>ตำแหน่ง:</strong></p>
                                <p>${registration.position}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="mb-1"><strong>เบอร์โทรศัพท์:</strong></p>
                                <p>${registration.phone}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="mb-1"><strong>อีเมล:</strong></p>
                                <p>${registration.email}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="mb-1"><strong>LINE ID:</strong></p>
                                <p>${registration.line_id || '-'}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="address-info">
                    <h5 class="mb-3">ข้อมูลที่อยู่</h5>
                    ${addressHTML}
                </div>
                
                ${documentsHTML}
            </div>
        </div>
        
        ${actionHtml}
    `;
    
    // เพิ่มหน้าแสดงข้อมูลเข้าไปในหน้าเว็บ
    document.querySelector('.container.py-4').appendChild(registrantInfoDiv);
    
    // เพิ่ม Event Listener สำหรับฟอร์มอัพโหลดหลักฐานการชำระเงิน
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            uploadPaymentWithDate(new FormData(this));
        });
    }
}

// ปรับปรุงฟังก์ชัน getTimelineSteps เพื่อพิจารณาทั้งสถานะการชำระเงินและการอนุมัติ
function getTimelineSteps(paymentStatus, isApproved) {
    const timelineSteps = [
        {
            title: 'ลงทะเบียน',
            description: 'ดำเนินการลงทะเบียนเรียบร้อยแล้ว',
            icon: 'fas fa-user-check',
            status: 'completed'
        },
        {
            title: 'ชำระเงิน',
            description: 'อัพโหลดหลักฐานการชำระเงิน',
            icon: 'fas fa-money-bill-wave',
            status: 'waiting'
        },
        {
            title: 'ตรวจสอบ',
            description: 'รอการตรวจสอบจากเจ้าหน้าที่',
            icon: 'fas fa-clipboard-check',
            status: 'waiting'
        },
        {
            title: 'เสร็จสมบูรณ์',
            description: 'การลงทะเบียนเสร็จสมบูรณ์',
            icon: 'fas fa-check-circle',
            status: 'waiting'
        }
    ];
    
    // ปรับสถานะตาม Timeline
    if (paymentStatus === 'paid') {
        timelineSteps[1].status = 'completed';
        timelineSteps[2].status = 'current';
        
        if (isApproved) {
            timelineSteps[2].status = 'completed';
            timelineSteps[3].status = 'completed';
        }
    }
    
    return timelineSteps;
}

// ฟังก์ชันสำหรับอัพโหลดหลักฐานการชำระเงินพร้อมวันที่
function uploadPaymentWithDate(formData) {
    // แสดง loading
    Swal.fire({
        title: 'กำลังอัพโหลด',
        text: 'กรุณารอสักครู่...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // ตรวจสอบว่ามีการกรอกวันที่และอัพโหลดไฟล์หรือไม่
    const paymentDate = formData.get('payment_date');
    const paymentFile = formData.get('payment_slip');
    
    if (!paymentDate) {
        Swal.fire({
            icon: 'error',
            title: 'กรุณาระบุวันที่และเวลาที่ชำระเงิน',
            text: 'โปรดกรอกวันที่และเวลาที่คุณชำระเงิน'
        });
        return;
    }
    
    if (!paymentFile || paymentFile.size === 0) {
        Swal.fire({
            icon: 'error',
            title: 'กรุณาเลือกไฟล์',
            text: 'คุณยังไม่ได้เลือกไฟล์หลักฐานการชำระเงิน'
        });
        return;
    }
    
    // ตรวจสอบประเภทไฟล์
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    const fileType = paymentFile.type;
    
    if (!allowedTypes.includes(fileType)) {
        Swal.fire({
            icon: 'error',
            title: 'ประเภทไฟล์ไม่ถูกต้อง',
            text: 'กรุณาอัพโหลดไฟล์ภาพ (JPG, PNG, GIF) หรือ PDF เท่านั้น'
        });
        return;
    }
    
    // ตรวจสอบขนาดไฟล์
    if (paymentFile.size > 5 * 1024 * 1024) { // 5MB
        Swal.fire({
            icon: 'error',
            title: 'ขนาดไฟล์ใหญ่เกินไป',
            text: 'กรุณาอัพโหลดไฟล์ขนาดไม่เกิน 5MB'
        });
        return;
    }
    
    // ส่งข้อมูลไปยังเซิร์ฟเวอร์
    fetch('upload_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('ข้อมูลตอบกลับไม่ถูกต้อง กรุณาติดต่อผู้ดูแลระบบ');
        }
    })
    .then(result => {
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'อัพโหลดสำเร็จ',
                text: 'หลักฐานการชำระเงินถูกอัพโหลดเรียบร้อยแล้ว กรุณารอการตรวจสอบจากเจ้าหน้าที่',
                confirmButtonText: 'ตกลง'
            }).then(() => {
                // รีเฟรชหน้าเพื่อแสดงสถานะล่าสุด
                window.location.reload();
            });
        } else {
            throw new Error(result.message || 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์');
        }
    })
    .catch(error => {
        console.error('Error uploading file:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: error.message || 'ไม่สามารถอัพโหลดไฟล์ได้ กรุณาลองใหม่อีกครั้ง',
            confirmButtonText: 'ตกลง'
        });
    });
}

function createTimelineHTML(timelineSteps, data) {
    return `
        <div class="timeline-container p-4">
            <div class="timeline">
                ${timelineSteps.map((step, index) => `
                    <div class="timeline-item ${step.status}">
                        <div class="timeline-icon">
                            <i class="${step.icon}"></i>
                        </div>
                        <div class="timeline-content">
                            <h5>${step.title}</h5>
                            <p>${step.description}</p>
                            ${step.status === 'current' ? `
                                <div class="timeline-status">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <span class="ms-2">กำลังดำเนินการ</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}


async function uploadPaymentSlip(formData) {
    try {
        // แสดง loading
        Swal.fire({
            title: 'กำลังอัพโหลด',
            text: 'กรุณารอสักครู่...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // ตรวจสอบว่ามีการอัพโหลดไฟล์หรือไม่
        const paymentFile = formData.get('payment_slip');
        console.log('ตรวจสอบไฟล์ที่อัพโหลด:', paymentFile);
        
        // ถ้าไม่ได้เลือกไฟล์
        if (!paymentFile || paymentFile.size === 0 || paymentFile.name === '') {
            console.log('ไม่พบไฟล์อัพโหลด');
            Swal.fire({
                icon: 'error',
                title: 'กรุณาเลือกไฟล์',
                text: 'คุณยังไม่ได้เลือกไฟล์หลักฐานการชำระเงิน'
            });
            return false;
        }

        console.log('กำลังอัพโหลดไฟล์:', paymentFile.name);
        
        // ตรวจสอบประเภทไฟล์
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        const fileType = paymentFile.type;
        
        if (!allowedTypes.includes(fileType)) {
            Swal.fire({
                icon: 'error',
                title: 'ประเภทไฟล์ไม่ถูกต้อง',
                text: 'กรุณาอัพโหลดไฟล์ภาพ (JPG, PNG, GIF) หรือ PDF เท่านั้น'
            });
            return false;
        }
        
        // ตรวจสอบขนาดไฟล์
        if (paymentFile.size > 5 * 1024 * 1024) { // 5MB
            Swal.fire({
                icon: 'error',
                title: 'ขนาดไฟล์ใหญ่เกินไป',
                text: 'กรุณาอัพโหลดไฟล์ขนาดไม่เกิน 5MB'
            });
            return false;
        }
        
        // แสดงข้อมูลใน FormData เพื่อ debug
        console.log('ข้อมูลที่ส่งไปยังเซิร์ฟเวอร์:');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        
        // ส่งข้อมูลไปยังเซิร์ฟเวอร์
        const response = await fetch('upload_payment.php', {
            method: 'POST',
            body: formData
        });

        console.log('การตอบกลับจากเซิร์ฟเวอร์:', response);
        
        const responseText = await response.text();
        console.log('ข้อความตอบกลับดิบ:', responseText);
        
        if (!response.ok) {
            console.error('HTTP error! status:', response.status);
            console.error('Error response:', responseText);
            
            let errorMessage = 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์';
            
            try {
                const errorData = JSON.parse(responseText);
                if (errorData.message) {
                    errorMessage = errorData.message;
                }
            } catch (e) {
                console.error('Error parsing JSON response:', e);
            }
            
            throw new Error(errorMessage);
        }
        
        try {
            const result = JSON.parse(responseText);
            console.log('ผลลัพธ์การอัพโหลด:', result);
            
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'อัพโหลดสำเร็จ',
                    text: 'หลักฐานการชำระเงินถูกอัพโหลดเรียบร้อยแล้ว กรุณารอการตรวจสอบ',
                    confirmButtonText: 'ตกลง'
                }).then(() => {
                    // รีเฟรชหน้าเพื่อแสดงสถานะล่าสุด
                    window.location.reload();
                });
                return true;
            } else {
                throw new Error(result.message || 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์');
            }
        } catch (jsonError) {
            console.error('ไม่สามารถแปลงข้อความตอบกลับเป็น JSON ได้:', jsonError);
            console.error('ข้อความตอบกลับดิบ:', responseText);
            throw new Error('ข้อมูลตอบกลับไม่ถูกต้อง กรุณาติดต่อผู้ดูแลระบบ');
        }
    } catch (error) {
        console.error('เกิดข้อผิดพลาดในกระบวนการอัพโหลด:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: error.message || 'ไม่สามารถอัพโหลดไฟล์ได้ กรุณาลองใหม่อีกครั้ง',
            confirmButtonText: 'ตกลง'
        });
        return false;
    }
}
// แสดงข้อความแจ้งเตือนข้อผิดพลาด
function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด',
        text: message,
        confirmButtonText: 'ตกลง'
    });
}



// ฟังก์ชันคัดลอกที่อยู่
function copyAddress(fromSection, toSection) {
    // คัดลอกที่อยู่
    document.querySelector(`textarea[name="${toSection}_address"]`).value = 
        document.querySelector(`textarea[name="${fromSection}_address"]`).value;
    
    // คัดลอกจังหวัด และโหลดอำเภอ
    const provinceSelect = document.querySelector(`select[name="${fromSection}_province"]`);
    const toProvinceSelect = document.querySelector(`select[name="${toSection}_province"]`);
    toProvinceSelect.value = provinceSelect.value;
    loadDistricts(provinceSelect.value, toSection).then(() => {
        // คัดลอกอำเภอ และโหลดตำบล
        const districtSelect = document.querySelector(`select[name="${fromSection}_district"]`);
        const toDistrictSelect = document.querySelector(`select[name="${toSection}_district"]`);
        toDistrictSelect.value = districtSelect.value;
        loadSubdistricts(districtSelect.value, toSection).then(() => {
            // คัดลอกตำบลและรหัสไปรษณีย์
            const subdistrictSelect = document.querySelector(`select[name="${fromSection}_subdistrict"]`);
            const toSubdistrictSelect = document.querySelector(`select[name="${toSection}_subdistrict"]`);
            toSubdistrictSelect.value = subdistrictSelect.value;
            updateZipcode(subdistrictSelect.value, toSection);
        });
    });
}

// เพิ่ม Event Listeners
document.getElementById('copyToHouse').addEventListener('change', function() {
    if (this.checked) {
        copyAddress('invoiceAddress', 'houseAddress');
    }
});

document.getElementById('copyToCurrent').addEventListener('change', function() {
    if (this.checked) {
        copyAddress('houseAddress', 'currentAddress');
    }
});


// อัพเดท Event Listener สำหรับการส่งฟอร์มลงทะเบียน
document.getElementById('seminarRegistration').addEventListener('submit', async function(e) {
    e.preventDefault();
    console.log('เริ่มการส่งฟอร์ม');

    // แสดง loading
    Swal.fire({
        title: 'กำลังประมวลผล',
        text: 'กรุณารอสักครู่...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const formData = new FormData(this);

    // Log ข้อมูลทั้งหมดในฟอร์ม
    console.log('ข้อมูลในฟอร์ม:');
    for (let [key, value] of formData.entries()) {
        if (key === 'payment_slip') {
            console.log(`${key}: ${value.name ? value.name : 'ไม่ได้อัพโหลดไฟล์'}`);
        } else {
            console.log(`${key}: ${value}`);
        }
    }

    try {
        console.log('กำลังส่งข้อมูลไปยังเซิร์ฟเวอร์...');
        const response = await fetch('process_registration.php', {
            method: 'POST',
            body: formData
        });

        console.log('การตอบกลับจากเซิร์ฟเวอร์:', response);

        if (!response.ok) {
            const errorText = await response.text();
            console.error('ข้อความตอบกลับจากเซิร์ฟเวอร์เมื่อเกิดข้อผิดพลาด:', errorText);
            
            let errorMessage = 'ไม่สามารถลงทะเบียนได้ กรุณาลองใหม่อีกครั้ง';
            try {
                const errorData = JSON.parse(errorText);
                if (errorData.message) {
                    errorMessage = errorData.message;
                }
            } catch (jsonError) {
                console.error('ไม่สามารถแปลงข้อความ error เป็น JSON ได้', jsonError);
            }
            
            throw new Error(errorMessage);
        }

        const responseText = await response.text();
        console.log('ข้อความตอบกลับดิบ:', responseText);

        try {
            const result = JSON.parse(responseText);
            console.log('ผลลัพธ์การประมวลผล:', result);

            if (result.success) {
                let successMessage = 'ลงทะเบียนสำเร็จ';
                
                // แสดงข้อความตามสถานะการชำระเงิน
                if (result.payment_status === 'paid') {
                    successMessage += ' และอัพโหลดหลักฐานการชำระเงินเรียบร้อยแล้ว กรุณารอการตรวจสอบจากเจ้าหน้าที่';
                } else {
                    successMessage += ' กรุณาอัพโหลดหลักฐานการชำระเงินเมื่อท่านชำระเงินเรียบร้อยแล้ว';
                }
                
                Swal.fire({
                    icon: 'success',
                    title: 'ลงทะเบียนสำเร็จ',
                    text: successMessage
                }).then(() => {
                    window.location.reload();
                });
            } else {
                throw new Error(result.message || 'เกิดข้อผิดพลาดในการลงทะเบียน');
            }
        } catch (jsonError) {
            console.error('ไม่สามารถแปลงข้อความตอบกลับเป็น JSON ได้:', jsonError);
            console.error('ข้อความตอบกลับดิบ:', responseText);
            throw new Error('ข้อมูลตอบกลับไม่ถูกต้อง กรุณาติดต่อผู้ดูแลระบบ');
        }
    } catch (error) {
        console.error('เกิดข้อผิดพลาดในการส่งฟอร์ม:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: error.message || 'ไม่สามารถลงทะเบียนได้ กรุณาลองใหม่อีกครั้ง'
        });
    }
});


document.addEventListener('DOMContentLoaded', function() {
    // เพิ่มปุ่มเพิ่มเอกสาร
    const addDocumentBtn = document.getElementById('add-document-btn');
    if (addDocumentBtn) {
        addDocumentBtn.addEventListener('click', addDocumentField);
    }
});

// ฟังก์ชันเพิ่มฟิลด์เอกสาร
function addDocumentField() {
    const documentsContainer = document.getElementById('documents-container');
    const documentCount = documentsContainer.querySelectorAll('.document-item').length;
    
    // จำกัดจำนวนเอกสารไม่เกิน 5 ชิ้น
    if (documentCount >= 5) {
        Swal.fire({
            icon: 'info',
            title: 'ข้อจำกัดการอัพโหลด',
            text: 'ท่านสามารถอัพโหลดเอกสารได้สูงสุด 5 รายการ'
        });
        return;
    }
    
    const documentItem = document.createElement('div');
    documentItem.className = 'document-item mb-3 p-3 border rounded';
    documentItem.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">เอกสารที่ ${documentCount + 1}</h6>
            <button type="button" class="btn btn-sm btn-outline-danger remove-document-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
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
    `;
    
    // เพิ่มปุ่มลบเอกสาร
    documentsContainer.appendChild(documentItem);
    
    // เพิ่ม event listener สำหรับปุ่มลบ
    documentItem.querySelector('.remove-document-btn').addEventListener('click', function() {
        documentsContainer.removeChild(documentItem);
    });
}
</script>