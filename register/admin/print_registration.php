<?php
require_once 'check_auth.php';
require_once '../config/database.php';

// สร้าง database connection
$database = new Database();
$pdo = $database->getConnection();

// รับ registration ID จาก URL parameter
$registration_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ถ้าไม่มี ID ที่ถูกต้อง
if ($registration_id <= 0) {
    echo "ไม่พบข้อมูลการลงทะเบียน";
    exit;
}

// ดึงข้อมูลการลงทะเบียน
$stmt = $pdo->prepare("
    SELECT r.*, 
           DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as formatted_date,
           DATE_FORMAT(r.approved_at, '%d/%m/%Y %H:%i') as formatted_approved_date,
           DATE_FORMAT(r.payment_updated_at, '%d/%m/%Y %H:%i') as formatted_payment_date
    FROM registrations r
    WHERE r.id = ?
");
$stmt->execute([$registration_id]);

if ($stmt->rowCount() === 0) {
    echo "ไม่พบข้อมูลการลงทะเบียน";
    exit;
}

$registration = $stmt->fetch(PDO::FETCH_ASSOC);

// ดึงข้อมูลที่อยู่
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

// ดึงข้อมูลเอกสาร
$stmt = $pdo->prepare("
    SELECT * FROM registration_documents
    WHERE registration_id = ?
");
$stmt->execute([$registration_id]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลหลักฐานการชำระเงิน
$stmt = $pdo->prepare("
    SELECT * FROM registration_files
    WHERE registration_id = ?
");
$stmt->execute([$registration_id]);
$payment_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// สร้างฟังก์ชันแปลงสถานะเป็นภาษาไทย
function getStatusThai($status) {
    switch ($status) {
        case 'paid':
            return 'ชำระเงินแล้ว';
        case 'not_paid':
            return 'ยังไม่ชำระเงิน';
        default:
            return 'ไม่ระบุ';
    }
}

// สร้างฟังก์ชันแปลงประเภทเอกสารเป็นภาษาไทย
function getDocumentTypeThai($type) {
    switch ($type) {
        case 'identification':
            return 'เอกสารยืนยันตัวตน';
        case 'certificate':
            return 'วุฒิบัตร/ประกาศนียบัตร';
        case 'professional':
            return 'เอกสารรับรองทางวิชาชีพ';
        case 'other':
            return 'เอกสารอื่นๆ';
        default:
            return 'เอกสารทั่วไป';
    }
}

// สร้าง HTML สำหรับการพิมพ์
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>พิมพ์ข้อมูลการลงทะเบียน - <?php echo $registration['fullname']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4;
            margin: 15mm 10mm;
        }
        
        body {
            font-family: 'Sarabun', sans-serif;
            margin: 0;
            padding: 0;
            font-size: 14px;
            line-height: 1.5;
            color: #333;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .print-header img {
            height: 60px;
            margin-bottom: 10px;
        }
        
        .print-header h1 {
            font-size: 22px;
            margin: 0 0 5px 0;
            font-weight: 600;
        }
        
        .print-header p {
            font-size: 16px;
            margin: 0;
            color: #555;
        }
        
        .print-section {
            margin-bottom: 30px;
        }
        
        .print-section h2 {
            font-size: 18px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
            margin-top: 0;
            margin-bottom: 15px;
            color: #1a237e;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-table td {
            padding: 8px 10px;
        }
        
        .info-table td:first-child {
            width: 30%;
            font-weight: 500;
            vertical-align: top;
        }
        
        .info-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .address-box {
            background-color: #f5f7fa;
            border: 1px solid #eee;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .address-box h3 {
            font-size: 16px;
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .address-box p {
            margin: 0 0 5px 0;
        }
        
        .document-list {
            padding-left: 20px;
        }
        
        .document-list li {
            margin-bottom: 5px;
        }
        
        .status-box {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 500;
            font-size: 14px;
            text-align: center;
            color: white;
        }
        
        .status-box.paid {
            background-color: #4caf50;
        }
        
        .status-box.not-paid {
            background-color: #f44336;
        }
        
        .status-box.approved {
            background-color: #1a237e;
        }
        
        .status-box.pending {
            background-color: #ff9800;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        
        @media print {
            body {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="print-header">
            <img src="https://arts.rmutsb.ac.th/image/logo_art_2019.png" alt="Logo">
            <h1>รายละเอียดการลงทะเบียน</h1>
            <p>การสัมมนาเพิ่มศักยภาพท้องถิ่นเพื่อการขับเคลื่อนอนาคตไทยอย่างยั่งยืน</p>
            <p>วันที่ 13-15 พฤษภาคม พ.ศ. 2568</p>
        </div>
        
        <div class="print-section">
            <h2>ข้อมูลการลงทะเบียน</h2>
            <table class="info-table">
                <tr>
                    <td>รหัสการลงทะเบียน:</td>
                    <td><strong><?php echo str_pad($registration_id, 6, '0', STR_PAD_LEFT); ?></strong></td>
                </tr>
                <tr>
                    <td>วันที่ลงทะเบียน:</td>
                    <td><?php echo $registration['formatted_date']; ?></td>
                </tr>
                <tr>
                    <td>สถานะการชำระเงิน:</td>
                    <td>
                        <?php if ($registration['payment_status'] == 'paid'): ?>
                            <span class="status-box paid">ชำระเงินแล้ว</span>
                            <?php if (!empty($registration['payment_updated_at'])): ?>
                                <span style="margin-left: 10px; font-size: 13px; color: #666;">
                                    (<?php echo $registration['formatted_payment_date']; ?>)
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="status-box not-paid">ยังไม่ชำระเงิน</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>สถานะการอนุมัติ:</td>
                    <td>
                        <?php if ($registration['is_approved'] == 1): ?>
                            <span class="status-box approved">อนุมัติแล้ว</span>
                            <?php if (!empty($registration['formatted_approved_date'])): ?>
                                <span style="margin-left: 10px; font-size: 13px; color: #666;">
                                    (<?php echo $registration['formatted_approved_date']; ?>)
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="status-box pending">รอการอนุมัติ</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="print-section">
            <h2>ข้อมูลส่วนตัว</h2>
            <table class="info-table">
                <tr>
                    <td>คำนำหน้า:</td>
                    <td>
                        <?php echo ($registration['title'] === 'other') ? $registration['title_other'] : $registration['title']; ?>
                    </td>
                </tr>
                <tr>
                    <td>ชื่อ-นามสกุล:</td>
                    <td><strong><?php echo $registration['fullname']; ?></strong></td>
                </tr>
                <tr>
                    <td>หน่วยงาน:</td>
                    <td><?php echo $registration['organization']; ?></td>
                </tr>
                <tr>
                    <td>ตำแหน่ง:</td>
                    <td><?php echo $registration['position']; ?></td>
                </tr>
                <tr>
                    <td>เบอร์โทรศัพท์:</td>
                    <td><?php echo $registration['phone']; ?></td>
                </tr>
                <tr>
                    <td>อีเมล:</td>
                    <td><?php echo $registration['email']; ?></td>
                </tr>
                <?php if (!empty($registration['line_id'])): ?>
                <tr>
                    <td>LINE ID:</td>
                    <td><?php echo $registration['line_id']; ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="print-section">
            <h2>ข้อมูลที่อยู่</h2>
            
            <?php if (isset($addresses['invoice'])): ?>
            <div class="address-box">
                <h3>ที่อยู่สำหรับออกใบเสร็จ</h3>
                <p><?php echo $addresses['invoice']['address']; ?></p>
                <p>
                    ตำบล/แขวง <?php echo $addresses['invoice']['subdistrict_name']; ?> 
                    อำเภอ/เขต <?php echo $addresses['invoice']['district_name']; ?> 
                    จังหวัด <?php echo $addresses['invoice']['province_name']; ?> 
                    <?php echo $addresses['invoice']['zipcode']; ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if (isset($addresses['house'])): ?>
            <div class="address-box">
                <h3>ที่อยู่ตามทะเบียนบ้าน</h3>
                <p><?php echo $addresses['house']['address']; ?></p>
                <p>
                    ตำบล/แขวง <?php echo $addresses['house']['subdistrict_name']; ?> 
                    อำเภอ/เขต <?php echo $addresses['house']['district_name']; ?> 
                    จังหวัด <?php echo $addresses['house']['province_name']; ?> 
                    <?php echo $addresses['house']['zipcode']; ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if (isset($addresses['current'])): ?>
            <div class="address-box">
                <h3>ที่อยู่ปัจจุบัน</h3>
                <p><?php echo $addresses['current']['address']; ?></p>
                <p>
                    ตำบล/แขวง <?php echo $addresses['current']['subdistrict_name']; ?> 
                    อำเภอ/เขต <?php echo $addresses['current']['district_name']; ?> 
                    จังหวัด <?php echo $addresses['current']['province_name']; ?> 
                    <?php echo $addresses['current']['zipcode']; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (count($documents) > 0): ?>
        <div class="print-section">
            <h2>เอกสารที่อัปโหลด</h2>
            <ul class="document-list">
                <?php foreach ($documents as $doc): ?>
                <li>
                    <strong><?php echo getDocumentTypeThai($doc['document_type']); ?></strong>
                    <?php if (!empty($doc['description'])): ?>
                    - <?php echo $doc['description']; ?>
                    <?php endif; ?>
                    <br>
                    <span style="color: #666; font-size: 13px;">
                        ชื่อไฟล์: <?php echo $doc['file_name']; ?> 
                        (<?php echo round($doc['file_size']/1024, 1); ?> KB)
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>เอกสารนี้พิมพ์เมื่อวันที่ <?php echo date('d/m/Y H:i:s'); ?></p>
            <p>ระบบลงทะเบียนการสัมมนาเพิ่มศักยภาพท้องถิ่นเพื่อการขับเคลื่อนอนาคตไทยอย่างยั่งยืน</p>
        </div>
    </div>
    
    <div class="no-print" style="text-align: center; margin: 20px;">
        <button type="button" onclick="window.print();" style="padding: 10px 20px; background-color: #1a237e; color: white; border: none; border-radius: 5px; cursor: pointer;">
            พิมพ์เอกสาร
        </button>
        <button type="button" onclick="window.close();" style="padding: 10px 20px; background-color: #f5f5f5; color: #333; border: 1px solid #ddd; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            ปิดหน้าต่าง
        </button>
    </div>
    
    <script>
        // พิมพ์อัตโนมัติเมื่อโหลดหน้าเสร็จ (ถ้าต้องการ)
        // window.onload = function() {
        //     setTimeout(function() {
        //         window.print();
        //     }, 500);
        // };
    </script>
</body>
</html>