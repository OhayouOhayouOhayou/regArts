<?php
// Database configuration
$config = [
    'db' => [
       // 'host' => 'localhost',
       // 'username' => 'root',
       // 'password' => '',
       // 'dbname' => 'booth_reservation'
       // 'host' => '103.132.3.66',
       // 'username' => 'admin',
       // 'password' => 'S0lut!0n',
       // 'dbname' => 'db_books'
        'host' => 'mysql',  
        'username' => 'dbuser',  
        'password' => 'dbpassword',  
        'dbname' => 'shared_db' 
    ],
    'app' => [
        'name' => 'ระบบจองบูธขายสินค้า',
        'url' => 'http://localhost:8001/booth',
        'version' => '1.0.0',
        'admin_email' => 'คุณจันจิรา ภู่สุวรรณ์ (คุณตูน) Tel : 062-4086398'
    ],
    'payment' => [
        'tax_rate' => 7, // VAT 7%
        'methods' => [
            'bank_transfer' => 'โอนเงิน',
            'credit_card' => 'บัตรเครดิต',
            'qr_payment' => 'QR Payment'
        ],
        'bank_accounts' => [
            'กรุงไทย' => '128-028939-2  มทร.สุวรรณภูมิ เงินรายได้'
        ]
    ]
];


// Create connection
$conn = new mysqli($config['db']['host'], $config['db']['username'], $config['db']['password'], $config['db']['dbname']);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to support Thai language
$conn->set_charset("utf8");

// Function to get settings from database
function getSetting($key, $conn, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    
    return $default;
}

// Function to generate order number
function generateOrderNumber() {
    $prefix = 'ORD';
    $timestamp = date('YmdHis');
    $random = rand(100, 999);
    return $prefix . $timestamp . $random;
}

// Function to format currency
function formatCurrency($amount) {
    return number_format($amount, 0, '.', ',') . ' บาท';
}

// Function to get booth details
function getBoothDetails($boothId, $conn) {
    $stmt = $conn->prepare("SELECT * FROM booths WHERE id = ?");
    $stmt->bind_param("i", $boothId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Function to check if a booth is available
function isBoothAvailable($boothId, $conn) {
    $stmt = $conn->prepare("SELECT status FROM booths WHERE id = ?");
    $stmt->bind_param("i", $boothId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['status'] === 'available';
    }
    
    return false;
}
/**
 * ฟังก์ชันสำหรับจองบูธและบันทึกข้อมูลลงในฐานข้อมูล
 * @param int $boothId รหัสบูธที่ต้องการจอง
 * @param string $customerName ชื่อลูกค้า
 * @param string $customerEmail อีเมลลูกค้า
 * @param string $customerPhone เบอร์โทรศัพท์ลูกค้า
 * @param string $customerCompany ชื่อบริษัท/ร้านค้า (ถ้ามี)
 * @param mysqli $conn การเชื่อมต่อฐานข้อมูล
 * @param string $customerAddress ที่อยู่ลูกค้า
 * @param string $customerLineId Line ID ลูกค้า (ถ้ามี)
 * @return array ผลลัพธ์การจอง
 */

// แก้ไขในไฟล์ config.php
function reserveBooth($boothId, $customerName, $customerEmail, $customerPhone, $customerCompany, $conn, $customerAddress = '', $customerLineId = '') {
    // ตรวจสอบว่าเชื่อมต่อกับฐานข้อมูลหรือไม่
    if (!$conn || $conn->connect_error) {
        return ["success" => false, "message" => "ไม่สามารถเชื่อมต่อกับฐานข้อมูลได้"];
    }
    
    try {
        // บันทึกลอกเพื่อตรวจสอบค่าที่ส่งเข้ามา (ไม่ควรส่งการแสดงผลออกมา)
        error_log("RESERVE BOOTH FUNCTION CALLED: Booth ID: $boothId");
        
        // ตั้งค่าตัวแปรที่อาจเป็น null
        $customerAddress = empty($customerAddress) ? 'ไม่ระบุ' : $customerAddress;
        $customerLineId = empty($customerLineId) ? 'ไม่ระบุ' : $customerLineId;
        
        // ตรวจสอบบูธ
        $stmt = $conn->prepare("SELECT status, price FROM booths WHERE id = ?");
        $stmt->bind_param("i", $boothId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            return ["success" => false, "message" => "ไม่พบข้อมูลบูธ"];
        }
        
        $booth = $result->fetch_assoc();
        
        // ตรวจสอบสถานะบูธ
        if ($booth['status'] != 'available') {
            return ["success" => false, "message" => "บูธนี้ถูกจองไปแล้ว"];
        }
        
        // เริ่ม transaction
        $conn->begin_transaction();
        
        try {
            // สร้างรหัสคำสั่งซื้อ
            $orderNumber = generateOrderNumber();
            
            // ตรวจสอบคอลัมน์ในตาราง orders
            $hasAddressAndLineId = true;
            $checkColumns = $conn->query("SHOW COLUMNS FROM orders LIKE 'customer_address'");
            $hasAddressColumn = $checkColumns->num_rows > 0;
            $checkColumns = $conn->query("SHOW COLUMNS FROM orders LIKE 'customer_line_id'");
            $hasLineIdColumn = $checkColumns->num_rows > 0;
            
            // สร้าง SQL query ตามคอลัมน์ที่มี
            if ($hasAddressColumn && $hasLineIdColumn) {
                $sql = "INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, customer_company, customer_address, customer_line_id, total_amount, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unpaid')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssd", $orderNumber, $customerName, $customerEmail, $customerPhone, $customerCompany, $customerAddress, $customerLineId, $booth['price']);
            } else {
                $sql = "INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, customer_company, total_amount, payment_status) VALUES (?, ?, ?, ?, ?, ?, 'unpaid')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssd", $orderNumber, $customerName, $customerEmail, $customerPhone, $customerCompany, $booth['price']);
            }
            
            // ดำเนินการ query
            if (!$stmt->execute()) {
                throw new Exception("ไม่สามารถบันทึกข้อมูลได้: " . $stmt->error);
            }
            
            $orderId = $conn->insert_id;
            
            // บันทึกข้อมูล order item
            $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, booth_id, price) VALUES (?, ?, ?)");
            $itemStmt->bind_param("iid", $orderId, $boothId, $booth['price']);
            
            if (!$itemStmt->execute()) {
                throw new Exception("ไม่สามารถบันทึกรายการสินค้าได้: " . $itemStmt->error);
            }
            
            // อัพเดตสถานะบูธ
            $updateStmt = $conn->prepare("UPDATE booths SET status = 'pending_payment' WHERE id = ?");
            $updateStmt->bind_param("i", $boothId);
            
            if (!$updateStmt->execute()) {
                throw new Exception("ไม่สามารถอัพเดตสถานะบูธได้: " . $updateStmt->error);
            }
            
            // ยืนยัน transaction
            $conn->commit();
            
            return [
                "success" => true, 
                "message" => "จองบูธสำเร็จ", 
                "order_id" => $orderId,
                "order_number" => $orderNumber
            ];
        } catch (Exception $ex) {
            // ยกเลิก transaction หากเกิดข้อผิดพลาด
            $conn->rollback();
            throw $ex; // ส่งต่อไปยัง catch ด้านนอก
        }
    } catch (Exception $e) {
        error_log("RESERVATION ERROR: " . $e->getMessage());
        return ["success" => false, "message" => "เกิดข้อผิดพลาดในการจองบูธ: " . $e->getMessage()];
    }
}
// Function to process payment (updated to support file upload)
function processPayment($orderId, $paymentMethod, $reference, $amount, $conn) {
    // Verify order exists
    $stmt = $conn->prepare("SELECT * FROM booths WHERE id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ["success" => false, "message" => "ไม่พบข้อมูลการจอง"];
    }
    
    $booth = $result->fetch_assoc();
    
    // Update order with payment info
    $updateStmt = $conn->prepare("UPDATE booths SET payment_status = 'pending', payment_method = ?, payment_reference = ?, payment_date = NOW() WHERE id = ?");
    $updateStmt->bind_param("ssi", $paymentMethod, $reference, $orderId);
    
    if ($updateStmt->execute()) {
        return ["success" => true, "message" => "บันทึกข้อมูลการชำระเงินเรียบร้อยแล้ว"];
    } else {
        return ["success" => false, "message" => "เกิดข้อผิดพลาด: " . $conn->error];
    }
}

// Function to confirm payment
function confirmPayment($orderId, $conn) {
    // Update order status
    $updateStmt = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
    $updateStmt->bind_param("i", $orderId);
    
    if ($updateStmt->execute()) {
        // Get all booths in this order
        $boothStmt = $conn->prepare("SELECT booth_id FROM order_items WHERE order_id = ?");
        $boothStmt->bind_param("i", $orderId);
        $boothStmt->execute();
        $boothResult = $boothStmt->get_result();
        
        // Update all booths status
        while ($row = $boothResult->fetch_assoc()) {
            $updateBoothStmt = $conn->prepare("UPDATE booths SET status = 'pending_payment', payment_status = 'paid' WHERE id = ?");
            $updateBoothStmt->bind_param("i", $row['booth_id']);
            $updateBoothStmt->execute();
        }
        
        return ["success" => true, "message" => "ยืนยันการชำระเงินเรียบร้อยแล้ว"];
    } else {
        return ["success" => false, "message" => "เกิดข้อผิดพลาด: " . $conn->error];
    }
}
?>