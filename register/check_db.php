<?php
// Database connection parameters
$host = "mysql";
$dbname = "shared_db";
$username = "dbuser";
$password = "dbpassword";

try {
    // Create database connection
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Start transaction
    $conn->beginTransaction();
    
    // Names of the registrants
    $registrants = [
        'ขวัญชัย ดวงขันเพชร',
        'กรวิษ แสนสุพรรณ์',
        'ธีระวรรธน์ เวชอรรถสิทธิ์'
    ];
    
    // Find registration IDs for these individuals
    $registrationIds = [];
    foreach ($registrants as $name) {
        $stmt = $conn->prepare("SELECT id FROM registrations 
                               WHERE fullname = :name 
                               AND organization LIKE '%บ้านต้อน%' 
                               AND phone = '0910616211'");
        $stmt->execute(['name' => $name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $registrationIds[] = $result['id'];
        }
    }
    
    if (count($registrationIds) < 3) {
        throw new Exception("Could not find all three registrants in the database.");
    }
    
    echo "Found " . count($registrationIds) . " registrants to update.<br>";
    
    // Get province, district, subdistrict IDs for หนองคาย, รัตนวาปี, บ้านต้อน
    $provinceName = "หนองคาย";
    $districtName = "รัตนวาปี";
    $subdistrictName = "บ้านต้อน";
    
    // Get province ID
    $stmt = $conn->prepare("SELECT id FROM provinces WHERE name_in_thai = :name");
    $stmt->execute(['name' => $provinceName]);
    $province = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$province) {
        throw new Exception("Province not found: $provinceName");
    }
    $provinceId = $province['id'];
    
    // Get district ID
    $stmt = $conn->prepare("SELECT id FROM districts WHERE name_in_thai = :name AND province_id = :province_id");
    $stmt->execute(['name' => $districtName, 'province_id' => $provinceId]);
    $district = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$district) {
        throw new Exception("District not found: $districtName");
    }
    $districtId = $district['id'];
    
    // Get subdistrict ID
    $stmt = $conn->prepare("SELECT id FROM subdistricts WHERE name_in_thai = :name AND district_id = :district_id");
    $stmt->execute(['name' => $subdistrictName, 'district_id' => $districtId]);
    $subdistrict = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$subdistrict) {
        throw new Exception("Subdistrict not found: $subdistrictName");
    }
    $subdistrictId = $subdistrict['id'];
    
    // Address data to update
    $address = "60 ม.9 อบต.บ้านต้อน";
    $zipcode = "43120";
    
    // Update or insert address for each registration
    foreach ($registrationIds as $regId) {
        // Check if address exists
        $stmt = $conn->prepare("SELECT id FROM registration_addresses 
                                WHERE registration_id = :reg_id 
                                AND address_type = 'invoice'");
        $stmt->execute(['reg_id' => $regId]);
        $addressExists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($addressExists) {
            // Update existing address
            $stmt = $conn->prepare("UPDATE registration_addresses 
                                   SET address = :address,
                                       province_id = :province_id,
                                       district_id = :district_id,
                                       subdistrict_id = :subdistrict_id,
                                       zipcode = :zipcode
                                   WHERE registration_id = :reg_id
                                   AND address_type = 'invoice'");
        } else {
            // Insert new address
            $stmt = $conn->prepare("INSERT INTO registration_addresses 
                                   (registration_id, address_type, address, 
                                    province_id, district_id, subdistrict_id, zipcode)
                                   VALUES 
                                   (:reg_id, 'invoice', :address, 
                                    :province_id, :district_id, :subdistrict_id, :zipcode)");
        }
        
        $stmt->execute([
            'reg_id' => $regId,
            'address' => $address,
            'province_id' => $provinceId,
            'district_id' => $districtId,
            'subdistrict_id' => $subdistrictId,
            'zipcode' => $zipcode
        ]);
        
        echo "Updated address for registration ID: $regId<br>";
    }
    
    // Commit transaction
    $conn->commit();
    echo "All addresses successfully updated!";
    
} catch (Exception $e) {
    // Roll back transaction on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Error: " . $e->getMessage();
}
?>