<?php
// ตั้งค่า headers สำหรับการดาวน์โหลดไฟล์ CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="registrations_export.csv"');

// สร้างการเชื่อมต่อฐานข้อมูล
try {
    $conn = new PDO(
        "mysql:host=mysql;port=3306;dbname=shared_db",
        "dbuser", 
        "dbpassword",
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection error: " . $e->getMessage());
}

// รับพารามิเตอร์สำหรับฟิลเตอร์
$province = $_GET['province'] ?? '';
$district = $_GET['district'] ?? '';
$firstName = $_GET['firstName'] ?? '';
$lastName = $_GET['lastName'] ?? '';
$phone = $_GET['phone'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// สร้าง SQL หลัก - ใช้ ANY_VALUE() เพื่อแก้ปัญหา GROUP BY

$sql = "SELECT r.id, r.fullname, r.organization, r.position, r.phone, 
               r.email, r.line_id, r.payment_status, r.is_approved, 
               r.payment_date, r.created_at, r.title,
               ANY_VALUE(a.address) AS address, 
               ANY_VALUE(a.zipcode) AS zipcode,
               ANY_VALUE(p.name_in_thai) AS province_name, 
               ANY_VALUE(d.name_in_thai) AS district_name, 
               ANY_VALUE(s.name_in_thai) AS subdistrict_name,
               GROUP_CONCAT(DISTINCT CONCAT(rd.document_type, ':', rd.file_path) SEPARATOR '|') AS document_paths,
               ANY_VALUE(rf.file_path) AS payment_slip_path,
               CASE 
                  WHEN r.payment_status = 'paid' AND r.is_approved = 1 THEN 'paid_approved'
                  WHEN r.payment_status = 'paid' AND r.is_approved = 0 THEN 'paid_pending'
                  WHEN r.payment_status = 'paid_onsite' THEN 'paid_onsite'
                  ELSE 'not_paid'
               END AS combined_status
        FROM registrations r 
        LEFT JOIN registration_addresses a ON r.id = a.registration_id AND a.address_type = 'invoice'
        LEFT JOIN provinces p ON a.province_id = p.id 
        LEFT JOIN districts d ON a.district_id = d.id 
        LEFT JOIN subdistricts s ON a.subdistrict_id = s.id 
        LEFT JOIN registration_documents rd ON r.id = rd.registration_id
        LEFT JOIN registration_files rf ON rf.id = r.payment_slip_id
        WHERE 1=1";

// สร้างเงื่อนไขและพารามิเตอร์สำหรับ Prepared Statement
$conditions = [];
$params = [];

if ($province) {
    $conditions[] = "a.province_id = :province";
    $params[':province'] = $province;
}
if ($district) {
    $conditions[] = "a.district_id = :district";
    $params[':district'] = $district;
}
if ($firstName) {
    $conditions[] = "r.fullname LIKE :firstName";
    $params[':firstName'] = "%$firstName%";
}
if ($phone) {
    $conditions[] = "r.phone LIKE :phone";
    $params[':phone'] = "%$phone%";
}

if ($status === 'approved') {
    $conditions[] = "r.is_approved = 1";
} else if ($status === 'pending') {
    $conditions[] = "r.is_approved = 0";
} else if ($status === 'paid_approved') {
    $conditions[] = "r.payment_status = 'paid_approved'";
} else if ($status === 'paid_pending') {
    $conditions[] = "r.payment_status = 'paid' AND r.is_approved = 0";
} else if ($status === 'paid') {
    $conditions[] = "r.payment_status = 'paid'";
} else if ($status === 'paid_onsite') {
    $conditions[] = "r.payment_status = 'paid_onsite'";
} else if ($status === 'not_paid') {
    $conditions[] = "r.payment_status = 'not_paid'";
}
if ($search) {
    $conditions[] = "(r.fullname LIKE :search OR r.email LIKE :search OR r.phone LIKE :search OR r.organization LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

$sql .= " GROUP BY r.id ORDER BY r.created_at DESC";

try {
    // รัน SQL ด้วย Prepared Statement
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    
    // เปิดไฟล์สำหรับเขียน
    $output = fopen('php://output', 'w');
    
    // เขียนหัวคอลัมน์ UTF-8 BOM (ช่วยให้ Excel อ่านภาษาไทยได้ถูกต้อง)
    fputs($output, "\xEF\xBB\xBF");
    
    // กำหนดหัวคอลัมน์
    fputcsv($output, [
        'วันที่ลงทะเบียน','คำนำหน้า' , 'ชื่อ-นามสกุล', 'หน่วยงาน', 'ตำแหน่ง', 'เบอร์โทร', 'อีเมล', 'ไลน์ไอดี', 
        'ที่อยู่', 'จังหวัด', 'อำเภอ', 'ตำบล', 'รหัสไปรษณีย์', 'สถานะการอนุมัติ', 'สถานะการชำระเงิน', 
        'วันที่ชำระเงิน', 'หลักฐานการชำระเงิน', 'เอกสารประกอบ'
    ]);
    
    // เขียนข้อมูล
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // แก้ปัญหาเบอร์โทรที่เลข 0 ด้านหน้าหาย โดยเพิ่มเครื่องหมาย ' ด้านหน้าเพื่อให้ Excel ทำเป็น text format
        $phoneNumber = "'" . $row['phone'];
        $Line = "'" . $row['line_id'];
        // แยกเอกสารประกอบเป็นรายการ
        $documents = '';
        if (!empty($row['document_paths'])) {
            $docArray = explode('|', $row['document_paths']);
            $docLinks = [];
            foreach ($docArray as $doc) {
                list($type, $path) = explode(':', $doc);
                $docType = '';
                switch ($type) {
                    case 'identification':
                        $docType = 'บัตรประชาชน';
                        break;
                    case 'certificate':
                        $docType = 'วุฒิบัตร';
                        break;
                    case 'professional':
                        $docType = 'เอกสารวิชาชีพ';
                        break;
                    default:
                        $docType = 'เอกสารอื่นๆ';
                }
                $baseUrl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
                $baseUrl .= $_SERVER['HTTP_HOST'];
                $fullPath = $baseUrl . '/' . $path;
                $docLinks[] = "{$docType}: {$fullPath}";
            }
            $documents = implode("\n", $docLinks);
        }
        
        // สร้าง URL สำหรับหลักฐานการชำระเงิน
        $paymentSlip = '';
        if (!empty($row['payment_slip_path'])) {
            $baseUrl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
            $baseUrl .= $_SERVER['HTTP_HOST'];
            $paymentSlip = $baseUrl . '/' . $row['payment_slip_path'];
        }
        
       // แปลงสถานะการชำระเงิน
        $paymentStatus = 'ยังไม่ชำระ';
        if ($row['payment_status'] === 'paid') {
            if ($row['is_approved'] == 1) {
                $paymentStatus = 'ชำระแล้ว (อนุมัติแล้ว)';
            } else {
                $paymentStatus = 'ชำระแล้ว (รอตรวจสอบจากเจ้าหน้าที่)';
            }
        } elseif ($row['payment_status'] === 'paid_onsite') {
            $paymentStatus = 'อนุมัติ (ชำระเงินที่หน้างาน)';
        }
        
        fputcsv($output, [
            $row['created_at'],
            $row['title'],
            $row['fullname'],
            $row['organization'],
            $row['position'],
            $phoneNumber,
            $row['email'],
            $Line,
            $row['address'] ?? '',
            $row['province_name'] ?? '',
            $row['district_name'] ?? '',
            $row['subdistrict_name'] ?? '',
            $row['zipcode'] ?? '',
            $row['is_approved'] == 1 ? 'อนุมัติแล้ว' : 'รอการอนุมัติ',
            $paymentStatus,
            $row['payment_date'] ?? '',
            $paymentSlip,
            $documents
        ]);
    }
    
    // ปิดไฟล์
    fclose($output);
    exit;
} catch(Exception $e) {
    die("Error: " . $e->getMessage());
}
?>