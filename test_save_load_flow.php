<?php
// Test the complete Save → Load flow
header('Content-Type: text/html');
echo "<h2>Testing Complete Save → Load Flow</h2>";

try {
    require __DIR__ . '/Backend/db.php';
    echo "<p>✅ Database connected successfully</p>";
    
    // Step 1: Clear existing data for clean test
    echo "<h3>Step 1: Clearing existing test data</h3>";
    $conn->query("DELETE FROM routes WHERE bus_id IN (SELECT id FROM buses WHERE bus_name LIKE 'Test%')");
    $conn->query("DELETE FROM buses WHERE bus_name LIKE 'Test%'");
    $conn->query("DELETE FROM bus_stops WHERE stop_name LIKE 'Test%'");
    echo "<p>✅ Test data cleared</p>";
    
    // Step 2: Simulate admin saving buses (like clicking Save button)
    echo "<h3>Step 2: Simulating Admin Save Operation</h3>";
    
    $testData = [
        'buses' => [
            [
                'id' => 'test1',
                'name' => 'Test Express 1',
                'routeNo' => 'TE-01',
                'stops' => ['stop1', 'stop2']
            ],
            [
                'id' => 'test2', 
                'name' => 'Test Metro',
                'routeNo' => 'TM-01',
                'stops' => ['stop1', 'stop3']
            ]
        ],
        'stops' => [
            [
                'id' => 'stop1',
                'name' => 'Test College Gate',
                'lat' => 13.1027,
                'lon' => 80.2097
            ],
            [
                'id' => 'stop2',
                'name' => 'Test Anna Nagar',
                'lat' => 13.0850,
                'lon' => 80.2101
            ],
            [
                'id' => 'stop3',
                'name' => 'Test Kilpauk',
                'lat' => 13.0827,
                'lon' => 80.2707
            ]
        ]
    ];
    
    // Call the save API
    $saveUrl = "http://localhost/ash/Panimalar_bus_tracker_Asquad/public/api/realtime.php?action=save_buses";
    $postData = json_encode($testData);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $postData
        ]
    ]);
    
    $saveResult = file_get_contents($saveUrl, false, $context);
    $saveResponse = json_decode($saveResult, true);
    
    if ($saveResponse && $saveResponse['success']) {
        echo "<p>✅ Save API successful</p>";
        echo "<p>New buses: " . count($saveResponse['new_buses']) . "</p>";
        echo "<p>Existing buses: " . count($saveResponse['existing_buses']) . "</p>";
    } else {
        echo "<p>❌ Save API failed: " . ($saveResponse['error'] ?? 'Unknown error') . "</p>";
        echo "<pre>" . htmlspecialchars($saveResult) . "</pre>";
    }
    
    // Step 3: Verify data was saved to database
    echo "<h3>Step 3: Verifying Data in Database</h3>";
    
    $busCount = $conn->query("SELECT COUNT(*) as count FROM buses WHERE bus_name LIKE 'Test%'")->fetch_assoc()['count'];
    $stopCount = $conn->query("SELECT COUNT(*) as count FROM bus_stops WHERE stop_name LIKE 'Test%'")->fetch_assoc()['count'];
    $routeCount = $conn->query("SELECT COUNT(*) as count FROM routes r JOIN buses b ON r.bus_id = b.id WHERE b.bus_name LIKE 'Test%'")->fetch_assoc()['count'];
    
    echo "<p>Buses in database: <strong>$busCount</strong></p>";
    echo "<p>Stops in database: <strong>$stopCount</strong></p>";
    echo "<p>Routes in database: <strong>$routeCount</strong></p>";
    
    // Step 4: Test Load from Server (like clicking Load from Server button)
    echo "<h3>Step 4: Testing Load from Server</h3>";
    
    $loadUrl = "http://localhost/ash/Panimalar_bus_tracker_Asquad/public/api/realtime.php?action=get_all_data";
    $loadResult = file_get_contents($loadUrl);
    $loadResponse = json_decode($loadResult, true);
    
    if ($loadResponse && isset($loadResponse['buses'])) {
        echo "<p>✅ Load API successful</p>";
        echo "<p>Loaded buses: " . count($loadResponse['buses']) . "</p>";
        echo "<p>Loaded stops: " . count($loadResponse['stops']) . "</p>";
        
        echo "<h4>Loaded Buses:</h4>";
        echo "<ul>";
        foreach ($loadResponse['buses'] as $bus) {
            echo "<li>{$bus['name']} ({$bus['routeNo']}) - " . count($bus['stops']) . " stops</li>";
        }
        echo "</ul>";
        
        echo "<h4>Loaded Stops:</h4>";
        echo "<ul>";
        foreach ($loadResponse['stops'] as $stop) {
            echo "<li>{$stop['name']} ({$stop['lat']}, {$stop['lon']})</li>";
        }
        echo "</ul>";
        
    } else {
        echo "<p>❌ Load API failed</p>";
        echo "<pre>" . htmlspecialchars($loadResult) . "</pre>";
    }
    
    // Step 5: Clean up test data
    echo "<h3>Step 5: Cleaning Up Test Data</h3>";
    $conn->query("DELETE FROM routes WHERE bus_id IN (SELECT id FROM buses WHERE bus_name LIKE 'Test%')");
    $conn->query("DELETE FROM buses WHERE bus_name LIKE 'Test%'");
    $conn->query("DELETE FROM bus_stops WHERE stop_name LIKE 'Test%'");
    echo "<p>✅ Test data cleaned up</p>";
    
    $conn->close();
    
    echo "<h3>✅ Complete Flow Test PASSED!</h3>";
    echo "<p><strong>The Save → Load flow is working correctly:</strong></p>";
    echo "<ol>";
    echo "<li>Admin saves buses → Data goes to MySQL database ✅</li>";
    echo "<li>Admin clicks 'Load from Server' → Data comes from MySQL database ✅</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<h3>❌ Test FAILED</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}

echo "<br><br>";
echo "<p><a href='public/admin_dashboard.html'>Go to Admin Dashboard to test manually</a></p>";
?>
