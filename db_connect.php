<?php
// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "examiner_system";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: Set charset to UTF-8 for proper encoding
$conn->set_charset("utf8mb4");
?>