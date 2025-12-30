<?php
// Function to sanitize user input
function sanitize_input($data) {
    global $conn;
    if (!$conn) {
        $conn = $GLOBALS['conn'];
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Function to register a new user
function register_user($name, $email, $password, $role, $additional_data = []) {
    global $conn;
    
    // Ensure we have a database connection
    if (!$conn || $conn->connect_error) {
        $conn = $GLOBALS['conn'];
        if (!$conn || $conn->connect_error) {
            throw new Exception("Database connection error");
        }
    }
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    if ($hashed_password === false) {
        throw new Exception("Password hashing failed");
    }
    
    // Set default values for all fields
    $roll_number = $additional_data['roll_number'] ?? null;
    $phone_number = $additional_data['phone_number'] ?? null;
    $admin_number = $additional_data['admin_number'] ?? null;
    $faculty_id = $additional_data['faculty_id'] ?? null;
    
    // Prepare the SQL query
    $query = "INSERT INTO users (name, email, password, role, " . 
             "roll_number, phone_number, admin_number, faculty_id) " .
             "VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $bind_result = $stmt->bind_param("ssssssss", 
        $name, 
        $email, 
        $hashed_password, 
        $role,
        $roll_number, 
        $phone_number, 
        $admin_number, 
        $faculty_id
    );
    
    if ($bind_result === false) {
        throw new Exception("Bind param failed: " . $stmt->error);
    }
    
    $execute_result = $stmt->execute();
    
    if ($execute_result === false) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    return $execute_result;
}

// Function to login user
function login_user($email, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Password is correct, set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            
            // Update last login time
            $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $update->bind_param("i", $user['id']);
            $update->execute();
            
            return true;
        }
    }
    
    return false;
}

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}
?>
