
<?php
// public/api/data.php
// Persists and serves admin-managed data (buses, drivers, stops, routeOrder)
// Frontend calls: GET /api/data, POST /api/data

// Load config first (which handles session start)
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  exit(0);
}

$rootDir = dirname(__DIR__); // public/
$jsonFile = $rootDir . DIRECTORY_SEPARATOR . 'buses.json';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  // Handle specific actions
  $action = $_GET['action'] ?? '';
  
  if ($action === 'check_driver') {
    // Check if driver exists in users table
    $driverName = $_GET['name'] ?? '';
    if (empty($driverName)) {
      echo json_encode(['exists' => false]);
      exit;
    }
    
    try {
      $stmt = $conn->prepare("SELECT id FROM users WHERE name = ? AND role = 'Driver' LIMIT 1");
      $stmt->bind_param('s', $driverName);
      $stmt->execute();
      $result = $stmt->get_result();
      $exists = ($result && $result->num_rows > 0);
      echo json_encode(['exists' => $exists]);
      $stmt->close();
      $conn->close();
      exit;
    } catch (Exception $e) {
      echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
      exit;
    }
  } 
  // Get current user profile data
  elseif ($action === 'get_profile') {
    // Debug session
    error_log("Session data: " . print_r($_SESSION, true));
    
    if (!isset($_SESSION['user_id'])) {
      http_response_code(401);
      echo json_encode(['error' => 'Not authenticated', 'session' => $_SESSION]);
      exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    try {
      
      // Get user details
      $stmt = $conn->prepare("SELECT id, name, email, phone_number, role FROM users WHERE id = ?");
      $stmt->bind_param('i', $userId);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
      }
      
      $user = $result->fetch_assoc();
      $response = [
        'success' => true,
        'data' => [
          'id' => $user['id'],
          'name' => $user['name'],
          'email' => $user['email'],
          'phone' => $user['phone_number'],
          'role' => $user['role']
        ]
      ];
      
      // If user is a driver, get additional driver details
      if ($user['role'] === 'Driver') {
        // Find driver by name matching since there's no user_id column
        $driverStmt = $conn->prepare("SELECT id, name FROM drivers WHERE name = ?");
        $driverStmt->bind_param('s', $user['name']);
        $driverStmt->execute();
        $driverResult = $driverStmt->get_result();
        
        if ($driverResult->num_rows > 0) {
          $driver = $driverResult->fetch_assoc();
          $response['data']['driver_id'] = $driver['id'];
          // Note: bus_number and license_number don't exist in the current schema
          $response['data']['bus_number'] = 'Not assigned';
          $response['data']['license_number'] = 'Not specified';
        }
        
        $driverStmt->close();
      }
      
      echo json_encode($response);
      $stmt->close();
      $conn->close();
      exit;
      
    } catch (Exception $e) {
      http_response_code(500);
      echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
      exit;
    }
  }
  // Get schedules for admin dashboard
  elseif ($action === 'get_schedules') {
    $date = $_GET['date'] ?? '';
    
    try {
      
      $query = "
        SELECT 
          bs.id,
          bs.schedule_date,
          bs.route_type,
          bs.time_slot,
          bs.route_category,
          bs.route_name,
          bs.route_number,
          bs.bus_count,
          bs.created_at,
          b.name as bus_name,
          b.route_no as bus_route_number,
          b.boarding_stop_name,
          b.end_stop_name
        FROM bus_schedules bs
        LEFT JOIN buses b ON (b.name = bs.route_name OR b.route_no = bs.route_number)
      ";
      
      $params = [];
      $types = '';
      
      if (!empty($date)) {
        $query .= " WHERE bs.schedule_date = ?";
        $params[] = $date;
        $types .= 's';
      }
      
      $query .= " ORDER BY bs.schedule_date ASC, bs.time_slot ASC";
      
      $stmt = $conn->prepare($query);
      
      if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
      }
      
      $stmt->execute();
      $result = $stmt->get_result();
      
      $schedules = [];
      while ($row = $result->fetch_assoc()) {
        $schedules[] = [
          'id' => $row['id'],
          'schedule_date' => $row['schedule_date'],
          'route_type' => $row['route_type'],
          'time_slot' => $row['time_slot'],
          'route_category' => $row['route_category'],
          'route_name' => $row['route_name'],
          'route_number' => $row['route_number'],
          'bus_count' => $row['bus_count'],
          'created_at' => $row['created_at'],
          'bus_name' => $row['bus_name'],
          'bus_route_number' => $row['bus_route_number'],
          'boarding_stop_name' => $row['boarding_stop_name'],
          'end_stop_name' => $row['end_stop_name']
        ];
      }
      
      echo json_encode([
        'success' => true,
        'schedules' => $schedules,
        'count' => count($schedules)
      ]);
      
      $stmt->close();
      $conn->close();
      exit;
    } catch (Exception $e) {
      http_response_code(500);
      echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
      exit;
    }
  }
  // Get driver assignments (improved version with hybrid ID/Name matching)
  elseif ($action === 'get_driver_assignments') {
    if (!isset($_SESSION['user_id'])) {
      error_log("Session check failed. Session data: " . print_r($_SESSION, true));
      error_log("Session ID: " . session_id());
      http_response_code(401);
      echo json_encode(['error' => 'Not authenticated', 'session_debug' => $_SESSION]);
      exit;
    }
    
    try {
      
      // Get logged-in driver's information
      $userId = $_SESSION['user_id'];
      $stmt = $conn->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'Driver'");
      $stmt->bind_param('i', $userId);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows === 0) {
        echo json_encode(['success' => true, 'assignments' => [], 'driver_name' => '']);
        exit;
      }
      
      $driver = $result->fetch_assoc();
      $driverName = $driver['name'];
      $stmt->close();
      
      // Hybrid approach: Get assigned buses using both ID and name matching
      $assignmentStmt = $conn->prepare("
        SELECT 
          b.id as bus_id,
          b.name as bus_name,
          b.route_no as bus_route_number,
          b.boarding_stop_name,
          b.end_stop_name,
          d.id as driver_id,
          d.name as driver_name,
          'assigned' as assignment_status
        FROM buses b
        JOIN bus_drivers bd ON b.id = bd.bus_id
        JOIN drivers d ON bd.driver_id = d.id
        WHERE d.name = ?
        ORDER BY b.name ASC
      ");
      
      $assignmentStmt->bind_param('s', $driverName);
      $assignmentStmt->execute();
      $assignmentResult = $assignmentStmt->get_result();
      
      $assignments = [];
      while ($row = $assignmentResult->fetch_assoc()) {
        $assignments[] = [
          'bus_id' => $row['bus_id'],
          'bus_name' => $row['bus_name'],
          'bus_route_number' => $row['bus_route_number'],
          'boarding_stop_name' => $row['boarding_stop_name'],
          'end_stop_name' => $row['end_stop_name'],
          'driver_id' => $row['driver_id'],
          'driver_name' => $row['driver_name'],
          'assignment_status' => $row['assignment_status'],
          'assigned_date' => date('Y-m-d H:i:s')
        ];
      }
      
      $assignmentStmt->close();
      
      echo json_encode([
        'success' => true,
        'assignments' => $assignments,
        'driver_name' => $driverName,
        'total_assignments' => count($assignments)
      ]);
      
    } catch (Exception $e) {
      error_log("Error getting driver assignments: " . $e->getMessage());
      http_response_code(500);
      echo json_encode(['error' => 'Failed to get driver assignments']);
    }
  }
  // Get driver schedules
  elseif ($action === 'get_driver_schedules') {
    if (!isset($_SESSION['user_id'])) {
      error_log("Session check failed. Session data: " . print_r($_SESSION, true));
      error_log("Session ID: " . session_id());
      http_response_code(401);
      echo json_encode(['error' => 'Not authenticated', 'session_debug' => $_SESSION]);
      exit;
    }
    
    try {
      
      // Get logged-in driver's name from users table
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
      
      // Debug: Log driver name
      error_log("Driver logged in: " . $driverName);
      
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
      
      error_log("Found driver ID: " . $driverId . " for driver: " . $driverName);
      
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
        error_log("Assigned bus: " . $bus['name'] . " (route_no: " . $bus['route_no'] . ")");
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
      
      // Debug: Log query results
      error_log("Schedule query returned " . $scheduleResult->num_rows . " rows for driver: " . $driverName);
      
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
          
          // IMPORTANT: Only match if this schedule is for THIS SPECIFIC bus
          // Check if the schedule route_number contains this bus's route_no
          $specificBusMatch = false;
          if ($bus['route_no'] && $row['route_number']) {
            // Convert route numbers to arrays and check for intersection
            $busRoutes = array_map('trim', explode("\n", $bus['route_no']));
            $scheduleRoutes = array_map('trim', explode("\n", $row['route_number']));
            
            // Check if any route number matches
            foreach ($busRoutes as $busRoute) {
              if (in_array($busRoute, $scheduleRoutes)) {
                $specificBusMatch = true;
                break;
              }
            }
          }
          
          if (($routeNameMatch || $routeNumberMatch || $busNameMatch) && $specificBusMatch) {
            $isAssigned = true;
            $matchedBus = $bus;
            $matchType = $routeNameMatch ? 'route_name' : ($routeNumberMatch ? 'route_number' : 'bus_name');
            error_log("Match found: Schedule route " . $row['route_name'] . "/" . $row['route_number'] . " matches bus " . $bus['name'] . " (route_no: " . $bus['route_no'] . ") via " . $matchType . " - SPECIFIC BUS MATCH");
            break;
          }
        }
        
        if ($isAssigned && $matchedBus) {
          error_log("Found schedule: " . print_r($row, true));
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
            'boarding_stop_name' => 'Not specified',
            'end_stop_name' => 'Not specified',
            'driver_name' => $driverName,
            'notification' => "Schedule: {$row['route_type']} - {$row['time_slot']} - Bus: {$matchedBus['name']}",
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
      exit;
    } catch (Exception $e) {
      http_response_code(500);
      echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
      exit;
    }
  }
  
  // Load data from MySQL database instead of JSON file
  try {
    require __DIR__ . '/../../Backend/db.php';
    
    // Get all buses with their routes
    $buses = [];
    
    // Check if boarding_stop_name and end_stop_name columns exist
    $hasBoardingName = false;
    $hasEndName = false;
    $hasStops = false;
    try {
      $res = $conn->query("SHOW COLUMNS FROM buses LIKE 'boarding_stop_name'");
      $hasBoardingName = ($res && $res->num_rows > 0);
    } catch (Exception $e) {
      error_log("Error checking boarding_stop_name column: " . $e->getMessage());
    }
    
    try {
      $res = $conn->query("SHOW COLUMNS FROM buses LIKE 'end_stop_name'");
      $hasEndName = ($res && $res->num_rows > 0);
    } catch (Exception $e) {
      error_log("Error checking end_stop_name column: " . $e->getMessage());
    }
    
    try {
      $res = $conn->query("SHOW COLUMNS FROM buses LIKE 'stops'");
      $hasStops = ($res && $res->num_rows > 0);
    } catch (Exception $e) {
      error_log("Error checking stops column: " . $e->getMessage());
    }
    
    // Build SELECT query based on available columns
    $selectFields = "id, name, route_no";
    if ($hasBoardingName) $selectFields .= ", boarding_stop_name";
    if ($hasEndName) $selectFields .= ", end_stop_name";
    if ($hasStops) $selectFields .= ", stops";
    
    $busResult = $conn->query("SELECT $selectFields FROM buses ORDER BY id");
    
    if ($busResult && $busResult->num_rows > 0) {
      while ($busRow = $busResult->fetch_assoc()) {
        $busId = $busRow['id'];
        
        // Get stops for this bus
        $stopIds = [];
        $stopNames = [];
        
        // If stops column exists and has data, use it
        if ($hasStops && !empty($busRow['stops'])) {
          $stopIds = json_decode($busRow['stops'], true) ?: [];
          
          // Get stop names from IDs
          if (!empty($stopIds)) {
            $idsString = "'" . implode("','", array_map([$conn, 'real_escape_string'], $stopIds)) . "'";
            $stopResult = $conn->query("SELECT id, name FROM stops WHERE id IN ($idsString)");
            if ($stopResult && $stopResult->num_rows > 0) {
              while ($stopRow = $stopResult->fetch_assoc()) {
                $stopNames[] = $stopRow['name'];
              }
            }
          }
        } else {
          // Fallback to bus_stops table - get stop names directly
          $routeResult = $conn->query("
            SELECT s.id, s.name, s.lat, s.lon, bs.position 
            FROM bus_stops bs 
            JOIN stops s ON bs.stop_id = s.id 
            WHERE bs.bus_id = '$busId' 
            ORDER BY bs.position
          ");
          
          if ($routeResult && $routeResult->num_rows > 0) {
            while ($stopRow = $routeResult->fetch_assoc()) {
              $stopIds[] = $stopRow['id'];
              $stopNames[] = $stopRow['name'];
            }
          }
        }
        
        // Get driver assignments for this bus
        $driverIds = [];
        $driverResult = $conn->query("
          SELECT driver_id 
          FROM bus_drivers 
          WHERE bus_id = '$busId'
        ");
        
        if ($driverResult && $driverResult->num_rows > 0) {
          while ($driverRow = $driverResult->fetch_assoc()) {
            $driverIds[] = $driverRow['driver_id'];
          }
        }
        
        $buses[] = [
          'id' => $busRow['id'],
          'name' => $busRow['name'],
          'routeNo' => $busRow['route_no'],
          'boardingName' => $hasBoardingName ? $busRow['boarding_stop_name'] : null,
          'endName' => $hasEndName ? $busRow['end_stop_name'] : null,
          'driverIds' => $driverIds,
          'stops' => $stopIds,
          'stopNames' => $stopNames
        ];
      }
    }
    
    // Get all stops
    $stops = [];
    $stopResult = $conn->query("SELECT id, name, lat, lon FROM stops ORDER BY id");
    
    if ($stopResult && $stopResult->num_rows > 0) {
      while ($stopRow = $stopResult->fetch_assoc()) {
        $stops[] = [
          'id' => $stopRow['id'],
          'name' => $stopRow['name'],
          'lat' => (float)$stopRow['lat'],
          'lon' => (float)$stopRow['lon']
        ];
      }
    }
    
    // Get all drivers from users table where role = 'driver'
    $drivers = [];
    $driverResult = $conn->query("SELECT id, name FROM users WHERE role = 'Driver' ORDER BY name");
    
    if ($driverResult && $driverResult->num_rows > 0) {
      while ($driverRow = $driverResult->fetch_assoc()) {
        $drivers[] = [
          'id' => $driverRow['id'],
          'name' => $driverRow['name']
        ];
      }
    }
    
    // Get schedules
    $schedules = [];
    $scheduleResult = $conn->query("SELECT external_id, schedule_date, route_type, time_slot, route_category, bus_count, route_name, route_number FROM bus_schedules ORDER BY schedule_date, time_slot");
    
    if ($scheduleResult && $scheduleResult->num_rows > 0) {
      while ($scheduleRow = $scheduleResult->fetch_assoc()) {
        $schedules[] = [
          'id' => $scheduleRow['external_id'],
          'date' => $scheduleRow['schedule_date'],
          'routeType' => $scheduleRow['route_type'],
          'timeSlot' => $scheduleRow['time_slot'],
          'routeCategory' => $scheduleRow['route_category'],
          'busCount' => (int)$scheduleRow['bus_count'],
          'routeName' => $scheduleRow['route_name'],
          'routeNumber' => $scheduleRow['route_number']
        ];
      }
    }
    
    // Get announcements
    $announcements = [];
    $announcementResult = $conn->query("SELECT external_id, title, message, start_date, end_date, affected_routes, is_active FROM announcements WHERE is_active = TRUE ORDER BY created_at DESC");
    
    if ($announcementResult && $announcementResult->num_rows > 0) {
      while ($announcementRow = $announcementResult->fetch_assoc()) {
        $announcements[] = [
          'id' => $announcementRow['external_id'],
          'title' => $announcementRow['title'],
          'message' => $announcementRow['message'],
          'startDate' => $announcementRow['start_date'],
          'endDate' => $announcementRow['end_date'],
          'affectedRoutes' => json_decode($announcementRow['affected_routes'] ?: '[]', true) ?: []
        ];
      }
    }
    
    $response = [
      'buses' => $buses,
      'drivers' => $drivers,
      'stops' => $stops,
      'schedules' => $schedules,
      'announcements' => $announcements
    ];
    
    echo json_encode($response);
    $conn->close();
    exit();
    
  } catch (Exception $e) {
    // Fallback to JSON file if database fails
    if (!file_exists($jsonFile)) {
      http_response_code(404);
      echo json_encode(["message" => "No saved data found"]);
      exit();
    }
    $raw = file_get_contents($jsonFile);
    if ($raw === false) {
      http_response_code(500);
      echo json_encode(["message" => "Failed to read saved data"]);
      exit();
    }
    echo $raw; // already JSON
    exit();
  }
}

if ($method === 'POST') {
  // Handle specific actions
  $action = $_GET['action'] ?? '';
  
  if ($action === 'add_driver') {
    // Add driver to users table
    $body = file_get_contents('php://input');
    if (!$body) {
      http_response_code(400);
      echo json_encode(["success" => false, "error" => "Invalid or empty body"]);
      exit();
    }
    
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
      http_response_code(400);
      echo json_encode(["success" => false, "error" => "Invalid JSON"]);
      exit();
    }
    
    $username = $decoded['username'] ?? '';
    $role = $decoded['role'] ?? 'driver';
    
    if (empty($username)) {
      echo json_encode(["success" => false, "error" => "Name is required"]);
      exit();
    }
    
    try {
      
      // Check if name already exists
      $stmt = $conn->prepare("SELECT id FROM users WHERE name = ? LIMIT 1");
      $stmt->bind_param('s', $username);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result && $result->num_rows > 0) {
        echo json_encode(["success" => false, "error" => "Name already exists"]);
        $stmt->close();
        $conn->close();
        exit();
      }
      
      // Insert new driver
      $stmt->close();
      $stmt = $conn->prepare("INSERT INTO users (name, role) VALUES (?, ?)");
      $stmt->bind_param('ss', $username, $role);
      $stmt->execute();
      
      if ($stmt->affected_rows > 0) {
        echo json_encode(["success" => true, "message" => "Driver added successfully"]);
      } else {
        echo json_encode(["success" => false, "error" => "Failed to add driver"]);
      }
      
      $stmt->close();
      $conn->close();
      exit();
    } catch (Exception $e) {
      echo json_encode(["success" => false, "error" => $e->getMessage()]);
      exit();
    }
  }
  
  $body = file_get_contents('php://input');
  if (!$body) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid or empty body"]);
    exit();
  }
  // Validate JSON
  $decoded = json_decode($body, true);
  if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(["message" => "Body must be valid JSON"]);
    exit();
  }

  // Ensure directory is writable (but don't fail if not, since we're using database primarily)
  if (!is_writable($rootDir)) {
    error_log("Warning: Directory not writable, but continuing with database save");
  }

  // Save pretty JSON for easier debugging (backup only)
  $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  file_put_contents($jsonFile, $pretty); // Don't fail if this fails, it's just backup

  // Primary save to MySQL database
  try {
    require __DIR__ . '/../../Backend/db.php';
    error_log("MySQL sync started - Database connected successfully");
    
    // Test database connection
    if ($conn->connect_error) {
      throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $hasBusExternal = false; $hasStopExternal = false; $hasBoardingId = false; $hasEndId = false; $hasStops = false;
    // Detect optional columns
    try {
      $res = $conn->query("SHOW COLUMNS FROM buses LIKE 'external_id'");
      $hasBusExternal = ($res && $res->num_rows > 0);
    } catch (Exception $e) {
      error_log("Error checking buses external_id column: " . $e->getMessage());
    }
    
    try {
      $res2 = $conn->query("SHOW COLUMNS FROM bus_stops LIKE 'external_id'");
      $hasStopExternal = ($res2 && $res2->num_rows > 0);
    } catch (Exception $e) {
      error_log("Error checking bus_stops external_id column: " . $e->getMessage());
    }
    
    try {
      $res3 = $conn->query("SHOW COLUMNS FROM buses LIKE 'boarding_stop_name'");
      $hasBoardingName = ($res3 && $res3->num_rows > 0);
    } catch (Exception $e) {
      error_log("Error checking buses boarding_stop_name column: " . $e->getMessage());
    }
    
    try {
      $res4 = $conn->query("SHOW COLUMNS FROM buses LIKE 'end_stop_name'");
      $hasEndName = ($res4 && $res4->num_rows > 0);
    } catch (Exception $e) {
      error_log("Error checking buses end_stop_name column: " . $e->getMessage());
    }
    
    try {
      $res5 = $conn->query("SHOW COLUMNS FROM buses LIKE 'stops'");
      $hasStops = ($res5 && $res5->num_rows > 0);
    } catch (Exception $e) {
      error_log("Error checking buses stops column: " . $e->getMessage());
    }

    $stops = $decoded['stops'] ?? [];
    $drivers = $decoded['drivers'] ?? [];
    $buses = $decoded['buses'] ?? [];
    $schedules = $decoded['schedules'] ?? [];
    $announcements = $decoded['announcements'] ?? [];
    
    error_log("Processing " . count($stops) . " stops, " . count($drivers) . " drivers, " . count($buses) . " buses, " . count($schedules) . " schedules, " . count($announcements) . " announcements");

    // Upsert stops
    $stopIdMap = []; // key: admin stop id -> db stop id
    foreach ($stops as $s) {
      $adminId = $s['id'] ?? null;
      $name = $s['name'] ?? '';
      $lat = isset($s['lat']) ? (float)$s['lat'] : null;
      $lon = isset($s['lon']) ? (float)$s['lon'] : null;
      if (!$name || $lat === null || $lon === null) continue;

      // Check if stop already exists by ID
      $stmt = $conn->prepare("SELECT id, name FROM stops WHERE id = ? LIMIT 1");
      $stmt->bind_param('s', $adminId);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result && $result->num_rows > 0) {
        // Stop already exists - update it
        $stmt->close();
        $stmt = $conn->prepare("UPDATE stops SET name=?, lat=?, lon=? WHERE id=?");
        $stmt->bind_param('sdds', $name, $lat, $lon, $adminId);
        $stmt->execute();
        $stmt->close();
        $stopIdMap[$adminId] = $adminId;
        error_log("Updated stop: ID=$adminId, Name='$name'");
      } else {
        // Stop doesn't exist, insert new one
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO stops (id, name, lat, lon) VALUES (?,?,?,?)");
        $stmt->bind_param('sdds', $adminId, $name, $lat, $lon);
        $stmt->execute();
        $stmt->close();
        $stopIdMap[$adminId] = $adminId;
        error_log("New stop created: ID=$adminId, Name='$name'");
      }
    }
    
    // Upsert drivers
    foreach ($drivers as $d) {
      $adminId = $d['id'] ?? null;
      $name = trim($d['name'] ?? '');
      if (empty($name)) {
        error_log("Skipping driver - missing name");
        continue;
      }

      // Check if driver already exists by ID
      $stmt = $conn->prepare("SELECT id, name FROM drivers WHERE id = ? LIMIT 1");
      $stmt->bind_param('s', $adminId);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result && $result->num_rows > 0) {
        // Driver already exists - update it
        $stmt->close();
        $stmt = $conn->prepare("UPDATE drivers SET name=? WHERE id=?");
        $stmt->bind_param('ss', $name, $adminId);
        $stmt->execute();
        $stmt->close();
        error_log("Updated driver: ID=$adminId, Name='$name'");
      } else {
        // Driver doesn't exist, insert new one
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO drivers (id, name) VALUES (?,?)");
        $stmt->bind_param('ss', $adminId, $name);
        $stmt->execute();
        $stmt->close();
        error_log("New driver created: ID=$adminId, Name='$name'");
      }
    }

    // Upsert buses and routes
    foreach ($buses as $b) {
      $adminId = $b['id'] ?? null;
      $name = trim($b['name'] ?? '');
      $routeNo = trim($b['routeNo'] ?? '');
      $stopIds = $b['stops'] ?? [];
      $boardingName = $b['boardingName'] ?? null;
      $endName = $b['endName'] ?? null;
      $driverIds = $b['driverIds'] ?? [];
      if (empty($name)) {
        error_log("Skipping bus - missing name");
        continue;
      }

      // Check if bus already exists by ID
      $stmt = $conn->prepare("SELECT id, name, route_no FROM buses WHERE id = ? LIMIT 1");
      $stmt->bind_param('s', $adminId);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result && $result->num_rows > 0) {
        // Bus already exists - update it
        $stmt->close();
        
        // Build update query based on available columns
        $updateFields = "name=?, route_no=?";
        $updateParams = "ss";
        $updateValues = [$name, $routeNo];
        
        if ($hasBoardingName) {
          $updateFields .= ", boarding_stop_name=?";
          $updateParams .= "s";
          $updateValues[] = $boardingName;
        }
        
        if ($hasEndName) {
          $updateFields .= ", end_stop_name=?";
          $updateParams .= "s";
          $updateValues[] = $endName;
        }
        
        if ($hasStops) {
          $updateFields .= ", stops=?";
          $updateParams .= "s";
          $stopsJson = json_encode($stopIds);
          $updateValues[] = $stopsJson;
        }
        
        $updateFields .= " WHERE id=?";
        $updateParams .= "s";
        $updateValues[] = $adminId;
        
        $stmt = $conn->prepare("UPDATE buses SET $updateFields");
        $stmt->bind_param($updateParams, ...$updateValues);
        $stmt->execute();
        $stmt->close();
        error_log("Updated bus: ID=$adminId, Name='$name', Route='$routeNo'" . ($hasBoardingName ? ", Boarding='$boardingName'" : "") . ($hasEndName ? ", End='$endName'" : ""));
        
        if (!isset($GLOBALS['existing_buses'])) $GLOBALS['existing_buses'] = [];
        $GLOBALS['existing_buses'][] = "$name ($routeNo)";
      } else {
        // Bus doesn't exist, insert new one
        $stmt->close();
        
        // Build insert query based on available columns
        $insertFields = "id, name, route_no";
        $insertParams = "sss";
        $insertValues = [$adminId, $name, $routeNo];
        $insertPlaceholders = "?,?,?";
        
        if ($hasBoardingName) {
          $insertFields .= ", boarding_stop_name";
          $insertParams .= "s";
          $insertValues[] = $boardingName;
          $insertPlaceholders .= ",?";
        }
        
        if ($hasEndName) {
          $insertFields .= ", end_stop_name";
          $insertParams .= "s";
          $insertValues[] = $endName;
          $insertPlaceholders .= ",?";
        }
        
        if ($hasStops) {
          $insertFields .= ", stops";
          $insertParams .= "s";
          $stopsJson = json_encode($stopIds);
          $insertValues[] = $stopsJson;
          $insertPlaceholders .= ",?";
        }
        
        $stmt = $conn->prepare("INSERT INTO buses ($insertFields) VALUES ($insertPlaceholders)");
        $stmt->bind_param($insertParams, ...$insertValues);
        $stmt->execute();
        $stmt->close();
        error_log("New bus created: ID=$adminId, Name='$name', Route='$routeNo'" . ($hasBoardingName ? ", Boarding='$boardingName'" : "") . ($hasEndName ? ", End='$endName'" : ""));
        
        if (!isset($GLOBALS['new_buses'])) $GLOBALS['new_buses'] = [];
        $GLOBALS['new_buses'][] = "$name ($routeNo)";
      }

      // Reset routes for this bus, then insert in given order
      $stmt = $conn->prepare("DELETE FROM bus_stops WHERE bus_id = ?");
      $stmt->bind_param('s', $adminId); $stmt->execute(); $stmt->close();

      $order = 1;
      foreach ($stopIds as $sid) {
        $sDbId = $stopIdMap[$sid] ?? null;
        if (!$sDbId) continue;
        $stmt = $conn->prepare("INSERT INTO bus_stops (bus_id, stop_id, position) VALUES (?,?,?)");
        $stmt->bind_param('ssi', $adminId, $sDbId, $order);
        $stmt->execute(); $stmt->close();
        $order++;
      }
      
      // Reset driver assignments for this bus, then insert new ones
      $stmt = $conn->prepare("DELETE FROM bus_drivers WHERE bus_id = ?");
      $stmt->bind_param('s', $adminId); $stmt->execute(); $stmt->close();

      foreach ($driverIds as $driverId) {
        $stmt = $conn->prepare("INSERT INTO bus_drivers (bus_id, driver_id) VALUES (?,?)");
        $stmt->bind_param('ss', $adminId, $driverId);
        $stmt->execute(); $stmt->close();
      }
    }

    // Upsert schedules
    foreach ($schedules as $s) {
      $adminId = $s['id'] ?? null;
      $date = $s['date'] ?? null;
      $routeType = $s['routeType'] ?? null;
      $timeSlot = $s['timeSlot'] ?? null;
      $routeCategory = $s['routeCategory'] ?? 'All Routes'; // Default to 'All Routes' if not set
      
      // Debug log the raw route category from JavaScript
      error_log("Raw routeCategory from JavaScript: " . ($s['routeCategory'] ?? 'NULL'));
      error_log("After default assignment: " . $routeCategory);
      
      // Ensure route category matches one of the allowed values
      $allowedCategories = ['All Routes', 'Main Routes', 'Common Routes'];
      if (!in_array($routeCategory, $allowedCategories)) {
          error_log("Invalid route category detected: " . $routeCategory . " - defaulting to All Routes");
          $routeCategory = 'All Routes'; // Fallback to default if invalid
      }
      
      $busCount = $s['busCount'] ?? 1;
      $selectedBuses = $s['selectedBuses'] ?? [];
      
      // Extract all bus names and route numbers from selected buses
      $routeNames = [];
      $routeNumbers = [];
      if (!empty($selectedBuses)) {
        foreach ($selectedBuses as $busId) {
          // Find bus details from the buses array
          foreach ($buses as $bus) {
            if ($bus['id'] === $busId) {
              $routeNames[] = $bus['name'];
              $routeNumbers[] = $bus['routeNo'] ?? '';
              break;
            }
          }
        }
      }
      
      // Convert to line-separated strings for database storage (one per line)
      $routeName = implode("\n", $routeNames);
      $routeNumber = implode("\n", $routeNumbers);
      
      // Debug log the raw input
      error_log("Raw schedule data: " . print_r($s, true));
      error_log("Processing schedule - ID: " . ($adminId ?? 'NULL') . 
                ", Route Category: " . $routeCategory . 
                ", Bus Count: " . $busCount . 
                ", Route Name: " . $routeName . 
                ", Route Number: " . $routeNumber);
      
      // Debug log to check the received schedule data
      error_log("Processing schedule - ID: " . ($adminId ?? 'NULL') . 
                ", Route Category: " . ($routeCategory ?? 'NULL') . 
                ", Bus Count: " . $busCount . 
                ", Route Name: " . ($routeName ?? 'NULL') . 
                ", Route Number: " . ($routeNumber ?? 'NULL'));
      
      // Format timeSlot to include AM/PM if not already present
      if (preg_match('/^\d{1,2}:\d{2}$/', $timeSlot)) {
        $timeParts = explode(':', $timeSlot);
        $hour = (int)$timeParts[0];
        
        if ($hour < 12) {
          $timeSlot = "$timeSlot AM";
        } elseif ($hour == 12) {
          $timeSlot = "$timeSlot PM";
        } else {
          $timeSlot = "$timeSlot PM";
        }
      }
      
      if (!$date || !$routeType || !$timeSlot) continue;
      
      // Check if schedule already exists
      $stmt = $conn->prepare("SELECT id FROM bus_schedules WHERE external_id = ?");
      $stmt->bind_param('s', $adminId);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result && $result->num_rows > 0) {
        // Update existing schedule
        $stmt->close();
        $updateSql = "UPDATE bus_schedules SET 
          schedule_date=?, 
          route_type=?, 
          time_slot=?, 
          route_category=?, 
          bus_count=?, 
          route_name=?, 
          route_number=? 
          WHERE external_id=?";
        
        error_log("Executing UPDATE: $updateSql");
        error_log("With values: date=$date, routeType=$routeType, timeSlot=$timeSlot, routeCategory=$routeCategory, busCount=$busCount, routeName=$routeName, routeNumber=$routeNumber, adminId=$adminId");
        
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param('ssssisss', $date, $routeType, $timeSlot, $routeCategory, $busCount, $routeName, $routeNumber, $adminId);
        $result = $stmt->execute();
        if (!$result) {
          error_log("Update failed: " . $stmt->error);
        }
        $stmt->close();
        error_log("Updated schedule: $adminId");
      } else {
        // Insert new schedule
        $stmt->close();
        $insertSql = "INSERT INTO bus_schedules (
          external_id, 
          schedule_date, 
          route_type, 
          time_slot, 
          route_category, 
          bus_count, 
          route_name, 
          route_number
        ) VALUES (?,?,?,?,?,?,?,?)";
        
        error_log("Executing INSERT: $insertSql");
        error_log("With values: adminId=$adminId, date=$date, routeType=$routeType, timeSlot=$timeSlot, routeCategory=$routeCategory, busCount=$busCount, routeName=$routeName, routeNumber=$routeNumber");
        
        $stmt = $conn->prepare($insertSql);
        $stmt->bind_param('ssssisss', $adminId, $date, $routeType, $timeSlot, $routeCategory, $busCount, $routeName, $routeNumber);
        $result = $stmt->execute();
        if (!$result) {
          error_log("Insert failed: " . $stmt->error);
        }
        $stmt->close();
        error_log("New schedule created: $adminId");
      }
    }

    // Upsert announcements
    foreach ($announcements as $a) {
      $adminId = $a['id'] ?? null;
      $title = $a['title'] ?? null;
      $message = $a['message'] ?? null;
      $startDate = $a['startDate'] ?? null;
      $endDate = $a['endDate'] ?? null;
      $affectedRoutes = json_encode($a['affectedRoutes'] ?? []);
      
      if (!$title || !$message || !$startDate || !$endDate) continue;
      
      // Check if announcement already exists
      $stmt = $conn->prepare("SELECT id FROM announcements WHERE external_id = ?");
      $stmt->bind_param('s', $adminId);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result && $result->num_rows > 0) {
        // Update existing announcement
        $stmt->close();
        $stmt = $conn->prepare("UPDATE announcements SET title=?, message=?, start_date=?, end_date=?, affected_routes=?, is_active=TRUE WHERE external_id=?");
        $stmt->bind_param('ssssss', $title, $message, $startDate, $endDate, $affectedRoutes, $adminId);
        $stmt->execute();
        $stmt->close();
        error_log("Updated announcement: $adminId");
      } else {
        // Insert new announcement
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO announcements (external_id, title, message, start_date, end_date, affected_routes, is_active) VALUES (?,?,?,?,?,?,TRUE)");
        $stmt->bind_param('ssssss', $adminId, $title, $message, $startDate, $endDate, $affectedRoutes);
        $stmt->execute();
        $stmt->close();
        error_log("New announcement created: $adminId");
      }
    }

    // Build detailed response message
    $message = "Saved and synced to MySQL";
    $details = [];
    
    if (isset($GLOBALS['new_buses']) && count($GLOBALS['new_buses']) > 0) {
      $details[] = "New buses created: " . implode(", ", $GLOBALS['new_buses']);
    }
    
    if (isset($GLOBALS['existing_buses']) && count($GLOBALS['existing_buses']) > 0) {
      $details[] = "Existing buses found (not overwritten): " . implode(", ", $GLOBALS['existing_buses']);
    }
    
    if (!empty($details)) {
      $message .= " | " . implode(" | ", $details);
    }
    
    echo json_encode([
      "message" => $message, 
      "path" => "/buses.json",
      "new_buses" => $GLOBALS['new_buses'] ?? [],
      "existing_buses" => $GLOBALS['existing_buses'] ?? []
    ]);
    $conn->close();
  } catch (Throwable $e) {
    // If DB sync fails, report the error
    error_log("Database sync failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
      "message" => "Database save failed", 
      "error" => $e->getMessage(),
      "file" => $e->getFile(),
      "line" => $e->getLine()
    ]);
    exit();
  }
  exit();
}

http_response_code(405);
echo json_encode(["message" => "Method not allowed"]);