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


function reserveBooth($boothId, $customerName, $customerEmail, $customerPhone, $customerCompany, $conn, $customerAddress = '', $customerLineId = '') {
    try {
        // บันทึกลอกเพื่อตรวจสอบค่าที่ส่งเข้ามา
        writeLog("RESERVE BOOTH FUNCTION CALLED:");
        writeLog("Booth ID: $boothId");
        writeLog("Customer Name: $customerName");
        writeLog("Customer Email: $customerEmail");
        writeLog("Customer Phone: $customerPhone");
        writeLog("Customer Company: $customerCompany");
        writeLog("Customer Address: $customerAddress"); // ตรวจสอบค่า address ที่ส่งเข้ามา
        writeLog("Customer Line ID: $customerLineId");
        
        // ตรวจสอบว่าค่า address เป็น null หรือว่าง
        if($customerAddress === null || $customerAddress === '') {
            $customerAddress = 'ไม่ระบุ'; // กำหนดค่าเริ่มต้นถ้าไม่มีข้อมูล
        }
        
        // ตรวจสอบว่าค่า line_id เป็น null หรือว่าง
        if($customerLineId === null || $customerLineId === '') {
            $customerLineId = 'ไม่ระบุ'; // กำหนดค่าเริ่มต้นถ้าไม่มีข้อมูล
        }
        
        $checkBooth = $conn->prepare("SELECT status, price FROM booths WHERE id = ?");
        $checkBooth->bind_param("i", $boothId);
        $checkBooth->execute();
        $result = $checkBooth->get_result();
        
        if ($result->num_rows == 0) {
            writeLog("ERROR: Booth not found");
            return ["success" => false, "message" => "ไม่พบข้อมูลบูธ"];
        }
        
        $booth = $result->fetch_assoc();
        writeLog("Booth data: " . json_encode($booth));
        
        if ($booth['status'] != 'available') {
            writeLog("ERROR: Booth not available");
            return ["success" => false, "message" => "บูธนี้ถูกจองไปแล้ว"];
        }
        
        // เริ่ม transaction
        $conn->begin_transaction();
        writeLog("Started transaction");
        
        // สร้างรหัสคำสั่งซื้อ (order number)
        $orderNumber = generateOrderNumber();
        writeLog("Generated order number: $orderNumber");
        
        // ลองใช้ prepared statement แบบตรงๆ โดยไม่ใช้ parameter binding
        try {
            // เช็คว่ามีคอลัมน์ customer_address และ customer_line_id หรือไม่
            $checkColumns = $conn->query("SHOW COLUMNS FROM orders LIKE 'customer_address'");
            $hasAddressColumn = $checkColumns->num_rows > 0;
            
            $checkColumns = $conn->query("SHOW COLUMNS FROM orders LIKE 'customer_line_id'");
            $hasLineIdColumn = $checkColumns->num_rows > 0;
            
            writeLog("Has address column: " . ($hasAddressColumn ? "Yes" : "No"));
            writeLog("Has line ID column: " . ($hasLineIdColumn ? "Yes" : "No"));
            
            // สร้าง SQL query ตามคอลัมน์ที่มี
            if ($hasAddressColumn && $hasLineIdColumn) {
                $sql = "INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, customer_company, customer_address, customer_line_id, total_amount, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unpaid')";
                writeLog("Using full SQL with address and line ID: $sql");
                
                $insertOrder = $conn->prepare($sql);
                $insertOrder->bind_param("sssssssd", $orderNumber, $customerName, $customerEmail, $customerPhone, $customerCompany, $customerAddress, $customerLineId, $booth['price']);
            } else {
                // ถ้าไม่มีคอลัมน์ address หรือ line_id
                $sql = "INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, customer_company, total_amount, payment_status) VALUES (?, ?, ?, ?, ?, ?, 'unpaid')";
                writeLog("Using simplified SQL without address and line ID: $sql");
                
                $insertOrder = $conn->prepare($sql);
                $insertOrder->bind_param("sssssd", $orderNumber, $customerName, $customerEmail, $customerPhone, $customerCompany, $booth['price']);
            }
            
            if (!$insertOrder) {
                writeLog("ERROR preparing SQL: " . $conn->error);
                throw new Exception("SQL Prepare Error: " . $conn->error);
            }
            
            writeLog("Binding parameters...");
            writeLog("Executing order insert...");
            
            $executeResult = $insertOrder->execute();
            if (!$executeResult) {
                writeLog("ERROR executing SQL: " . $insertOrder->error);
                throw new Exception("Execute Error: " . $insertOrder->error);
            }
            
            $orderId = $conn->insert_id;
            writeLog("Order inserted with ID: $orderId");
            
            // บันทึกข้อมูล order item
            $insertItem = $conn->prepare("INSERT INTO order_items (order_id, booth_id, price) VALUES (?, ?, ?)");
            $insertItem->bind_param("iid", $orderId, $boothId, $booth['price']);
            $insertItem->execute();
            writeLog("Order item inserted");
            
            // อัพเดตสถานะบูธเป็น reserved
            $updateBooth = $conn->prepare("UPDATE booths SET status = 'pending_payment' WHERE id = ?");
            $updateBooth->bind_param("i", $boothId);
            $updateBooth->execute();
            writeLog("Booth status updated");
            
            // ยืนยัน transaction
            $conn->commit();
            writeLog("Transaction committed successfully");
            
            return [
                "success" => true, 
                "message" => "จองบูธสำเร็จ", 
                "order_id" => $orderId,
                "order_number" => $orderNumber
            ];
        } catch (Exception $e) {
            writeLog("INNER QUERY ERROR: " . $e->getMessage());
            throw $e; // ส่งต่อไปยัง catch ด้านนอก
        }
    } catch (Exception $e) {
        // ยกเลิก transaction หากเกิดข้อผิดพลาด
        if ($conn && $conn->ping()) {
            $conn->rollback();
            writeLog("Transaction rolled back due to error");
        }
        
        // บันทึก error log
        writeLog("RESERVATION ERROR: " . $e->getMessage());
        writeLog("Stack trace: " . $e->getTraceAsString());
        
        // ทดลองใช้ prepared statement แบบตรงๆ
        try {
            writeLog("Attempting direct query without prepared statements");
            
            // สร้างรหัสคำสั่งซื้อใหม่
            $orderNumber = generateOrderNumber();
            $safePhone = $conn->real_escape_string($customerPhone);
            $safeName = $conn->real_escape_string($customerName);
            $safeEmail = $conn->real_escape_string($customerEmail);
            $safeCompany = $conn->real_escape_string($customerCompany);
            $price = $booth['price'];
            
            $directQuery = "INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, customer_company, total_amount, payment_status) 
                            VALUES ('$orderNumber', '$safeName', '$safeEmail', '$safePhone', '$safeCompany', $price, 'unpaid')";
            
            writeLog("Direct query: $directQuery");
            $directResult = $conn->query($directQuery);
            
            if ($directResult) {
                $orderId = $conn->insert_id;
                writeLog("Direct query successful. Order ID: $orderId");
                
                // บันทึกข้อมูล order item
                $boothIdSafe = intval($boothId);
                $directItemQuery = "INSERT INTO order_items (order_id, booth_id, price) 
                                    VALUES ($orderId, $boothIdSafe, $price)";
                $conn->query($directItemQuery);
                
                // อัพเดตสถานะบูธ
                $directUpdateQuery = "UPDATE booths SET status = 'pending_payment' WHERE id = $boothIdSafe";
                $conn->query($directUpdateQuery);
                
                return [
                    "success" => true, 
                    "message" => "จองบูธสำเร็จ (ใช้วิธีสำรอง)", 
                    "order_id" => $orderId,
                    "order_number" => $orderNumber
                ];
            } else {
                writeLog("Direct query failed: " . $conn->error);
            }
        } catch (Exception $e2) {
            writeLog("DIRECT QUERY ERROR: " . $e2->getMessage());
        }
        
        return ["success" => false, "message" => "เกิดข้อผิดพลาดในการจองบูธ: " . $e->getMessage()];
    }
}
?>
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