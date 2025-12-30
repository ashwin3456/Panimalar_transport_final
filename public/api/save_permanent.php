<?php
// Permanent save for admin dashboard data
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';

// Ensure we have a valid mysqli connection and that it throws exceptions
if (!isset($conn) && isset($GLOBALS['conn'])) {
    $conn = $GLOBALS['conn'];
}

if (!($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection not available'
    ]);
    exit();
}

// Make mysqli throw exceptions instead of PHP warnings/notices
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input data']);
    exit();
}

$buses = $input['buses'] ?? [];
$stops = $input['stops'] ?? [];
$drivers = $input['drivers'] ?? [];
$routeOrder = $input['routeOrder'] ?? [];

$result = [
    'success' => false,
    'message' => '',
    'new_buses' => [],
    'existing_buses' => [],
    'stats' => [
        'buses_saved' => 0,
        'stops_saved' => 0,
        'drivers_saved' => 0
    ]
];

try {
    $conn->begin_transaction();
    
    // 1. Save stops
    if (!empty($stops)) {
        $stmtStop = $conn->prepare("INSERT INTO stops (id, name, lat, lon) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), lat=VALUES(lat), lon=VALUES(lon)");
        foreach ($stops as $s) {
            $id = (string)($s['id'] ?? '');
            $name = trim((string)($s['name'] ?? ''));
            $lat = (float)($s['lat'] ?? 0);
            $lon = (float)($s['lon'] ?? 0);
            if ($id === '' || $name === '') continue;
            
            $stmtStop->bind_param('ssdd', $id, $name, $lat, $lon);
            if ($stmtStop->execute()) {
                $result['stats']['stops_saved']++;
            }
        }
        $stmtStop->close();
    }
    
    // 2. Save drivers
    if (!empty($drivers)) {
        $stmtDrv = $conn->prepare("INSERT INTO drivers (id, name) VALUES (?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)");
        foreach ($drivers as $d) {
            $id = (string)($d['id'] ?? '');
            $name = trim((string)($d['name'] ?? ''));
            if ($id === '' || $name === '') continue;
            
            $stmtDrv->bind_param('ss', $id, $name);
            if ($stmtDrv->execute()) {
                $result['stats']['drivers_saved']++;
            }
        }
        $stmtDrv->close();
    }
    
    // Function to convert stop ID to name
function getStopNameById($conn, $stopId) {
    if (empty($stopId)) return null;
    
    // If it's already a name (contains letters), return as-is
    if (!is_numeric($stopId)) {
        return $stopId;
    }
    
    // Try to get name from stops table
    $stmt = $conn->prepare("SELECT name FROM stops WHERE id = ?");
    $stmt->bind_param('s', $stopId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['name'];
    }
    
    $stmt->close();
    return $stopId; // Return as-is if not found
}

    // 3. Save buses
    if (!empty($buses)) {
        $stmtBus = $conn->prepare("INSERT INTO buses (id, name, route_no, color, boarding_stop_name, end_stop_name) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), route_no=VALUES(route_no), color=VALUES(color), boarding_stop_name=VALUES(boarding_stop_name), end_stop_name=VALUES(end_stop_name)");
        
        foreach ($buses as $b) {
            $id = (string)($b['id'] ?? '');
            $name = trim((string)($b['name'] ?? ''));
            $routeNo = trim((string)($b['routeNo'] ?? ''));
            $color = (string)($b['color'] ?? null);
            // Handle all possible input formats - use names directly
$boardingName = $b['boardingName'] ?? $b['boardingname'] ?? $b['boardingId'] ?? $b['boarding_id'] ?? null;
$endName = $b['endName'] ?? $b['endname'] ?? $b['endId'] ?? $b['end_id'] ?? null;

// Debug logging
error_log("SAVE_DEBUG: Bus ID=$id, BoardingName=" . ($boardingName ?? 'NULL') . ", EndName=" . ($endName ?? 'NULL'));
            
            if ($id === '' || $name === '') continue;

            // Check if bus exists
            $check = $conn->prepare("SELECT 1 FROM buses WHERE id = ?");
            $check->bind_param('s', $id);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            $stmtBus->bind_param('ssssss', $id, $name, $routeNo, $color, $boardingName, $endName);
            
// Log before execution
error_log("SAVE_DEBUG: Executing SQL with params: ID=$id, Name=$name, BoardingName=" . ($boardingName ?? 'NULL') . ", EndName=" . ($endName ?? 'NULL'));
            
if ($stmtBus->execute()) {
    error_log("SAVE_DEBUG: SUCCESS - Bus saved");
    $result['stats']['buses_saved']++;
    if ($exists) {
        $result['existing_buses'][] = "$name ($routeNo)";
    } else {
        $result['new_buses'][] = "$name ($routeNo)";
    }
} else {
    error_log("SAVE_DEBUG: SQL Error - " . $stmtBus->error);
    error_log("SAVE_DEBUG: Failed to save bus: ID=$id, Name=$name");
}
            
            // 4. Save bus-driver relationships
            $del = $conn->prepare("DELETE FROM bus_drivers WHERE bus_id = ?");
            $del->bind_param('s', $id);
            $del->execute();
            $del->close();
            
            if (!empty($b['driverIds']) && is_array($b['driverIds'])) {
                $ins = $conn->prepare("INSERT INTO bus_drivers (bus_id, driver_id) VALUES (?,?)");
                foreach ($b['driverIds'] as $drvId) {
                    $drvId = (string)$drvId;
                    if ($drvId === '') continue;
                    $ins->bind_param('ss', $id, $drvId);
                    $ins->execute();
                }
                $ins->close();
            }
            
            // 5. Save bus-stop relationships
            $del = $conn->prepare("DELETE FROM bus_stops WHERE bus_id = ?");
            $del->bind_param('s', $id);
            $del->execute();
            $del->close();
            
            if (!empty($b['stops']) && is_array($b['stops'])) {
                $pos = 1;
                $ins = $conn->prepare("INSERT INTO bus_stops (bus_id, stop_id, position) VALUES (?,?,?)");
                foreach ($b['stops'] as $sid) {
                    $sid = (string)$sid;
                    if ($sid === '') continue;
                    $position = $pos++;
                    $ins->bind_param('ssi', $id, $sid, $position);
                    $ins->execute();
                }
                $ins->close();
            }
        }
        $stmtBus->close();
    }
    
    // 6. Save global route order
    $conn->query("DELETE FROM global_route_order");
    if (!empty($routeOrder) && is_array($routeOrder)) {
        $pos = 1;
        $ins = $conn->prepare("INSERT INTO global_route_order (position, stop_id) VALUES (?,?)");
        foreach ($routeOrder as $sid) {
            $sid = (string)$sid;
            if ($sid === '') continue;
            $position = $pos++;
            $ins->bind_param('is', $position, $sid);
            $ins->execute();
        }
        $ins->close();
    }
    
    $conn->commit();
    
    $result['success'] = true;
    $result['message'] = 'All data saved permanently to database!';
    $result['timestamp'] = date('Y-m-d H:i:s');
    
} catch (Throwable $e) {
    // Rollback and return a clean JSON error instead of an HTML/PHP error page
    if ($conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $_) {
            // ignore rollback errors
        }
    }

    http_response_code(500);
    $result['success'] = false;
    $result['error'] = $e->getMessage();
    $result['message'] = 'Save failed: ' . $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);
$conn->close();
?>
