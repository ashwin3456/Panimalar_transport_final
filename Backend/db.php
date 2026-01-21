<?php
// Backend/db.php

// Start output buffering to prevent any accidental output
if (ob_get_level() == 0) {
    ob_start();
}

// Database configuration
$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'panimalar_bus_tracker',
    'charset' => 'utf8mb4',
    'port' => 3306
];

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/db_errors.log');

// Initialize connection
$conn = null;

/**
 * Get database connection
 * @return mysqli
 * @throws Exception
 */
function getDbConnection() {
    global $conn, $config;
    
    // Return existing connection if available and valid
    if ($conn && $conn instanceof mysqli && $conn->ping()) {
        return $conn;
    }
    
    try {
        // Create new connection
        $conn = @new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database'],
            $config['port']
        );

        // Check connection
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        // Set charset
        if (!$conn->set_charset($config['charset'])) {
            throw new Exception("Error setting charset: " . $conn->error);
        }
        
        // Enable error reporting
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        return $conn;
        
    } catch (Exception $e) {
        // Log the error
        error_log("Database Connection Error: " . $e->getMessage());
        
        // Clean any output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Send JSON error response
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database Error',
            'message' => 'Failed to connect to the database. Please try again later.',
            'debug' => (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? $e->getMessage() : null
        ], JSON_UNESCAPED_UNICODE);
        exit(1);
    }
}

/**
 * Send a JSON response and exit
 */
function sendJsonResponse($data, $statusCode = 200) {
    // Clear any previous output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Set headers
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    
    // Encode the response
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if ($json === false) {
        // If json_encode failed, send an error
        $response = [
            'success' => false,
            'error' => 'JSON encoding error',
            'message' => 'Failed to encode response data',
            'json_error' => json_last_error_msg()
        ];
        $json = json_encode($response);
    }
    
    echo $json;
    exit;
}

// Set custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno] $errstr in $errfile on line $errline");
    return true; // Don't execute PHP internal error handler
});

// Set exception handler
set_exception_handler(function($e) {
    error_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    $response = [
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => 'An unexpected error occurred.',
        'debug' => (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ] : null
    ];
    
    sendJsonResponse($response, 500);
});

// Handle fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING])) {
        error_log("Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}");
        
        $response = [
            'success' => false,
            'error' => 'Internal Server Error',
            'message' => 'A fatal error occurred. Please try again later.',
            'debug' => (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? $error : null
        ];
        
        sendJsonResponse($response, 500);
    }
});

// Initialize the connection
if (!isset($conn)) {
    $conn = getDbConnection();
}