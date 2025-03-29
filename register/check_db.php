<?php
/**
 * Database Schema Maintenance Script
 * 
 * This script checks and updates the database schema for the registration system
 * to ensure all required fields are present for multi-registrant functionality.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'db_maintenance.log');

require_once 'config/database.php';

// Function to check if a column exists in a table
function columnExists($conn, $table, $column) {
    $sql = "SHOW COLUMNS FROM {$table} LIKE '{$column}'";
    $result = $conn->query($sql);
    return $result->rowCount() > 0;
}

// Function to add a column if it doesn't exist
function addColumnIfNotExists($conn, $table, $column, $definition) {
    if (!columnExists($conn, $table, $column)) {
        $sql = "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}";
        $conn->query($sql);
        echo "Added column '{$column}' to table '{$table}'<br>";
        error_log("Added column '{$column}' to table '{$table}'");
        return true;
    }
    return false;
}

try {
    // Connect to database
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h1>Database Schema Maintenance</h1>";
    
    // Check registrations table
    echo "<h2>Checking registrations table...</h2>";
    
    addColumnIfNotExists($conn, "registrations", "registration_group", "VARCHAR(50) NULL");
    addColumnIfNotExists($conn, "registrations", "payment_status", "ENUM('not_paid', 'paid') NOT NULL DEFAULT 'not_paid'");
    addColumnIfNotExists($conn, "registrations", "is_approved", "TINYINT(1) NOT NULL DEFAULT 0");
    addColumnIfNotExists($conn, "registrations", "payment_date", "DATETIME NULL");
    addColumnIfNotExists($conn, "registrations", "payment_slip_id", "INT NULL");
    addColumnIfNotExists($conn, "registrations", "payment_updated_at", "DATETIME NULL");
    
    // Check registration_documents table
    echo "<h2>Checking registration_documents table...</h2>";
    
    // First check if table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'registration_documents'")->rowCount() > 0;
    if (!$tableExists) {
        $sql = "CREATE TABLE registration_documents (
            id INT(11) NOT NULL AUTO_INCREMENT,
            registration_id INT(11) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            file_size INT(11) NOT NULL,
            document_type VARCHAR(50) NOT NULL DEFAULT 'general',
            description TEXT NULL,
            uploaded_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_registration_id (registration_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $conn->query($sql);
        echo "Created table 'registration_documents'<br>";
        error_log("Created table 'registration_documents'");
    } else {
        addColumnIfNotExists($conn, "registration_documents", "document_type", "VARCHAR(50) NOT NULL DEFAULT 'general'");
        addColumnIfNotExists($conn, "registration_documents", "description", "TEXT NULL");
    }
    
    // Check registration_files table
    echo "<h2>Checking registration_files table...</h2>";
    
    // First check if table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'registration_files'")->rowCount() > 0;
    if (!$tableExists) {
        $sql = "CREATE TABLE registration_files (
            id INT(11) NOT NULL AUTO_INCREMENT,
            registration_id INT(11) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            file_size INT(11) NOT NULL,
            uploaded_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_registration_id (registration_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $conn->query($sql);
        echo "Created table 'registration_files'<br>";
        error_log("Created table 'registration_files'");
    }
    
    echo "<h2>Schema maintenance completed successfully!</h2>";
    
} catch (Exception $e) {
    echo "<h2>Error:</h2> " . $e->getMessage();
    error_log("Schema maintenance error: " . $e->getMessage());
}