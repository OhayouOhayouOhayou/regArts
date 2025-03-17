<?php
// Include these at the top, outside of any conditional blocks
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Then check for PHPMailer
$phpmailer_installed = false;
if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require __DIR__ . '/../../../vendor/autoload.php';
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $phpmailer_installed = true;
    }
}

// สร้างไฟล์สำหรับบันทึกข้อผิดพลาด
$error_log_file = __DIR__ . '/email_error.log';
file_put_contents($error_log_file, "--- เริ่มการทำงาน " . date('Y-m-d H:i:s') . " ---\n", FILE_APPEND);

try {
    require_once '../../config/database.php';

    // บันทึกข้อมูลที่ได้รับมา
    $log_msg = "กำลังเริ่มกระบวนการส่งอีเมล\n";
    file_put_contents($error_log_file, $log_msg, FILE_APPEND);

    // รับข้อมูลจาก POST request
    $registration_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $fullname = isset($_POST['fullname']) ? $_POST['fullname'] : '';
    $payment_status = isset($_POST['payment_status']) ? $_POST['payment_status'] : 'not_paid';

    $log_msg = "ข้อมูลที่ได้รับ: registration_id=$registration_id, email=$email, payment_status=$payment_status\n";
    file_put_contents($error_log_file, $log_msg, FILE_APPEND);

    // ตรวจสอบข้อมูล
    if ($registration_id <= 0 || empty($email)) {
        $log_msg = "ข้อมูลไม่ครบถ้วน\n";
        file_put_contents($error_log_file, $log_msg, FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }

    // เตรียมข้อมูลอีเมล
    $log_msg = "กำลังเชื่อมต่อฐานข้อมูล\n";
    file_put_contents($error_log_file, $log_msg, FILE_APPEND);
    
    $database = new Database();
    $pdo = $database->getConnection();

    $log_msg = "เชื่อมต่อฐานข้อมูลสำเร็จ\n";
    file_put_contents($error_log_file, $log_msg, FILE_APPEND);

    // ดึงข้อมูลเพิ่มเติมจากฐานข้อมูล
    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ?");
    $stmt->execute([$registration_id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        $log_msg = "ไม่พบข้อมูลการลงทะเบียน ID: $registration_id\n";
        file_put_contents($error_log_file, $log_msg, FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลการลงทะเบียน']);
        exit;
    }

    $log_msg = "ดึงข้อมูลการลงทะเบียนสำเร็จ\n";
    file_put_contents($error_log_file, $log_msg, FILE_APPEND);

    // กำหนดหัวข้ออีเมล
    $subject = 'ยืนยันการลงทะเบียนการสัมมนา - มหาวิทยาลัยเทคโนโลยีราชมงคลสุวรรณภูมิ';

    // กำหนดเนื้อหาอีเมล
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
            <p style="margin: 0;">หากมีข้อสงสัยประการใด กรุณาติดต่อเจ้าหน้าที่ได้ที่ <a href="mailto:arts@rmutsb.ac.th" style="color: white;">arts@rmutsb.ac.th</a> หรือโทร. 034-XXX-XXX</p>
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
            <p style="margin: 0;">หากมีข้อสงสัยประการใด กรุณาติดต่อเจ้าหน้าที่ได้ที่ <a href="mailto:arts@rmutsb.ac.th" style="color: white;">arts@rmutsb.ac.th</a> หรือโทร. 034-XXX-XXX</p>
        </div>
    </div>';
    }

    $log_msg = "สร้างเนื้อหาอีเมลเรียบร้อย\n";
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

    // ทดลองส่งอีเมลด้วย PHPMailer
    if ($phpmailer_installed) {
        $log_msg = "กำลังใช้ PHPMailer...\n";
        file_put_contents($error_log_file, $log_msg, FILE_APPEND);

        try {
            $mail = new PHPMailer(true);
            
            // บันทึกการทำงานละเอียด
            $mail->SMTPDebug = 3; // ระดับการบันทึกสูงสุด
            $mail->Debugoutput = function($str, $level) use ($error_log_file) {
                file_put_contents($error_log_file, "PHPMailer Debug: $str\n", FILE_APPEND);
            };
            
            // ตั้งค่า SMTP
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'your-email@gmail.com'; // แก้ไขเป็นอีเมลที่ถูกต้อง
            $mail->Password = 'your-app-password'; // แก้ไขเป็น App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';
            
            // ตั้งค่าผู้ส่งและผู้รับ
            $mail->setFrom('your-email@gmail.com', 'คณะศิลปศาสตร์ มทร.สุวรรณภูมิ');
            $mail->addAddress($email, $fullname);
            
            // เนื้อหาอีเมล
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // ส่งอีเมล
            $log_msg = "กำลังส่งอีเมลด้วย PHPMailer...\n";
            file_put_contents($error_log_file, $log_msg, FILE_APPEND);
            
            $mail->send();
            
            $log_msg = "ส่งอีเมลสำเร็จด้วย PHPMailer\n";
            file_put_contents($error_log_file, $log_msg, FILE_APPEND);
            
            // บันทึกประวัติว่าส่งสำเร็จ
            if (isset($email_log_id)) {
                $stmt = $pdo->prepare("UPDATE email_logs SET status = 'success', error_message = NULL WHERE id = ?");
                $stmt->execute([$email_log_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO email_logs (registration_id, email, subject, sent_at, status) 
                                      VALUES (?, ?, ?, NOW(), 'success')");
                $stmt->execute([$registration_id, $email, $subject]);
            }
            
            echo json_encode(['success' => true, 'message' => 'ส่งอีเมลสำเร็จ']);
            exit;
        } catch (Exception $e) {
            $log_msg = "เกิดข้อผิดพลาดในการส่งอีเมลด้วย PHPMailer: " . $e->getMessage() . "\n";
            file_put_contents($error_log_file, $log_msg, FILE_APPEND);
            
            // บันทึกข้อผิดพลาดแต่ให้ทดลองส่งแบบอื่นต่อไป
            if (isset($email_log_id)) {
                $stmt = $pdo->prepare("UPDATE email_logs SET status = 'failed', error_message = ? WHERE id = ?");
                $stmt->execute([$e->getMessage(), $email_log_id]);
            }
        }
    }
    
    // ทางเลือกที่ 2: ใช้ mail() function พื้นฐานของ PHP
    $log_msg = "กำลังใช้ PHP mail() function...\n";
    file_put_contents($error_log_file, $log_msg, FILE_APPEND);
    
    $headers = "From: คณะศิลปศาสตร์ มทร.สุวรรณภูมิ <your-email@gmail.com>\r\n";
    $headers .= "Reply-To: your-email@gmail.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // ส่งอีเมล
    $mail_sent = mail($email, $subject, $message, $headers);
    
    if ($mail_sent) {
        $log_msg = "ส่งอีเมลสำเร็จด้วย PHP mail() function\n";
        file_put_contents($error_log_file, $log_msg, FILE_APPEND);
        
        // บันทึกประวัติว่าส่งสำเร็จ
        if (isset($email_log_id)) {
            $stmt = $pdo->prepare("UPDATE email_logs SET status = 'success', error_message = NULL WHERE id = ?");
            $stmt->execute([$email_log_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO email_logs (registration_id, email, subject, sent_at, status) 
                                 VALUES (?, ?, ?, NOW(), 'success')");
            $stmt->execute([$registration_id, $email, $subject]);
        }
        
        echo json_encode(['success' => true, 'message' => 'ส่งอีเมลสำเร็จ']);
    } else {
        $error = error_get_last();
        $error_message = $error ? $error['message'] : 'Unknown error';
        
        $log_msg = "ส่งอีเมลไม่สำเร็จด้วย PHP mail() function: $error_message\n";
        file_put_contents($error_log_file, $log_msg, FILE_APPEND);
        
        // บันทึกประวัติว่าส่งไม่สำเร็จ
        if (isset($email_log_id)) {
            $stmt = $pdo->prepare("UPDATE email_logs SET status = 'failed', error_message = ? WHERE id = ?");
            $stmt->execute([$error_message, $email_log_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO email_logs (registration_id, email, subject, sent_at, status, error_message) 
                                 VALUES (?, ?, ?, NOW(), 'failed', ?)");
            $stmt->execute([$registration_id, $email, $subject, $error_message]);
        }
        
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถส่งอีเมลได้ กรุณาลองใหม่ภายหลัง']);
    }
    
} catch (Exception $e) {
    // จับข้อผิดพลาดทั้งหมด
    $log_msg = "เกิดข้อผิดพลาดทั่วไป: " . $e->getMessage() . "\n";
    file_put_contents($error_log_file, $log_msg, FILE_APPEND);
    
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}

// บันทึกการทำงานเสร็จสิ้น
file_put_contents($error_log_file, "--- จบการทำงาน " . date('Y-m-d H:i:s') . " ---\n\n", FILE_APPEND);