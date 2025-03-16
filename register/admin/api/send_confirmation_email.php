<?php
require_once '../../config/database.php';

// รับข้อมูลจาก POST request
$registration_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$email = isset($_POST['email']) ? $_POST['email'] : '';
$fullname = isset($_POST['fullname']) ? $_POST['fullname'] : '';
$payment_status = isset($_POST['payment_status']) ? $_POST['payment_status'] : 'not_paid';

// ตรวจสอบข้อมูล
if ($registration_id <= 0 || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

// เตรียมข้อมูลอีเมล
$database = new Database();
$pdo = $database->getConnection();

// ดึงข้อมูลเพิ่มเติมจากฐานข้อมูล (ถ้าจำเป็น)
$stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ?");
$stmt->execute([$registration_id]);
$registration = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registration) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลการลงทะเบียน']);
    exit;
}

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
    
    <div style="display: flex; justify-content: center; gap: 20px; margin-bottom: 20px;">
        <div style="text-align: center;">
            <p style="font-weight: bold; margin-bottom: 10px;">จองห้องพัก</p>
            <img src="https://arts.rmutsb.ac.th/storage/content_picture/cp20250315123535.jpg" alt="QR Code สำหรับจองห้องพัก" style="width: 120px; height: 120px;">
        </div>
        <div style="text-align: center;">
            <p style="font-weight: bold; margin-bottom: 10px;">Line OA</p>
            <img src="https://arts.rmutsb.ac.th/storage/content_picture/cp20250307131841.jpg" alt="QR Code สำหรับ Line OA" style="width: 120px; height: 120px;">
        </div>
    </div>
    
    <div style="background-color: #1a237e; color: white; padding: 15px; border-radius: 5px; margin-top: 20px; text-align: center;">
        <p style="margin: 0;">หากมีข้อสงสัยประการใด กรุณาติดต่อเจ้าหน้าที่ได้ที่ <a href="mailto:arts@rmutsb.ac.th" style="color: white;">arts@rmutsb.ac.th</a> หรือโทร. 095-543-9933</p>
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
    
    <div style="display: flex; justify-content: center; gap: 20px; margin-bottom: 20px;">
        <div style="text-align: center;">
            <p style="font-weight: bold; margin-bottom: 10px;">จองห้องพัก</p>
            <img src="https://arts.rmutsb.ac.th/storage/content_picture/cp20250315123535.jpg" alt="QR Code สำหรับจองห้องพัก" style="width: 120px; height: 120px;">
        </div>
        <div style="text-align: center;">
            <p style="font-weight: bold; margin-bottom: 10px;">Line OA</p>
            <img src="https://arts.rmutsb.ac.th/storage/content_picture/cp20250307131841.jpg" alt="QR Code สำหรับ Line OA" style="width: 120px; height: 120px;">
        </div>
    </div>
    
    <div style="background-color: #1a237e; color: white; padding: 15px; border-radius: 5px; margin-top: 20px; text-align: center;">
        <p style="margin: 0;">หากมีข้อสงสัยประการใด กรุณาติดต่อเจ้าหน้าที่ได้ที่ <a href="mailto:arts@rmutsb.ac.th" style="color: white;">arts@rmutsb.ac.th</a> หรือโทร. 034-XXX-XXX</p>
    </div>
</div>';
}

require '../../vendor/autoload.php'; // ต้องติดตั้ง PHPMailer ผ่าน Composer ก่อน
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // ตั้งค่า SMTP สำหรับ Gmail
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'arts@rmutsb.ac.th';
    $mail->Password   = 'artsrus6';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    // ตั้งค่าผู้ส่งและผู้รับ
    $mail->setFrom('arts@rmutsb.ac.th', 'คณะศิลปศาสตร์ มทร.สุวรรณภูมิ');
    $mail->addAddress($email, $fullname);
    $mail->addReplyTo('arts@rmutsb.ac.th', 'คณะศิลปศาสตร์ มทร.สุวรรณภูมิ');

    // เนื้อหาอีเมล
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $message;
    $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $message));

    // ส่งอีเมล
    $mail->send();

    // บันทึกประวัติการส่งอีเมล
    try {
        $stmt = $pdo->prepare("INSERT INTO email_logs (registration_id, email, subject, message, sent_at, status) VALUES (?, ?, ?, ?, NOW(), 'success')");
        $stmt->execute([$registration_id, $email, $subject, $message]);
    } catch (PDOException $e) {
        // หากไม่สามารถบันทึกประวัติได้ ให้เก็บบันทึกข้อผิดพลาด แต่ยังคงถือว่าส่งอีเมลสำเร็จ
        error_log('ไม่สามารถบันทึกประวัติการส่งอีเมล: ' . $e->getMessage());
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // บันทึกข้อผิดพลาดการส่งอีเมล
    try {
        $stmt = $pdo->prepare("INSERT INTO email_logs (registration_id, email, subject, message, sent_at, status, error_message) VALUES (?, ?, ?, ?, NOW(), 'failed', ?)");
        $stmt->execute([$registration_id, $email, $subject, $message, $mail->ErrorInfo]);
    } catch (PDOException $logError) {
        // หากไม่สามารถบันทึกประวัติได้ ให้เก็บบันทึกข้อผิดพลาด
        error_log('ไม่สามารถบันทึกประวัติข้อผิดพลาดการส่งอีเมล: ' . $logError->getMessage());
    }
    
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการส่งอีเมล: ' . $mail->ErrorInfo]);
}
?>