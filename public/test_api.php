<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Simulate a logged-in user for testing
$_SESSION['user_id'] = 1; // Replace with a valid user ID

// Include the database connection
require_once __DIR__ . '/../Backend/db.php';

// Test get_profile action
function testGetProfile() {
    $ch = curl_init('http://localhost/ash/Panimalar_bus_tracker_Asquad/public/api/data.php?action=get_profile');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    echo "=== Testing get_profile ===\n";
    echo "Headers:\n$headers\n";
    echo "Response:\n$body\n\n";
    
    curl_close($ch);
    return $body;
}

// Test get_driver_schedules action
function testGetDriverSchedules() {
    $ch = curl_init('http://localhost/ash/Panimalar_bus_tracker_Asquad/public/api/data.php?action=get_driver_schedules');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    echo "=== Testing get_driver_schedules ===\n";
    echo "Headers:\n$headers\n";
    echo "Response:\n$body\n";
    
    curl_close($ch);
    return $body;
}

// Run tests
echo "Starting API tests...\n\n";
$profileResponse = testGetProfile();
$schedulesResponse = testGetDriverSchedules();

// Check if responses are valid JSON
$profileData = json_decode($profileResponse, true);
$schedulesData = json_decode($schedulesResponse, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "\n=== JSON Parse Errors ===\n";
    echo "Profile response: " . json_last_error_msg() . "\n";
    echo "Schedules response: " . json_last_error_msg() . "\n";
}

// Output session info for debugging
echo "\n=== Session Info ===\n";
print_r($_SESSION);

// Output database connection status
echo "\n=== Database Connection ===\n";
if (isset($conn) && $conn->ping()) {
    echo "Connected to database successfully\n";
    echo "Database: " . $conn->host_info . "\n";
} else {
    echo "Database connection failed: " . ($conn->connect_error ?? 'Unknown error') . "\n";
}
