<?php
// Comprehensive Bus View - Shows all bus data in one table
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprehensive Bus View - Panimalar Bus Tracker</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white; 
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
        }
        .header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        .controls {
            padding: 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        
        .table-container {
            overflow-x: auto;
            padding: 20px;
        }
        .bus-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .bus-table th {
            background: #1f2937;
            color: white;
            padding: 15px 10px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .bus-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .bus-table tr:hover {
            background: #f3f4f6;
        }
        .bus-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 16px;
        }
        .route-number {
            background: #3b82f6;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
        }
        .status {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status.active { background: #d1fae5; color: #065f46; }
        .status.inactive { background: #fee2e2; color: #991b1b; }
        .status.maintenance { background: #fef3c7; color: #92400e; }
        
        .route-stops {
            max-width: 300px;
            line-height: 1.4;
            color: #374151;
        }
        .driver-info {
            color: #6b7280;
        }
        .location-info {
            font-size: 12px;
            color: #6b7280;
        }
        .boarding-point, .end-point {
            font-weight: 600;
        }
        .boarding-point { color: #059669; }
        .end-point { color: #dc2626; }
        
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            min-width: 150px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2563eb;
        }
        .stat-label {
            color: #6b7280;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }
        
        .refresh-info {
            text-align: center;
            padding: 10px;
            background: #f0f9ff;
            color: #0369a1;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚌 Comprehensive Bus View</h1>
            <p>Complete overview of all buses with routes, drivers, and stops</p>
        </div>
        
        <div class="controls">
            <a href="public/admin_dashboard.html" class="btn btn-primary">Admin Dashboard</a>
            <a href="?" class="btn btn-success">Refresh Data</a>
            <a href="fix_database_columns.php" class="btn btn-secondary">Fix Database</a>
            <span style="margin-left: auto; color: #6b7280;">
                Last Updated: <?php echo date('Y-m-d H:i:s'); ?>
            </span>
        </div>

        <?php
        try {
            require __DIR__ . '/Backend/db.php';
            
            // Get comprehensive bus data
            $query = "
                SELECT 
                    b.id as bus_id,
                    b.bus_name,
                    b.route_number,
                    b.status,
                    COALESCE(b.current_lat, 0) as current_lat,
                    COALESCE(b.current_lon, 0) as current_lon,
                    COALESCE(b.last_updated, b.created_at) as last_updated,
                    
                    -- Driver information
                    COALESCE(u.name, 'No Driver Assigned') as driver_name,
                    COALESCE(u.phone_number, '-') as driver_phone,
                    
                    -- Count of stops
                    COUNT(DISTINCT r.stop_id) as total_stops,
                    
                    b.created_at
                    
                FROM buses b
                LEFT JOIN users u ON b.driver_id = u.id
                LEFT JOIN routes r ON b.id = r.bus_id
                GROUP BY b.id, b.bus_name, b.route_number, b.status, b.current_lat, b.current_lon, 
                         b.last_updated, u.name, u.phone_number, b.created_at
                ORDER BY b.id
            ";
            
            $result = $conn->query($query);
            
            if ($result && $result->num_rows > 0) {
                $buses = [];
                $totalBuses = 0;
                $activeBuses = 0;
                $totalStops = 0;
                
                while ($row = $result->fetch_assoc()) {
                    $buses[] = $row;
                    $totalBuses++;
                    if ($row['status'] == 'active') $activeBuses++;
                    $totalStops += $row['total_stops'];
                }
                
                // Display statistics
                echo '<div class="stats">';
                echo '<div class="stat-card">';
                echo '<div class="stat-number">' . $totalBuses . '</div>';
                echo '<div class="stat-label">Total Buses</div>';
                echo '</div>';
                echo '<div class="stat-card">';
                echo '<div class="stat-number">' . $activeBuses . '</div>';
                echo '<div class="stat-label">Active Buses</div>';
                echo '</div>';
                echo '<div class="stat-card">';
                echo '<div class="stat-number">' . $totalStops . '</div>';
                echo '<div class="stat-label">Total Route Stops</div>';
                echo '</div>';
                echo '</div>';
                
                echo '<div class="table-container">';
                echo '<table class="bus-table">';
                echo '<thead>';
                echo '<tr>';
                echo '<th>Bus Details</th>';
                echo '<th>Route Info</th>';
                echo '<th>Driver</th>';
                echo '<th>Route Stops</th>';
                echo '<th>Boarding Point</th>';
                echo '<th>End Point</th>';
                echo '<th>Location</th>';
                echo '<th>Status</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                foreach ($buses as $bus) {
                    echo '<tr>';
                    
                    // Bus Details
                    echo '<td>';
                    echo '<div class="bus-name">' . htmlspecialchars($bus['bus_name']) . '</div>';
                    echo '<div style="font-size: 12px; color: #6b7280;">ID: ' . $bus['bus_id'] . '</div>';
                    echo '</td>';
                    
                    // Route Info
                    echo '<td>';
                    if ($bus['route_number']) {
                        echo '<span class="route-number">' . htmlspecialchars($bus['route_number']) . '</span>';
                    } else {
                        echo '<span style="color: #6b7280;">No Route #</span>';
                    }
                    echo '</td>';
                    
                    // Driver
                    echo '<td class="driver-info">';
                    echo '<div>' . htmlspecialchars($bus['driver_name']) . '</div>';
                    if ($bus['driver_phone'] != '-') {
                        echo '<div style="font-size: 12px;">' . htmlspecialchars($bus['driver_phone']) . '</div>';
                    }
                    echo '</td>';
                    
                    // Route Stops - Get detailed route
                    echo '<td class="route-stops">';
                    $routeQuery = "
                        SELECT bs.stop_name, r.stop_order 
                        FROM routes r 
                        JOIN bus_stops bs ON r.stop_id = bs.id 
                        WHERE r.bus_id = ? 
                        ORDER BY r.stop_order
                    ";
                    $stmt = $conn->prepare($routeQuery);
                    $stmt->bind_param('i', $bus['bus_id']);
                    $stmt->execute();
                    $routeResult = $stmt->get_result();
                    
                    $stops = [];
                    while ($stop = $routeResult->fetch_assoc()) {
                        $stops[] = $stop['stop_order'] . '. ' . $stop['stop_name'];
                    }
                    
                    if (count($stops) > 0) {
                        echo implode(' → ', $stops);
                        echo '<div style="font-size: 12px; color: #6b7280; margin-top: 5px;">';
                        echo 'Total: ' . count($stops) . ' stops';
                        echo '</div>';
                    } else {
                        echo '<span style="color: #6b7280;">No stops assigned</span>';
                    }
                    $stmt->close();
                    echo '</td>';
                    
                    // Boarding Point (placeholder - you can enhance this)
                    echo '<td class="boarding-point">';
                    if (count($stops) > 0) {
                        echo explode('. ', $stops[0])[1] ?? 'First Stop';
                    } else {
                        echo '<span style="color: #6b7280;">Not Set</span>';
                    }
                    echo '</td>';
                    
                    // End Point (placeholder - you can enhance this)
                    echo '<td class="end-point">';
                    if (count($stops) > 0) {
                        $lastStop = end($stops);
                        echo explode('. ', $lastStop)[1] ?? 'Last Stop';
                    } else {
                        echo '<span style="color: #6b7280;">Not Set</span>';
                    }
                    echo '</td>';
                    
                    // Location
                    echo '<td class="location-info">';
                    if ($bus['current_lat'] != 0 || $bus['current_lon'] != 0) {
                        echo 'Lat: ' . number_format($bus['current_lat'], 4) . '<br>';
                        echo 'Lon: ' . number_format($bus['current_lon'], 4) . '<br>';
                        echo '<small>Updated: ' . date('H:i', strtotime($bus['last_updated'])) . '</small>';
                    } else {
                        echo '<span style="color: #6b7280;">No location data</span>';
                    }
                    echo '</td>';
                    
                    // Status
                    echo '<td>';
                    echo '<span class="status ' . $bus['status'] . '">' . ucfirst($bus['status']) . '</span>';
                    echo '</td>';
                    
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
                echo '</div>';
                
            } else {
                echo '<div class="no-data">';
                echo '<h3>No buses found in database</h3>';
                echo '<p>Add some buses using the Admin Dashboard first.</p>';
                echo '<a href="public/admin_dashboard.html" class="btn btn-primary">Go to Admin Dashboard</a>';
                echo '</div>';
            }
            
            $conn->close();
            
        } catch (Exception $e) {
            echo '<div style="padding: 20px; background: #fee2e2; color: #991b1b; border-radius: 8px; margin: 20px;">';
            echo '<h3>Database Error</h3>';
            echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p>Make sure XAMPP MySQL is running and run the database fix script.</p>';
            echo '<a href="fix_database_columns.php" class="btn btn-secondary">Fix Database</a>';
            echo '</div>';
        }
        ?>
        
        <div class="refresh-info">
            💡 This page shows real-time data from your MySQL database. 
            Click "Refresh Data" to see the latest updates.
        </div>
    </div>
</body>
</html>
