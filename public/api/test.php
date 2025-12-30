<?php
// Simple test endpoint to check what's wrong
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  exit(0);
}

try {
    // Test 1: Basic PHP
    $response = ['status' => 'PHP working'];
    
    // Test 2: Database connection
    require __DIR__ . '/../../Backend/db.php';
    $response['database'] = 'connected';
    
    // Test 3: Check tables
    $tables = ['buses', 'bus_stops', 'routes'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        $response['tables'][$table] = ($result && $result->num_rows > 0) ? 'exists' : 'missing';
    }
    
    // Test 4: Check if we can insert
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = file_get_contents('php://input');
        $response['received_data'] = json_decode($body, true);
        
        // Try a simple insert
        $testName = 'Test Bus ' . date('H:i:s');
        $testRoute = 'TEST-' . rand(100, 999);
        
        $stmt = $conn->prepare("INSERT INTO buses (bus_name, route_number, status) VALUES (?, ?, 'active')");
        $stmt->bind_param('ss', $testName, $testRoute);
        
        if ($stmt->execute()) {
            $busId = $conn->insert_id;
            $response['test_insert'] = "Success - ID: $busId";
            
            // Clean up
            $conn->query("DELETE FROM buses WHERE id = $busId");
        } else {
            $response['test_insert'] = "Failed: " . $stmt->error;
        }
        $stmt->close();
    }
    
    $conn->close();
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>
