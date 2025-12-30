<?php
// db.php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "panimalar_bus_tracker";

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");

// Test query (optional, for debugging)
// $test = $conn->query("SELECT 1");
// if (!$test) {
//     error_log("Test query failed: " . $conn->error);
//     die(json_encode(["error" => "Database test query failed"]));
// }

return $conn;