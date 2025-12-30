<?php
// buses.php - Complete bus management API
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
        if (isset($_GET['id'])) {
            // Get specific bus
            $bus_id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT b.*, u.name as driver_name, u.phone_number as driver_phone 
                                   FROM buses b 
                                   LEFT JOIN users u ON b.driver_id = u.id 
                                   WHERE b.id = ?");
            $stmt->bind_param("i", $bus_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                echo json_encode($result->fetch_assoc());
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Bus not found"]);
            }
            $stmt->close();
        } else {
            // Get all buses
            $result = $conn->query("SELECT b.*, u.name as driver_name, u.phone_number as driver_phone 
                                   FROM buses b 
                                   LEFT JOIN users u ON b.driver_id = u.id 
                                   ORDER BY b.created_at DESC");
            $buses = [];
            while ($row = $result->fetch_assoc()) {
                $buses[] = $row;
            }
            echo json_encode($buses);
        }
        break;
        
    case 'POST':
        // Create new bus
        $bus_name = $data['bus_name'] ?? '';
        $route_number = $data['route_number'] ?? '';
        $driver_id = $data['driver_id'] ?? null;
        $capacity = $data['capacity'] ?? 50;
        $status = $data['status'] ?? 'active';
        
        if (!$bus_name || !$route_number) {
            http_response_code(400);
            echo json_encode(["message" => "Bus name and route number are required"]);
            break;
        }
        
        $stmt = $conn->prepare("INSERT INTO buses (bus_name, route_number, driver_id, capacity, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiss", $bus_name, $route_number, $driver_id, $capacity, $status);
        
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            echo json_encode(["message" => "Bus created successfully", "id" => $new_id]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to create bus", "error" => $stmt->error]);
        }
        $stmt->close();
        break;
        
    case 'PUT':
        // Update bus
        $bus_id = $data['id'] ?? 0;
        $bus_name = $data['bus_name'] ?? '';
        $route_number = $data['route_number'] ?? '';
        $driver_id = $data['driver_id'] ?? null;
        $capacity = $data['capacity'] ?? 50;
        $status = $data['status'] ?? 'active';
        
        if (!$bus_id || !$bus_name || !$route_number) {
            http_response_code(400);
            echo json_encode(["message" => "Bus ID, name and route number are required"]);
            break;
        }
        
        $stmt = $conn->prepare("UPDATE buses SET bus_name = ?, route_number = ?, driver_id = ?, capacity = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssissi", $bus_name, $route_number, $driver_id, $capacity, $status, $bus_id);
        
        if ($stmt->execute()) {
            echo json_encode(["message" => "Bus updated successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update bus", "error" => $stmt->error]);
        }
        $stmt->close();
        break;
        
    case 'DELETE':
        // Delete bus
        $bus_id = $data['id'] ?? 0;
        
        if (!$bus_id) {
            http_response_code(400);
            echo json_encode(["message" => "Bus ID is required"]);
            break;
        }
        
        $stmt = $conn->prepare("DELETE FROM buses WHERE id = ?");
        $stmt->bind_param("i", $bus_id);
        
        if ($stmt->execute()) {
            echo json_encode(["message" => "Bus deleted successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to delete bus", "error" => $stmt->error]);
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
