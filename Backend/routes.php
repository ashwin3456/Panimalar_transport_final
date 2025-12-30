<?php
// routes.php - Bus routes management API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

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
            // Get routes for specific bus
            $bus_id = intval($_GET['bus_id']);
            $stmt = $conn->prepare("SELECT r.*, bs.stop_name, bs.latitude, bs.longitude, bs.address 
                                   FROM routes r 
                                   JOIN bus_stops bs ON r.stop_id = bs.id 
                                   WHERE r.bus_id = ? 
                                   ORDER BY r.stop_order ASC");
            $stmt->bind_param("i", $bus_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $routes = [];
            while ($row = $result->fetch_assoc()) {
                $routes[] = $row;
            }
            echo json_encode($routes);
            $stmt->close();
        } else {
            // Get all routes with bus and stop details
            $result = $conn->query("SELECT r.*, b.bus_name, b.route_number, bs.stop_name, bs.latitude, bs.longitude 
                                   FROM routes r 
                                   JOIN buses b ON r.bus_id = b.id 
                                   JOIN bus_stops bs ON r.stop_id = bs.id 
                                   ORDER BY b.bus_name, r.stop_order ASC");
            $routes = [];
            while ($row = $result->fetch_assoc()) {
                $routes[] = $row;
            }
            echo json_encode($routes);
        }
        break;
        
    case 'POST':
        // Add stop to bus route
        $bus_id = $data['bus_id'] ?? 0;
        $stop_id = $data['stop_id'] ?? 0;
        $stop_order = $data['stop_order'] ?? 0;
        $estimated_time = $data['estimated_time'] ?? null;
        
        if (!$bus_id || !$stop_id || !$stop_order) {
            http_response_code(400);
            echo json_encode(["message" => "Bus ID, stop ID and stop order are required"]);
            break;
        }
        
        $stmt = $conn->prepare("INSERT INTO routes (bus_id, stop_id, stop_order, estimated_time) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $bus_id, $stop_id, $stop_order, $estimated_time);
        
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            echo json_encode(["message" => "Route stop added successfully", "id" => $new_id]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to add route stop", "error" => $stmt->error]);
        }
        $stmt->close();
        break;
        
    case 'PUT':
        // Update route stop
        $route_id = $data['id'] ?? 0;
        $stop_order = $data['stop_order'] ?? 0;
        $estimated_time = $data['estimated_time'] ?? null;
        
        if (!$route_id || !$stop_order) {
            http_response_code(400);
            echo json_encode(["message" => "Route ID and stop order are required"]);
            break;
        }
        
        $stmt = $conn->prepare("UPDATE routes SET stop_order = ?, estimated_time = ? WHERE id = ?");
        $stmt->bind_param("isi", $stop_order, $estimated_time, $route_id);
        
        if ($stmt->execute()) {
            echo json_encode(["message" => "Route updated successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update route", "error" => $stmt->error]);
        }
        $stmt->close();
        break;
        
    case 'DELETE':
        // Remove stop from route
        $route_id = $data['id'] ?? 0;
        
        if (!$route_id) {
            http_response_code(400);
            echo json_encode(["message" => "Route ID is required"]);
            break;
        }
        
        $stmt = $conn->prepare("DELETE FROM routes WHERE id = ?");
        $stmt->bind_param("i", $route_id);
        
        if ($stmt->execute()) {
            echo json_encode(["message" => "Route stop removed successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to remove route stop", "error" => $stmt->error]);
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