<?php
// Test script to check MySQL bus saving
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing MySQL Bus Save</h2>";

try {
    require __DIR__ . '/Backend/db.php';
    echo "✅ Database connected successfully<br>";
    
    // Test if tables exist
    $tables = ['buses', 'bus_stops', 'routes'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "✅ Table '$table' exists<br>";
        } else {
            echo "❌ Table '$table' missing<br>";
        }
    }
    
    // Check table structure
    echo "<h3>Buses Table Structure:</h3>";
    $result = $conn->query("DESCRIBE buses");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "- {$row['Field']} ({$row['Type']})<br>";
        }
    }
    
    // Test inserting a bus
    echo "<h3>Testing Bus Insert:</h3>";
    $testBusName = "Test Bus " . date('H:i:s');
    $testRoute = "TEST-" . rand(100, 999);
    
    $stmt = $conn->prepare("INSERT INTO buses (bus_name, route_number, status) VALUES (?, ?, 'active')");
    $stmt->bind_param('ss', $testBusName, $testRoute);
    
    if ($stmt->execute()) {
        $busId = $conn->insert_id;
        echo "✅ Bus inserted successfully! ID: $busId<br>";
        echo "   Name: $testBusName<br>";
        echo "   Route: $testRoute<br>";
        
        // Clean up test data
        $conn->query("DELETE FROM buses WHERE id = $busId");
        echo "✅ Test data cleaned up<br>";
    } else {
        echo "❌ Failed to insert bus: " . $stmt->error . "<br>";
    }
    $stmt->close();
    
    // Check current buses
    echo "<h3>Current Buses in Database:</h3>";
    $result = $conn->query("SELECT id, bus_name, route_number, status FROM buses ORDER BY id");
    if ($result && $result->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Route</th><th>Status</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['id']}</td><td>{$row['bus_name']}</td><td>{$row['route_number']}</td><td>{$row['status']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "No buses found in database<br>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><a href='public/admin_dashboard.html'>Back to Admin Dashboard</a>";
?>
