<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_connect.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    exit('Please sign in to access this file.');
}

$allowedRoles = ['teacher', 'competent_authority', 'admin'];
$role = $_SESSION['role'];
if (!in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    exit('You are not authorized to access this file.');
}

$assignmentId = filter_var($_GET['assignment_id'] ?? null, FILTER_VALIDATE_INT);
$fileField = $_GET['file'] ?? '';

$allowedFields = [
    'syllabus',
    'question_set1',
    'question_set2',
    'marking_key',
    'bill_pdf',
];

if (!$assignmentId || $assignmentId < 1 || !in_array($fileField, $allowedFields, true)) {
    http_response_code(400);
    exit('Invalid file request.');
}

$stmt = $conn->prepare(
    'SELECT teacher_id, syllabus, question_set1, question_set2, marking_key, bill_pdf
     FROM assignments
     WHERE id = ?
     LIMIT 1'
);
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$assignment) {
    http_response_code(404);
    exit('Assignment not found.');
}

if ($role === 'teacher' && (int) $assignment['teacher_id'] !== (int) $_SESSION['user_id']) {
    http_response_code(403);
    exit('You are not authorized to access this assignment.');
}

$relativePath = $assignment[$fileField] ?? '';
if (!is_string($relativePath) || !preg_match('#^Uploads/[A-Za-z0-9_-]+\\.pdf$#D', $relativePath)) {
    http_response_code(404);
    exit('File not found.');
}

$uploadRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Uploads');
$filePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

if (
    !$uploadRoot ||
    !$filePath ||
    !str_starts_with($filePath, $uploadRoot . DIRECTORY_SEPARATOR) ||
    !is_file($filePath) ||
    !is_readable($filePath)
) {
    http_response_code(404);
    exit('File not found.');
}

if (!class_exists('finfo')) {
    http_response_code(500);
    exit('File validation is unavailable on this server.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($filePath);
$handle = fopen($filePath, 'rb');
$signature = $handle ? fread($handle, 5) : '';
if ($handle) {
    fclose($handle);
}

if ($mime !== 'application/pdf' || $signature !== '%PDF-') {
    error_log('Blocked non-PDF upload for assignment ' . $assignmentId);
    http_response_code(415);
    exit('The requested file is not a valid PDF.');
}

$downloadName = $fileField . '_assignment_' . $assignmentId . '.pdf';

header('Content-Type: application/pdf');
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Security-Policy: sandbox');

session_write_close();
readfile($filePath);
exit();
?>
