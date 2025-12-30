<?php
header('Content-Type: application/json');
include 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['email'], $data['password'], $data['role'])) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid request"]);
    exit;
}

$email = $conn->real_escape_string($data['email']);
$password = $data['password'];
$role = $conn->real_escape_string($data['role']);

$sql = "SELECT * FROM users WHERE email='$email' AND role='$role'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        // Login successful
        echo json_encode(["message" => "Login successful", "token" => bin2hex(random_bytes(16))]);
    } else {
        http_response_code(401);
        echo json_encode(["message" => "Invalid password"]);
    }
} else {
    http_response_code(404);
    echo json_encode(["message" => "User not found"]);
}

$conn->close();
?>
