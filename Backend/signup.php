<?php
// signup.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

require "db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid input"]);
    exit();
}

$name = $data["name"] ?? "";
$email = $data["email"] ?? "";
$password = $data["password"] ?? "";
$role = $data["role"] ?? "";
$roll_number = $data["roll_number"] ?? null;
$phone_number = $data["phone_number"] ?? null;
$admin_number = $data["admin_number"] ?? null;
$faculty_id = $data["faculty_id"] ?? null;

if (!$name || !$email || !$password || !$role) {
    http_response_code(400);
    echo json_encode(["message" => "Missing required fields"]);
    exit();
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Insert into DB
$stmt = $conn->prepare("INSERT INTO users (name, email, password, role, roll_number, phone_number, admin_number, faculty_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssss", $name, $email, $hashedPassword, $role, $roll_number, $phone_number, $admin_number, $faculty_id);

if ($stmt->execute()) {
    echo json_encode(["message" => "Signup successful"]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Signup failed", "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
