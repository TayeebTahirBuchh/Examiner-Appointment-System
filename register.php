<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

if ($name === '' || $email === '' || $username === '' || $password === '' || $designation === '') {
    header('Location: index.html?msg=' . urlencode('All fields are required.'));
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: index.html?msg=' . urlencode('Invalid email address.'));
    exit();
}

if (!in_array($designation, $allowedDesignations, true)) {
    header('Location: index.html?msg=' . urlencode('Invalid designation selected.'));
    exit();
}

if (strlen($password) < 8) {
    header('Location: index.html?msg=' . urlencode('Password must be at least 8 characters.'));
    exit();
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
    $check->bind_param('ss', $username, $email);
    $check->execute();
    $existing = $check->get_result();

    if ($existing->num_rows > 0) {
        $check->close();
        $conn->rollback();
        header('Location: index.html?msg=' . urlencode('Email or username already exists.'));
        exit();
    }
    $check->close();

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'teacher';

    $userStmt = $conn->prepare(
        'INSERT INTO users (username, password, role) VALUES (?, ?, ?)'
    );
    $userStmt->bind_param('sss', $username, $passwordHash, $role);
    $userStmt->execute();
    $userId = $conn->insert_id;
    $userStmt->close();

    $teacherStmt = $conn->prepare(
        'INSERT INTO teachers (id, name, email, designation, status)
         VALUES (?, ?, ?, ?, "pending")'
    );
    $teacherStmt->bind_param('isss', $userId, $name, $email, $designation);
    $teacherStmt->execute();
    $teacherStmt->close();

    $conn->commit();
    $conn->close();

    header('Location: login.html?msg=' . urlencode('Registration successful. Await admin approval.'));
    exit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Teacher registration failed: ' . $e->getMessage());
    $conn->close();
    header('Location: index.html?msg=' . urlencode('Registration could not be completed. Please try again.'));
    exit();
}
?>
