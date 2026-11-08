<?php
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

function registrationRedirect(string $message): void
{
    header('Location: index.html?msg=' . urlencode($message));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $conn->close();
    header('Location: index.html');
    exit();
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$designation = trim($_POST['designation'] ?? '');

$allowedDesignations = [
    'Assistant Professor',
    'Senior Assistant Professor',
    'Lecturer'
];

if ($name === '' || $email === '' || $username === '' ||
    $password === '' || $designation === '') {
    $conn->close();
    registrationRedirect('All fields are required.');
}

if (strlen($name) > 150 || strlen($email) > 190 ||
    strlen($username) > 80 || !preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $username)) {
    $conn->close();
    registrationRedirect('Please check the length and format of your details.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $conn->close();
    registrationRedirect('Invalid email address.');
}

if (!in_array($designation, $allowedDesignations, true)) {
    $conn->close();
    registrationRedirect('Invalid designation selected.');
}

if (strlen($password) < 8 || strlen($password) > 4096) {
    $conn->close();
    registrationRedirect('Password must be at least 8 characters.');
}

try {
    $conn->begin_transaction();

    $check = $conn->prepare(
        'SELECT id FROM users WHERE username = ?
         UNION
         SELECT u.id FROM teachers t
         JOIN users u ON u.id = t.id
         WHERE t.email = ?'
    );
    if (!$check) {
        throw new RuntimeException('Could not prepare duplicate account check.');
    }

    $check->bind_param('ss', $username, $email);
    $check->execute();
    $existing = $check->get_result();

    if ($existing->num_rows > 0) {
        $check->close();
        $conn->rollback();
        $conn->close();
        registrationRedirect('Email or username already exists.');
    }
    $check->close();

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        throw new RuntimeException('Password hashing failed.');
    }

    $role = 'teacher';
    $userStmt = $conn->prepare(
        'INSERT INTO users (username, password, role) VALUES (?, ?, ?)'
    );
    if (!$userStmt) {
        throw new RuntimeException('Could not prepare user registration.');
    }
    $userStmt->bind_param('sss', $username, $passwordHash, $role);
    $userStmt->execute();
    $userId = $conn->insert_id;
    $userStmt->close();

    $teacherStmt = $conn->prepare(
        "INSERT INTO teachers (id, name, email, designation, status)
         VALUES (?, ?, ?, ?, 'pending')"
    );
    if (!$teacherStmt) {
        throw new RuntimeException('Could not prepare teacher profile registration.');
    }
    $teacherStmt->bind_param('isss', $userId, $name, $email, $designation);
    $teacherStmt->execute();
    $teacherStmt->close();

    $conn->commit();
    $conn->close();

    header('Location: login.html?msg=' . urlencode('Registration successful. Await admin approval.'));
    exit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
        // The transaction may already have been rolled back.
    }
    error_log('Teacher registration failed: ' . $e->getMessage());
    $conn->close();
    registrationRedirect('Registration could not be completed. Please try again.');
}
?>