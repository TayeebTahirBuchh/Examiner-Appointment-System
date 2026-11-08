<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_connect.php';

function registrationRedirect(string $message): void
{
    header('Location: index.php?msg=' . urlencode($message));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $conn->close();
    header('Location: index.php');
    exit();
}

if (!hash_equals($_SESSION['registration_csrf'] ?? '', $_POST['csrf_token'] ?? '')) {
    $conn->close();
    registrationRedirect('Security check failed. Refresh the registration page and try again.');
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$designation = trim($_POST['designation'] ?? '');

$allowedDesignations = ['Assistant Professor', 'Senior Assistant Professor', 'Lecturer'];

if ($name === '' || $email === '' || $username === '' || $password === '' || $designation === '') {
    $conn->close();
    registrationRedirect('All fields are required.');
}
if (strlen($name) > 150 || strlen($email) > 190 || strlen($username) > 80 ||
    !preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $username)) {
    $conn->close();
    registrationRedirect('Please check the length and format of your details.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !in_array($designation, $allowedDesignations, true) ||
    strlen($password) < 8 || strlen($password) > 4096) {
    $conn->close();
    registrationRedirect('Invalid email, designation, or password. Password must be at least 8 characters.');
}

try {
    $conn->begin_transaction();
    $check = $conn->prepare(
        'SELECT id FROM users WHERE username = ?
         UNION
         SELECT u.id FROM teachers t JOIN users u ON u.id = t.id WHERE t.email = ?'
    );
    $check->bind_param('ss', $username, $email);
    $check->execute();
    $existing = $check->get_result();
    $check->close();

    if ($existing->num_rows > 0) {
        $conn->rollback();
        $conn->close();
        registrationRedirect('Email or username already exists.');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        throw new RuntimeException('Password hashing failed.');
    }

    $role = 'teacher';
    $userStmt = $conn->prepare(
        'INSERT INTO users (username, password, role) VALUES (?, ?, ?)'
    );
    $userStmt->bind_param('sss', $username, $passwordHash, $role);
    $userStmt->execute();
    $userId = $conn->insert_id;
    $userStmt->close();

    $teacherStmt = $conn->prepare(
        "INSERT INTO teachers (id, name, email, designation, status)
         VALUES (?, ?, ?, ?, 'pending')"
    );
    $teacherStmt->bind_param('isss', $userId, $name, $email, $designation);
    $teacherStmt->execute();
    $teacherStmt->close();

    $conn->commit();
    unset($_SESSION['registration_csrf']);
    $conn->close();
    header('Location: login.html?msg=' . urlencode('Registration successful. Await admin approval.'));
    exit();
} catch (Throwable $exception) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('Teacher registration failed: ' . $exception->getMessage());
    $conn->close();
    registrationRedirect('Registration could not be completed. Please try again.');
}
?>
