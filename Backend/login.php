<?php
// login.php
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

$email = $data["email"] ?? "";
$password = $data["password"] ?? "";
$role = $data["role"] ?? "";

if (!$email || !$password || !$role) {
    http_response_code(400);
    echo json_encode(["message" => "Missing email, password or role"]);
    exit();
}

// Check user
$stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? AND role = ?");
$stmt->bind_param("ss", $email, $role);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user["password"])) {
        // Simple token (for real production, use JWT)
        $token = base64_encode(random_bytes(24));

        echo json_encode([
            "message" => "Login successful",
            "token" => $token,
            "user" => [
                "id" => $user["id"],
                "name" => $user["name"],
                "email" => $user["email"],
                "role" => $user["role"]
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["message" => "Invalid password"]);
    }
} else {
    http_response_code(404);
    echo json_encode(["message" => "User not found"]);
}

$stmt->close();
$conn->close();
?>
