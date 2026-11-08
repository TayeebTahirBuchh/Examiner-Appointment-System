<?php
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$course = trim((string) ($_GET['course'] ?? ''));

if ($course === '' || strlen($course) > 100) {
    http_response_code(400);
    echo json_encode([]);
    $conn->close();
    exit();
}

try {
    $stmt = $conn->prepare(
        'SELECT DISTINCT semester
         FROM courses
         WHERE course_name = ?
         ORDER BY semester'
    );
    $stmt->bind_param('s', $course);
    $stmt->execute();
    $result = $stmt->get_result();

    $semesters = [];
    while ($row = $result->fetch_assoc()) {
        $semesters[] = ['semester' => (int) $row['semester']];
    }

    $stmt->close();
    echo json_encode($semesters, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('get_semesters.php failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
} finally {
    $conn->close();
}
?>