<?php
// Database configuration
define('DB_HOST', 'mysql');
define('DB_USER', 'dbuser');
define('DB_PASS', 'dbpassword');
define('DB_NAME', 'shared_db');

// Connection class
class Database {
    private $connection;
    public $error;
    
    public function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            // Store error message instead of outputting directly
            $this->error = "Connection Error: " . $e->getMessage();
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
}
// No closing PHP tag to prevent accidental whitespace