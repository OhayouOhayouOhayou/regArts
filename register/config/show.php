<?php
// Include the database configuration file
require_once 'database.php';

// Page header
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Information</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h1, h2 {
            color: #333;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .error {
            color: #e74c3c;
            background-color: #fae2e1;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <h1>Database Information</h1>';

// Create database connection
$database = new Database();
$connection = $database->getConnection();

// Check if connection was successful
if ($database->error) {
    echo '<div class="error">' . $database->error . '</div>';
} else {
    // List all databases
    try {
        $query = "SHOW DATABASES";
        $statement = $connection->prepare($query);
        $statement->execute();
        $databases = $statement->fetchAll(PDO::FETCH_COLUMN);
        
        echo '<h2>Available Databases</h2>';
        echo '<table>
                <tr>
                    <th>Database Name</th>
                </tr>';
        
        foreach ($databases as $db) {
            echo '<tr><td>' . htmlspecialchars($db) . '</td></tr>';
        }
        
        echo '</table>';
        
        // Get tables in the current database (shared_db)
        $query = "SHOW TABLES FROM " . DB_NAME;
        $statement = $connection->prepare($query);
        $statement->execute();
        $tables = $statement->fetchAll(PDO::FETCH_COLUMN);
        
        echo '<h2>Tables in ' . htmlspecialchars(DB_NAME) . '</h2>';
        
        if (count($tables) > 0) {
            echo '<table>
                    <tr>
                        <th>Table Name</th>
                    </tr>';
            
            foreach ($tables as $table) {
                echo '<tr>
                        <td>' . htmlspecialchars($table) . '</td>
                      </tr>';
            }
            
            echo '</table>';
        } else {
            echo '<p>No tables found in the database.</p>';
        }
        
    } catch (PDOException $e) {
        echo '<div class="error">Error: ' . $e->getMessage() . '</div>';
    }
}

echo '</body></html>';
?>