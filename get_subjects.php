<?php
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$semester = filter_var($_GET['semester'] ?? null, FILTER_VALIDATE_INT);
$course = trim((string) ($_GET['course'] ?? ''));

if ($semester === false || $semester === null || $semester < 1 || $semester > 255 ||
    strlen($course) > 100) {
    http_response_code(400);
    echo json_encode([]);
    $conn->close();
    exit();
}

try {
    if ($course === '') {
        $stmt = $conn->prepare(
            'SELECT course_code, course_title
             FROM courses
             WHERE semester = ?
             ORDER BY course_title'
        );
        $stmt->bind_param('i', $semester);
    } else {
        $stmt = $conn->prepare(
            'SELECT course_code, course_title
             FROM courses
             WHERE semester = ? AND course_name = ?
             ORDER BY course_title'
        );
        $stmt->bind_param('is', $semester, $course);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $subjects = [];

    while ($row = $result->fetch_assoc()) {
        $subjects[] = [
            'course_code' => $row['course_code'],
            'course_title' => $row['course_title'],
        ];
    }

    $stmt->close();
    echo json_encode($subjects, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('get_subjects.php failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
} finally {
    $conn->close();
}
?>