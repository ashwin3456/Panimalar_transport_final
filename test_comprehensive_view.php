<?php
// Test the comprehensive bus view functionality
header('Content-Type: text/html');
echo "<h2>Testing Comprehensive Bus View</h2>";

try {
    require __DIR__ . '/Backend/db.php';
    echo "<p>✅ Database connected successfully</p>";
    
    // Step 1: Check if we need to add missing columns
    echo "<h3>Step 1: Checking Database Structure</h3>";
    
    $columns = [];
    $result = $conn->query("DESCRIBE buses");
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    $missingColumns = [];
    $requiredColumns = ['current_lat', 'current_lon', 'last_updated'];
    
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $columns)) {
            $missingColumns[] = $col;
        }
    }
    
    if (count($missingColumns) > 0) {
        echo "<p>⚠️ Missing columns: " . implode(', ', $missingColumns) . "</p>";
        echo "<p>Adding missing columns...</p>";
        
        foreach ($missingColumns as $col) {
            switch ($col) {
                case 'current_lat':
                    $conn->query("ALTER TABLE buses ADD COLUMN current_lat DECIMAL(10, 8) DEFAULT 0");
                    break;
                case 'current_lon':
                    $conn->query("ALTER TABLE buses ADD COLUMN current_lon DECIMAL(11, 8) DEFAULT 0");
                    break;
                case 'last_updated':
                    $conn->query("ALTER TABLE buses ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                    break;
            }
        }
        echo "<p>✅ Missing columns added</p>";
    } else {
        echo "<p>✅ All required columns exist</p>";
    }
    
    // Step 2: Check current data
    echo "<h3>Step 2: Current Database Data</h3>";
    
    $busCount = $conn->query("SELECT COUNT(*) as count FROM buses")->fetch_assoc()['count'];
    $stopCount = $conn->query("SELECT COUNT(*) as count FROM bus_stops")->fetch_assoc()['count'];
    $routeCount = $conn->query("SELECT COUNT(*) as count FROM routes")->fetch_assoc()['count'];
    
    echo "<p>Buses: <strong>$busCount</strong></p>";
    echo "<p>Stops: <strong>$stopCount</strong></p>";
    echo "<p>Routes: <strong>$routeCount</strong></p>";
    
    // Step 3: Test the comprehensive API
    echo "<h3>Step 3: Testing Comprehensive API</h3>";
    
    $apiUrl = "http://localhost/ash/Panimalar_bus_tracker_Asquad/public/api/realtime.php?action=get_comprehensive_view";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10
        ]
    ]);
    
    $apiResult = @file_get_contents($apiUrl, false, $context);
    
    if ($apiResult) {
        $apiData = json_decode($apiResult, true);
        if ($apiData && $apiData['success']) {
            echo "<p>✅ Comprehensive API working!</p>";
            echo "<p>Buses returned: " . count($apiData['buses']) . "</p>";
            echo "<p>Statistics: " . json_encode($apiData['statistics']) . "</p>";
            
            if (count($apiData['buses']) > 0) {
                echo "<h4>Sample Bus Data:</h4>";
                $sampleBus = $apiData['buses'][0];
                echo "<pre>";
                echo "Bus Name: " . $sampleBus['bus_name'] . "\n";
                echo "Route Number: " . ($sampleBus['route_number'] ?: 'Not set') . "\n";
                echo "Driver: " . $sampleBus['driver_name'] . "\n";
                echo "Total Stops: " . $sampleBus['total_stops'] . "\n";
                echo "Route: " . ($sampleBus['route_text'] ?: 'No route set') . "\n";
                echo "Boarding: " . $sampleBus['boarding_point'] . "\n";
                echo "End Point: " . $sampleBus['end_point'] . "\n";
                echo "</pre>";
            }
        } else {
            echo "<p>❌ API returned error: " . ($apiData['error'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p>❌ Could not connect to API</p>";
    }
    
    // Step 4: Insert sample data if none exists
    if ($busCount == 0) {
        echo "<h3>Step 4: Adding Sample Data</h3>";
        
        // Insert sample stops
        $conn->query("INSERT INTO bus_stops (stop_name, latitude, longitude) VALUES 
            ('College Main Gate', 13.1027, 80.2097),
            ('Anna Nagar Metro', 13.0850, 80.2101),
            ('Kilpauk Medical College', 13.0827, 80.2707),
            ('Central Railway Station', 13.0827, 80.2707),
            ('T. Nagar Bus Stand', 13.0418, 80.2341)");
        
        // Insert sample buses
        $conn->query("INSERT INTO buses (bus_name, route_number, status, current_lat, current_lon) VALUES 
            ('College Express 1', 'CE-01', 'active', 13.1027, 80.2097),
            ('Metro Connect', 'MC-01', 'active', 13.0850, 80.2101),
            ('City Shuttle', 'CS-01', 'inactive', 0, 0)");
        
        // Insert sample routes
        $conn->query("INSERT INTO routes (bus_id, stop_id, stop_order) VALUES 
            (1, 1, 1), (1, 2, 2), (1, 3, 3), (1, 4, 4),
            (2, 1, 1), (2, 2, 2), (2, 5, 3),
            (3, 2, 1), (3, 3, 2), (3, 4, 3), (3, 5, 4)");
        
        echo "<p>✅ Sample data added!</p>";
        echo "<p>Added 3 buses with complete routes</p>";
    }
    
    $conn->close();
    
    echo "<h3>✅ Test Complete!</h3>";
    echo "<p><strong>Your comprehensive bus view is ready!</strong></p>";
    
    echo "<h3>🔗 Quick Links:</h3>";
    echo "<ul>";
    echo "<li><a href='comprehensive_bus_view.php' target='_blank'>View Comprehensive Bus Table</a></li>";
    echo "<li><a href='public/api/realtime.php?action=get_comprehensive_view' target='_blank'>Test API Directly</a></li>";
    echo "<li><a href='public/admin_dashboard.html' target='_blank'>Admin Dashboard</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}
?>
