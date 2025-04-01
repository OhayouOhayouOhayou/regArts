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

// Test network connectivity to mail servers
try {
    $smtp_servers = [
        ['host' => 'smtp.gmail.com', 'port' => 587],
        ['host' => 'smtp.gmail.com', 'port' => 465],
        ['host' => 'mail.rmutsb.ac.th', 'port' => 25]
    ];
    
    logMessage("ทดสอบการเชื่อมต่อกับเซิร์ฟเวอร์อีเมล:", 2);
    
    foreach ($smtp_servers as $server) {
        logMessage("ทดสอบเชื่อมต่อกับ {$server['host']}:{$server['port']}...", 2);
        $socket_connection = @fsockopen($server['host'], $server['port'], $errno, $errstr, 5);
        
        if ($socket_connection) {
            logMessage("สามารถเชื่อมต่อกับ {$server['host']}:{$server['port']} ได้", 2);
            fclose($socket_connection);
        } else {
            logMessage("ไม่สามารถเชื่อมต่อกับ {$server['host']}:{$server['port']} ได้: $errstr ($errno)", 2);
        }
    }
} catch (Exception $e) {
    logMessage("เกิดข้อผิดพลาดในการทดสอบเชื่อมต่อ: " . $e->getMessage(), 2);
}

// Improved autoloader discovery with detailed logging
$autoloader_paths = [
    __DIR__ . '/vendor/autoload.php',                // Current directory
    dirname(__DIR__) . '/vendor/autoload.php',       // Parent directory
    dirname(dirname(__DIR__)) . '/vendor/autoload.php', // Two levels up
    // Add more potential paths if needed
];

logMessage("กำลังค้นหา Composer Autoloader...", 2);
$autoloader_loaded = false;

foreach ($autoloader_paths as $path) {
    logMessage("ตรวจสอบ path: $path", 3);
    
    if (file_exists($path)) {
        logMessage("พบ Composer autoloader ที่: $path", 2);
        require_once $path;
        $autoloader_loaded = true;
        break;
    }
}

// If autoloader not found, try PHPMailer direct inclusion with improved path discovery
if (!$autoloader_loaded) {
    logMessage("ไม่พบ Composer autoloader กำลังค้นหา PHPMailer โดยตรง...", 2);
    
    $phpmailer_paths = [
        __DIR__ . '/PHPMailer/src/',
        dirname(__DIR__) . '/PHPMailer/src/',
        dirname(dirname(__DIR__)) . '/PHPMailer/src/',
        __DIR__ . '/libraries/PHPMailer/src/',      // Alternative directory structure
        dirname(__DIR__) . '/libraries/PHPMailer/src/',
        __DIR__ . '/includes/PHPMailer/src/',       // Another common location
        // Add more potential paths if needed
    ];
    
    $phpmailer_loaded = false;
    
    foreach ($phpmailer_paths as $path) {
        logMessage("ตรวจสอบ PHPMailer ที่: $path", 3);
        
        if (file_exists($path . 'PHPMailer.php')) {
            logMessage("พบ PHPMailer ที่: $path", 2);
            
            // Ensure all required PHPMailer files are included
            $required_files = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];
            $all_files_exist = true;
            
            foreach ($required_files as $file) {
                if (!file_exists($path . $file)) {
                    logMessage("ไฟล์ $file ไม่พบที่ $path", 2);
                    $all_files_exist = false;
                    break;
                }
            }
            
            if ($all_files_exist) {
                require_once $path . 'Exception.php';
                require_once $path . 'PHPMailer.php';
                require_once $path . 'SMTP.php';
                $phpmailer_loaded = true;
                break;
            }
        }
    }
    
    if (!$phpmailer_loaded) {
        logMessage("ไม่พบ PHPMailer ไม่สามารถส่งอีเมลได้");
        echo json_encode([
            'success' => false, 
            'message' => 'ไม่พบ PHPMailer ไม่สามารถส่งอีเมลได้ กรุณาติดต่อผู้ดูแลระบบ'
        ]);
        exit;
    }
}

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

try {
    // Improved database config loading with error handling
    logMessage("กำลังโหลดการตั้งค่าฐานข้อมูล", 2);
    
    $db_config_paths = [
        dirname(__DIR__) . '/config/database.php',
        __DIR__ . '/config/database.php',
        dirname(dirname(__DIR__)) . '/config/database.php',
        // Add more potential paths if needed
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
    
    // Check if email_logs table exists and create if needed
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'email_logs'");
        if ($stmt->rowCount() == 0) {
            logMessage("ไม่พบตาราง email_logs กำลังสร้างตาราง...");
            
            // Create email_logs table
            $sql = "CREATE TABLE `email_logs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `registration_id` int(11) NOT NULL,
                `email` varchar(255) NOT NULL,
                `subject` varchar(255) NOT NULL,
                `sent_at` datetime NOT NULL,
                `status` enum('success','failed') NOT NULL DEFAULT 'success',
                `error_message` text,
                `smtp_server` varchar(255),
                `smtp_port` int(11),
                PRIMARY KEY (`id`),
                KEY `registration_id` (`registration_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            $pdo->exec($sql);
            logMessage("สร้างตาราง email_logs สำเร็จ");
        } else {
            logMessage("พบตาราง email_logs แล้ว");
            
            // Check if we need to add the smtp_server and smtp_port columns
            try {
                $columnsResult = $pdo->query("SHOW COLUMNS FROM email_logs LIKE 'smtp_server'");
                if ($columnsResult->rowCount() == 0) {
                    $pdo->exec("ALTER TABLE email_logs ADD COLUMN smtp_server varchar(255) AFTER error_message");
                    $pdo->exec("ALTER TABLE email_logs ADD COLUMN smtp_port int(11) AFTER smtp_server");
                    logMessage("เพิ่มคอลัมน์ smtp_server และ smtp_port ในตาราง email_logs", 2);
                }
            } catch (PDOException $e) {
                logMessage("ไม่สามารถตรวจสอบหรือเพิ่มคอลัมน์ในตาราง email_logs: " . $e->getMessage(), 2);
            }
        }
    } catch (PDOException $e) {
        logMessage("เกิดข้อผิดพลาดในการตรวจสอบหรือสร้างตาราง email_logs: " . $e->getMessage());
    }
    
    // Set email subject
    $subject = 'ยืนยันการลงทะเบียนการสัมมนา - มหาวิทยาลัยเทคโนโลยีราชมงคลสุวรรณภูมิ';
    
    // Record email request in database
    try {
        $stmt = $pdo->prepare("INSERT INTO email_logs (registration_id, email, subject, sent_at, status, error_message) 
                               VALUES (?, ?, ?, NOW(), 'success', 'เริ่มกระบวนการส่งอีเมล')");
        $stmt->execute([$registration_id, $email, $subject]);
        $email_log_id = $pdo->lastInsertId();
        
        logMessage("บันทึกข้อมูลการส่งอีเมลในฐานข้อมูล ID: $email_log_id");
    } catch (PDOException $e) {
        logMessage("ไม่สามารถบันทึกข้อมูลการส่งอีเมลในฐานข้อมูล: " . $e->getMessage());
    }
    
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
    
    // Define multiple email configurations
    $email_configs = [
        // Option 1: University's SMTP server (if available)
        [
            'description' => 'เซิร์ฟเวอร์อีเมลของมหาวิทยาลัย',
            'host' => 'mail.rmutsb.ac.th',
            'username' => 'arts@rmutsb.ac.th',
            'password' => 'artsrus6',
            'secure' => '',
            'port' => 25,
            'auth' => true
        ],
        // Option 2: Gmail with STARTTLS
        [
            'description' => 'Gmail พอร์ต 587 (STARTTLS)',
            'host' => 'smtp.gmail.com',
            'username' => 'arts@rmutsb.ac.th',
            'password' => 'artsrus6',
            'secure' => 'tls',
            'port' => 587,
            'auth' => true
        ],
        // Option 3: Gmail with SSL
        [
            'description' => 'Gmail พอร์ต 465 (SSL)',
            'host' => 'smtp.gmail.com',
            'username' => 'arts@rmutsb.ac.th',
            'password' => 'artsrus6',
            'secure' => 'ssl',
            'port' => 465,
            'auth' => true
        ],
        // Option 4: PHP mail() function
        [
            'description' => 'ฟังก์ชัน mail() ของ PHP',
            'host' => '',
            'username' => '',
            'password' => '',
            'secure' => '',
            'port' => 0,
            'auth' => false,
            'use_mail_function' => true
        ]
    ];
    
    // Try each email configuration until one succeeds
    $success = false;
    $last_error = '';
    
    foreach ($email_configs as $index => $config) {
        logMessage("กำลังลองส่งอีเมลด้วยวิธีที่ " . ($index + 1) . ": " . $config['description'], 2);
        
        try {
            // Initialize a new PHPMailer instance for each attempt
            $mail = new PHPMailer(true);
            
            // Basic setup
            $mail->CharSet = 'UTF-8';
            
            // Set email format to HTML
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags(str_replace(['<div>', '</div>', '<p>', '</p>', '<li>', '</li>'], ["\n", '', "\n", "\n", "- ", "\n"], $message));
            
            // Check if using the mail() function or SMTP
            if (isset($config['use_mail_function']) && $config['use_mail_function']) {
                logMessage("ใช้ฟังก์ชัน mail() ของ PHP", 2);
                $mail->isMail();
                
                // Set sender and recipient
                $mail->setFrom('arts@rmutsb.ac.th', 'คณะศิลปศาสตร์ มหาวิทยาลัยเทคโนโลยีราชมงคลสุวรรณภูมิ');
                $mail->addAddress($email, $fullname);
                $mail->addReplyTo('arts@rmutsb.ac.th', 'คณะศิลปศาสตร์');
            } else {
                logMessage("ใช้ SMTP: {$config['host']}:{$config['port']}", 2);
                $mail->isSMTP();
                
                // Server settings
                $mail->Host = $config['host'];
                $mail->SMTPAuth = $config['auth'];
                
                if ($config['auth']) {
                    $mail->Username = $config['username'];
                    $mail->Password = $config['password'];
                }
                
                if (!empty($config['secure'])) {
                    if ($config['secure'] === 'tls') {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    } elseif ($config['secure'] === 'ssl') {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    }
                }
                
                $mail->Port = $config['port'];
                
                // Set additional SMTP options for more reliable connections
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                // Set higher timeout values
                $mail->Timeout = 30;
                
                // Enable verbose debug output
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                
                // Redirect SMTP debug output to log file
                $mail->Debugoutput = function($str, $level) use ($index) {
                    logMessage("SMTP CONFIG[$index] DEBUG[$level]: $str", 3);
                };
                
                // Set sender and recipient
                $mail->setFrom($config['username'], 'คณะศิลปศาสตร์ มหาวิทยาลัยเทคโนโลยีราชมงคลสุวรรณภูมิ');
                $mail->addAddress($email, $fullname);
                $mail->addReplyTo($config['username'], 'คณะศิลปศาสตร์');
            }
            
            // Try to send the email
            logMessage("กำลังพยายามส่งอีเมลด้วย " . $config['description'], 2);
            if ($mail->send()) {
                logMessage("ส่งอีเมลสำเร็จด้วย " . $config['description'], 1);
                $success = true;
                
                // Update email log
                if (isset($email_log_id)) {
                    $stmt = $pdo->prepare("UPDATE email_logs SET status = 'success', error_message = ?, smtp_server = ?, smtp_port = ? WHERE id = ?");
                    $stmt->execute(["ส่งอีเมลสำเร็จด้วย " . $config['description'], $config['host'], $config['port'], $email_log_id]);
                }
                
                // Return success response
                echo json_encode([
                    'success' => true,
                    'message' => "ส่งอีเมลยืนยันไปยัง $email เรียบร้อยแล้ว",
                    'method' => $config['description']
                ]);
                
                // Exit the loop and end the script
                break;
            }
        } catch (Exception $e) {
            $error_message = $mail->ErrorInfo ?: $e->getMessage();
            logMessage("ไม่สามารถส่งอีเมลด้วย " . $config['description'] . ": " . $error_message, 2);
            $last_error = $error_message;
            
            // Continue to next configuration
            continue;
        }
    }
    
    // If all email configurations failed
    if (!$success) {
        logMessage("ไม่สามารถส่งอีเมลด้วยทุกวิธี");
        
        // Update email log with failure status
        if (isset($email_log_id)) {
            $stmt = $pdo->prepare("UPDATE email_logs SET status = 'failed', error_message = ? WHERE id = ?");
            $stmt->execute(["ไม่สามารถส่งอีเมลด้วยทุกวิธี: " . $last_error, $email_log_id]);
        }
        
        // Get helpful error message based on error type
        $error_suggestion = "";
        if (strpos($last_error, 'authenticate') !== false || strpos($last_error, 'Authentication') !== false) {
            $error_suggestion = "อาจเกิดจากรหัสผ่านไม่ถูกต้องหรือการตั้งค่าความปลอดภัยของอีเมล";
        } elseif (strpos($last_error, 'Connection') !== false) {
            $error_suggestion = "อาจเกิดจากปัญหาการเชื่อมต่อเครือข่ายหรือการปิดกั้นพอร์ต";
        } elseif (strpos($last_error, 'SMTP connect() failed') !== false) {
            $error_suggestion = "ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ SMTP ได้";
        }
        
        // Return failure response
        echo json_encode([
            'success' => false,
            'message' => "ไม่สามารถส่งอีเมลได้" . ($error_suggestion ? " - " . $error_suggestion : ""),
            'error' => $last_error
        ]);
        
        // Append additional troubleshooting suggestions to log
        logMessage("คำแนะนำในการแก้ไขปัญหา:");
        logMessage("1. ตรวจสอบรหัสผ่านอีเมลว่าถูกต้อง");
        logMessage("2. ตรวจสอบว่าเปิดใช้งาน 'Less secure app access' หรือสร้าง App Password");
        logMessage("3. ตรวจสอบการเชื่อมต่ออินเทอร์เน็ตและการตั้งค่าไฟร์วอลล์");
        logMessage("4. ทดลองใช้อีเมลสำรอง หรือบริการส่งอีเมลภายนอก เช่น SendGrid หรือ Mailgun");
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