<?php
// bus_stops.php - Bus stops management API
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
            // Get specific bus stop
            $stop_id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT * FROM bus_stops WHERE id = ?");
            $stmt->bind_param("i", $stop_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                echo json_encode($result->fetch_assoc());
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Bus stop not found"]);
            }
            $stmt->close();
        } else {
            // Get all bus stops
            $result = $conn->query("SELECT * FROM bus_stops ORDER BY stop_name ASC");
            $stops = [];
            while ($row = $result->fetch_assoc()) {
                $stops[] = $row;
            }
            echo json_encode($stops);
        }
        break;
        
    case 'POST':
        // Create new bus stop
        $stop_name = $data['stop_name'] ?? '';
        $latitude = $data['latitude'] ?? 0;
        $longitude = $data['longitude'] ?? 0;
        $address = $data['address'] ?? '';
        
        if (!$stop_name || !$latitude || !$longitude) {
            http_response_code(400);
            echo json_encode(["message" => "Stop name, latitude and longitude are required"]);
            break;
        }
        
        $stmt = $conn->prepare("INSERT INTO bus_stops (stop_name, latitude, longitude, address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdds", $stop_name, $latitude, $longitude, $address);
        
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            echo json_encode(["message" => "Bus stop created successfully", "id" => $new_id]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to create bus stop", "error" => $stmt->error]);
        }
        $stmt->close();
        break;
        
    case 'PUT':
        // Update bus stop
        $stop_id = $data['id'] ?? 0;
        $stop_name = $data['stop_name'] ?? '';
        $latitude = $data['latitude'] ?? 0;
        $longitude = $data['longitude'] ?? 0;
        $address = $data['address'] ?? '';
        
        if (!$stop_id || !$stop_name || !$latitude || !$longitude) {
            http_response_code(400);
            echo json_encode(["message" => "Stop ID, name, latitude and longitude are required"]);
            break;
        }
        
        $stmt = $conn->prepare("UPDATE bus_stops SET stop_name = ?, latitude = ?, longitude = ?, address = ? WHERE id = ?");
        $stmt->bind_param("sddsi", $stop_name, $latitude, $longitude, $address, $stop_id);
        
        if ($stmt->execute()) {
            echo json_encode(["message" => "Bus stop updated successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update bus stop", "error" => $stmt->error]);
        }
        $stmt->close();
        break;
        
    case 'DELETE':
        // Delete bus stop
        $stop_id = $data['id'] ?? 0;
        
        if (!$stop_id) {
            http_response_code(400);
            echo json_encode(["message" => "Stop ID is required"]);
            break;
        }
        
        $stmt = $conn->prepare("DELETE FROM bus_stops WHERE id = ?");
        $stmt->bind_param("i", $stop_id);
        
        if ($stmt->execute()) {
            echo json_encode(["message" => "Bus stop deleted successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to delete bus stop", "error" => $stmt->error]);
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
