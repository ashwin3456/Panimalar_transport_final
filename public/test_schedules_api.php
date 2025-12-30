<?php
session_start();
require_once '../Backend/db.php';

// Simulate the exact same logic as get_driver_schedules API
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    // For testing, set user_id to 2
    $_SESSION['user_id'] = 2;
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'Driver'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => true, 'schedules' => []]);
    exit;
}

$driver = $result->fetch_assoc();
$driverName = $driver['name'];
$stmt->close();

// Get driver's ID from drivers table using name
$driverIdStmt = $conn->prepare("SELECT id FROM drivers WHERE name = ?");
$driverIdStmt->bind_param('s', $driverName);
$driverIdStmt->execute();
$driverIdResult = $driverIdStmt->get_result();

if ($driverIdResult->num_rows === 0) {
    echo json_encode(['success' => true, 'driver_name' => $driverName, 'schedules' => []]);
    exit;
}

$driver = $driverIdResult->fetch_assoc();
$driverId = $driver['id'];
$driverIdStmt->close();

// Get buses assigned to this driver through bus_drivers table
$busStmt = $conn->prepare("
  SELECT b.* 
  FROM buses b
  JOIN bus_drivers bd ON b.id = bd.bus_id
  WHERE bd.driver_id = ?
");
$busStmt->bind_param('s', $driverId);
$busStmt->execute();
$busResult = $busStmt->get_result();

$assignedBuses = [];
while ($bus = $busResult->fetch_assoc()) {
    $assignedBuses[] = $bus;
}
$busStmt->close();

// Get all schedules first
$scheduleStmt = $conn->prepare("
  SELECT 
    bs.id,
    bs.schedule_date,
    bs.route_type,
    bs.time_slot,
    bs.route_name,
    bs.route_number,
    bs.route_category
  FROM bus_schedules bs
  ORDER BY bs.schedule_date ASC, bs.time_slot ASC
");

$scheduleStmt->execute();
$scheduleResult = $scheduleStmt->get_result();

$schedules = [];
while ($row = $scheduleResult->fetch_assoc()) {
    // Check if this schedule matches any assigned bus
    $isAssigned = false;
    $matchedBus = null;
    
    foreach ($assignedBuses as $bus) {
        // Match by route_name or route_number (exact match first, then partial)
        $routeNameMatch = ($row['route_name'] && $bus['route_no'] && 
                          (strtolower($row['route_name']) === strtolower($bus['route_no']) ||
                           strpos(strtolower($row['route_name']), strtolower($bus['route_no'])) !== false));
        $routeNumberMatch = ($row['route_number'] && $bus['route_no'] && 
                            (strtolower($row['route_number']) === strtolower($bus['route_no']) ||
                             strpos(strtolower($row['route_number']), strtolower($bus['route_no'])) !== false));
        
        // NEW: Also match by bus name if route_no is empty or doesn't match
        $busNameMatch = false;
        if (!$routeNameMatch && !$routeNumberMatch) {
            $busNameMatch = ($row['route_name'] && $bus['name'] && 
                            (strpos(strtolower($row['route_name']), strtolower($bus['name'])) !== false ||
                             strpos(strtolower($bus['name']), strtolower($row['route_name'])) !== false));
        }
        
        if ($routeNameMatch || $routeNumberMatch || $busNameMatch) {
            $isAssigned = true;
            $matchedBus = $bus;
            break;
        }
    }
    
    if ($isAssigned && $matchedBus) {
        $schedules[] = [
            'id' => $row['id'],
            'schedule_date' => $row['schedule_date'],
            'route_type' => $row['route_type'],
            'time_slot' => $row['time_slot'],
            'route_name' => $row['route_name'],
            'route_number' => $row['route_number'],
            'route_category' => $row['route_category'],
            'bus_name' => $matchedBus['name'],
            'bus_route_number' => $matchedBus['route_no'],
            'driver_name' => $driverName,
            'assigned_date' => date('Y-m-d H:i:s')
        ];
    }
}

echo json_encode([
    'success' => true,
    'driver_name' => $driverName,
    'total_schedules' => count($schedules),
    'schedules' => $schedules
]);

$scheduleStmt->close();
$conn->close();
?>
