<?php
require_once 'config/database.php';  // Include your database connection file

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $sql = "ALTER TABLE registrations 
            MODIFY COLUMN payment_status ENUM('not_paid', 'paid', 'paid_onsite') NOT NULL DEFAULT 'not_paid';";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    echo "Database schema updated successfully.";
} catch (PDOException $e) {
    echo "Error updating database schema: " . $e->getMessage();
}