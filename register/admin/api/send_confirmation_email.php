<?php
// Set response header
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Bangkok');

// Prevent direct access
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Define log directory and file paths
$log_dir = __DIR__;
$error_log_file = $log_dir . '/email_error.log';
$debug_level = 3; // 1 = minimal, 2 = detailed, 3 = very detailed

// Initialize logging function
function logMessage($message, $level = 1) {
    global $error_log_file, $debug_level;
    if ($level <= $debug_level) {
        @file_put_contents(
            $error_log_file, 
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", 
            FILE_APPEND
        );
    }
}

// Test log file creation
try {
    if (!is_writable($log_dir)) {
        // If directory is not writable, try to change permissions
        @chmod($log_dir, 0755);
        if (!is_writable($log_dir)) {
            echo json_encode(['success' => false, 'message' => 'Directory not writable: ' . $log_dir]);
            exit;
        }
    }
    
    // Log start of process
    logMessage("--- เริ่มการทำงาน " . date('Y-m-d H:i:s') . " ---");
    
    // Log server information for troubleshooting
    logMessage("SERVER_INFO: " . php_uname(), 2);
    logMessage("PHP_VERSION: " . phpversion(), 2);
    logMessage("SERVER_SOFTWARE: " . (isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'N/A'), 2);
    logMessage("REMOTE_ADDR: " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'N/A'), 2);
    
    // Log POST data - sanitized to avoid sensitive info in logs
    $safe_post = $_POST;
    if (isset($safe_post['password'])) $safe_post['password'] = '******';
    logMessage("POST_DATA: " . json_encode($safe_post, JSON_UNESCAPED_UNICODE), 2);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Cannot create log file: ' . $e->getMessage()]);
    exit;
}

// Get registration data from POST request
$registration_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
$payment_status = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : '';

logMessage("ข้อมูลที่ได้รับ: registration_id=$registration_id, email=$email, payment_status=$payment_status");

// Validate required fields
if ($registration_id <= 0 || empty($email) || empty($fullname)) {
    logMessage("ข้อมูลไม่ครบถ้วน");
    echo json_encode([
        'success' => false,
        'message' => 'ข้อมูลไม่ครบถ้วน กรุณาระบุ ID, อีเมล และชื่อผู้ลงทะเบียน'
    ]);
    exit;
}

try {
    // Load database configuration
    logMessage("กำลังโหลดการตั้งค่าฐานข้อมูล", 2);
    
    $db_config_paths = [
        dirname(__DIR__) . '/config/database.php',
        __DIR__ . '/config/database.php',
        dirname(dirname(__DIR__)) . '/config/database.php',
        '../../config/database.php'
    ];
    
    $db_config_loaded = false;
    
    foreach ($db_config_paths as $path) {
        logMessage("ตรวจสอบไฟล์ config ที่: $path", 3);
        
        if (file_exists($path)) {
            logMessage("พบไฟล์ config ที่: $path", 2);
            require_once $path;
            $db_config_loaded = true;
            break;
        }
    }
    
    if (!$db_config_loaded) {
        throw new Exception("ไม่พบไฟล์ config ฐานข้อมูล");
    }
    
    logMessage("กำลังเชื่อมต่อฐานข้อมูล");
    
    // Create database connection using the Database class
    $database = new Database();
    $pdo = $database->getConnection();
    
    logMessage("เชื่อมต่อฐานข้อมูลสำเร็จ");
    
    // Fetch complete registration data from database
    $stmt = $pdo->prepare("
        SELECT r.*, 
               DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as formatted_date
        FROM registrations r
        WHERE r.id = ?
    ");
    $stmt->execute([$registration_id]);
    
    if ($stmt->rowCount() === 0) {
        logMessage("ไม่พบข้อมูลการลงทะเบียน ID: $registration_id");
        echo json_encode([
            'success' => false,
            'message' => 'ไม่พบข้อมูลการลงทะเบียนที่ระบุ'
        ]);
        exit;
    }
    
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    logMessage("ดึงข้อมูลการลงทะเบียนสำเร็จ");
    
    // Set email subject
    $subject = 'ยืนยันการลงทะเบียนการสัมมนา - มหาวิทยาลัยเทคโนโลยีราชมงคลสุวรรณภูมิ';
  
    // Set email content based on payment status
    if ($payment_status == 'paid') {
        // Email content for paid registration
        $message = '<div style="font-family: \'Sarabun\', sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eaeaea; border-radius: 5px; background-color: #ffffff;">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="https://arts.rmutsb.ac.th/image/logo_art_2019.png" alt="Logo" style="max-width: 200px;">
            <h2 style="color: #1a237e; margin-top: 15px;">ยืนยันการลงทะเบียนสัมมนา</h2>
        </div>
        
        <p style="margin-bottom: 15px;">เรียน คุณ'.$fullname.'</p>
        
        <p style="margin-bottom: 15px;">มหาวิทยาลัยเทคโนโลยีราชมงคลสุวรรณภูมิ ขอขอบคุณที่ท่านได้ลงทะเบียนเข้าร่วมการสัมมนา</p>
        
        <div style="background-color: #f5f7fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <p style="font-weight: bold; color: #1a237e; margin-top: 0;">สถานะการลงทะเบียน: <span style="color: #4caf50;">ลงทะเบียนและชำระเงินเรียบร้อยแล้ว</span></p>
            <p style="margin-bottom: 0;">กรุณารอการตรวจสอบจากเจ้าหน้าที่อีกครั้ง ท่านจะได้รับอีเมลยืนยันเมื่อการลงทะเบียนได้รับการอนุมัติ</p>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3 style="color: #1a237e; font-size: 18px; border-bottom: 1px solid #eaeaea; padding-bottom: 10px;">ข้อมูลสำคัญ</h3>
            <ul style="padding-left: 20px;">
                <li style="margin-bottom: 10px;">กรุณาตรวจสอบความถูกต้องของชื่อ-นามสกุลที่ใช้ในการลงทะเบียน</li>
                <li style="margin-bottom: 10px;">กรุณาตรวจสอบความถูกต้องของที่อยู่สำหรับการออกใบเสร็จ</li>
                <li style="margin-bottom: 10px;">ท่านสามารถสแกน QR Code เพื่อจองห้องพักโรงแรมในอัตราพิเศษสำหรับผู้เข้าร่วมสัมมนา</li>
                <li style="margin-bottom: 10px;">เข้าร่วมกลุ่ม Line OA สำหรับรับข่าวสารและอัพเดทล่าสุดเกี่ยวกับการสัมมนา</li>
            </ul>
        </div>
        
        <div style="background-color: #1a237e; color: white; padding: 15px; border-radius: 5px; margin-top: 20px; text-align: center;">
            <p style="margin: 0;">หากมีข้อสงสัยประการใด กรุณาติดต่อเจ้าหน้าที่ได้ที่ <a href="mailto:arts@rmutsb.ac.th" style="color: white;">arts@rmutsb.ac.th</a></p>
        </div>
    </div>';
    } else {
        // Email content for unpaid registration
        $message = '<div style="font-family: \'Sarabun\', sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eaeaea; border-radius: 5px; background-color: #ffffff;">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="https://arts.rmutsb.ac.th/image/logo_art_2019.png" alt="Logo" style="max-width: 200px;">
            <h2 style="color: #1a237e; margin-top: 15px;">ยืนยันการลงทะเบียนสัมมนา</h2>
        </div>
        
        <p style="margin-bottom: 15px;">เรียน คุณ'.$fullname.'</p>
        
        <p style="margin-bottom: 15px;">มหาวิทยาลัยเทคโนโลยีราชมงคลสุวรรณภูมิ ขอขอบคุณที่ท่านได้ลงทะเบียนเข้าร่วมการสัมมนา</p>
        
        <div style="background-color: #f5f7fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <p style="font-weight: bold; color: #1a237e; margin-top: 0;">สถานะการลงทะเบียน: <span style="color: #ff9800;">ลงทะเบียนเรียบร้อยแล้ว (รอชำระเงิน)</span></p>
            <p style="margin-bottom: 0;">กรุณาชำระค่าลงทะเบียนและแจ้งการชำระเงินเพื่อยืนยันการเข้าร่วมสัมมนา</p>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3 style="color: #1a237e; font-size: 18px; border-bottom: 1px solid #eaeaea; padding-bottom: 10px;">ข้อมูลการชำระเงิน</h3>
            <ul style="padding-left: 20px;">
                <li style="margin-bottom: 10px;"><strong>ค่าลงทะเบียนสัมมนา:</strong> 3,900 บาท ต่อท่าน</li>
                <li style="margin-bottom: 10px;"><strong>ช่องทางการชำระเงิน:</strong>
                    <ul style="padding-left: 20px; margin-top: 5px;">
                        <li style="margin-bottom: 5px;">โอนผ่านธนาคารกรุงไทย สาขาโรจนะ</li>
                        <li style="margin-bottom: 5px;">ชื่อบัญชี: "มทร.สุวรรณภูมิ เงินรายได้"</li>
                        <li style="margin-bottom: 5px;">เลขที่บัญชี: 128-028-9392</li>
                    </ul>
                </li>
                <li style="margin-bottom: 10px;">สามารถชำระเงินหน้างานเป็นเงินสดได้</li>
            </ul>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3 style="color: #1a237e; font-size: 18px; border-bottom: 1px solid #eaeaea; padding-bottom: 10px;">คำแนะนำเพิ่มเติม</h3>
            <ul style="padding-left: 20px;">
                <li style="margin-bottom: 10px;">กรุณาตรวจสอบความถูกต้องของชื่อ-นามสกุลที่ใช้ในการลงทะเบียน</li>
                <li style="margin-bottom: 10px;">กรุณาตรวจสอบความถูกต้องของที่อยู่สำหรับการออกใบเสร็จ</li>
                <li style="margin-bottom: 10px;">ท่านสามารถสแกน QR Code เพื่อจองห้องพักโรงแรมในอัตราพิเศษสำหรับผู้เข้าร่วมสัมมนา</li>
                <li style="margin-bottom: 10px;">เข้าร่วมกลุ่ม Line OA สำหรับรับข่าวสารและอัพเดทล่าสุดเกี่ยวกับการสัมมนา</li>
            </ul>
        </div>
        
        <div style="background-color: #1a237e; color: white; padding: 15px; border-radius: 5px; margin-top: 20px; text-align: center;">
            <p style="margin: 0;">หากมีข้อสงสัยประการใด กรุณาติดต่อเจ้าหน้าที่ได้ที่ <a href="mailto:arts@rmutsb.ac.th" style="color: white;">arts@rmutsb.ac.th</a> </p>
        </div>
    </div>';
    }
    
    logMessage("สร้างเนื้อหาอีเมลเรียบร้อย");
    
    // Plain text version of the email
    $text_message = strip_tags(str_replace(['<div>', '</div>', '<p>', '</p>', '<li>', '</li>'], ["\n", '', "\n", "\n", "- ", "\n"], $message));
    
    // Mailjet API configuration
    $api_key = '829cbd1ae749929b8ae832c63f6fe511'; 
    $api_secret = '3be69cc8ed38c1e0e5456fd5904b4465';
    $url = 'https://api.mailjet.com/v3.1/send';
    
    $sender_email = 'arts@rmutsb.ac.th';
    $sender_name = 'คณะศิลปศาสตร์ มทร.สุวรรณภูมิ';
    
    // Prepare the request data for Mailjet
    $data = [
        'Messages' => [
            [
                'From' => [
                    'Email' => $sender_email,
                    'Name' => $sender_name
                ],
                'To' => [
                    [
                        'Email' => $email,
                        'Name' => $fullname
                    ]
                ],
                'Subject' => $subject,
                'HTMLPart' => $message,
                'TextPart' => $text_message,
                'Headers' => [
                    'Reply-To' => 'arts@rmutsb.ac.th'
                ]
            ]
        ]
    ];
    
    logMessage("กำลังส่งอีเมลผ่าน Mailjet API ไปยัง: $email", 2);
    
    // Initialize cURL request with improved error handling
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':' . $api_secret);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Set timeout to 30 seconds
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Verify SSL
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Connection timeout
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    // Log detailed API response
    logMessage("Mailjet API Response Code: $httpCode", 2);
    if (!empty($response)) {
        logMessage("Mailjet API Response: $response", 3);
    }
    if (!empty($error)) {
        logMessage("Mailjet API Error: $error", 2);
    }
    
    // Parse JSON response
    $responseData = json_decode($response, true);
    
    // Process the response
    if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['Messages'][0]['Status']) && $responseData['Messages'][0]['Status'] === 'success') {
        // Success
        $messageId = isset($responseData['Messages'][0]['To'][0]['MessageID']) ? $responseData['Messages'][0]['To'][0]['MessageID'] : '';
        
        logMessage("ส่งอีเมลสำเร็จ");
        
        echo json_encode([
            'success' => true,
            'message' => "ส่งอีเมลยืนยันไปยัง $email เรียบร้อยแล้ว",
            'method' => 'Mailjet API',
            'message_id' => $messageId
        ]);
    } else {
        // Failure
        $errorMessage = '';
        
        // Try to extract detailed error from Mailjet response
        if (isset($responseData['Messages'][0]['Errors'])) {
            foreach ($responseData['Messages'][0]['Errors'] as $err) {
                $errorMessage .= $err['ErrorMessage'] . ' ';
            }
        } elseif (!empty($error)) {
            $errorMessage = $error;
        } else {
            $errorMessage = 'Unknown error';
        }
        
        logMessage("ไม่สามารถส่งอีเมลได้: " . $errorMessage);
        
        echo json_encode([
            'success' => false,
            'message' => "ไม่สามารถส่งอีเมลได้ - กรุณาตรวจสอบการตั้งค่า Mailjet API",
            'error' => $errorMessage
        ]);
    }
    
} catch (PDOException $e) {
    $error_message = $e->getMessage();
    logMessage("เกิดข้อผิดพลาดกับฐานข้อมูล: " . $error_message);
    
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดกับฐานข้อมูล',
        'error' => $error_message
    ]);
} catch (Exception $e) {
    $error_message = $e->getMessage();
    logMessage("เกิดข้อผิดพลาดทั่วไป: " . $error_message);
    
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $error_message
    ]);
}

// Log end of process
logMessage("--- จบการทำงาน " . date('Y-m-d H:i:s') . " ---\n");
?>