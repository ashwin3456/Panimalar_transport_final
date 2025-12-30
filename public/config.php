<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'panimalar_bus_tracker');

// Initialize connection variable
$conn = null;

// Create connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4 for proper character encoding
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // Log error and display user-friendly message
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Make $conn available globally
$GLOBALS['conn'] = $conn;
?>
