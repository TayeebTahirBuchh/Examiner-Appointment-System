<?php
require_once 'db_connect.php';

$course = isset($_GET['course']) ? filter_var($_GET['course'], FILTER_SANITIZE_STRING) : '';

header('Content-Type: application/json');

if (!$course) {
    echo json_encode([]);
    exit;
}

try {
    $sql = "SELECT DISTINCT semester FROM courses WHERE course_name = ? ORDER BY semester";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $course);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $semesters = [];
    while ($row = $result->fetch_assoc()) {
        $semesters[] = ['semester' => $row['semester']];
    }
    
    echo json_encode($semesters);
    $stmt->close();
} catch (Exception $e) {
    error_log("get_semesters.php: Error - " . $e->getMessage());
    echo json_encode([]);
}

$conn->close();
?>