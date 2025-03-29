<?php
// db_setup.php - Run this script once to set up the necessary tables

require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if admin_users table exists and if display_name column exists
    $tableExists = false;
    $displayNameExists = false;
    
    // Check if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'admin_users'");
    $tableExists = ($checkTable->rowCount() > 0);
    
    if ($tableExists) {
        // Check if display_name column exists
        $checkColumn = $conn->query("SHOW COLUMNS FROM admin_users LIKE 'display_name'");
        $displayNameExists = ($checkColumn->rowCount() > 0);
        
        // If table exists but display_name column doesn't, add it
        if (!$displayNameExists) {
            $conn->exec("ALTER TABLE admin_users ADD COLUMN display_name VARCHAR(100) NOT NULL AFTER password");
            echo "Added missing 'display_name' column to admin_users table<br>";
        }
    } else {
        // Create admin_users table if it doesn't exist
        $sql = "CREATE TABLE IF NOT EXISTS admin_users (
            id INT(11) NOT NULL AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'staff',
            password_change_required TINYINT(1) NOT NULL DEFAULT 1,
            last_login DATETIME NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->exec($sql);
        echo "Created admin_users table<br>";
    }
    
    // Create admin_logs table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS admin_logs (
        id INT(11) NOT NULL AUTO_INCREMENT,
        admin_id INT(11) NOT NULL,
        action_type ENUM('login', 'logout', 'insert', 'update', 'delete', 'view') NOT NULL,
        target_table VARCHAR(50) NULL,
        record_id VARCHAR(50) NULL,
        details TEXT NULL,
        ip_address VARCHAR(50) NOT NULL,
        user_agent TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($sql);
    
    // Add initial users
    $initialUsers = [
        ['username' => 'Jintana', 'display_name' => 'จินตนา'],
        ['username' => 'Jaruwan', 'display_name' => 'จารุวรรณ'],
        ['username' => 'Kochchaphun', 'display_name' => 'คชพันธ์'],
        ['username' => 'Siriphen', 'display_name' => 'ศิริเพ็ญ'],
        ['username' => 'Rungtiwa', 'display_name' => 'รุ่งทิวา'],
        ['username' => 'Kantarath', 'display_name' => 'กันตรัตน์']
    ];
    
    // Hash the default password
    $defaultPassword = password_hash('12345678', PASSWORD_DEFAULT);
    
    // Prepare statement for inserting users
    $stmt = $conn->prepare("INSERT INTO admin_users (username, password, display_name, role, password_change_required) 
                           VALUES (?, ?, ?, 'staff', 1)
                           ON DUPLICATE KEY UPDATE password = VALUES(password), 
                                                  display_name = VALUES(display_name), 
                                                  password_change_required = 1");
    
    // Insert each user
    foreach ($initialUsers as $user) {
        $stmt->execute([$user['username'], $defaultPassword, $user['display_name']]);
        echo "Added user: {$user['username']}<br>";
    }
    
    echo "Database setup completed successfully!";
    
} catch(PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>