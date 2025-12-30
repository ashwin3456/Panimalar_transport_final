<?php
// Quick database debug script
header('Content-Type: application/json');

try {
    require __DIR__ . '/Backend/db.php';
    
    $debug = [];
    $debug['connection'] = 'success';
    
    // Check if tables exist
    $tables = ['buses', 'bus_stops', 'routes'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        $debug['tables'][$table] = ($result && $result->num_rows > 0) ? 'exists' : 'missing';
    }
    
    // Check table structures
    $result = $conn->query("DESCRIBE buses");
    $debug['buses_structure'] = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $debug['buses_structure'][] = $row['Field'] . ' (' . $row['Type'] . ')';
        }
    }
    
    // Count records
    $result = $conn->query("SELECT COUNT(*) as count FROM buses");
    $debug['buses_count'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM bus_stops");
    $debug['stops_count'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM routes");
    $debug['routes_count'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Show recent buses
    $result = $conn->query("SELECT id, bus_name, route_number, status FROM buses ORDER BY id DESC LIMIT 5");
    $debug['recent_buses'] = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $debug['recent_buses'][] = $row;
        }
    }
    
    echo json_encode($debug, JSON_PRETTY_PRINT);
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'connection' => 'failed',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
?>
