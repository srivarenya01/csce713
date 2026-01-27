<?php
// Disable error display to prevent breaking JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Database configuration
$db_host = getenv('DB_HOST') ?: 'db';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: 'hackerhub2024';
$db_name = getenv('DB_NAME') ?: 'hackerhub';

// Create database connection with retry logic
$max_retries = 10;
$retry_delay = 1; // seconds
$conn = null;

for ($i = 0; $i < $max_retries; $i++) {
    @$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if (!$conn->connect_error) {
        break; // Connection successful
    }
    
    if ($i < $max_retries - 1) {
        sleep($retry_delay);
    }
}

// Check if connection was successful
if ($conn === null || $conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Set charset
$conn->set_charset("utf8mb4");

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// Helper function to get current user
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username']
        ];
    }
    return null;
}
?>

