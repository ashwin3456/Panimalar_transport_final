<?php
// location.php - Bus location tracking API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require "db.php";

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['bus_id'])) {
            // Get location history for specific bus
            $bus_id = intval($_GET['bus_id']);
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
            
            $stmt = $conn->prepare("SELECT bl.*, b.bus_name, b.route_number 
                                   FROM bus_locations bl 
                                   JOIN buses b ON bl.bus_id = b.id 
                                   WHERE bl.bus_id = ? 
                                   ORDER BY bl.timestamp DESC 
                                   LIMIT ?");
            $stmt->bind_param("ii", $bus_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $locations = [];
            while ($row = $result->fetch_assoc()) {
                $locations[] = $row;
            }
            echo json_encode($locations);
            $stmt->close();
        } else {
            // Get latest location for all buses
            $result = $conn->query("SELECT bl1.*, b.bus_name, b.route_number 
                                   FROM bus_locations bl1 
                                   JOIN buses b ON bl1.bus_id = b.id 
                                   WHERE bl1.timestamp = (
                                       SELECT MAX(bl2.timestamp) 
                                       FROM bus_locations bl2 
                                       WHERE bl2.bus_id = bl1.bus_id
                                   )
                                   ORDER BY bl1.timestamp DESC");
            $locations = [];
            while ($row = $result->fetch_assoc()) {
                $locations[] = $row;
            }
            echo json_encode($locations);
        }
        break;
        
    case 'POST':
        // Update bus location
        $bus_id = $data['bus_id'] ?? 0;
        $latitude = $data['latitude'] ?? 0;
        $longitude = $data['longitude'] ?? 0;
        $speed = $data['speed'] ?? 0;
        $heading = $data['heading'] ?? 0;
        
        if (!$bus_id || !$latitude || !$longitude) {
            http_response_code(400);
            echo json_encode(["message" => "Bus ID, latitude and longitude are required"]);
            break;
        }
        
        // Verify bus exists
        $checkStmt = $conn->prepare("SELECT id FROM buses WHERE id = ?");
        $checkStmt->bind_param("i", $bus_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            http_response_code(404);
            echo json_encode(["message" => "Bus not found"]);
            $checkStmt->close();
            break;
        }
        $checkStmt->close();
        
        // Insert location
        $stmt = $conn->prepare("INSERT INTO bus_locations (bus_id, latitude, longitude, speed, heading) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("idddi", $bus_id, $latitude, $longitude, $speed, $heading);
        
        if ($stmt->execute()) {
            echo json_encode([
                "message" => "Location updated successfully",
                "timestamp" => date('Y-m-d H:i:s')
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update location", "error" => $stmt->error]);
        }
        $stmt->close();
        break;
        
    default:
        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
        break;
}

$conn->close();
?>
