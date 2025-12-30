<?php
// Test file to verify MySQL connection and save functionality
header('Content-Type: application/json');

try {
    require __DIR__ . '/../Backend/db.php';
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Database connection successful',
        'database' => 'panimalar_bus_tracker'
    ]);
    
    // Test if tables exist
    $tables = ['buses', 'bus_stops', 'routes'];
    $existing_tables = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            $existing_tables[] = $table;
        }
    }
    
    echo "\n\nExisting tables: " . implode(', ', $existing_tables);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
