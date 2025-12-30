<?php
// login.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// Function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Function to validate CSRF token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Main login logic
try {
    // Get and validate input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !is_array($data)) {
        throw new Exception('Invalid input data');
    }

    $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? '';
    $csrfToken = $data['csrf_token'] ?? '';

    // Validate CSRF token only if provided (backward compatibility for current frontend)
    if (!empty($csrfToken) && !validateCSRFToken($csrfToken)) {
        throw new Exception('Invalid CSRF token');
    }

    // Validate required fields
    if (empty($email) || empty($password) || empty($role)) {
        throw new Exception('Email, password, and role are required');
    }

    // Additional validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    if (!in_array($role, ['Admin', 'Driver', 'Faculty', 'Student'])) {
        throw new Exception('Invalid role specified');
    }

    // Check user with prepared statement (no last_login / is_active for compatibility)
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? AND role = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        // Log failed login attempt
        error_log("Login failed: User not found - $email ($role)");
        throw new Exception('Invalid email or password');
    }

    $user = $result->fetch_assoc();

    // Verify password
    if (!password_verify($password, $user['password'])) {
        // Log failed login attempt
        error_log("Login failed: Invalid password for $email");
        throw new Exception('Invalid email or password');
    }

    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['last_activity'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // If you add a last_login column later, you can update it here.

    // Generate a secure token (for API use if needed)
    $token = bin2hex(random_bytes(32));

    // Store token in database (for API authentication), if the table exists
    $tokenStmt = $conn->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
                               VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))");
    if ($tokenStmt) {
        $tokenStmt->bind_param("isss", $user['id'], $token, $_SESSION['ip_address'], $_SESSION['user_agent']);
        $tokenStmt->execute();
    } else {
        // Log but don't fail login if sessions table is missing
        error_log('user_sessions insert prepare failed: ' . $conn->error);
    }

    // Prepare response
    $response = [
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ],
        'token' => $token,
        'redirect' => strtolower($user['role']) . '_dashboard.html',
        'csrf_token' => generateCSRFToken() // Generate new CSRF token for next request
    ];

    // Set secure cookie if using HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        setcookie('auth_token', $token, [
            'expires' => time() + (86400 * 30), // 30 days
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'csrf_token' => generateCSRFToken() // Always include CSRF token in response
    ]);
} finally {
    // Close statements and connection
    if (isset($stmt)) $stmt->close();
    if (isset($update)) $update->close();
    if (isset($tokenStmt)) $tokenStmt->close();
    if (isset($conn)) $conn->close();
}
?>
