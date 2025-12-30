<?php
// Debug save functionality
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo json_encode([
    'status' => 'debug_mode',
    'connection' => $conn ? 'OK' : 'FAILED',
    'error' => $conn->connect_error ?? null,
    'database' => function_exists('mysqli_query') ? 'MySQLi available' : 'MySQLi missing'
]);

// Test a simple insert
try {
    $test_id = 'debug_' . time();
    $stmt = $conn->prepare("INSERT INTO stops (id, name, lat, lon) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssdd', $test_id, 'Debug Stop', 13.0, 80.0);
    $result = $stmt->execute();
    echo json_encode([
        'test_insert' => $result ? 'SUCCESS' : 'FAILED',
        'test_id' => $test_id,
        'error' => $stmt->error ?? null
    ]);
    $stmt->close();
    
    // Clean up
    $conn->query("DELETE FROM stops WHERE id = '$test_id'");
} catch (Exception $e) {
    echo json_encode(['exception' => $e->getMessage()]);
}

$conn->close();
?>
