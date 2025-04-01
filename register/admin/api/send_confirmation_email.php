<?php
// Set response header
header('Content-Type: application/json; charset=utf-8');

// Prevent direct access
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// เริ่มต้นบันทึก log
$error_log_file = __DIR__ . '/email_error.log';
file_put_contents($error_log_file, "--- เริ่มการทำงาน " . date('Y-m-d H:i:s') . " ---\n", FILE_APPEND);

// Required for email functionality
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Include PHPMailer via Composer's autoloader
require_once 'vendor/autoload.php';
require_once '../../config/database.php';

// Get registration data from POST request
$registration_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
$payment_status = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : '';

$log_msg = "ข้อมูลที่ได้รับ: registration_id=$registration_id, email=$email, payment_status=$payment_status\n";
file_put_contents($error_log_file, $log_msg, FILE_APPEND);

// Validate required fields
if ($registration_id <= 0 || empty($email) || empty($fullname)) {
    $log_msg = "ข้อมูลไม่ครบถ้วน\n";
    file_put_contents($error_log_file, $log_msg, FILE_APPEND);
    
    echo json_encode([
        'success' => false,
        'message' => 'ข้อมูลไม่ครบถ้วน กรุณาระบุ ID, อีเมล และชื่อผู้ลงทะเบียน'
    ]);
    exit;
}

try {
    $log_msg = "กำลังเชื่อมต่อฐานข้อมูล\n";
    file_put_contents($error_log_file, $log_msg, FILE_APPEND);
    
    // Create database connection using the Database class
    $database = new Database();
    $pdo = $database->getConnection();
    
    $log_msg = "เชื่อมต่อฐานข้อมูลสำเร็จ\n";
    file_put_contents($error_log_file, $log_msg, FILE_APPEND);
    
    // Fetch complete registration data from database
    $stmt = $pdo->prepare("
        SELECT r.*, 
               DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as formatted_date
        FROM registrations r
        WHERE r.id = ?
    ");
    $stmt->execute([$registration_id]);
    
    if ($stmt->rowCount() === 0) {
        $log_msg = "ไม่พบข้อมูลการลงทะเบียน ID: $registration_id\n";
        file_put_contents($error_log_file, $log_msg, FILE_APPEND);
        
        echo json_encode([
            'success' => false,
            'message' => 'ไม่พบข้อมูลการลงทะเบียนที่ระบุ'
        ]);
        exit;
    }
    
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $log_msg = "ดึงข้อมูลการลงทะเบียนสำเร็จ\n";
    file_put_contents($error_log_file, $log_msg, FILE_APPEND);
    
    // ตรวจสอบว่ามีตาราง email_logs หรือไม่
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'email_logs'");
        if ($stmt->rowCount() == 0) {
            $log_msg = "ไม่พบตาราง email_logs กำลังสร้างตาราง...\n";
            file_put_contents($error_log_file, $log_msg, FILE_APPEND);
            
            // สร้างตาราง email_logs
            $sql = "CREATE TABLE `email_logs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `registration_id` int(11) NOT NULL,
                `email` varchar(255) NOT NULL,
                `subject` varchar(255) NOT NULL,
                `sent_at` datetime NOT NULL,
                `status` enum('success','failed') NOT NULL DEFAULT 'success',
                `error_message` text,
                PRIMARY KEY (`id`),
                KEY `registration_id` (`registration_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            $pdo->exec($sql);
            $log_msg = "สร้างตาราง email_logs สำเร็จ\n";
            file_put_contents($error_log_file, $log_msg, FILE_APPEND);
        } else {
            $log_msg = "พบตาราง email_logs แล้ว\n";
            file_put_contents($error_log_file, $log_msg, FILE_APPEND);
        }
    } catch (PDOException $e) {
        $log_msg = "เกิดข้อผิดพลาดในการตรวจสอบหรือสร้างตาราง email_logs: " . $e->getMessage() . "\n";
        file_put_contents($error_log_file, $log_msg, FILE_APPEND);
    }
    
    // กำหนดหัวข้ออีเมล
    $subject = 'ยืนยันการลงทะเบียนการสัมมนา - มหาวิทยาลัยเทคโนโลยีราชมงคลสุวรรณภูมิ';
    
    // บันทึกว่าได้รับคำขอส่งอีเมล (แม้จะยังไม่ได้ส่ง)
    try {
        $stmt = $pdo->prepare("INSERT INTO email_logs (registration_id, email, subject, sent_at, status, error_message) 
                              VALUES (?, ?, ?, NOW(), 'success', 'เริ่มกระบวนการส่งอีเมล')");
        $stmt->execute([$registration_id, $email, $subject]);
        $email_log_id = $pdo->lastInsertId();
        
        $log_msg = "บันทึกข้อมูลการส่งอีเมลในฐานข้อมูล ID: $email_log_id\n";
        file_put_contents($error_log_file, $log_msg, FILE_APPEND);
    } catch (PDOException $e) {
        $log_msg = "ไม่สามารถบันทึกข้อมูลการส่งอีเมลในฐานข้อมูล: " . $e->getMessage() . "\n";
        file_put_contents($error_log_file, $log_msg, FILE_APPEND);
    }
    
    // Initialize PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'arts@rmutsb.ac.th';
        $mail->Password = 'artsrus6';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // For debugging - uncomment if needed
        //$mail->SMTPDebug = SMTP::DEBUG_SERVER;
        
        // Recipients
        $mail->setFrom('arts@rmutsb.ac.th', 'คณะศิลปศาสตร์ มหาวิทยาลัยเทคโนโลยีราชมงคลสุวรรณภูมิ');
        $mail->addAddress($email, $fullname);
        $mail->addReplyTo('arts@rmutsb.ac.th', 'คณะศิลปศาสตร์');
        
        // Set email subject
        $mail->Subject = $subject;
        
        // กำหนดเนื้อหาอีเมล ตามรูปแบบที่ต้องการ
        if ($payment_status == 'paid') {
            // กรณีชำระเงินแล้ว
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
            // กรณียังไม่ชำระเงิน
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
        
        $log_msg = "สร้างเนื้อหาอีเมลเรียบร้อย\n";
        file_put_contents($error_log_file, $log_msg, FILE_APPEND);
        
        // Set email format to HTML and content
        $mail->isHTML(true);
        $mail->Body = $message;
        $mail->AltBody = strip_tags(str_replace(['<div>', '</div>', '<p>', '</p>', '<li>', '</li>'], ["\n", '', "\n", "\n", "- ", "\n"], $message));
        
        // Send email
        $mail->send();
        
        $log_msg = "ส่งอีเมลสำเร็จ\n";
        file_put_contents($error_log_file, $log_msg, FILE_APPEND);
        
        // บันทึกประวัติว่าส่งสำเร็จ
        if (isset($email_log_id)) {
            $stmt = $pdo->prepare("UPDATE email_logs SET status = 'success', error_message = NULL WHERE id = ?");
            $stmt->execute([$email_log_id]);
        }
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'ส่งอีเมลยืนยันไปยัง '.$email.' เรียบร้อยแล้ว'
        ]);
        
    } catch (Exception $e) {
        // Log error
        $error_message = $mail->ErrorInfo;
        $log_msg = "ส่งอีเมลไม่สำเร็จ: " . $error_message . "\n";
        file_put_contents($error_log_file, $log_msg, FILE_APPEND);
        
        // บันทึกประวัติว่าส่งไม่สำเร็จ
        if (isset($email_log_id)) {
            $stmt = $pdo->prepare("UPDATE email_logs SET status = 'failed', error_message = ? WHERE id = ?");
            $stmt->execute([$error_message, $email_log_id]);
        }
        
        // Return error response
        echo json_encode([
            'success' => false,
            'message' => 'ไม่สามารถส่งอีเมลได้ กรุณาลองใหม่อีกครั้ง',
            'error' => $error_message
        ]);
    }
    
} catch (PDOException $e) {
    // Log database error
    $log_msg = "เกิดข้อผิดพลาดกับฐานข้อมูล: " . $e->getMessage() . "\n";
    file_put_contents($error_log_file, $log_msg, FILE_APPEND);
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดกับฐานข้อมูล',
        'error' => $e->getMessage()
    ]);
}

// บันทึกการทำงานเสร็จสิ้น
file_put_contents($error_log_file, "--- จบการทำงาน " . date('Y-m-d H:i:s') . " ---\n\n", FILE_APPEND);
?>