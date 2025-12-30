<?php
// Fix missing database columns
header('Content-Type: text/html');
echo "<h2>Fixing Database Columns for Real-time Tracking</h2>";

try {
    require __DIR__ . '/Backend/db.php';
    echo "<p>✅ Database connected successfully</p>";
    
    // Check current table structure
    echo "<h3>Current buses table structure:</h3>";
    $result = $conn->query("DESCRIBE buses");
    $existingColumns = [];
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        $existingColumns[] = $row['Field'];
        echo "<li>{$row['Field']} ({$row['Type']})</li>";
    }
    echo "</ul>";
    
    // Check if location columns exist
    $needsCurrentLat = !in_array('current_lat', $existingColumns);
    $needsCurrentLon = !in_array('current_lon', $existingColumns);
    $needsLastUpdated = !in_array('last_updated', $existingColumns);
    
    echo "<h3>Adding missing columns:</h3>";
    
    // Add current_lat column
    if ($needsCurrentLat) {
        $conn->query("ALTER TABLE buses ADD COLUMN current_lat DECIMAL(10, 8) DEFAULT 0");
        echo "<p>✅ Added current_lat column</p>";
    } else {
        echo "<p>✓ current_lat column already exists</p>";
    }
    
    // Add current_lon column
    if ($needsCurrentLon) {
        $conn->query("ALTER TABLE buses ADD COLUMN current_lon DECIMAL(11, 8) DEFAULT 0");
        echo "<p>✅ Added current_lon column</p>";
    } else {
        echo "<p>✓ current_lon column already exists</p>";
    }
    
    // Add last_updated column
    if ($needsLastUpdated) {
        $conn->query("ALTER TABLE buses ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "<p>✅ Added last_updated column</p>";
    } else {
        echo "<p>✓ last_updated column already exists</p>";
    }
    
    // Show updated table structure
    echo "<h3>Updated buses table structure:</h3>";
    $result = $conn->query("DESCRIBE buses");
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li><strong>{$row['Field']}</strong> ({$row['Type']})</li>";
    }
    echo "</ul>";
    
    // Test the API now
    echo "<h3>Testing Real-time API:</h3>";
    $apiUrl = "http://localhost/ash/Panimalar_bus_tracker_Asquad/public/api/realtime.php?action=get_all_data";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10
        ]
    ]);
    
    $apiResult = @file_get_contents($apiUrl, false, $context);
    
    if ($apiResult) {
        $apiData = json_decode($apiResult, true);
        if ($apiData && !isset($apiData['error'])) {
            echo "<p>✅ Real-time API is working!</p>";
            echo "<p>Buses found: " . count($apiData['buses']) . "</p>";
            echo "<p>Stops found: " . count($apiData['stops']) . "</p>";
        } else {
            echo "<p>❌ API returned error: " . ($apiData['error'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p>❌ Could not connect to API</p>";
    }
    
    $conn->close();
    
    echo "<h3>✅ Database Fix Complete!</h3>";
    echo "<p><strong>Your database now supports real-time location tracking!</strong></p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}

echo "<br><br>";
echo "<p><a href='public/admin_dashboard.html'>Go to Admin Dashboard</a></p>";
echo "<p><a href='public/api/realtime.php?action=get_all_data' target='_blank'>Test Real-time API</a></p>";
?>
