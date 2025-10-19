<?php
require_once 'db_connect.php';

$semester = isset($_GET['semester']) ? filter_var($_GET['semester'], FILTER_SANITIZE_NUMBER_INT) : '';
$course = isset($_GET['course']) ? filter_var($_GET['course'], FILTER_SANITIZE_STRING) : '';

header('Content-Type: application/json');

if (!$semester) {
    error_log("get_subjects.php: Missing semester - semester: '$semester', course: '$course'");
    echo json_encode([]);
    exit;
}

try {
    // Log query parameters
    error_log("get_subjects.php: Querying courses for course='$course', semester='$semester'");
    
    // Query: filter by semester and course_name (if provided)
    $sql = "SELECT course_code, course_title FROM courses WHERE semester = ?";
    $params = [$semester];
    $types = "i";
    
    if ($course) {
        $sql .= " AND course_name = ?";
        $params[] = $course;
        $types .= "s";
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = [
            'course_code' => $row['course_code'],
            'course_title' => $row['course_title']
        ];
    }
    
    error_log("get_subjects.php: Fetched " . count($subjects) . " courses: " . json_encode($subjects));
    echo json_encode($subjects);
    $stmt->close();
} catch (Exception $e) {
    error_log("get_subjects.php: Error - " . $e->getMessage());
    echo json_encode([]);
}

$conn->close();
?>