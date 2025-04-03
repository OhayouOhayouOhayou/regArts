<?php
ini_set('display_errors', 0); // Turn off error display in production
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
session_start(); 
require_once 'config.php';

function writeLog($message) {
    $logFile = 'address_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// ตรวจสอบการล็อกอิน
$isLoggedIn = isset($_SESSION['phone']) && !empty($_SESSION['phone']);
$customerPhone = $isLoggedIn ? $_SESSION['phone'] : '';
$customerName = $isLoggedIn ? $_SESSION['name'] : '';
$customerEmail = $isLoggedIn ? $_SESSION['email'] : '';
$customerCompany = $isLoggedIn ? $_SESSION['company'] : '';
$customerAddress = $isLoggedIn ? $_SESSION['address'] : ''; // เพิ่มที่อยู่
$customerLineId = $isLoggedIn ? $_SESSION['line_id'] : ''; // เพิ่ม Line ID

// ระบบล็อกอิน
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"])) {
    // Set content type to JSON for all AJAX responses
    header('Content-Type: application/json');
    
    if ($_POST["action"] == "login") {
        $phone = $_POST["phone"];
        $name = $_POST["name"] ?? '';
        $email = $_POST["email"] ?? '';
        $company = $_POST["company"] ?? '';
        $address = $_POST["address"] ?? ''; // รับค่าที่อยู่
        $lineId = $_POST["lineId"] ?? ''; // รับค่า Line ID
        
        // ตรวจสอบความถูกต้องของข้อมูล
        if (empty($phone) || empty($name) || empty($email) || empty($address)) {
            echo json_encode(["success" => false, "message" => "กรุณากรอกข้อมูลให้ครบถ้วน (ชื่อ, เบอร์โทร, อีเมล และที่อยู่)"]);
            exit;
        }
        
        // ตรวจสอบรูปแบบเบอร์โทรศัพท์ (ตัวเลข 9-10 หลัก)
        if (!preg_match('/^[0-9]{9,10}$/', $phone)) {
            echo json_encode(["success" => false, "message" => "กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (ตัวเลข 9-10 หลัก)"]);
            exit;
        }
        
        // ตรวจสอบรูปแบบอีเมล
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(["success" => false, "message" => "กรุณากรอกอีเมลให้ถูกต้อง"]);
            exit;
        }
        
        // แสดงลอก debug ข้อมูลที่ได้รับ
        error_log("Received address: " . $address);
        
        // บันทึกข้อมูลลงใน session
        $_SESSION['phone'] = $phone;
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        $_SESSION['company'] = $company;
        $_SESSION['address'] = $address; // บันทึกที่อยู่
        $_SESSION['line_id'] = $lineId; // บันทึก Line ID
        
        echo json_encode(["success" => true]);
        exit;
    }
    
    // ตรวจสอบการล็อกอินก่อนทำรายการอื่นๆ
    if (!$isLoggedIn && $_POST["action"] != "login") {
        echo json_encode(["success" => false, "message" => "กรุณาเข้าสู่ระบบก่อนทำรายการ"]);
        exit;
    }
    
    // กรณีส่งคำสั่งจอง
    if ($_POST["action"] == "reserve") {
        try {
            $boothId = $_POST["boothId"];
            
            // แสดงลอก debug ข้อมูลที่ได้รับจาก session
            error_log("Customer address from session: " . $_SESSION['address']);
            
            // ตรวจสอบว่า booth ยังว่างอยู่หรือไม่
            $checkBooth = $conn->prepare("SELECT status FROM booths WHERE id = ?");
            $checkBooth->bind_param("i", $boothId);
            $checkBooth->execute();
            $checkResult = $checkBooth->get_result();
            
            if ($checkResult->num_rows === 0) {
                echo json_encode(["success" => false, "message" => "ไม่พบข้อมูลบูธที่ต้องการจอง"]);
                exit;
            }
            
            $boothData = $checkResult->fetch_assoc();
            if ($boothData['status'] !== 'available') {
                echo json_encode(["success" => false, "message" => "บูธนี้ถูกจองไปแล้ว"]);
                exit;
            }
            
            // สร้างคำสั่งซื้อใหม่
            $orderNumber = "ORD" . date("ymd") . rand(1000, 9999);
            $orderDate = date("Y-m-d H:i:s");
            
            // ดึงข้อมูลราคาบูธ
            $getPriceQuery = $conn->prepare("SELECT price FROM booths WHERE id = ?");
            $getPriceQuery->bind_param("i", $boothId);
            $getPriceQuery->execute();
            $priceResult = $getPriceQuery->get_result();
            $priceData = $priceResult->fetch_assoc();
            $price = $priceData['price'];
            
            // เริ่ม transaction
            $conn->begin_transaction();
            
            // สร้างคำสั่งซื้อใหม่
            $createOrder = $conn->prepare("INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, customer_company, customer_address, customer_line_id, order_date, total_amount, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $createOrder->bind_param("ssssssssd", $orderNumber, $customerName, $customerEmail, $customerPhone, $customerCompany, $customerAddress, $customerLineId, $orderDate, $price);
            $createOrder->execute();
            
            $orderId = $conn->insert_id;
            
            // เพิ่มรายการบูธ
            $addItem = $conn->prepare("INSERT INTO order_items (order_id, booth_id, price) VALUES (?, ?, ?)");
            $addItem->bind_param("iid", $orderId, $boothId, $price);
            $addItem->execute();
            
            // อัพเดตสถานะบูธเป็น reserved
            $updateBooth = $conn->prepare("UPDATE booths SET status = 'reserved', reserved_by = ?, reserved_at = NOW() WHERE id = ?");
            $updateBooth->bind_param("si", $customerPhone, $boothId);
            $updateBooth->execute();
            
            // ยืนยัน transaction
            $conn->commit();
            
            echo json_encode([
                "success" => true, 
                "order_id" => $orderId,
                "order_number" => $orderNumber
            ]);
            
        } catch (Exception $e) {
            // กรณีเกิด error ให้ rollback
            $conn->rollback();
            error_log("Reserve error: " . $e->getMessage());
            echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาดในการจอง: " . $e->getMessage()]);
        }
        exit;
    }
    else if ($_POST["action"] == "upload_slip") {
        $orderId = $_POST["orderId"];
        $paymentMethod = $_POST["paymentMethod"];
        
        // ตรวจสอบว่ามีไฟล์อัพโหลดหรือไม่
        if (!isset($_FILES['paymentSlip']) || $_FILES['paymentSlip']['error'] == UPLOAD_ERR_NO_FILE) {
            echo json_encode(["success" => false, "message" => "กรุณาอัพโหลดหลักฐานการชำระเงิน"]);
            exit;
        }
        
        // ตรวจสอบประเภทไฟล์
        $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        if (!in_array($_FILES['paymentSlip']['type'], $allowed)) {
            echo json_encode(["success" => false, "message" => "กรุณาอัพโหลดไฟล์รูปภาพ (JPG, PNG) หรือ PDF เท่านั้น"]);
            exit;
        }
        
        // ตรวจสอบขนาดไฟล์ (ไม่เกิน 5MB)
        if ($_FILES['paymentSlip']['size'] > 5 * 1024 * 1024) {
            echo json_encode(["success" => false, "message" => "ขนาดไฟล์ต้องไม่เกิน 5MB"]);
            exit;
        }
        
        // สร้างโฟลเดอร์เก็บไฟล์หากยังไม่มี
        $uploadDir = 'uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // สร้างชื่อไฟล์ใหม่
        $fileExtension = pathinfo($_FILES['paymentSlip']['name'], PATHINFO_EXTENSION);
        $newFileName = 'slip_' . $orderId . '_' . time() . '.' . $fileExtension;
        $targetFile = $uploadDir . $newFileName;
        
        // อัพโหลดไฟล์
        if (move_uploaded_file($_FILES['paymentSlip']['tmp_name'], $targetFile)) {
            try {
                // เริ่ม transaction
                $conn->begin_transaction();
                
                // ดึงข้อมูลบูธจาก order_items
                $getBooth = $conn->prepare("SELECT booth_id FROM order_items WHERE order_id = ?");
                $getBooth->bind_param("i", $orderId);
                $getBooth->execute();
                $boothResult = $getBooth->get_result();
                
                if ($boothResult->num_rows > 0) {
                    while ($boothRow = $boothResult->fetch_assoc()) {
                        $boothId = $boothRow['booth_id'];
                        
                        // อัพเดตข้อมูลการชำระเงินในตาราง booths โดยตรง
                        $updateBooth = $conn->prepare("UPDATE booths SET 
                            status = 'pending_payment',
                            payment_status = 'pending',
                            payment_method = ?,
                            payment_reference = ?,
                            payment_date = NOW()
                            WHERE id = ?");
                        $updateBooth->bind_param("ssi", $paymentMethod, $targetFile, $boothId);
                        $updateBooth->execute();
                    }
                    
                    // อัพเดตสถานะการชำระเงินในตาราง orders
                    $updateOrder = $conn->prepare("UPDATE orders SET 
                        payment_status = 'pending',
                        payment_method = ?,
                        payment_reference = ?,
                        payment_date = NOW()
                        WHERE id = ?");
                    $updateOrder->bind_param("ssi", $paymentMethod, $targetFile, $orderId);
                    $updateOrder->execute();
                    
                    // ยืนยัน transaction
                    $conn->commit();
                    
                    echo json_encode(["success" => true, "message" => "อัพโหลดสลิปเรียบร้อยแล้ว เจ้าหน้าที่จะตรวจสอบและยืนยันการชำระเงินต่อไป"]);
                } else {
                    // ไม่พบข้อมูลบูธที่เกี่ยวข้องกับ order นี้
                    $conn->rollback();
                    echo json_encode(["success" => false, "message" => "ไม่พบข้อมูลบูธที่เกี่ยวข้องกับคำสั่งซื้อนี้"]);
                }
            } catch (Exception $e) {
                // บันทึก error log
                $conn->rollback();
                error_log("Payment upload error: " . $e->getMessage());
                echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage()]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาดในการอัพโหลดไฟล์"]);
        }
        exit;
    }
    else if ($_POST["action"] == "logout") {
        // ล้าง session เมื่อล็อกเอาท์
        session_unset();
        session_destroy();
        echo json_encode(["success" => true]);
        exit;
    }
}
function getSetting($key, $conn, $defaultValue = '') {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Error in getSetting: " . $e->getMessage());
    }
    
    return $defaultValue;
}
// ฟังก์ชันแสดงราคาในรูปแบบเงินบาท
function formatCurrency($amount) {
    return number_format($amount, 0, '.', ',') . ' บาท';
}

// Get all booths with order information
$sql = "SELECT b.*, 
       oi.order_id, 
       IFNULL(o.payment_status, '') AS order_payment_status 
       FROM booths b 
       LEFT JOIN order_items oi ON b.id = oi.booth_id 
       LEFT JOIN orders o ON oi.order_id = o.id 
       ORDER BY b.zone, b.floor, b.booth_number";

try {
    $result = $conn->query($sql);
    $booths = [];

    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $booths[] = $row;
        }
    }
    file_put_contents('error_log.txt', date('Y-m-d H:i:s') . " - Successfully fetched " . count($booths) . " booths\n", FILE_APPEND);
} catch (Exception $e) {
    file_put_contents('error_log.txt', date('Y-m-d H:i:s') . " - Error fetching booths: " . $e->getMessage() . "\n", FILE_APPEND);
}

// Get zone prices for display
$zoneAPrices = $conn->query("SELECT MIN(price) as min_price, MAX(price) as max_price FROM booths WHERE zone = 'A'")->fetch_assoc();
$zoneBPrices = $conn->query("SELECT MIN(price) as min_price, MAX(price) as max_price FROM booths WHERE zone = 'B'")->fetch_assoc();
$zoneCPrices = $conn->query("SELECT MIN(price) as min_price, MAX(price) as max_price FROM booths WHERE zone = 'C'")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจองบูธขายสินค้า</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        /* เพิ่ม CSS สำหรับสถานะการชำระเงิน */
        .booth.reserved {
            background-color: #9e9e9e;
            color: white;
            cursor: not-allowed;
        }
        .booth.pending_payment {
            background-color: #ffc107;
            color: black;
            cursor: not-allowed;
        }
        .booth.paid {
            background-color: #dc3545;
            color: white;
            cursor: not-allowed;
        }
        /* สีตามโซน */
        .booth-blue {
            background-color: #00bcd4;
            color: white;
        }
        .booth-green {
            background-color: #4caf50;
            color: white;
        }
        .booth-purple {
            background-color: #9c27b0;
            color: white;
        }
        /* สำหรับบูธที่จองแล้วให้แสดงสีตามสถานะ */
        .booth.reserved.booth-blue, 
        .booth.reserved.booth-green, 
        .booth.reserved.booth-purple {
            background-color: #9e9e9e;
        }
        .booth.pending_payment.booth-blue, 
        .booth.pending_payment.booth-green, 
        .booth.pending_payment.booth-purple {
            background-color: #ffc107;
            color: black;
        }
        .booth.paid.booth-blue, 
        .booth.paid.booth-green, 
        .booth.paid.booth-purple {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center my-4">ระบบจองบูธขายสินค้า</h1>
        
        <?php if ($isLoggedIn): ?>
        <!-- แสดงข้อมูลผู้ใช้เมื่อล็อกอินแล้ว -->
        <div class="user-info">
            <div>
                <span class="user-name"><?php echo $customerName; ?></span>
                <span class="ms-2"><?php echo $customerPhone; ?></span>
            </div>
            <div>
                <a href="my-reservations.php" class="btn btn-sm btn-outline-primary me-2">
                    <i class="bi bi-calendar-check"></i> ประวัติการจอง
                </a>
                <button class="btn btn-sm btn-outline-danger" onclick="logout()">ออกจากระบบ</button>
            </div>
        </div>
        <?php else: ?>
        <!-- แสดงข้อความเชิญชวนให้ล็อกอิน -->
        <div class="login-cta">
            <p class="mb-2">กรุณาลงทะเบียนเพื่อเข้าใช้งานระบบจองบูธ</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">ลงทะเบียน / เข้าสู่ระบบ</button>
        </div>
        <?php endif; ?>
        
        <div class="overview-section mb-4">
            <h2 class="text-center mb-4">แผนผังภาพรวมงาน</h2>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <img src="zone/overview11.png" alt="ภาพรวมงาน 1" class="card-img-top booth-overview-img" data-bs-toggle="modal" data-bs-target="#overviewModal" data-img="zone/overview11.png">
                        <div class="card-body">
                            <h5 class="card-title">มุมมองที่ 1</h5>
                            <p class="card-text">แผนผังรวมของพื้นที่จัดงานทั้งหมด <small class="text-muted">(คลิกเพื่อดูขนาดใหญ่)</small></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <img src="zone/overview12.png" alt="ภาพรวมงาน 2" class="card-img-top booth-overview-img" data-bs-toggle="modal" data-bs-target="#overviewModal" data-img="zone/overview12.png">
                        <div class="card-body">
                            <h5 class="card-title">มุมมองที่ 2</h5>
                            <p class="card-text">ภาพรวมพื้นที่จัดแสดงสินค้า <small class="text-muted">(คลิกเพื่อดูขนาดใหญ่)</small></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info mt-3">
                <p class="mb-0"><i class="bi bi-info-circle-fill me-2"></i> กรุณาเลือกโซนที่ท่านสนใจด้านล่างเพื่อดูรายละเอียดเพิ่มเติมและทำการจอง</p>
            </div>
        </div>
          
        <div class="price-info">
            <div class="row">
                <div class="col-md-4">
                    <div class="card h-100 border-info">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">โซน A (ห้องสัมมนา)</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>ราคา:</strong> <?php echo formatCurrency($zoneAPrices['min_price']); ?> <span class="text-muted small">(exclude VAT 7%)</span></p>
                            <p><i class="bi bi-check-circle-fill text-success me-2"></i>ราคาเช่าพร้อมบูธมาตรฐาน</p>
                            <p><i class="bi bi-check-circle-fill text-success me-2"></i>พื้นที่ห้องแอร์</p>
                            <p><i class="bi bi-check-circle-fill text-success me-2"></i>ตำแหน่งยอดนิยม</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-success">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">โซน B</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>ราคา:</strong> <?php echo formatCurrency($zoneBPrices['min_price']); ?> <span class="text-muted small">(exclude VAT 7%)</span></p>
                            <p><i class="bi bi-check-circle-fill text-success me-2"></i>ราคาเช่าพื้นที่ ไม่มีบูธ</p>
                            <p><i class="bi bi-check-circle-fill text-success me-2"></i>พื้นที่ห้องแอร์</p>
                            <p><i class="bi bi-check-circle-fill text-success me-2"></i>ทำเลดี การเข้าถึงสะดวก</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-purple" style="border-color: #9c27b0;">
                        <div class="card-header text-white" style="background-color: #9c27b0;">
                            <h5 class="mb-0">โซน C</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>ราคา:</strong> <?php echo formatCurrency($zoneCPrices['min_price']); ?> <span class="text-muted small">(exclude VAT 7%)</span></p>
                            <p><i class="bi bi-check-circle-fill text-success me-2"></i>ราคาเช่าพื้นที่ ไม่มีบูธ</p>
                            <p><i class="bi bi-check-circle-fill text-success me-2"></i>พื้นที่ไม่มีแอร์</p>
                            <p><i class="bi bi-check-circle-fill text-success me-2"></i>ราคาประหยัด</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="zone-tabs">
            <div class="zone-tab tab-a active" onclick="showZone('A')">โซน A</div>
            <div class="zone-tab tab-b" onclick="showZone('B')">โซน B</div>
            <div class="zone-tab tab-c1" onclick="showZone('C1')">โซน C (ชั้น 1)</div>
            <div class="zone-tab tab-c2" onclick="showZone('C2')">โซน C (ชั้น 2)</div>
        </div>
        
        <div class="legend">
            <div class="legend-item">
                <div class="legend-color" style="background-color: #00bcd4;"></div>
                <span>โซน A (ห้องสัมมนา)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #4caf50;"></div>
                <span>โซน B</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #9c27b0;"></div>
                <span>โซน C</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #9e9e9e;"></div>
                <span>บูธถูกจองแล้ว</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #ffc107;"></div>
                <span>รอชำระเงิน</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #dc3545;"></div>
                <span>ชำระเงินแล้ว</span>
            </div>
        </div>
        <div class="booth-map">
            <!-- Floor 1 Zone A (Blue) -->
            <div class="floor active" id="zone-A">
             <div class="floor-title">แผนผัง</div>
                <img src="zone/a.jpg" alt="โซน A (ห้องสัมมนา)" style="width:100%">
                <div class="floor-title">โซน A (ห้องสัมมนา)</div>
                
                <div class="d-flex justify-content-end mb-4">
                    <div class="venue-feature px-4 py-2 bg-danger text-white">
                        ห้องรับรองพิเศษแขก
                    </div>
                </div>
                
                <div class="d-flex flex-wrap justify-content-center mb-4">
                    <?php 
                    // Display booths 1-9 (zone A)
                    for ($i = 1; $i <= 9; $i++) {
                        $isReserved = false;
                        $status = 'available';
                        $paymentStatus = '';
                        
                        foreach ($booths as $booth) {
                            if ($booth['booth_number'] == $i && $booth['zone'] == 'A') {
                                $status = $booth['status'];
                                $paymentStatus = $booth['order_payment_status'] ?? '';
                                if ($status != 'available') {
                                    $isReserved = true;
                                }
                                break;
                            }
                        }
                        
                        // ปรับสีตามสถานะการชำระเงินจาก order_payment_status
                        if ($isReserved && $paymentStatus == 'paid') {
                            $status = 'paid';
                        } else if ($isReserved && $paymentStatus == 'pending') {
                            $status = 'pending_payment';
                        }
                        
                        $class = ($isReserved) ? "booth $status" : "booth";
                        $class .= " booth-blue"; // Blue colored booths for zone A
                        
                        $boothId = 0;
                        $price = 0;
                        
                        foreach ($booths as $booth) {
                            if ($booth['booth_number'] == $i && $booth['zone'] == 'A') {
                                $boothId = $booth['id'];
                                $price = $booth['price'];
                                break;
                            }
                        }
                    ?>
                    <div class="<?php echo $class; ?>" data-id="<?php echo $boothId; ?>"
                    data-number="<?php echo $i; ?>" data-zone="A" data-price="<?php echo $price; ?>" onclick="selectBooth(this)">
                        <?php echo $i; ?>
                    </div>
                    <?php } ?>
                </div>
                
                <div class="venue-feature px-4 py-2 mb-4 bg-warning">
                    <strong>Backdrop 1 ต้อนรับ</strong>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="d-flex flex-wrap">
                            <?php 
                            // Display booths 10-23 (scattered groups in zone A)
                            for ($i = 10; $i <= 23; $i++) {
                                $isReserved = false;
                                $status = 'available';
                                $paymentStatus = '';
                                
                                foreach ($booths as $booth) {
                                    if ($booth['booth_number'] == $i && $booth['zone'] == 'A') {
                                        $status = $booth['status'];
                                        $paymentStatus = $booth['order_payment_status'] ?? '';
                                        if ($status != 'available') {
                                            $isReserved = true;
                                        }
                                        break;
                                    }
                                }
                                
                                // ปรับสีตามสถานะการชำระเงินจาก order_payment_status
                                if ($isReserved && $paymentStatus == 'paid') {
                                    $status = 'paid';
                                } else if ($isReserved && $paymentStatus == 'pending') {
                                    $status = 'pending_payment';
                                }
                                
                                $class = ($isReserved) ? "booth $status" : "booth";
                                $class .= " booth-blue"; // Blue colored booths for zone A
                                
                                $boothId = 0;
                                $price = 0;
                                
                                foreach ($booths as $booth) {
                                    if ($booth['booth_number'] == $i && $booth['zone'] == 'A') {
                                        $boothId = $booth['id'];
                                        $price = $booth['price'];
                                        break;
                                    }
                                }
                                
                                // Create grouped booths with proper layout (simplified version)
                                if ($i == 10 || $i == 14 || $i == 18 || $i == 22) {
                                    echo '<div class="d-flex mb-2">';
                                }
                            ?>
                            <div class="<?php echo $class; ?>" data-id="<?php echo $boothId; ?>" data-number="<?php echo $i; ?>" data-zone="A" data-price="<?php echo $price; ?>" onclick="selectBooth(this)">
                                <?php echo $i; ?>
                            </div>
                            <?php 
                                if ($i == 13 || $i == 17 || $i == 21 || $i == 23) {
                                    echo '</div>';
                                }
                            } 
                            ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="d-flex flex-wrap">
                            <?php 
                            // Display booths 24-30 (right side of zone A)
                            for ($i = 24; $i <= 30; $i++) {
                                $isReserved = false;
                                $status = 'available';
                                $paymentStatus = '';
                                
                                foreach ($booths as $booth) {
                                    if ($booth['booth_number'] == $i && $booth['zone'] == 'A') {
                                        $status = $booth['status'];
                                        $paymentStatus = $booth['order_payment_status'] ?? '';
                                        if ($status != 'available') {
                                            $isReserved = true;
                                        }
                                        break;
                                    }
                                }
                                
                                // ปรับสีตามสถานะการชำระเงินจาก order_payment_status
                                if ($isReserved && $paymentStatus == 'paid') {
                                    $status = 'paid';
                                } else if ($isReserved && $paymentStatus == 'pending') {
                                    $status = 'pending_payment';
                                }
                                
                                $class = ($isReserved) ? "booth $status" : "booth";
                                $class .= " booth-blue"; // Blue colored booths for zone A
                                
                                $boothId = 0;
                                $price = 0;
                                
                                foreach ($booths as $booth) {
                                    if ($booth['booth_number'] == $i && $booth['zone'] == 'A') {
                                        $boothId = $booth['id'];
                                        $price = $booth['price'];
                                        break;
                                    }
                                }
                                
                                if ($i == 24 || $i == 28) {
                                    echo '<div class="d-flex mb-2">';
                                }
                            ?>
                            <div class="<?php echo $class; ?>" data-id="<?php echo $boothId; ?>" data-number="<?php echo $i; ?>" data-zone="A" data-price="<?php echo $price; ?>" onclick="selectBooth(this)">
                                <?php echo $i; ?>
                            </div>
                            <?php 
                                if ($i == 27 || $i == 30) {
                                    echo '</div>';
                                }
                            } 
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <div style="display: inline-block; background-color: #00bcd4; width: 40px; height: 40px; border-radius: 50%; line-height: 40px; font-weight: bold;">A</div>
                </div>
            </div>
            
            <!-- Zone B (Green) -->
            <div class="floor" id="zone-B">
                <div class="floor-title">แผนผัง</div>
                <img src="zone/b.jpg" alt="โซน B" style="width:100%">
                <div class="floor-title">โซน B</div>
                
                <div class="row">
                    <div class="col-md-8 offset-md-2">
                        <div class="d-flex flex-wrap justify-content-center mb-4">
                            <?php 
                            // Display booths 1-60 in zone B (green booths)
                            for ($i = 1; $i <= 60; $i++) {
                                $isReserved = false;
                                $status = 'available';
                                $paymentStatus = '';
                                
                                foreach ($booths as $booth) {
                                    if ($booth['booth_number'] == $i && $booth['zone'] == 'B') {
                                        $status = $booth['status'];
                                        $paymentStatus = $booth['order_payment_status'] ?? '';
                                        if ($status != 'available') {
                                            $isReserved = true;
                                        }
                                        break;
                                    }
                                }
                                
                                // ปรับสีตามสถานะการชำระเงินจาก order_payment_status
                                if ($isReserved && $paymentStatus == 'paid') {
                                    $status = 'paid';
                                } else if ($isReserved && $paymentStatus == 'pending') {
                                    $status = 'pending_payment';
                                }
                                
                                $class = ($isReserved) ? "booth $status" : "booth";
                                $class .= " booth-green"; // Green colored booths for zone B
                                
                                $boothId = 0;
                                $price = 0;
                                
                                foreach ($booths as $booth) {
                                    if ($booth['booth_number'] == $i && $booth['zone'] == 'B') {
                                        $boothId = $booth['id'];
                                        $price = $booth['price'];
                                        break;
                                    }
                                }
                            ?>
                            <div class="<?php echo $class; ?>" data-id="<?php echo $boothId; ?>" data-number="<?php echo $i; ?>" data-zone="B" data-price="<?php echo $price; ?>" onclick="selectBooth(this)">
                                <?php echo $i; ?>
                            </div>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="mt-4 mb-2">
                        <h5 class="text-center">บูธโซน C (C1-C29)</h5>
                    </div>
                    <div class="d-flex flex-wrap justify-content-center">
                        <?php 
                    
                        for ($i = 1; $i <= 29; $i++) {
                            $boothNumber = $i;
                            $displayBoothNumber = 'C' . $i; 
                            
                            $isReserved = false;
                            $status = 'available';
                            $paymentStatus = '';
                            
                            foreach ($booths as $booth) {
                                if ($booth['booth_number'] == $boothNumber && $booth['zone'] == 'C' && $booth['floor'] == 1) {
                                    $status = $booth['status'];
                                    $paymentStatus = $booth['order_payment_status'] ?? '';
                                    if ($status != 'available') {
                                        $isReserved = true;
                                    }
                                    break;
                                }
                            }
                            
                            // ปรับสีตามสถานะการชำระเงินจาก order_payment_status
                            if ($isReserved && $paymentStatus == 'paid') {
                                $status = 'paid';
                            } else if ($isReserved && $paymentStatus == 'pending') {
                                $status = 'pending_payment';
                            }
                            
                            $class = ($isReserved) ? "booth $status" : "booth";
                            $class .= " booth-purple"; 
                            
                            $boothId = 0;
                            $price = 0;
                            
                            foreach ($booths as $booth) {
                                if ($booth['booth_number'] == $boothNumber && $booth['zone'] == 'C' && $booth['floor'] == 1) {
                                    $boothId = $booth['id'];
                                    $price = $booth['price'];
                                    break;
                                }
                            }
                        ?>
                        <div class="<?php echo $class; ?>" data-id="<?php echo $boothId; ?>" data-number="<?php echo $displayBoothNumber; ?>" data-zone="C" data-floor="1" data-price="<?php echo $price; ?>" onclick="selectBooth(this)">
                            <?php echo $displayBoothNumber; ?>
                        </div>
                        <?php } ?>
                    </div>

                </div>
                
                <div class="text-center mt-4">
                    <div style="display: inline-block; background-color: #4caf50; width: 40px; height: 40px; border-radius: 50%; line-height: 40px; font-weight: bold;">B</div>
                </div>
            </div>
            
            <!-- Zone C Floor 1 (Purple) -->
            <div class="floor" id="zone-C1">
                <div class="floor-title">แผนผัง</div>
                <img src="zone/c1.jpg" alt="โซน c" style="width:100%">
                <div class="floor-title">โซน C (ชั้น 1)</div>
                
           
                <div class="row">
                    <div class="col-md-12">
                        <div class="d-flex flex-wrap justify-content-center">
                            <?php 
                            // Display booths 30-47 (zone C, floor 1)
                            for ($i = 30; $i <= 47; $i++) {
                                $isReserved = false;
                                $status = 'available';
                                $paymentStatus = '';
                                
                                foreach ($booths as $booth) {
                                    if ($booth['booth_number'] == $i && $booth['zone'] == 'C' && $booth['floor'] == 1) {
                                        $status = $booth['status'];
                                        $paymentStatus = $booth['order_payment_status'] ?? '';
                                        if ($status != 'available') {
                                            $isReserved = true;
                                        }
                                        break;
                                    }
                                }
                                
                                // ปรับสีตามสถานะการชำระเงินจาก order_payment_status
                                if ($isReserved && $paymentStatus == 'paid') {
                                    $status = 'paid';
                                } else if ($isReserved && $paymentStatus == 'pending') {
                                    $status = 'pending_payment';
                                }
                                
                                $class = ($isReserved) ? "booth $status" : "booth";
                                $class .= " booth-purple"; // Purple colored booths for zone C
                                
                                $boothId = 0;
                                $price = 0;
                                
                                foreach ($booths as $booth) {
                                    if ($booth['booth_number'] == $i && $booth['zone'] == 'C' && $booth['floor'] == 1) {
                                        $boothId = $booth['id'];
                                        $price = $booth['price'];
                                        break;
                                    }
                                }
                            ?>
                            <div class="<?php echo $class; ?>" data-id="<?php echo $boothId; ?>" data-number="<?php echo $i; ?>" data-zone="C" data-floor="1" data-price="<?php echo $price; ?>" onclick="selectBooth(this)">
                                <?php echo $i; ?>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-8 offset-md-2">
                        <div class="d-flex flex-wrap justify-content-center">
                            <?php 
                            // Display booths 48-92 (zone C, floor 1 - larger section)
                            for ($i = 48; $i <= 92; $i++) {
                                $isReserved = false;
                                $status = 'available';
                                $paymentStatus = '';
                                
                                foreach ($booths as $booth) {
                                    if ($booth['booth_number'] == $i && $booth['zone'] == 'C' && $booth['floor'] == 1) {
                                        $status = $booth['status'];
                                        $paymentStatus = $booth['order_payment_status'] ?? '';
                                        if ($status != 'available') {
                                            $isReserved = true;
                                        }
                                        break;
                                    }
                                }
                                
                                // ปรับสีตามสถานะการชำระเงินจาก order_payment_status
                                if ($isReserved && $paymentStatus == 'paid') {
                                    $status = 'paid';
                                } else if ($isReserved && $paymentStatus == 'pending') {
                                    $status = 'pending_payment';
                                }
                                
                                $class = ($isReserved) ? "booth $status" : "booth";
                                $class .= " booth-purple"; // Purple colored booths for zone C
                                
                                $boothId = 0;
                                $price = 0;
                                
                                foreach ($booths as $booth) {
                                    if ($booth['booth_number'] == $i && $booth['zone'] == 'C' && $booth['floor'] == 1) {
                                        $boothId = $booth['id'];
                                        $price = $booth['price'];
                                        break;
                                    }
                                }
                            ?>
                            <div class="<?php echo $class; ?>" data-id="<?php echo $boothId; ?>" data-number="<?php echo $i; ?>" data-zone="C" data-floor="1" data-price="<?php echo $price; ?>" onclick="selectBooth(this)">
                                <?php echo $i; ?>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <div style="display: inline-block; background-color: #9c27b0; width: 40px; height: 40px; border-radius: 50%; line-height: 40px; font-weight: bold; color: white;">C</div>
                </div>
            </div>
            
            <!-- Zone C Floor 2 -->
            <div class="floor" id="zone-C2">
                <div class="floor-title">แผนผัง</div>
                <img src="zone/c2.jpg" alt="โซน c" style="width:100%">
                <div class="floor-title">โซน C (ชั้น 2)</div>
                
                
                <div class="text-center my-4">ระเบียงทางเดินชั้น 2</div>
                
                <div class="d-flex flex-wrap justify-content-center">
                    <?php 
                    // Display booths 93-116 (zone C, floor 2)
                    for ($i = 93; $i <= 116; $i++) {
                        $isReserved = false;
                        $status = 'available';
                        $paymentStatus = '';
                        
                        foreach ($booths as $booth) {
                            if ($booth['booth_number'] == $i && $booth['zone'] == 'C' && $booth['floor'] == 2) {
                                $status = $booth['status'];
                                $paymentStatus = $booth['order_payment_status'] ?? '';
                                if ($status != 'available') {
                                    $isReserved = true;
                                }
                                break;
                            }
                        }
                        
                        // ปรับสีตามสถานะการชำระเงินจาก order_payment_status
                        if ($isReserved && $paymentStatus == 'paid') {
                            $status = 'paid';
                        } else if ($isReserved && $paymentStatus == 'pending') {
                            $status = 'pending_payment';
                        }
                        
                        $class = ($isReserved) ? "booth $status" : "booth";
                        $class .= " booth-purple"; // Purple colored booths for zone C
                        
                        $boothId = 0;
                        $price = 0;
                        
                        foreach ($booths as $booth) {
                            if ($booth['booth_number'] == $i && $booth['zone'] == 'C' && $booth['floor'] == 2) {
                                $boothId = $booth['id'];
                                $price = $booth['price'];
                                break;
                            }
                        }
                    ?>
                    <div class="<?php echo $class; ?>" data-id="<?php echo $boothId; ?>" data-number="<?php echo $i; ?>" data-zone="C" data-floor="2" data-price="<?php echo $price; ?>" onclick="selectBooth(this)">
                        <?php echo $i; ?>
                    </div>
                    <?php } ?>
                </div>
                
                <div class="text-center mt-4">
                    <div style="display: inline-block; background-color: #9c27b0; width: 40px; height: 40px; border-radius: 50%; line-height: 40px; font-weight: bold; color: white;">C</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="loginModalLabel">ลงทะเบียน / เข้าสู่ระบบ</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="loginForm">
              <div class="mb-3">
                <label for="loginPhone" class="form-label">เบอร์โทรศัพท์<span class="text-danger">*</span></label>
                <input type="tel" class="form-control" id="loginPhone" required>
                <div class="form-text">ใช้เบอร์โทรศัพท์สำหรับเข้าสู่ระบบ</div>
              </div>
              
              <div class="mb-3">
                <label for="loginName" class="form-label">ชื่อ-นามสกุล<span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="loginName" required>
              </div>
              
              <div class="mb-3">
                <label for="loginEmail" class="form-label">อีเมล<span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="loginEmail" required>
              </div>
              
              <div class="mb-3">
                <label class="form-label">ที่อยู่<span class="text-danger">*</span></label>
                
                <div class="mb-2">
                  <input type="text" class="form-control" id="loginAddressDetail" placeholder="บ้านเลขที่ หมู่บ้าน ถนน ซอย" required>
                </div>
                
                <div class="row mb-2">
                  <div class="col-md-6">
                    <select class="form-select" id="loginProvince" required>
                      <option value="">-- เลือกจังหวัด --</option>
                      <!-- จังหวัดจะถูกเพิ่มด้วย JavaScript -->
                    </select>
                  </div>
                  <div class="col-md-6">
                    <select class="form-select" id="loginDistrict" required disabled>
                      <option value="">-- เลือกอำเภอ/เขต --</option>
                      <!-- อำเภอจะถูกเพิ่มด้วย JavaScript -->
                    </select>
                  </div>
                </div>
                
                <div class="row">
                  <div class="col-md-6">
                    <select class="form-select" id="loginSubdistrict" required disabled>
                      <option value="">-- เลือกตำบล/แขวง --</option>
                      <!-- ตำบลจะถูกเพิ่มด้วย JavaScript -->
                    </select>
                  </div>
                  <div class="col-md-6">
                    <input type="text" class="form-control" id="loginZipcode" placeholder="รหัสไปรษณีย์" required readonly>
                  </div>
                </div>
                
                <input type="hidden" id="loginAddress">
              </div>
              
              <div class="mb-3">
                <label for="loginLineId" class="form-label">Line ID</label>
                <input type="text" class="form-control" id="loginLineId">
              </div>
              
              <div class="mb-3">
                <label for="loginCompany" class="form-label">ชื่อบริษัท/ร้านค้า</label>
                <input type="text" class="form-control" id="loginCompany">
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="button" class="btn btn-primary" onclick="login()">เข้าสู่ระบบ</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reservation Modal -->
    <div class="modal fade" id="reservationModal" tabindex="-1" aria-labelledby="reservationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reservationModalLabel">จองบูธหมายเลข <span id="selectedBoothNumber"></span> โซน <span id="selectedZone"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="payment-summary mb-4">
                        <h6>ราคาบูธ</h6>
                        <div class="price-tag" id="boothPrice"></div>
                    </div>
                    
                    <div class="alert alert-info">
                        <p>ข้อมูลผู้จอง:</p>
                        <p><strong>ชื่อ-นามสกุล:</strong> <?php echo $customerName; ?><br>
                        <strong>อีเมล:</strong> <?php echo $customerEmail; ?><br>
                        <strong>เบอร์โทรศัพท์:</strong> <?php echo $customerPhone; ?><br>
                        <strong>ที่อยู่:</strong> <?php echo $customerAddress; ?><br>
                        <?php if (!empty($customerLineId)): ?>
                        <strong>Line ID:</strong> <?php echo $customerLineId; ?><br>
                        <?php endif; ?>
                        <?php if (!empty($customerCompany)): ?>
                        <strong>บริษัท/ร้านค้า:</strong> <?php echo $customerCompany; ?>
                        <?php endif; ?>
                        </p>
                    </div>
                    
                    <input type="hidden" id="boothId" name="boothId">
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="payLater" checked>
                        <label class="form-check-label" for="payLater">
                            จ่ายเงินภายหลัง
                        </label>
                        <div class="form-text">คุณสามารถชำระเงินภายหลังได้ภายใน 24 ชั่วโมง หลังจากทำการจอง</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-success" onclick="submitReservation()">ยืนยันการจอง</button>
                    <button type="button" class="btn btn-primary" onclick="submitReservationAndPay()" id="payNowBtn">ยืนยันและชำระเงิน</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel">ชำระเงิน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="payment-summary mb-4">
                        <h6>ยอดรวมที่ต้องชำระ</h6>
                        <div class="price-tag" id="totalAmount"></div>
                        <input type="hidden" id="orderId">
                        <input type="hidden" id="orderNumber">
                    </div>
                    
                    <form id="paymentForm" enctype="multipart/form-data">
                        <div class="mb-4">
                            <h6>วิธีการชำระเงิน</h6>
                            <div class="border p-3 rounded">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="paymentMethod" id="bankTransfer" value="bank_transfer" checked>
                                    <label class="form-check-label" for="bankTransfer">
                                        โอนเงินผ่านธนาคาร
                                    </label>
                                </div>
                                <div class="collapse show" id="bankTransferDetails">
                                    <div class="bank-details p-3 bg-light rounded">
                                        <p><strong>รายละเอียดบัญชี:</strong></p>
                                        <p><?php echo getSetting('bank_account', $conn, 'ธนาคารกรุงไทย 123-4-56789-0 มทร.สุวรรณภูมิ เงินรายได้'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="form-check mb-2 mt-3">
                                    <input class="form-check-input" type="radio" name="paymentMethod" id="creditCard" value="credit_card">
                                    <label class="form-check-label" for="creditCard">
                                        บัตรเครดิต/เดบิต
                                    </label>
                                </div>
                                <div class="collapse" id="creditCardDetails">
                                    <div class="p-3 bg-light rounded">
                                        <p>กรุณาติดต่อเจ้าหน้าที่เพื่อทำการชำระเงินผ่านบัตร</p>
                                        <p>โทร: <?php echo getSetting('contact_phone', $conn, 'คุณจันจิรา ภู่สุวรรณ์ (คุณตูน) Tel : 062-4086398'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="form-check mb-2 mt-3">
                                    <input class="form-check-input" type="radio" name="paymentMethod" id="qrPayment" value="qr_payment">
                                    <label class="form-check-label" for="qrPayment">
                                        QR Payment
                                    </label>
                                </div>
                                <div class="collapse" id="qrPaymentDetails">
                                    <div class="p-3 bg-light rounded text-center">
                                        <p>สแกนเพื่อชำระเงิน</p>
                                        <img src="qr.jpg" alt="QR Code" style="max-width: 200px;" class="img-fluid">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="paymentSlip" class="form-label">หลักฐานการชำระเงิน (สลิป)<span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="paymentSlip" name="paymentSlip" accept="image/jpeg,image/png,image/jpg,application/pdf" required>
                            <div class="form-text">อัพโหลดสลิปการโอนเงิน หรือหลักฐานการชำระเงินอื่นๆ (รองรับไฟล์ JPG, PNG, PDF ขนาดไม่เกิน 5MB)</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" onclick="submitPayment()">ยืนยันการชำระเงิน</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="successModalLabel">จองสำเร็จแล้ว</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="display-1 text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h4>การจองสำเร็จเรียบร้อย!</h4>
                        <p>หมายเลขคำสั่งซื้อ: <strong><span id="successOrderNumber"></span></strong></p>
                    </div>
                    
                    <div class="alert alert-info" id="successPaymentInfo">
                        <!-- ข้อความจะถูกเปลี่ยนตามเงื่อนไขการชำระเงิน -->
                    </div>
                    
                    <div id="paymentButtonContainer" class="d-flex justify-content-center mt-3">
                        <!-- ปุ่มชำระเงินจะถูกเพิ่มเมื่อเลือกจ่ายทีหลัง -->
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="my-reservations.php" class="btn btn-outline-primary me-2">ไปที่ประวัติการจอง</a>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="location.reload()">ตกลง</button>
                </div>
            </div>
        </div>
    </div>


<div class="contact-sticky">
    <button class="btn btn-primary contact-btn" onclick="toggleContactInfo()">
        <i class="bi bi-telephone-fill"></i> ติดต่อเจ้าหน้าที่
    </button>
    <div class="contact-info" id="contactInfo">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">สนใจจองบูธติดต่อ</h5>
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 mt-2 me-2" aria-label="Close" onclick="toggleContactInfo()"></button>
            </div>
            <div class="card-body">
                <p><strong>คุณจันจิรา ภู่สุวรรณ์ (คุณตูน)</strong><br>
                Tel: 062-4086398<br>
                Line ID: lunytoon</p>
                
                <p><strong>คุณสาลินี ขจรไพร (คุณสา)</strong><br>
                Tel: 093-2952519<br>
                Line ID: 0932952519</p>
                
                <h6 class="mt-3">ราคาบูธ</h6>
                <ul class="list-unstyled">
                    <li>ZONE A: 30,000 บาท</li>
                    <li>ZONE B: 23,000 บาท</li>
                    <li>ZONE C: 10,000 บาท</li>
                </ul>
                
                <div class="mt-3 text-center">
                    <a href="https://line.me/ti/g/PhW4LmGJyZ" target="_blank" class="btn btn-success">
                        <i class="bi bi-line"></i> ติดต่อผ่าน Line
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Modal สำหรับแสดงภาพขนาดใหญ่ -->
<div class="modal fade" id="overviewModal" tabindex="-1" aria-labelledby="overviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="overviewModalLabel">แผนผังภาพรวมงาน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img src="" id="modalImage" class="img-fluid" alt="ภาพขยาย">
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- เพิ่ม CSS ในส่วน style -->
<style>
    .booth-overview-img {
        cursor: pointer;
        transition: transform 0.3s ease;
    }
    
    .booth-overview-img:hover {
        transform: scale(1.02);
    }
</style>

<!-- เพิ่ม JavaScript ท้ายไฟล์ก่อนปิด </body> -->
<script>
    // เพิ่มโค้ดนี้ภายใต้ $(document).ready(function() { ... });
    $(document).ready(function() {
        // โค้ดที่มีอยู่เดิม...
        
        // JavaScript สำหรับ modal ภาพขยาย
        $('.booth-overview-img').on('click', function() {
            const imgSrc = $(this).data('img');
            $('#modalImage').attr('src', imgSrc);
        });
        
       
    });
</script>

<script>
   
    function toggleContactInfo() {
        const contactInfo = document.getElementById('contactInfo');
        contactInfo.classList.toggle('active');
    }
</script>
  
    <script>
        // เช็คสถานะการเข้าสู่ระบบ
        const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
        let currentOrderId = 0;
        let currentOrderNumber = '';
        
        function showZone(zone) {
            // Hide all floors/zones
            document.querySelectorAll('.floor').forEach(floor => {
                floor.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.zone-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected floor/zone
            let floorId = 'zone-' + zone;
            document.getElementById(floorId).classList.add('active');
            
           // Make tab active
           let tab;
            if (zone === 'A') {
                tab = document.querySelector('.tab-a');
            } else if (zone === 'B') {
                tab = document.querySelector('.tab-b');
            } else if (zone === 'C1') {
                tab = document.querySelector('.tab-c1');
            } else if (zone === 'C2') {
                tab = document.querySelector('.tab-c2');
            }
            
            if (tab) {
                tab.classList.add('active');
            }
        }
        
        function selectBooth(element) {
            // ตรวจสอบการล็อกอิน
            if (!isLoggedIn) {
                alert('กรุณาลงทะเบียนหรือเข้าสู่ระบบก่อนทำการจอง');
                var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                loginModal.show();
                return;
            }
            
            // Check if booth is already reserved
            if (element.classList.contains('reserved') || element.classList.contains('pending_payment') || element.classList.contains('paid')) {
                alert('บูธนี้ถูกจองไปแล้ว');
                return;
            }
            
            // Get booth information
            const boothId = element.getAttribute('data-id');
            const boothNumber = element.getAttribute('data-number');
            const zone = element.getAttribute('data-zone');
            const price = element.getAttribute('data-price');
            
            // Set values in the modal
            document.getElementById('selectedBoothNumber').textContent = boothNumber;
            document.getElementById('selectedZone').textContent = zone;
            document.getElementById('boothId').value = boothId;
            document.getElementById('boothPrice').textContent = formatCurrency(price);
            
            // กำหนดการแสดงปุ่มตามเงื่อนไขการจ่ายเงิน
            updatePaymentButtons();
            
            // Show the modal
            var reservationModal = new bootstrap.Modal(document.getElementById('reservationModal'));
            reservationModal.show();
        }
        
        // อัพเดตการแสดงปุ่มในหน้าจองตามเงื่อนไข
        function updatePaymentButtons() {
            const payLater = document.getElementById('payLater').checked;
            document.getElementById('payNowBtn').style.display = payLater ? 'none' : 'block';
        }
        
        // เพิ่ม event listener สำหรับ checkbox
        document.getElementById('payLater').addEventListener('change', updatePaymentButtons);
        
        // ฟังก์ชั่นจองโดยจ่ายทีหลัง
        function submitReservation() {
            // Get form data
            const boothId = document.getElementById('boothId').value;
            
            // แสดงข้อความกำลังดำเนินการ
            const reserveBtn = document.querySelector('[onclick="submitReservation()"]');
            reserveBtn.disabled = true;
            reserveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> กำลังดำเนินการ...';
            
            // Submit reservation via AJAX
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'reserve',
                    boothId: boothId
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Server response:', response);
                    
                    if (response && response.success) {
                        // Hide reservation modal
                        var reservationModal = bootstrap.Modal.getInstance(document.getElementById('reservationModal'));
                        reservationModal.hide();
                        
                        // บันทึกข้อมูลคำสั่งซื้อ
                        currentOrderId = response.order_id;
                        currentOrderNumber = response.order_number;
                        
                        // Set success info และข้อความสำหรับกรณีจ่ายทีหลัง
                        document.getElementById('successOrderNumber').textContent = response.order_number;
                        document.getElementById('successPaymentInfo').innerHTML = `
                            <p><strong>คุณเลือกชำระเงินภายหลัง</strong></p>
                            <p>กรุณาชำระเงินภายใน 24 ชั่วโมง มิเช่นนั้นการจองจะถูกยกเลิกโดยอัตโนมัติ</p>
                            <p>หากมีข้อสงสัยสามารถติดต่อได้ที่ ${getSetting('contact_phone', '0812345678')}</p>
                        `;
                        
                        // เพิ่มปุ่มชำระเงิน
                        document.getElementById('paymentButtonContainer').innerHTML = `
                            <button class="btn btn-primary" onclick="showPaymentModal('${response.order_id}', '${response.order_number}')">
                                <i class="bi bi-credit-card me-2"></i>ชำระเงินตอนนี้
                            </button>
                        `;
                        
                        // Show success modal
                        var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                        successModal.show();
                    } else {
                        alert(response && response.message ? response.message : 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
                    }
                    
                    // คืนค่าปุ่ม
                    reserveBtn.disabled = false;
                    reserveBtn.innerHTML = 'ยืนยันการจอง';
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    console.log('Response text:', xhr.responseText);
                    
                    try {
                        // พยายามแปลงเป็น JSON
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.success) {
                            // ถ้าแปลงได้และเป็น success ให้ดำเนินการต่อ
                            var reservationModal = bootstrap.Modal.getInstance(document.getElementById('reservationModal'));
                            reservationModal.hide();
                            
                            // บันทึกข้อมูลคำสั่งซื้อ
                            currentOrderId = response.order_id;
                            currentOrderNumber = response.order_number;
                            
                            // Set success info
                            document.getElementById('successOrderNumber').textContent = response.order_number;
                            document.getElementById('successPaymentInfo').innerHTML = `
                                <p><strong>คุณเลือกชำระเงินภายหลัง</strong></p>
                                <p>กรุณาชำระเงินภายใน 24 ชั่วโมง มิเช่นนั้นการจองจะถูกยกเลิกโดยอัตโนมัติ</p>
                                <p>หากมีข้อสงสัยสามารถติดต่อได้ที่ ${getSetting('contact_phone', '0812345678')}</p>
                            `;
                            
                            // เพิ่มปุ่มชำระเงิน
                            document.getElementById('paymentButtonContainer').innerHTML = `
                                <button class="btn btn-primary" onclick="showPaymentModal('${response.order_id}', '${response.order_number}')">
                                    <i class="bi bi-credit-card me-2"></i>ชำระเงินตอนนี้
                                </button>
                            `;
                            
                            // Show success modal
                            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                            successModal.show();
                            return;
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                    }
                    
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error);
                    
                    // คืนค่าปุ่ม
                    reserveBtn.disabled = false;
                    reserveBtn.innerHTML = 'ยืนยันการจอง';
                }
            });
        }

        function submitReservationAndPay() {
            // Get form data
            const boothId = document.getElementById('boothId').value;
            
            // แสดงข้อความกำลังดำเนินการ
            const payNowBtn = document.querySelector('[onclick="submitReservationAndPay()"]');
            payNowBtn.disabled = true;
            payNowBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> กำลังดำเนินการ...';
            
            // Submit reservation via AJAX
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'reserve',
                    boothId: boothId
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Server response:', response);
                    
                    if (response && response.success) {
                        // Hide reservation modal
                        var reservationModal = bootstrap.Modal.getInstance(document.getElementById('reservationModal'));
                        reservationModal.hide();
                        
                        // นำข้อมูลมาแสดงที่หน้าชำระเงิน
                        document.getElementById('orderId').value = response.order_id;
                        document.getElementById('orderNumber').value = response.order_number;
                        
                        // ดึงราคาบูธ
                        const price = document.getElementById('boothPrice').textContent;
                        document.getElementById('totalAmount').textContent = price;
                        
                        // Show payment modal
                        var paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
                        paymentModal.show();
                    } else {
                        alert(response && response.message ? response.message : 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
                    }
                    
                    // คืนค่าปุ่ม
                    payNowBtn.disabled = false;
                    payNowBtn.innerHTML = 'ยืนยันและชำระเงิน';
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    console.log('Response text:', xhr.responseText);
                    
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error);
                    
                    // คืนค่าปุ่ม
                    payNowBtn.disabled = false;
                    payNowBtn.innerHTML = 'ยืนยันและชำระเงิน';
                }
            });
        }
        // แสดงหน้าชำระเงินสำหรับคำสั่งซื้อที่มีอยู่แล้ว
        function showPaymentModal(orderId, orderNumber) {
            // บันทึกข้อมูลคำสั่งซื้อ
            document.getElementById('orderId').value = orderId;
            document.getElementById('orderNumber').value = orderNumber;
            
            // ค้นหาราคาของบูธจากคำสั่งซื้อ
            const price = document.getElementById('boothPrice').textContent;
            document.getElementById('totalAmount').textContent = price;
            
            // ปิด success modal ก่อน
            var successModal = bootstrap.Modal.getInstance(document.getElementById('successModal'));
            if (successModal) {
                successModal.hide();
            }
            
            // แสดง payment modal
            var paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
            paymentModal.show();
        }
        
        // ชำระเงิน
        function submitPayment() {
            // Get payment info
            const orderId = document.getElementById('orderId').value;
            const orderNumber = document.getElementById('orderNumber').value;
            const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
            const paymentSlip = document.getElementById('paymentSlip').files[0];
            
            // ตรวจสอบว่าอัพโหลดไฟล์หรือไม่
            if (!paymentSlip) {
                alert('กรุณาอัพโหลดหลักฐานการชำระเงิน');
                return;
            }
            
            // ส่งข้อมูลผ่าน FormData เพื่อส่งไฟล์
            const formData = new FormData();
            formData.append('action', 'upload_slip');
            formData.append('orderId', orderId);
            formData.append('paymentMethod', paymentMethod);
            formData.append('paymentSlip', paymentSlip);
            
            // แสดงข้อความกำลังอัพโหลด
            const paymentBtn = document.querySelector('[onclick="submitPayment()"]');
            paymentBtn.disabled = true;
            paymentBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> กำลังอัพโหลด...';
            
            // Submit payment via AJAX
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    console.log('Server response:', response);
                    
                    try {
                        // บางครั้ง response อาจเป็น string ต้องแปลงเป็น JSON
                        if (typeof response === 'string') {
                            response = JSON.parse(response);
                        }
                        
                        if (response.success) {
                            // Hide payment modal
                            var paymentModal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
                            paymentModal.hide();
                            
                            // Set success info
                            document.getElementById('successOrderNumber').textContent = orderNumber;
                            document.getElementById('successPaymentInfo').innerHTML = `
                                <p>เราได้รับหลักฐานการชำระเงินของคุณแล้ว</p>
                                <p>เจ้าหน้าที่จะตรวจสอบและยืนยันการชำระเงินภายใน 24 ชั่วโมง</p>
                                <p>หากมีข้อสงสัยสามารถติดต่อได้ที่ ${getSetting('contact_phone', '0812345678')}</p>
                            `;
                            
                            // ซ่อนปุ่มชำระเงิน เพราะชำระแล้ว
                            document.getElementById('paymentButtonContainer').innerHTML = '';
                            
                            // Show success modal
                            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                            successModal.show();
                        } else {
                            alert(response.message || 'เกิดข้อผิดพลาดในการอัพโหลด');
                        }
                    } catch (error) {
                        console.error('Error parsing response:', error, response);
                        alert('เกิดข้อผิดพลาดในการประมวลผลข้อมูลจากเซิร์ฟเวอร์');
                    }
                    
                    // คืนค่าปุ่ม
                    paymentBtn.disabled = false;
                    paymentBtn.innerHTML = 'ยืนยันการชำระเงิน';
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    console.log('Response text:', xhr.responseText);
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error);
                    
                    // คืนค่าปุ่ม
                    paymentBtn.disabled = false;
                    paymentBtn.innerHTML = 'ยืนยันการชำระเงิน';
                }
            });
        }


        function login() {
            const phone = document.getElementById('loginPhone').value;
            const name = document.getElementById('loginName').value;
            const email = document.getElementById('loginEmail').value;
            const lineId = document.getElementById('loginLineId').value;
            const company = document.getElementById('loginCompany').value;
            
            // สร้างที่อยู่แบบเต็มจากองค์ประกอบต่างๆ
            const addressDetail = document.getElementById('loginAddressDetail').value;
            const province = $('#loginProvince option:selected').text() !== '-- เลือกจังหวัด --' ? $('#loginProvince option:selected').text() : '';
            const district = $('#loginDistrict option:selected').text() !== '-- เลือกอำเภอ/เขต --' ? $('#loginDistrict option:selected').text() : '';
            const subdistrict = $('#loginSubdistrict option:selected').text() !== '-- เลือกตำบล/แขวง --' ? $('#loginSubdistrict option:selected').text() : '';
            const zipcode = document.getElementById('loginZipcode').value;
            
            // รวมเป็นที่อยู่เต็มรูปแบบ
            let fullAddress = addressDetail;
            if (subdistrict) fullAddress += ' ตำบล/แขวง' + subdistrict;
            if (district) fullAddress += ' อำเภอ/เขต' + district;
            if (province) fullAddress += ' จังหวัด' + province;
            if (zipcode) fullAddress += ' ' + zipcode;
            
            // ตรวจสอบข้อมูล
            if (!phone || !name || !email || !addressDetail || !province || !district || !subdistrict) {
                alert('กรุณากรอกข้อมูลให้ครบถ้วน (ชื่อ, เบอร์โทร, อีเมล และที่อยู่)');
                return;
            }
            
            // ตรวจสอบรูปแบบเบอร์โทรศัพท์ (ตัวเลข 9-10 หลัก)
            if (!phone.match(/^[0-9]{9,10}$/)) {
                alert('กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (ตัวเลข 9-10 หลัก)');
                return;
            }
            
            // ตรวจสอบรูปแบบอีเมล
            const emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/;
            if (!email.match(emailPattern)) {
                alert('กรุณากรอกอีเมลให้ถูกต้อง');
                return;
            }
            
            // พิมพ์ค่าที่อยู่เพื่อตรวจสอบ
            console.log('ส่งที่อยู่:', fullAddress);
            
            // ส่งข้อมูลไปยังเซิร์ฟเวอร์
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'login',
                    phone: phone,
                    name: name,
                    email: email,
                    address: fullAddress, // ส่งที่อยู่แบบเต็ม
                    lineId: lineId,
                    company: company
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // ปิด modal
                        var loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
                        loginModal.hide();
                        
                        // รีโหลดหน้าเพื่อแสดงสถานะเข้าสู่ระบบ
                        location.reload();
                    } else {
                        alert(response.message || 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', xhr.responseText);
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error);
                }
            });
        }
        
        // ล็อกเอาท์
        function logout() {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'logout'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        }
        
        // ฟังก์ชันฟอร์แมตตัวเลขให้เป็นรูปแบบเงิน
        function formatCurrency(amount) {
            return parseFloat(amount).toLocaleString('th-TH', {
                style: 'currency',
                currency: 'THB',
                minimumFractionDigits: 0
            });
        }
        
        // Helper function สำหรับดึงการตั้งค่า
        function getSetting(key, defaultValue = '') {
            // ในตัวอย่างนี้จะใช้ค่า default ที่กำหนด แต่ในระบบจริงควรดึงค่าจาก API
            const settings = {
                'contact_phone': '062-4086398',
                'bank_account': 'ธนาคารกรุงไทย 123-4-56789-0 มทร.สุวรรณภูมิ เงินรายได้'
            };
            
            return settings[key] || defaultValue;
        }
        
        // Handle payment method change
        $(document).ready(function() {
            $('input[name="paymentMethod"]').change(function() {
                // Hide all payment details
                $('#bankTransferDetails, #creditCardDetails, #qrPaymentDetails').collapse('hide');
                
                // Show selected payment details
                if (this.value === 'bank_transfer') {
                    $('#bankTransferDetails').collapse('show');
                } else if (this.value === 'credit_card') {
                    $('#creditCardDetails').collapse('show');
                } else if (this.value === 'qr_payment') {
                    $('#qrPaymentDetails').collapse('show');
                }
            });
        });

        // เพิ่มโค้ด JavaScript สำหรับที่อยู่
        $(document).ready(function() {
            // โหลดจังหวัดตอนเริ่มต้น
            loadProvinces();
            
            // Event listener เมื่อเลือกจังหวัด
            $('#loginProvince').change(function() {
                const provinceId = $(this).val();
                if (provinceId) {
                    loadDistricts(provinceId);
                    $('#loginDistrict').prop('disabled', false);
                    $('#loginSubdistrict').prop('disabled', true).html('<option value="">-- เลือกตำบล/แขวง --</option>');
                    $('#loginZipcode').val('');
                } else {
                    $('#loginDistrict').prop('disabled', true).html('<option value="">-- เลือกอำเภอ/เขต --</option>');
                    $('#loginSubdistrict').prop('disabled', true).html('<option value="">-- เลือกตำบล/แขวง --</option>');
                    $('#loginZipcode').val('');
                }
                updateAddressField();
            });
            
            // Event listener เมื่อเลือกอำเภอ
            $('#loginDistrict').change(function() {
                const districtId = $(this).val();
                if (districtId) {
                    loadSubdistricts(districtId);
                    $('#loginSubdistrict').prop('disabled', false);
                } else {
                    $('#loginSubdistrict').prop('disabled', true).html('<option value="">-- เลือกตำบล/แขวง --</option>');
                    $('#loginZipcode').val('');
                }
                updateAddressField();
            });
            
            // Event listener เมื่อเลือกตำบล
            $('#loginSubdistrict').change(function() {
                const subdistrictId = $(this).val();
                if (subdistrictId) {
                    // ดึงรหัสไปรษณีย์
                    fetchZipcode(subdistrictId);
                } else {
                    $('#loginZipcode').val('');
                }
                updateAddressField();
            });
            
            // Event listener เมื่อกรอกรายละเอียดที่อยู่
            $('#loginAddressDetail').on('input', updateAddressField);
        });

        // ฟังก์ชันโหลดข้อมูลจังหวัด
        function loadProvinces() {
            $.ajax({
                url: 'get_location.php',
                type: 'GET',
                data: {
                    action: 'get_provinces'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let options = '<option value="">-- เลือกจังหวัด --</option>';
                        
                        response.data.forEach(function(province) {
                            options += `<option value="${province.id}">${province.name_in_thai}</option>`;
                        });
                        
                        $('#loginProvince').html(options);
                    }
                },
                error: function() {
                    console.error('ไม่สามารถโหลดข้อมูลจังหวัดได้');
                }
            });
        }

        // ฟังก์ชันโหลดข้อมูลอำเภอตามจังหวัด
        function loadDistricts(provinceId) {
            $.ajax({
                url: 'get_location.php',
                type: 'GET',
                data: {
                    action: 'get_districts',
                    province_id: provinceId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let options = '<option value="">-- เลือกอำเภอ/เขต --</option>';
                        
                        response.data.forEach(function(district) {
                            options += `<option value="${district.id}">${district.name_in_thai}</option>`;
                        });
                        
                        $('#loginDistrict').html(options);
                    }
                },
                error: function() {
                    console.error('ไม่สามารถโหลดข้อมูลอำเภอได้');
                }
            });
        }

        // ฟังก์ชันโหลดข้อมูลตำบลตามอำเภอ
        function loadSubdistricts(districtId) {
            $.ajax({
                url: 'get_location.php',
                type: 'GET',
                data: {
                    action: 'get_subdistricts',
                    district_id: districtId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let options = '<option value="">-- เลือกตำบล/แขวง --</option>';
                        
                        response.data.forEach(function(subdistrict) {
                            options += `<option value="${subdistrict.id}" data-zipcode="${subdistrict.zip_code}">${subdistrict.name_in_thai}</option>`;
                        });
                        
                        $('#loginSubdistrict').html(options);
                    }
                },
                error: function() {
                    console.error('ไม่สามารถโหลดข้อมูลตำบลได้');
                }
            });
        }

        // ฟังก์ชันดึงรหัสไปรษณีย์
        function fetchZipcode(subdistrictId) {
            const zipcode = $('#loginSubdistrict option:selected').data('zipcode');
            $('#loginZipcode').val(zipcode || '');
        }

        // ฟังก์ชันอัพเดตฟิลด์ที่อยู่รวม
        function updateAddressField() {
            const addressDetail = $('#loginAddressDetail').val();
            const provinceName = $('#loginProvince option:selected').text();
            const districtName = $('#loginDistrict option:selected').text();
            const subdistrictName = $('#loginSubdistrict option:selected').text();
            const zipcode = $('#loginZipcode').val();
            
            // สร้างที่อยู่รวม
            let fullAddress = addressDetail;
            
            if (subdistrictName && subdistrictName !== '-- เลือกตำบล/แขวง --') {
                fullAddress += ' ตำบล/แขวง' + subdistrictName;
            }
            
            if (districtName && districtName !== '-- เลือกอำเภอ/เขต --') {
                fullAddress += ' อำเภอ/เขต' + districtName;
            }
            
            if (provinceName && provinceName !== '-- เลือกจังหวัด --') {
                fullAddress += ' จังหวัด' + provinceName;
            }
            
            if (zipcode) {
                fullAddress += ' ' + zipcode;
            }
            
            // เก็บที่อยู่รวมในฟิลด์ซ่อน
            $('#loginAddress').val(fullAddress);
        }
    </script>
</body>
</html>