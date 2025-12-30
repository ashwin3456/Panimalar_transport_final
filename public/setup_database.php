<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "panimalar_bus_tracker";

// Create connection without selecting database
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// SQL to create users table
$sql = "CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL,
    `password` varchar(255) NOT NULL,
    `role` enum('Admin','Driver','Faculty','Student') NOT NULL,
    `roll_number` varchar(50) DEFAULT NULL,
    `phone_number` varchar(20) DEFAULT NULL,
    `admin_number` varchar(50) DEFAULT NULL,
    `faculty_id` varchar(50) DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `last_login` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "Table 'users' created successfully or already exists<br>";
} else {
    die("Error creating table: " . $conn->error);
}

echo "Database setup completed successfully. <a href='register.php'>Go to Registration Page</a>";

$conn->close();
?>
