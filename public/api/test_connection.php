<?php
// Test database connection and tables
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$result = [
    'connection' => false,
    'database' => false,
    'tables' => []
];

// Test connection
if ($conn && !$conn->connect_error) {
    $result['connection'] = true;
    
    // Test database selection
    $db_check = $conn->query("SELECT DATABASE()");
    if ($db_check) {
        $db_name = $db_check->fetch_row()[0];
        $result['database'] = $db_name;
        
        // Check required tables
        $required_tables = ['stops', 'drivers', 'buses', 'bus_drivers', 'bus_stops', 'global_route_order'];
        foreach ($required_tables as $table) {
            $check = $conn->query("SHOW TABLES LIKE '$table'");
            $result['tables'][$table] = ($check && $check->num_rows > 0);
        }
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);
$conn->close();
?>
