<?php
// signup.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

function sanitize($v) {
    return htmlspecialchars(trim((string)$v));
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid input"]);
    exit();
}

$name = sanitize($data["name"] ?? "");
$email = sanitize($data["email"] ?? "");
$password = (string)($data["password"] ?? "");
$role = sanitize($data["role"] ?? "");
$roll_number = sanitize($data["roll_number"] ?? "");
$phone_number = sanitize($data["phone_number"] ?? "");
$admin_number = sanitize($data["admin_number"] ?? "");
$faculty_id = sanitize($data["faculty_id"] ?? "");

// Basic validations
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid email format"]);
    exit();
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(["message" => "Password must be at least 8 characters"]);
    exit();
}

// Allowed roles
$allowed_roles = ['Admin','Driver','Faculty','Student'];
if (!in_array($role, $allowed_roles, true)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid role"]);
    exit();
}

// Role-specific required fields (matches DB CHECK constraint)
if ($role === 'Student' && $roll_number === '') {
    http_response_code(400);
    echo json_encode(["message" => "Roll number is required for Student"]);
    exit();
}
if ($role === 'Driver' && $phone_number === '') {
    http_response_code(400);
    echo json_encode(["message" => "Phone number is required for Driver"]);
    exit();
}
if ($role === 'Admin' && $admin_number === '') {
    http_response_code(400);
    echo json_encode(["message" => "Admin number is required for Admin"]);
    exit();
}
if ($role === 'Faculty' && $faculty_id === '') {
    http_response_code(400);
    echo json_encode(["message" => "Faculty ID is required for Faculty"]);
    exit();
}

if (!$name || !$email || !$password || !$role) {
    http_response_code(400);
    echo json_encode(["message" => "Missing required fields"]);
    exit();
}

// Check if user already exists
$checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$checkStmt->bind_param("s", $email);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["message" => "User with this email already exists"]);
    $checkStmt->close();
    $conn->close();
    exit();
}
$checkStmt->close();

// Hash password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Normalize optional empty strings to NULL
$roll_number = $roll_number !== '' ? $roll_number : null;
$phone_number = $phone_number !== '' ? $phone_number : null;
$admin_number = $admin_number !== '' ? $admin_number : null;
$faculty_id = $faculty_id !== '' ? $faculty_id : null;

// Insert into DB
$stmt = $conn->prepare("INSERT INTO users (name, email, password, role, roll_number, phone_number, admin_number, faculty_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["message" => "Server error", "error" => $conn->error]);
    exit();
}
$stmt->bind_param("ssssssss", $name, $email, $hashedPassword, $role, $roll_number, $phone_number, $admin_number, $faculty_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Signup successful"]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Signup failed", "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
