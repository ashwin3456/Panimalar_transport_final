<?php
// Real-time database-only API for Panimalar Bus Tracker
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database connection (use app config)
require_once __DIR__ . '/../config.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_all_data':
            getAllData();
            break;
        case 'save_buses':
            saveBuses();
            break;
        case 'get_buses':
            getBuses();
            break;
        case 'get_stops':
            getStops();
            break;
        case 'update_bus_location':
            updateBusLocation();
            break;
        case 'get_bus_locations':
            getBusLocations();
            break;
        case 'add_bus':
            addBus();
            break;
        case 'add_stop':
            addStop();
            break;
        case 'delete_bus':
            deleteBus();
            break;
        case 'delete_stop':
            deleteStop();
            break;
        case 'get_comprehensive_view':
            getComprehensiveView();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

function getAllData() {
    global $conn;

    if (!$conn || $conn->connect_error) {
        throw new Exception('Database connection failed: ' . ($conn->connect_error ?? 'Unknown'));
    }

    // Load stops
    $stops = [];
    $res = $conn->query("SELECT id, name, lat, lon FROM stops ORDER BY id");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $stops[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'lat' => (float)$row['lat'],
                'lon' => (float)$row['lon']
            ];
        }
        $res->close();
    }

    // Load drivers
    $drivers = [];
    $res = $conn->query("SELECT id, name FROM drivers ORDER BY name");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $drivers[] = [ 'id' => $row['id'], 'name' => $row['name'] ];
        }
        $res->close();
    }

    // Load buses with relations
    $buses = [];
    $res = $conn->query("SELECT id, name, route_no, color, boarding_stop_name, end_stop_name FROM buses ORDER BY name");
    if ($res) {
        while ($b = $res->fetch_assoc()) {
            // driverIds
            $driverIds = [];
            $ds = $conn->prepare("SELECT driver_id FROM bus_drivers WHERE bus_id = ?");
            $ds->bind_param('s', $b['id']);
            $ds->execute();
            $dsRes = $ds->get_result();
            while ($r = $dsRes->fetch_assoc()) { $driverIds[] = $r['driver_id']; }
            $ds->close();

            // ordered stops
            $stopIds = [];
            $ss = $conn->prepare("SELECT stop_id FROM bus_stops WHERE bus_id = ? ORDER BY position ASC");
            $ss->bind_param('s', $b['id']);
            $ss->execute();
            $ssRes = $ss->get_result();
            while ($r = $ssRes->fetch_assoc()) { $stopIds[] = $r['stop_id']; }
            $ss->close();

            $buses[] = [
                'id' => $b['id'],
                'name' => $b['name'],
                'routeNo' => $b['route_no'],
                'color' => $b['color'],
                'driverIds' => $driverIds,
                'stops' => $stopIds,
                'boardingName' => $b['boarding_stop_name'],
                'endName' => $b['end_stop_name']
            ];
        }
        $res->close();
    }

    // global route order (optional)
    $routeOrder = [];
    $res = $conn->query("SELECT stop_id FROM global_route_order ORDER BY position ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) { $routeOrder[] = $row['stop_id']; }
        $res->close();
    }

    echo json_encode([
        'buses' => $buses,
        'drivers' => $drivers,
        'stops' => $stops,
        'routeOrder' => $routeOrder,
        'timestamp' => time()
    ]);
}

function saveBuses() {
    global $conn;

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !is_array($input)) {
        throw new Exception('Invalid input data');
    }

    $buses = $input['buses'] ?? [];
    $stops = $input['stops'] ?? [];
    $drivers = $input['drivers'] ?? [];
    $routeOrder = $input['routeOrder'] ?? [];

    $conn->begin_transaction();
    try {
        // Upsert stops
        $stmtStop = $conn->prepare("INSERT INTO stops (id, name, lat, lon) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), lat=VALUES(lat), lon=VALUES(lon)");
        foreach ($stops as $s) {
            $id = (string)($s['id'] ?? '');
            $name = trim((string)($s['name'] ?? ''));
            $lat = (float)($s['lat'] ?? 0);
            $lon = (float)($s['lon'] ?? 0);
            if ($id === '' || $name === '') continue;
            $stmtStop->bind_param('ssdd', $id, $name, $lat, $lon);
            $stmtStop->execute();
        }
        $stmtStop->close();

        // Upsert drivers
        $stmtDrv = $conn->prepare("INSERT INTO drivers (id, name) VALUES (?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)");
        foreach ($drivers as $d) {
            $id = (string)($d['id'] ?? '');
            $name = trim((string)($d['name'] ?? ''));
            if ($id === '' || $name === '') continue;
            $stmtDrv->bind_param('ss', $id, $name);
            $stmtDrv->execute();
        }
        $stmtDrv->close();

        $newBuses = [];
        $existingBuses = [];

        // Upsert buses + relations
        $stmtBus = $conn->prepare("INSERT INTO buses (id, name, route_no, color, boarding_stop_name, end_stop_name) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), route_no=VALUES(route_no), color=VALUES(color), boarding_stop_name=VALUES(boarding_stop_name), end_stop_name=VALUES(end_stop_name)");
        foreach ($buses as $b) {
            $id = (string)($b['id'] ?? '');
            $name = trim((string)($b['name'] ?? ''));
            $routeNo = trim((string)($b['routeNo'] ?? ''));
            $color = (string)($b['color'] ?? null);
            $boardingName = $b['boardingName'] ?? null;
            $endName = $b['endName'] ?? null;
            if ($id === '' || $name === '') continue;

            // Track new vs existing
            $existsRes = $conn->prepare("SELECT 1 FROM buses WHERE id = ?");
            $existsRes->bind_param('s', $id);
            $existsRes->execute();
            $exists = $existsRes->get_result()->num_rows > 0;
            $existsRes->close();

            $stmtBus->bind_param('ssssss', $id, $name, $routeNo, $color, $boardingName, $endName);
            $stmtBus->execute();
            if ($exists) { $existingBuses[] = "$name ($routeNo)"; } else { $newBuses[] = "$name ($routeNo)"; }

            // Replace drivers
            $del = $conn->prepare("DELETE FROM bus_drivers WHERE bus_id = ?");
            $del->bind_param('s', $id);
            $del->execute();
            $del->close();
            if (!empty($b['driverIds']) && is_array($b['driverIds'])) {
                $ins = $conn->prepare("INSERT INTO bus_drivers (bus_id, driver_id) VALUES (?,?)");
                foreach ($b['driverIds'] as $drvId) {
                    $drvId = (string)$drvId; if ($drvId === '') continue;
                    $ins->bind_param('ss', $id, $drvId);
                    $ins->execute();
                }
                $ins->close();
            }

            // Replace ordered stops
            $del = $conn->prepare("DELETE FROM bus_stops WHERE bus_id = ?");
            $del->bind_param('s', $id);
            $del->execute();
            $del->close();
            if (!empty($b['stops']) && is_array($b['stops'])) {
                $pos = 1;
                $ins = $conn->prepare("INSERT INTO bus_stops (bus_id, stop_id, position) VALUES (?,?,?)");
                foreach ($b['stops'] as $sid) {
                    $sid = (string)$sid; if ($sid === '') continue;
                    $ins->bind_param('ssi', $id, $sid, $pos++);
                    $ins->execute();
                }
                $ins->close();
            }
        }
        $stmtBus->close();

        // Replace global route order
        $conn->query("DELETE FROM global_route_order");
        if (!empty($routeOrder) && is_array($routeOrder)) {
            $pos = 1;
            $ins = $conn->prepare("INSERT INTO global_route_order (position, stop_id) VALUES (?,?)");
            foreach ($routeOrder as $sid) {
                $sid = (string)$sid; if ($sid === '') continue;
                $ins->bind_param('is', $pos++, $sid);
                $ins->execute();
            }
            $ins->close();
        }

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Data saved successfully',
            'new_buses' => $newBuses,
            'existing_buses' => $existingBuses,
            'timestamp' => time()
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function updateBusLocation() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $busId = $input['bus_id'] ?? '';
    $status = $input['status'] ?? 'active';
    
    if (!$busId) {
        throw new Exception('Bus ID is required');
    }
    
    // Update status only (location tracking not implemented in new schema yet)
    $stmt = $conn->prepare("UPDATE buses SET updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('s', $busId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Bus updated successfully',
            'timestamp' => time()
        ]);
    } else {
        throw new Exception('Failed to update bus');
    }
    $stmt->close();
}

function getBusLocations() {
    global $conn;
    
    $result = $conn->query("
        SELECT b.id, b.name, b.route_no, b.updated_at
        FROM buses b 
        ORDER BY b.updated_at DESC
    ");
    
    $locations = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $locations[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'route' => $row['route_no'],
                'lastUpdated' => $row['updated_at']
            ];
        }
    }
    
    echo json_encode([
        'locations' => $locations,
        'timestamp' => time()
    ]);
}

function addBus() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $name = trim($input['name'] ?? '');
    $routeNo = trim($input['routeNo'] ?? '');
    
    if (empty($name) || empty($routeNo)) {
        throw new Exception('Bus name and route number are required');
    }
    
    $stmt = $conn->prepare("INSERT INTO buses (bus_name, route_number, status, current_lat, current_lon, last_updated) VALUES (?, ?, 'active', 0, 0, NOW())");
    $stmt->bind_param('ss', $name, $routeNo);
    
    if ($stmt->execute()) {
        $busId = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'bus_id' => $busId,
            'message' => 'Bus added successfully'
        ]);
    } else {
        throw new Exception('Failed to add bus');
    }
    $stmt->close();
}

function addStop() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $name = trim($input['name'] ?? '');
    $lat = (float)($input['lat'] ?? 0);
    $lon = (float)($input['lon'] ?? 0);
    
    if (empty($name) || !$lat || !$lon) {
        throw new Exception('Stop name, latitude, and longitude are required');
    }
    
    $stmt = $conn->prepare("INSERT INTO bus_stops (stop_name, latitude, longitude) VALUES (?, ?, ?)");
    $stmt->bind_param('sdd', $name, $lat, $lon);
    
    if ($stmt->execute()) {
        $stopId = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'stop_id' => $stopId,
            'message' => 'Stop added successfully'
        ]);
    } else {
        throw new Exception('Failed to add stop');
    }
    $stmt->close();
}

function deleteBus() {
    global $conn;
    
    $busId = (int)($_GET['id'] ?? 0);
    if (!$busId) {
        throw new Exception('Bus ID is required');
    }
    
    $conn->autocommit(false);
    try {
        // Delete routes first
        $stmt = $conn->prepare("DELETE FROM routes WHERE bus_id = ?");
        $stmt->bind_param('i', $busId);
        $stmt->execute();
        $stmt->close();
        
        // Delete bus
        $stmt = $conn->prepare("DELETE FROM buses WHERE id = ?");
        $stmt->bind_param('i', $busId);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Bus deleted successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function deleteStop() {
    global $conn;
    
    $stopId = (int)($_GET['id'] ?? 0);
    if (!$stopId) {
        throw new Exception('Stop ID is required');
    }
    
    $conn->autocommit(false);
    try {
        // Delete routes first
        $stmt = $conn->prepare("DELETE FROM routes WHERE stop_id = ?");
        $stmt->bind_param('i', $stopId);
        $stmt->execute();
        $stmt->close();
        
        // Delete stop
        $stmt = $conn->prepare("DELETE FROM bus_stops WHERE id = ?");
        $stmt->bind_param('i', $stopId);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Stop deleted successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function getComprehensiveView() {
    global $conn;
    
    // Get comprehensive bus data with all details
    $query = "
        SELECT 
            b.id as bus_id,
            b.name as bus_name,
            b.route_no,
            b.color,
            b.boarding_stop_name,
            b.end_stop_name,
            b.created_at,
            
            -- Count of stops
            (SELECT COUNT(*) FROM bus_stops WHERE bus_id = b.id) as total_stops
            
        FROM buses b
        ORDER BY b.name
    ";
    
    $result = $conn->query($query);
    $buses = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Get detailed route stops for each bus
            $routeQuery = "
                SELECT s.id, s.name, s.lat, s.lon, bs.position 
                FROM bus_stops bs 
                JOIN stops s ON bs.stop_id = s.id 
                WHERE bs.bus_id = ? 
                ORDER BY bs.position
            ";
            $stmt = $conn->prepare($routeQuery);
            $stmt->bind_param('s', $row['bus_id']);
            $stmt->execute();
            $routeResult = $stmt->get_result();
            
            $stops = [];
            $routeText = [];
            while ($stop = $routeResult->fetch_assoc()) {
                $stops[] = [
                    'id' => $stop['id'],
                    'name' => $stop['name'],
                    'lat' => (float)$stop['lat'],
                    'lon' => (float)$stop['lon'],
                    'position' => (int)$stop['position']
                ];
                $routeText[] = $stop['position'] . '. ' . $stop['name'];
            }
            $stmt->close();
            
            // Get drivers for this bus
            $driverQuery = "SELECT d.id, d.name FROM drivers d 
                           JOIN bus_drivers bd ON d.id = bd.driver_id 
                           WHERE bd.bus_id = ?";
            $stmt = $conn->prepare($driverQuery);
            $stmt->bind_param('s', $row['bus_id']);
            $stmt->execute();
            $driverResult = $stmt->get_result();
            
            $drivers = [];
            while ($driver = $driverResult->fetch_assoc()) {
                $drivers[] = [
                    'id' => $driver['id'],
                    'name' => $driver['name']
                ];
            }
            $stmt->close();
            
            $row['route_stops'] = $stops;
            $row['route_text'] = implode(' → ', $routeText);
            $row['drivers'] = $drivers;
            $row['boarding_point'] = count($stops) > 0 ? $stops[0]['name'] : 'Not Set';
            $row['end_point'] = count($stops) > 0 ? end($stops)['name'] : 'Not Set';
            
            $buses[] = $row;
        }
    }
    
    // Get summary statistics
    $stats = [
        'total_buses' => count($buses),
        'total_stops' => array_sum(array_column($buses, 'total_stops')),
        'buses_with_routes' => count(array_filter($buses, function($b) { return $b['total_stops'] > 0; }))
    ];
    
    echo json_encode([
        'success' => true,
        'buses' => $buses,
        'statistics' => $stats,
        'timestamp' => time()
    ]);
}

$conn->close();
?>
