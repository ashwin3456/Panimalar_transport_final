<?php
// Test script to check why Load from Server is not working
header('Content-Type: application/json');

echo "<h2>Testing Load from Server Issue</h2>";

try {
    require __DIR__ . '/Backend/db.php';
    echo "<p>✅ Database connected successfully</p>";
    
    // Test 1: Check if buses table exists and has data
    echo "<h3>1. Checking Buses Table:</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM buses");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "<p>Total buses in database: <strong>$count</strong></p>";
        
        if ($count > 0) {
            echo "<h4>Sample buses:</h4>";
            $result = $conn->query("SELECT id, bus_name, route_number, status FROM buses LIMIT 5");
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Name</th><th>Route</th><th>Status</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr><td>{$row['id']}</td><td>{$row['bus_name']}</td><td>{$row['route_number']}</td><td>{$row['status']}</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p>❌ No buses found in database!</p>";
        }
    }
    
    // Test 2: Check if bus_stops table exists and has data
    echo "<h3>2. Checking Bus Stops Table:</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM bus_stops");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "<p>Total stops in database: <strong>$count</strong></p>";
        
        if ($count > 0) {
            echo "<h4>Sample stops:</h4>";
            $result = $conn->query("SELECT id, stop_name, latitude, longitude FROM bus_stops LIMIT 5");
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Name</th><th>Latitude</th><th>Longitude</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr><td>{$row['id']}</td><td>{$row['stop_name']}</td><td>{$row['latitude']}</td><td>{$row['longitude']}</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p>❌ No stops found in database!</p>";
        }
    }
    
    // Test 3: Test the realtime API directly
    echo "<h3>3. Testing Realtime API:</h3>";
    $apiUrl = "http://localhost/ash/Panimalar_bus_tracker_Asquad/public/api/realtime.php?action=get_all_data";
    echo "<p>API URL: <a href='$apiUrl' target='_blank'>$apiUrl</a></p>";
    
    // Test 4: Check if columns exist
    echo "<h3>4. Checking Table Structure:</h3>";
    $result = $conn->query("DESCRIBE buses");
    if ($result) {
        echo "<h4>Buses table columns:</h4>";
        echo "<ul>";
        while ($row = $result->fetch_assoc()) {
            echo "<li>{$row['Field']} ({$row['Type']})</li>";
        }
        echo "</ul>";
    }
    
    // Test 5: Insert sample data if none exists
    $busCount = $conn->query("SELECT COUNT(*) as count FROM buses")->fetch_assoc()['count'];
    if ($busCount == 0) {
        echo "<h3>5. Inserting Sample Data:</h3>";
        
        // Insert sample stops
        $conn->query("INSERT INTO bus_stops (stop_name, latitude, longitude) VALUES 
            ('College Gate', 13.1027, 80.2097),
            ('Anna Nagar', 13.0850, 80.2101),
            ('Kilpauk', 13.0827, 80.2707)");
        
        // Insert sample buses
        $conn->query("INSERT INTO buses (bus_name, route_number, status) VALUES 
            ('College Express 1', 'CE-01', 'active'),
            ('Metro Connect', 'MC-01', 'active')");
        
        echo "<p>✅ Sample data inserted!</p>";
        
        // Insert sample routes
        $conn->query("INSERT INTO routes (bus_id, stop_id, stop_order) VALUES 
            (1, 1, 1), (1, 2, 2), (1, 3, 3),
            (2, 1, 1), (2, 3, 2)");
        
        echo "<p>✅ Sample routes created!</p>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}

echo "<br><br>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><a href='public/api/realtime.php?action=get_all_data' target='_blank'>Test Realtime API</a></li>";
echo "<li><a href='public/admin_dashboard.html' target='_blank'>Go to Admin Dashboard</a></li>";
echo "<li>Try clicking 'Load from Server' button</li>";
echo "</ol>";
?>
