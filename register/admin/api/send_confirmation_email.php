<?php
// Set response header and timezone
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Bangkok');
use MailerSend\MailerSend;
use MailerSend\Helpers\Builder\Recipient;
use MailerSend\Helpers\Builder\EmailParams;
// Initialize logging
$log_dir = __DIR__;
$error_log_file = $log_dir . '/email_error.log';
$debug_level = 3;

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

// Prevent direct access
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Begin processing
try {
    logMessage("--- เริ่มการทำงาน " . date('Y-m-d H:i:s') . " ---");
    
    // Get and validate input data
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
    
    // Database connection and data retrieval
    require_once '/var/www/html/config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
    logMessage("เชื่อมต่อฐานข้อมูลสำเร็จ");
    
    $stmt = $pdo->prepare("
        SELECT r.*, DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as formatted_date
        FROM registrations r WHERE r.id = ?
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
    
    // Email content preparation
    $subject = 'ยืนยันการลงทะเบียนการสัมมนา - มหาวิทยาลัยเทคโนโลยีราชมงคลสุวรรณภูมิ';
    $html_content = ($payment_status == 'paid') ? generatePaidEmailContent($fullname) : generateUnpaidEmailContent($fullname);
    $text_content = strip_tags(str_replace(['<div>', '</div>', '<p>', '</p>', '<li>', '</li>'], ["\n", '', "\n", "\n", "- ", "\n"], $html_content));
    
    logMessage("สร้างเนื้อหาอีเมลเรียบร้อย");
    
    // Install Composer and the MailerSend SDK if not already installed
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        logMessage("MailerSend SDK not found, attempting installation", 2);
        exec('composer require mailersend/mailersend', $output, $return_var);
        if ($return_var !== 0) {
            throw new Exception("Failed to install MailerSend SDK: " . implode("\n", $output));
        }
    }
    
    require_once __DIR__ . '/vendor/autoload.php';
    
    // MailerSend implementation
    logMessage("กำลังส่งอีเมลผ่าน MailerSend SDK ไปยัง: $email", 2);
    

    
    $mailersend = new MailerSend(['api_key' => 'YOUR_API_KEY_HERE']);
    
    $recipients = [
        new Recipient($email, $fullname),
    ];
    
    // Use a verified domain from your MailerSend account
    // Change this to rmutsb.ac.th after verification is complete
    $sender_email = 'verified_email@your-verified-domain.com';
    $sender_name = 'คณะศิลปศาสตร์ มทร.สุวรรณภูมิ';
    
    $emailParams = (new EmailParams())
        ->setFrom($sender_email)
        ->setFromName($sender_name)
        ->setRecipients($recipients)
        ->setSubject($subject)
        ->setHtml($html_content)
        ->setText($text_content);
    
    try {
        $response = $mailersend->email->send($emailParams);
        logMessage("MailerSend API Response: " . json_encode($response), 3);
        logMessage("ส่งอีเมลสำเร็จ");
        
        echo json_encode([
            'success' => true,
            'message' => "ส่งอีเมลยืนยันไปยัง $email เรียบร้อยแล้ว",
            'method' => 'MailerSend SDK'
        ]);
    } catch (\Exception $e) {
        logMessage("ไม่สามารถส่งอีเมลได้: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => "ไม่สามารถส่งอีเมลได้ - กรุณาตรวจสอบการตั้งค่า MailerSend API",
            'error' => $e->getMessage()
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

logMessage("--- จบการทำงาน " . date('Y-m-d H:i:s') . " ---\n");

// Helper function to generate email content for paid registrations
function generatePaidEmailContent($fullname) {
    return '<div style="font-family: \'Sarabun\', sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eaeaea; border-radius: 5px; background-color: #ffffff;">
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
}

// Helper function to generate email content for unpaid registrations
function generateUnpaidEmailContent($fullname) {
    return '<div style="font-family: \'Sarabun\', sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eaeaea; border-radius: 5px; background-color: #ffffff;">
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
?>