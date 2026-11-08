<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $conn->close();
    header('Location: login.html?msg=' . urlencode('Username and password are required.'));
    exit();
}

$stmt = $conn->prepare(
    'SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1'
);
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user['password'])) {
    $conn->close();
    header('Location: login.html?msg=' . urlencode('Invalid username or password.'));
    exit();
}

if ($user['role'] === 'teacher') {
    $teacherStmt = $conn->prepare('SELECT status FROM teachers WHERE id = ? LIMIT 1');
    $teacherStmt->bind_param('i', $user['id']);
    $teacherStmt->execute();
    $teacher = $teacherStmt->get_result()->fetch_assoc();
    $teacherStmt->close();

    if (!$teacher || $teacher['status'] !== 'approved') {
        $conn->close();
        header('Location: login.html?msg=' . urlencode('Your account is awaiting approval or has been disallowed.'));
        exit();
    }
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['role'] = $user['role'];

$conn->close();

switch ($user['role']) {
    case 'teacher':
        header('Location: teacher_dashboard.php');
        break;
    case 'competent_authority':
        header('Location: competent_authority_dashboard.php');
        break;
    case 'admin':
        header('Location: admin_dashboard.php');
        break;
    default:
        session_destroy();
        header('Location: login.html?msg=' . urlencode('Invalid user role.'));
}
exit();
?>
