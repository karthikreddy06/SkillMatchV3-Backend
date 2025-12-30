<?php
// includes/config.php
// Correct DB settings for your XAMPP MariaDB server on port 3307.

$DB_HOST = '127.0.0.1';
$DB_PORT = 3307;          // Your MySQL server port
$DB_USER = 'root';
$DB_PASS = '';            // Empty password for XAMPP
$DB_NAME = 'skillmatch';

// Create mysqli connection (IMPORTANT → port is last parameter)
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

// Handle connection error cleanly
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Database connection failed",
        "error" => $conn->connect_error
    ]);
    exit;
}

// Set charset
$conn->set_charset("utf8mb4");

// Make connection globally accessible in any script that includes this file
$GLOBALS['conn'] = $conn;
