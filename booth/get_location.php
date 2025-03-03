<?php
require_once 'config.php';

// ตั้งค่า header เพื่อส่งข้อมูลแบบ JSON
header('Content-Type: application/json');

// รับค่า action จาก request
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get_provinces':
        // ดึงข้อมูลจังหวัดทั้งหมด
        $sql = "SELECT id, code, name_in_thai, name_in_english FROM provinces ORDER BY name_in_thai";
        $result = $conn->query($sql);
        
        $provinces = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $provinces[] = $row;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $provinces
        ]);
        break;
        
    case 'get_districts':
        // ตรวจสอบว่ามีการส่ง province_id มาหรือไม่
        if (!isset($_GET['province_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'ต้องระบุรหัสจังหวัด'
            ]);
            exit;
        }
        
        $provinceId = $_GET['province_id'];
        
        // ดึงข้อมูลอำเภอตามจังหวัด
        $sql = "SELECT id, code, name_in_thai, name_in_english FROM districts WHERE province_id = ? ORDER BY name_in_thai";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $provinceId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $districts = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $districts[] = $row;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $districts
        ]);
        break;
        
    case 'get_subdistricts':
        // ตรวจสอบว่ามีการส่ง district_id มาหรือไม่
        if (!isset($_GET['district_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'ต้องระบุรหัสอำเภอ'
            ]);
            exit;
        }
        
        $districtId = $_GET['district_id'];
        
        // ดึงข้อมูลตำบลตามอำเภอ
        $sql = "SELECT id, code, name_in_thai, name_in_english, zip_code FROM subdistricts WHERE district_id = ? ORDER BY name_in_thai";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $districtId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $subdistricts = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $subdistricts[] = $row;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $subdistricts
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'ไม่รู้จัก action: ' . $action
        ]);
}
?>