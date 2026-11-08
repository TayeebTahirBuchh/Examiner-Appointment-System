<?php
// Local XAMPP defaults. Use environment variables for non-local deployments.
mysqli_report(MYSQLI_REPORT_OFF);

$host = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
$database = getenv('DB_NAME') ?: 'examiner_system';

$conn = @new mysqli($host, $username, $password, $database);

if ($conn->connect_errno) {
    error_log('Database connection failed (' . $conn->connect_errno . '): ' . $conn->connect_error);
    http_response_code(500);
    exit('Unable to connect to the application database. Please try again later.');
}

if (!$conn->set_charset('utf8mb4')) {
    error_log('Could not set database connection charset: ' . $conn->error);
    http_response_code(500);
    exit('A server configuration error occurred. Please contact the administrator.');
}
?>