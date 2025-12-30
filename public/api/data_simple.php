<?php
// Simplified data.php to fix 500 errors
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  // Load data from MySQL database
  try {
    require __DIR__ . '/../../Backend/db.php';
    
    // Get all buses with their routes
    $buses = [];
    $busResult = $conn->query("SELECT id, bus_name, route_number, status FROM buses WHERE status = 'active' ORDER BY id");
    
    if ($busResult && $busResult->num_rows > 0) {
      while ($busRow = $busResult->fetch_assoc()) {
        $busId = $busRow['id'];
        
        // Get stops for this bus
        $stopIds = [];
        $routeResult = $conn->query("
          SELECT bs.id 
          FROM routes r 
          JOIN bus_stops bs ON r.stop_id = bs.id 
          WHERE r.bus_id = $busId 
          ORDER BY r.stop_order
        ");
        
        if ($routeResult && $routeResult->num_rows > 0) {
          while ($stopRow = $routeResult->fetch_assoc()) {
            $stopIds[] = $stopRow['id'];
          }
        }
        
        $buses[] = [
          'id' => $busRow['id'],
          'name' => $busRow['bus_name'],
          'routeNo' => $busRow['route_number'],
          'stops' => $stopIds
        ];
      }
    }
    
    // Get all stops
    $stops = [];
    $stopResult = $conn->query("SELECT id, stop_name, latitude, longitude FROM bus_stops ORDER BY id");
    
    if ($stopResult && $stopResult->num_rows > 0) {
      while ($stopRow = $stopResult->fetch_assoc()) {
        $stops[] = [
          'id' => $stopRow['id'],
          'name' => $stopRow['stop_name'],
          'lat' => (float)$stopRow['latitude'],
          'lon' => (float)$stopRow['longitude']
        ];
      }
    }
    
    $response = [
      'buses' => $buses,
      'stops' => $stops,
      'drivers' => [],
      'routeOrder' => []
    ];
    
    echo json_encode($response);
    $conn->close();
    exit();
    
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
      'error' => 'Database error: ' . $e->getMessage(),
      'file' => $e->getFile(),
      'line' => $e->getLine()
    ]);
    exit();
  }
}

if ($method === 'POST') {
  try {
    // Get and validate input
    $body = file_get_contents('php://input');
    if (!$body) {
      http_response_code(400);
      echo json_encode(["message" => "Invalid or empty body"]);
      exit();
    }
    
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
      http_response_code(400);
      echo json_encode(["message" => "Body must be valid JSON"]);
      exit();
    }
    
    // Connect to database
    require __DIR__ . '/../../Backend/db.php';
    
    if ($conn->connect_error) {
      throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $stops = $decoded['stops'] ?? [];
    $buses = $decoded['buses'] ?? [];
    
    $newBuses = [];
    $existingBuses = [];
    
    // Process stops first
    $stopIdMap = [];
    foreach ($stops as $s) {
      $name = trim($s['name'] ?? '');
      $lat = isset($s['lat']) ? (float)$s['lat'] : null;
      $lon = isset($s['lon']) ? (float)$s['lon'] : null;
      
      if (empty($name) || $lat === null || $lon === null) continue;
      
      // Check if stop exists
      $stmt = $conn->prepare("SELECT id FROM bus_stops WHERE stop_name = ? AND ABS(latitude - ?) < 0.0001 AND ABS(longitude - ?) < 0.0001 LIMIT 1");
      $stmt->bind_param('sdd', $name, $lat, $lon);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result && $result->num_rows > 0) {
        $dbId = (int)$result->fetch_assoc()['id'];
      } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO bus_stops (stop_name, latitude, longitude) VALUES (?,?,?)");
        $stmt->bind_param('sdd', $name, $lat, $lon);
        $stmt->execute();
        $dbId = $conn->insert_id;
      }
      $stmt->close();
      
      if (isset($s['id'])) {
        $stopIdMap[$s['id']] = $dbId;
      }
    }
    
    // Process buses
    foreach ($buses as $b) {
      $name = trim($b['name'] ?? '');
      $routeNo = trim($b['routeNo'] ?? '');
      $stopIds = $b['stops'] ?? [];
      
      if (empty($name) || empty($routeNo)) continue;
      
      // Check if bus exists
      $stmt = $conn->prepare("SELECT id FROM buses WHERE bus_name = ? AND route_number = ? LIMIT 1");
      $stmt->bind_param('ss', $name, $routeNo);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result && $result->num_rows > 0) {
        $busDbId = (int)$result->fetch_assoc()['id'];
        $existingBuses[] = "$name ($routeNo)";
      } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO buses (bus_name, route_number, status) VALUES (?, ?, 'active')");
        $stmt->bind_param('ss', $name, $routeNo);
        $stmt->execute();
        $busDbId = $conn->insert_id;
        $newBuses[] = "$name ($routeNo)";
      }
      $stmt->close();
      
      // Clear existing routes for this bus
      $stmt = $conn->prepare("DELETE FROM routes WHERE bus_id = ?");
      $stmt->bind_param('i', $busDbId);
      $stmt->execute();
      $stmt->close();
      
      // Add new routes
      $order = 1;
      foreach ($stopIds as $sid) {
        $sDbId = $stopIdMap[$sid] ?? null;
        if (!$sDbId) continue;
        
        $stmt = $conn->prepare("INSERT INTO routes (bus_id, stop_id, stop_order) VALUES (?,?,?)");
        $stmt->bind_param('iii', $busDbId, $sDbId, $order);
        $stmt->execute();
        $stmt->close();
        $order++;
      }
    }
    
    // Build response
    $message = "Saved and synced to MySQL";
    $details = [];
    
    if (count($newBuses) > 0) {
      $details[] = "New buses created: " . implode(", ", $newBuses);
    }
    
    if (count($existingBuses) > 0) {
      $details[] = "Existing buses found (not overwritten): " . implode(", ", $existingBuses);
    }
    
    if (!empty($details)) {
      $message .= " | " . implode(" | ", $details);
    }
    
    echo json_encode([
      "message" => $message,
      "new_buses" => $newBuses,
      "existing_buses" => $existingBuses
    ]);
    
    $conn->close();
    
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
      "message" => "Database save failed",
      "error" => $e->getMessage(),
      "file" => $e->getFile(),
      "line" => $e->getLine()
    ]);
  }
  exit();
}

http_response_code(405);
echo json_encode(["message" => "Method not allowed"]);
?>
