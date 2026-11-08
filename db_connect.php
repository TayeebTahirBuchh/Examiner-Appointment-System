<?php
// Local XAMPP defaults. Use environment variables for non-local deployments.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
$database = getenv('DB_NAME') ?: 'examiner_system';

try {
    $conn = new mysqli($host, $username, $password, $database);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('Database connection/configuration failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to connect to the application database. Please try again later.');
}
?>