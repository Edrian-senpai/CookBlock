<?php
// Attempt to establish a connection to the MySQL database using PDO
try {
    // Create a new PDO instance with host, database name, username, and password
    $pdo = new PDO("mysql:host=localhost;dbname=recipe_system_db", "root", "");
    
    // Set the PDO error mode to exception to catch and handle database errors properly
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If the connection fails, display the error message and stop execution
    die("Connection failed: " . $e->getMessage());
}
?>
