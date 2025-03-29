<?php
// Include the database configuration file
require_once 'database.php';

// Page header
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Database Information</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h1, h2, h3 {
            color: #333;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
            font-size: 14px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
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
        .table-container {
            margin-bottom: 30px;
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 5px;
        }
        .data-container {
            max-height: 500px;
            overflow-y: auto;
            margin-top: 10px;
        }
        .null-value {
            color: #999;
            font-style: italic;
        }
        .structure-table {
            width: 60%;
        }
    </style>
</head>
<body>
    <h1>Complete Database Information</h1>';

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
        
        echo '<h2>Tables and Data in ' . htmlspecialchars(DB_NAME) . '</h2>';
        
        if (count($tables) > 0) {
            foreach ($tables as $table) {
                echo '<div class="table-container">';
                echo '<h3>Table: ' . htmlspecialchars($table) . '</h3>';
                
                // Get table structure
                $query = "DESCRIBE " . $table;
                $statement = $connection->prepare($query);
                $statement->execute();
                $columns = $statement->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<h4>Table Structure</h4>';
                echo '<table class="structure-table">
                        <tr>
                            <th>Field</th>
                            <th>Type</th>
                            <th>Null</th>
                            <th>Key</th>
                            <th>Default</th>
                            <th>Extra</th>
                        </tr>';
                
                foreach ($columns as $column) {
                    echo '<tr>';
                    foreach ($column as $key => $value) {
                        echo '<td>' . (is_null($value) ? '<span class="null-value">NULL</span>' : htmlspecialchars($value)) . '</td>';
                    }
                    echo '</tr>';
                }
                
                echo '</table>';
                
                // Get table data with LIMIT to avoid large datasets
                $query = "SELECT * FROM " . $table . " ";
                $statement = $connection->prepare($query);
                $statement->execute();
                $data = $statement->fetchAll(PDO::FETCH_ASSOC);
                $columnNames = [];
                
                if (count($data) > 0) {
                    $columnNames = array_keys($data[0]);
                } else {
                    // If no data, get column names from structure
                    foreach ($columns as $column) {
                        $columnNames[] = $column['Field'];
                    }
                }
                
                echo '<h4>Table Data</h4>';
                echo '<div class="data-container">';
                echo '<table>
                        <tr>';
                
                foreach ($columnNames as $columnName) {
                    echo '<th>' . htmlspecialchars($columnName) . '</th>';
                }
                
                echo '</tr>';
                
                if (count($data) > 0) {
                    foreach ($data as $row) {
                        echo '<tr>';
                        foreach ($row as $value) {
                            if (is_null($value)) {
                                echo '<td><span class="null-value">NULL</span></td>';
                            } else {
                                echo '<td>' . htmlspecialchars($value) . '</td>';
                            }
                        }
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="' . count($columnNames) . '">No data in this table</td></tr>';
                }
                
                echo '</table>';
                echo '</div>'; // End data-container
                echo '</div>'; // End table-container
            }
        } else {
            echo '<p>No tables found in the database.</p>';
        }
        
    } catch (PDOException $e) {
        echo '<div class="error">Error: ' . $e->getMessage() . '</div>';
    }
}

echo '</body></html>';
?>