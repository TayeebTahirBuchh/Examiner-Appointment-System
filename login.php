<?php
// Apply safer session settings before starting the session.
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/db_connect.php';

function redirectToLogin(string $message): void
{
    header('Location: login.html?msg=' . urlencode($message));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToLogin('Please sign in using the login form.');
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '' || strlen($username) > 80) {
    $conn->close();
    redirectToLogin('Invalid username or password.');
}

$stmt = $conn->prepare(
    'SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1'
);
if (!$stmt) {
    error_log('Login query preparation failed: ' . $conn->error);
    $conn->close();
    http_response_code(500);
    exit('A server error occurred. Please try again later.');
}

$stmt->bind_param('s', $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$validRoles = ['teacher', 'competent_authority', 'admin'];
if (!$user || !password_verify($password, $user['password']) ||
    !in_array($user['role'], $validRoles, true)) {
    $conn->close();
    redirectToLogin('Invalid username or password.');
}

if ($user['role'] === 'teacher') {
    $teacherStmt = $conn->prepare(
        'SELECT status FROM teachers WHERE id = ? LIMIT 1'
    );
    if (!$teacherStmt) {
        error_log('Teacher status query preparation failed: ' . $conn->error);
        $conn->close();
        http_response_code(500);
        exit('A server error occurred. Please try again later.');
    }

    $teacherStmt->bind_param('i', $user['id']);
    $teacherStmt->execute();
    $teacher = $teacherStmt->get_result()->fetch_assoc();
    $teacherStmt->close();

    if (!$teacher || $teacher['status'] !== 'approved') {
        $conn->close();
        redirectToLogin('Your account is awaiting approval or is not currently active.');
    }
}

// Rotate the session ID after successful authentication.
session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['role'] = $user['role'];
$_SESSION['authenticated_at'] = time();

$conn->close();

$destinations = [
    'teacher' => 'teacher_dashboard.php',
    'competent_authority' => 'competent_authority_dashboard.php',
    'admin' => 'admin_dashboard.php',
];

header('Location: ' . $destinations[$user['role']]);
exit();
?>