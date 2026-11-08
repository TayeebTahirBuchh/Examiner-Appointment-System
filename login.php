<?php
require_once __DIR__ . '/session_bootstrap.php';
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
$ipAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);

if ($username === '' || $password === '' || strlen($username) > 80) {
    $conn->close();
    redirectToLogin('Invalid username or password.');
}

// Persistent rate limit: maximum 5 failed attempts per username/IP in 15 minutes.
try {
    $rate = $conn->prepare(
        'SELECT COUNT(*) AS attempts FROM login_attempts
         WHERE username = ? AND ip_address = ?
           AND attempted_at >= (NOW() - INTERVAL 15 MINUTE)'
    );
    $rate->bind_param('ss', $username, $ipAddress);
    $rate->execute();
    $attempts = (int)($rate->get_result()->fetch_assoc()['attempts'] ?? 0);
    $rate->close();

    if ($attempts >= 5) {
        $conn->close();
        http_response_code(429);
        exit('Too many unsuccessful login attempts. Please wait 15 minutes before trying again.');
    }

    $stmt = $conn->prepare(
        'SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1'
    );
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $validRoles = ['teacher', 'competent_authority', 'admin'];
    $validCredentials = $user
        && password_verify($password, $user['password'])
        && in_array($user['role'], $validRoles, true);

    if ($validCredentials && $user['role'] === 'teacher') {
        $teacherStmt = $conn->prepare(
            'SELECT status FROM teachers WHERE id = ? LIMIT 1'
        );
        $teacherStmt->bind_param('i', $user['id']);
        $teacherStmt->execute();
        $teacher = $teacherStmt->get_result()->fetch_assoc();
        $teacherStmt->close();
        $validCredentials = $teacher && $teacher['status'] === 'approved';
    }

    if (!$validCredentials) {
        $record = $conn->prepare(
            'INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)'
        );
        $record->bind_param('ss', $username, $ipAddress);
        $record->execute();
        $record->close();
        $conn->close();
        redirectToLogin('Invalid username or password, or account not approved.');
    }

    $clear = $conn->prepare(
        'DELETE FROM login_attempts WHERE username = ? AND ip_address = ?'
    );
    $clear->bind_param('ss', $username, $ipAddress);
    $clear->execute();
    $clear->close();

    session_regenerate_id(true);
    $_SESSION = [
        'user_id' => (int)$user['id'],
        'role' => $user['role'],
        'authenticated_at' => time(),
    ];

    $conn->close();
    $destinations = [
        'teacher' => 'teacher_dashboard.php',
        'competent_authority' => 'competent_authority_dashboard.php',
        'admin' => 'admin_dashboard.php',
    ];
    header('Location: ' . $destinations[$user['role']]);
    exit();
} catch (Throwable $exception) {
    error_log('Login processing failed: ' . $exception->getMessage());
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    http_response_code(500);
    exit('A server error occurred. Please try again later.');
}
?>
