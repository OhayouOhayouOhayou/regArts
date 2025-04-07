<?php
// Get registration data from POST
$registration_id = $_POST['id'] ?? 0;
$email = $_POST['email'] ?? '';
$fullname = $_POST['fullname'] ?? '';
$payment_status = $_POST['payment_status'] ?? '';

// Set content based on payment status
$subject = 'ยืนยันการลงทะเบียนการสัมมนา - มหาวิทยาลัยเทคโนโลยีราชมงคลสุวรรณภูมิ';
$message = ($payment_status == 'paid') ? 
    "เรียน คุณ$fullname\n\nขอบคุณสำหรับการลงทะเบียนและชำระเงิน กรุณาตรวจสอบอีเมลของท่านสำหรับรายละเอียดเพิ่มเติม" :
    "เรียน คุณ$fullname\n\nขอบคุณสำหรับการลงทะเบียน กรุณาชำระเงินเพื่อยืนยันการเข้าร่วมสัมมนา";

// Email headers
$headers = "From: seminars@rmutsb.ac.th\r\n";
$headers .= "Reply-To: arts@rmutsb.ac.th\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// Send email using PHP's mail function
$success = mail($email, $subject, $message, $headers);

// Return response
echo json_encode([
    'success' => $success,
    'message' => $success ? "ส่งอีเมลไปยัง $email เรียบร้อยแล้ว" : "ไม่สามารถส่งอีเมลได้"
]);
?>