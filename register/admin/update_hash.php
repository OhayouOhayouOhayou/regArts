<?php
// Include the database configuration
require_once '../config/database.php';

// Initialize the database connection
$db = new Database();
if ($db->error) {
    die("Database connection failed: " . $db->error);
}

$conn = $db->getConnection();

// Function to update or insert a user
function updateOrInsertUser($conn, $username, $role, $display_name = null) {
    // Convert username and role to lowercase
    $username = strtolower($username);
    $role = strtolower($role);
    
    // If display_name is not provided, use the original name
    if ($display_name === null) {
        $display_name = $username;
    }
    
    // Hash the default password
    $default_password = password_hash('12345678', PASSWORD_DEFAULT);
    
    try {
        // Check if user exists
        $checkStmt = $conn->prepare("SELECT id FROM admin_users WHERE username = :username");
        $checkStmt->bindParam(':username', $username);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            // User exists, update
            $user = $checkStmt->fetch(PDO::FETCH_ASSOC);
            $updateStmt = $conn->prepare("UPDATE admin_users SET password = :password, role = :role, 
                                         password_change_required = 1, display_name = :display_name 
                                         WHERE id = :id");
            $updateStmt->bindParam(':password', $default_password);
            $updateStmt->bindParam(':role', $role);
            $updateStmt->bindParam(':display_name', $display_name);
            $updateStmt->bindParam(':id', $user['id']);
            $updateStmt->execute();
            return "Updated user: " . $username . " with role: " . $role;
        } else {
            // User doesn't exist, insert
            $insertStmt = $conn->prepare("INSERT INTO admin_users (username, password, display_name, role, password_change_required) 
                                         VALUES (:username, :password, :display_name, :role, 1)");
            $insertStmt->bindParam(':username', $username);
            $insertStmt->bindParam(':password', $default_password);
            $insertStmt->bindParam(':display_name', $display_name);
            $insertStmt->bindParam(':role', $role);
            $insertStmt->execute();
            return "Inserted new user: " . $username . " with role: " . $role;
        }
    } catch (PDOException $e) {
        return "Error processing user " . $username . ": " . $e->getMessage();
    }
}

// Start transaction
$conn->beginTransaction();

try {
    // Array of users to process [username, role, display_name]
    $users = [
        ['admin', 'admin', 'Admin'],
        ['pailin', 'admin', 'Pailin'],
        ['wannapa', 'admin', 'Wannapa'],
        ['pattita', 'admin', 'Pattita'],
        ['panwas', 'admin', 'Panwas'],
        ['theemaporn', 'admin', 'Theemaporn'],
        ['jirawoot', 'admin', 'Jirawoot'],
        ['wanvisa', 'admin', 'Wanvisa'],
        ['narissara', 'admin', 'Narissara'],
        ['jintana', 'admin finance', 'Jintana'],
        ['jaruwan', 'admin finance', 'Jaruwan'],
        ['kochchaphun', 'admin finance', 'Kochchaphun'],
        ['siriphen', 'admin finance', 'Siriphen'],
        ['rungtiwa', 'admin finance', 'Rungtiwa'],
        ['kantarath', 'admin finance', 'Kantarath'],
        ['sompong', 'head admin', 'Sompong']
    ];
    
    // Process each user
    $results = [];
    foreach ($users as $user) {
        $results[] = updateOrInsertUser($conn, $user[0], $user[1], $user[2]);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Output results
    echo "<h2>User Update Results</h2>";
    echo "<pre>";
    foreach ($results as $result) {
        echo htmlspecialchars($result) . "\n";
    }
    echo "</pre>";
    echo "<p>All users have been processed successfully. Default password set to '12345678' with password change required.</p>";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    echo "<h2>Error Processing Users</h2>";
    echo "<p>An error occurred: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>