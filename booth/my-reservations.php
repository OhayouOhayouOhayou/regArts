<?php
session_start();
require_once 'config.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['phone']) || empty($_SESSION['phone'])) {
    header("Location: index.php");
    exit;
}

$customerPhone = $_SESSION['phone'];
$customerName = $_SESSION['name'] ?? '';
$customerEmail = $_SESSION['email'] ?? '';
$customerCompany = $_SESSION['company'] ?? '';
$customerAddress = $_SESSION['address'] ?? ''; // เพิ่มที่อยู่
$customerLineId = $_SESSION['line_id'] ?? ''; // เพิ่ม Line ID

// Handle AJAX requests for showing payments
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"])) {
    header('Content-Type: application/json');
    
    if ($_POST["action"] == "cancel_reservation") {
        $orderId = $_POST["orderId"];
        
        // ตรวจสอบว่าเป็นออเดอร์ของผู้ใช้งานหรือไม่
        $checkStmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND customer_phone = ? AND payment_status = 'unpaid'");
        $checkStmt->bind_param("is", $orderId, $customerPhone);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            echo json_encode(["success" => false, "message" => "คุณไม่มีสิทธิ์ยกเลิกการจองนี้หรือได้ชำระเงินแล้ว"]);
            exit;
        }
        
        // เริ่ม transaction
        $conn->begin_transaction();
        
        try {
            // ดึงข้อมูลบูธที่เกี่ยวข้องกับออเดอร์นี้
            $getBoothsStmt = $conn->prepare("SELECT booth_id FROM order_items WHERE order_id = ?");
            $getBoothsStmt->bind_param("i", $orderId);
            $getBoothsStmt->execute();
            $boothsResult = $getBoothsStmt->get_result();
            
            // อัพเดตสถานะบูธเป็น available
            while ($boothRow = $boothsResult->fetch_assoc()) {
                $boothId = $boothRow['booth_id'];
                $updateBoothStmt = $conn->prepare("UPDATE booths SET status = 'available' WHERE id = ?");
                $updateBoothStmt->bind_param("i", $boothId);
                $updateBoothStmt->execute();
            }
            
            // อัพเดตสถานะออเดอร์เป็น cancelled
            $updateOrderStmt = $conn->prepare("UPDATE orders SET payment_status = 'cancelled' WHERE id = ?");
            $updateOrderStmt->bind_param("i", $orderId);
            $updateOrderStmt->execute();
            
            // ยืนยัน transaction
            $conn->commit();
            
            echo json_encode(["success" => true, "message" => "ยกเลิกการจองเรียบร้อยแล้ว"]);
        } catch (Exception $e) {
            // ยกเลิก transaction หากเกิดข้อผิดพลาด
            $conn->rollback();
            echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
        }
        
        exit;
    }
    if ($_POST["action"] == "upload_slip") {
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
                
                // อัพเดตข้อมูลการชำระเงินในตาราง orders
                $updateOrder = $conn->prepare("UPDATE orders SET 
                    payment_status = 'pending',
                    payment_method = ?,
                    payment_reference = ?,
                    payment_date = NOW()
                    WHERE id = ?");
                $updateOrder->bind_param("ssi", $paymentMethod, $targetFile, $orderId);
                $updateOrder->execute();
                
                // อัพเดตสถานะบูธที่เกี่ยวข้อง
                $getBoothsStmt = $conn->prepare("SELECT booth_id FROM order_items WHERE order_id = ?");
                $getBoothsStmt->bind_param("i", $orderId);
                $getBoothsStmt->execute();
                $boothsResult = $getBoothsStmt->get_result();
                
                while ($boothRow = $boothsResult->fetch_assoc()) {
                    $boothId = $boothRow['booth_id'];
                    $updateBoothStmt = $conn->prepare("UPDATE booths SET status = 'pending_payment' WHERE id = ?");
                    $updateBoothStmt->bind_param("i", $boothId);
                    $updateBoothStmt->execute();
                }
                
                // ยืนยัน transaction
                $conn->commit();
                
                echo json_encode(["success" => true, "message" => "อัพโหลดสลิปเรียบร้อยแล้ว เจ้าหน้าที่จะตรวจสอบและยืนยันการชำระเงินต่อไป"]);
            } catch (Exception $e) {
                // ยกเลิก transaction หากเกิดข้อผิดพลาด
                $conn->rollback();
                echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage()]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาดในการอัพโหลดไฟล์"]);
        }
        exit;
    }
}
// ดึงข้อมูลการจองของผู้ใช้
$stmt = $conn->prepare("
    SELECT o.*, i.booth_id, b.zone, b.floor, b.booth_number, b.price, b.location, b.status as booth_status
    FROM orders o
    JOIN order_items i ON o.id = i.order_id
    JOIN booths b ON i.booth_id = b.id
    WHERE o.customer_phone = ?
    ORDER BY o.created_at DESC
");
$stmt->bind_param("s", $customerPhone);
$stmt->execute();
$result = $stmt->get_result();

$reservations = [];
while ($row = $result->fetch_assoc()) {
    $reservations[] = $row;
}

// ฟังก์ชันจัดรูปแบบสกุลเงิน (หากยังไม่มีในไฟล์ config.php)
if(!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return number_format($amount, 2, '.', ',') . ' บาท';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการจองบูธ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f5f5f5;
        }
        
        .header {
            background-color: #fff;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .reservation-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
        }
        
        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        
        .booth-number {
            font-size: 22px;
            font-weight: bold;
            color: #333;
        }
        
        .booth-zone {
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            border-radius: 50%;
            color: white;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .zone-a {
            background-color: #00bcd4;
        }
        
        .zone-b {
            background-color: #4caf50;
        }
        
        .zone-c {
            background-color: #9c27b0;
        }
        
        .price-tag {
            font-size: 18px;
            font-weight: bold;
            color: #e91e63;
        }
        
        .reservation-details {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .payment-proof {
            margin-top: 15px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        
        .payment-proof img {
            max-width: 100%;
            max-height: 200px;
            display: block;
            margin: 10px auto;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .user-info {
            background-color: #eaf7ff;
            padding: 10px 15px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .user-name {
            font-weight: bold;
        }
        
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .countdown {
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>
<body>
<header class="header">
    <div class="container">
        <div class="nav-container">
            <h1>ประวัติการจองบูธ</h1>
            <div>
                <a href="index.php" class="btn btn-outline-primary me-2">
                    <i class="bi bi-house-door"></i> หน้าหลัก
                </a>
            </div>
        </div>
    </div>
</header>

<div class="container">
    <div class="user-info">
        <div>
            <span class="user-name"><?php echo $customerName; ?></span>
            <span class="ms-2"><?php echo $customerPhone; ?></span>
            <?php if(!empty($customerAddress)): ?>
            <p class="mb-0 mt-1"><small><?php echo $customerAddress; ?></small></p>
            <?php endif; ?>
            <?php if(!empty($customerLineId)): ?>
            <p class="mb-0"><small>Line ID: <?php echo $customerLineId; ?></small></p>
            <?php endif; ?>
        </div>
        <a href="index.php?logout=1" class="btn btn-sm btn-outline-danger">ออกจากระบบ</a>
    </div>
    
    <h2 class="mb-4">รายการจองบูธของคุณ</h2>
    
    <?php if (empty($reservations)): ?>
    <div class="alert alert-info">
        <p>คุณยังไม่มีประวัติการจองบูธ</p>
        <a href="index.php" class="btn btn-primary mt-2">ไปยังหน้าจองบูธ</a>
    </div>
    <?php else: ?>
        <?php foreach ($reservations as $reservation): ?>
    <div class="reservation-card">
        <div class="status-badge badge 
            <?php 
            if ($reservation['payment_status'] == 'paid') {
                echo 'bg-success';
            } elseif ($reservation['payment_status'] == 'pending') {
                echo 'bg-warning';
            } elseif ($reservation['payment_status'] == 'cancelled') {
                echo 'bg-secondary';
            } else {
                echo 'bg-danger';
            }
            ?>">
            <?php 
            if ($reservation['payment_status'] == 'paid') {
                echo 'ชำระเงินแล้ว';
            } elseif ($reservation['payment_status'] == 'pending') {
                echo 'รอตรวจสอบ';
            } elseif ($reservation['payment_status'] == 'cancelled') {
                echo 'ยกเลิกแล้ว';
            } else {
                echo 'รอชำระเงิน';
            }
            ?>
        </div>
        
        <div class="d-flex align-items-center">
            <span class="booth-zone zone-<?php echo strtolower($reservation['zone']); ?>"><?php echo $reservation['zone']; ?></span>
            <span class="booth-number">บูธ #<?php echo $reservation['booth_number']; ?></span>
            <span class="ms-2 text-muted">(ชั้น <?php echo $reservation['floor']; ?>)</span>
        </div>
        <div class="mt-2">
            <p class="mb-1"><strong>โซน:</strong> 
                <?php 
                if ($reservation['zone'] == 'A') {
                    echo 'โซน A - บริเวณทางเข้าหลัก (พื้นที่ขายดี)';
                } elseif ($reservation['zone'] == 'B') {
                    echo 'โซน B - บริเวณกลางฮอลล์ (พื้นที่มีการสัญจรสูง)';
                } elseif ($reservation['zone'] == 'C') {
                    echo 'โซน C - บริเวณด้านใน (พื้นที่เงียบสงบ)';
                } else {
                    echo 'โซน ' . $reservation['zone'];
                }
                ?>
            </p>
            <p class="mb-1"><strong>ตำแหน่ง:</strong> <?php echo $reservation['location']; ?></p>
            <p class="mb-1"><strong>หมายเลขคำสั่งซื้อ:</strong> <?php echo $reservation['order_number']; ?></p>
            <p class="mb-1"><strong>ราคา:</strong> <span class="price-tag"><?php echo formatCurrency($reservation['total_amount']); ?></span></p>
            <p class="mb-1"><strong>วันที่จอง:</strong> <?php echo date('d/m/Y H:i', strtotime($reservation['created_at'])); ?></p>
            
            <?php if ($reservation['payment_status'] == 'unpaid'): ?>
            <?php 
                // คำนวณเวลาที่เหลือก่อนยกเลิกอัตโนมัติ (24 ชั่วโมงนับจากเวลาจอง)
                $reservedTime = strtotime($reservation['created_at']);
                $expiryTime = $reservedTime + (24 * 60 * 60); // 24 ชั่วโมง
                $currentTime = time();
                $timeLeft = $expiryTime - $currentTime;
                
                $hoursLeft = floor($timeLeft / 3600);
                $minutesLeft = floor(($timeLeft % 3600) / 60);
            ?>
            <p class="mb-1">
                <strong>สถานะการชำระเงิน:</strong> 
                <span class="badge bg-danger">ยังไม่ชำระเงิน</span>
                
                <?php if ($timeLeft > 0): ?>
                <span class="countdown ms-2">
                    เหลือเวลาอีก <?php echo $hoursLeft; ?> ชั่วโมง <?php echo $minutesLeft; ?> นาที
                </span>
                <?php else: ?>
                <span class="countdown ms-2">
                    หมดเวลาชำระเงินแล้ว
                </span>
                <?php endif; ?>
            </p>

            <?php if ($timeLeft > 0): ?>
            <div class="mt-3">
                <button class="btn btn-primary" onclick="showPaymentForm(<?php echo $reservation['id']; ?>, '<?php echo $reservation['order_number']; ?>', <?php echo $reservation['total_amount']; ?>)">
                    <i class="bi bi-credit-card"></i> ชำระเงิน
                </button>
                <button class="btn btn-outline-danger" onclick="cancelReservation(<?php echo $reservation['id']; ?>)">
                    <i class="bi bi-x-circle"></i> ยกเลิกการจอง
                </button>
            </div>
            <?php endif; ?>
            <?php elseif ($reservation['payment_status'] == 'pending'): ?>
            <p class="mb-1">
                <strong>สถานะการชำระเงิน:</strong> 
                <span class="badge bg-warning">รอตรวจสอบ</span>
            </p>
            <?php elseif ($reservation['payment_status'] == 'cancelled'): ?>
            <p class="mb-1">
                <strong>สถานะการชำระเงิน:</strong> 
                <span class="badge bg-secondary">ยกเลิกแล้ว</span>
            </p>
            <?php else: ?>
            <p class="mb-1">
                <strong>สถานะการชำระเงิน:</strong> 
                <span class="badge bg-success">ชำระเงินแล้ว</span>
            </p>
            <?php endif; ?>
            <?php if(!empty($reservation['payment_method'])): ?>
            <div class="payment-proof">
                <p><strong>ข้อมูลการชำระเงิน:</strong></p>
                <p><strong>วิธีการชำระเงิน:</strong> 
                    <?php 
                    if ($reservation['payment_method'] == 'bank_transfer') {
                        echo 'โอนเงินผ่านธนาคาร';
                    } elseif ($reservation['payment_method'] == 'credit_card') {
                        echo 'บัตรเครดิต/เดบิต';
                    } elseif ($reservation['payment_method'] == 'qr_payment') {
                        echo 'QR Payment';
                    } else {
                        echo $reservation['payment_method'];
                    }
                    ?>
                </p>
                <?php if(strpos($reservation['payment_reference'], 'uploads/') !== false): ?>
                <p><strong>หลักฐานการชำระเงิน:</strong></p>
                <?php if(substr($reservation['payment_reference'], -3) === 'pdf'): ?>
                <a href="<?php echo $reservation['payment_reference']; ?>" target="_blank" class="btn btn-sm btn-primary">ดูเอกสาร PDF</a>
                <?php else: ?>
                <img src="<?php echo $reservation['payment_reference']; ?>" alt="หลักฐานการชำระเงิน">
                <?php endif; ?>
                <?php else: ?>
                <p><strong>หลักฐานการชำระเงิน:</strong> <?php echo $reservation['payment_reference']; ?></p>
                <?php endif; ?>
                <p><strong>วันที่ชำระเงิน:</strong> <?php echo date('d/m/Y H:i', strtotime($reservation['payment_date'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php endif; ?>
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
                                    <p>โทร: <?php echo getSetting('contact_phone', $conn, '0812345678'); ?></p>
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
                <h5 class="modal-title" id="successModalLabel">ดำเนินการสำเร็จ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="display-1 text-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <h4>ดำเนินการเรียบร้อยแล้ว!</h4>
                    <p id="successMessage">การทำรายการสำเร็จ</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="location.reload()">ตกลง</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">ยืนยันการยกเลิก</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>คุณแน่ใจหรือไม่ที่จะยกเลิกการจองบูธนี้?</p>
                <p class="text-danger">หากยกเลิกแล้ว คุณจะไม่สามารถกลับมาจองบูธนี้ได้อีก หากบูธถูกจองโดยผู้อื่น</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ไม่ยกเลิก</button>
                <button type="button" class="btn btn-danger" id="confirmCancelBtn">ยืนยันการยกเลิก</button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // แสดงหน้าชำระเงิน
    function showPaymentForm(orderId, orderNumber, price) {
        document.getElementById('orderId').value = orderId;
        document.getElementById('orderNumber').value = orderNumber;
        document.getElementById('totalAmount').textContent = formatCurrency(price);
        
        // แสดง Modal
        var paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
        paymentModal.show();
    }
    
    // ชำระเงิน
    function submitPayment() {
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
        
        // แสดงข้อความกำลังดำเนินการ
        const submitBtn = document.querySelector('[onclick="submitPayment()"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> กำลังอัพโหลด...';
        
        // ส่งข้อมูลไปยังเซิร์ฟเวอร์
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                // คืนค่าปุ่ม
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'ยืนยันการชำระเงิน';
                
                if (response.success) {
                    // ปิด modal
                    var paymentModal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
                    paymentModal.hide();
                    
                    // แสดงข้อความสำเร็จ
                    document.getElementById('successMessage').textContent = 'อัพโหลดหลักฐานการชำระเงินเรียบร้อยแล้ว เจ้าหน้าที่จะตรวจสอบและยืนยันการชำระเงินภายใน 24 ชั่วโมง';
                    var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    successModal.show();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr, status, error) {
                // คืนค่าปุ่ม
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'ยืนยันการชำระเงิน';
                
                console.error('AJAX error:', xhr.responseText);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error);
            }
        });
    }
    
    // ยกเลิกการจอง
    function cancelReservation(orderId) {
        // แสดง modal ยืนยัน
        var confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        confirmModal.show();
        
        // เพิ่ม event listener สำหรับปุ่มยืนยัน
        document.getElementById('confirmCancelBtn').onclick = function() {
            const cancelBtn = document.getElementById('confirmCancelBtn');
            cancelBtn.disabled = true;
            cancelBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> กำลังยกเลิก...';
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'cancel_reservation',
                    orderId: orderId
                },
                dataType: 'json',
                success: function(response) {
                    // คืนค่าปุ่ม
                    cancelBtn.disabled = false;
                    cancelBtn.innerHTML = 'ยืนยันการยกเลิก';
                    
                    // ปิด modal ยืนยัน
                    var confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmModal'));
                    confirmModal.hide();
                    
                    if (response.success) {
                        // แสดงข้อความสำเร็จ
                        document.getElementById('successMessage').textContent = 'ยกเลิกการจองเรียบร้อยแล้ว';
                        var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                        successModal.show();
                    } else {
                        alert(response.message);
                    }
                },
                error: function(xhr, status, error) {
                    // คืนค่าปุ่ม
                    cancelBtn.disabled = false;
                    cancelBtn.innerHTML = 'ยืนยันการยกเลิก';
                    
                    console.error('AJAX error:', xhr.responseText);
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error);
                }
            });
        };
    }
    
    // ฟังก์ชันฟอร์แมตตัวเลขให้เป็นรูปแบบเงิน
    function formatCurrency(amount) {
        return parseFloat(amount).toLocaleString('th-TH', {
            style: 'currency',
            currency: 'THB',
            minimumFractionDigits: 2
        });
    }
    
    // จัดการการแสดงผลวิธีการชำระเงิน
    $(document).ready(function() {
        $('input[name="paymentMethod"]').change(function() {
            // ซ่อนรายละเอียดทั้งหมด
            $('#bankTransferDetails, #creditCardDetails, #qrPaymentDetails').collapse('hide');
            
            // แสดงรายละเอียดตามวิธีที่เลือก
            if (this.value === 'bank_transfer') {
                $('#bankTransferDetails').collapse('show');
            } else if (this.value === 'credit_card') {
                $('#creditCardDetails').collapse('show');
            } else if (this.value === 'qr_payment') {
                $('#qrPaymentDetails').collapse('show');
            }
        });
        
        // อัพเดตเวลานับถอยหลังทุกนาที
        setInterval(function() {
            location.reload();
        }, 60000); // อัพเดททุก 1 นาที
    });
</script>
</body>
</html>