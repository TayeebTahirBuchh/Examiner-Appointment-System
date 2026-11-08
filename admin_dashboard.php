<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.html?msg=' . urlencode('Unauthorized access'));
    exit();
}

$stmt = $conn->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$username = htmlspecialchars($user['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['teacher_id'])) {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $msg = 'Security check failed. Refresh the page and try again.';
    } else {
        $teacherId = filter_var($_POST['teacher_id'], FILTER_VALIDATE_INT);
        $action = $_POST['action'];
        if ($teacherId && in_array($action, ['approve', 'disallow'], true)) {
            $status = $action === 'approve' ? 'approved' : 'disallowed';
            $update = $conn->prepare("UPDATE teachers SET status = ? WHERE id = ?");
            $update->bind_param('si', $status, $teacherId);
            $msg = $update->execute() ? 'Teacher status updated.' : 'Could not update teacher status.';
            $update->close();
        } else {
            $msg = 'Invalid teacher or action.';
        }
    }
}

$pendingTeachers = [];
$result = $conn->query(
    "SELECT t.id, t.name, t.email, u.username, t.designation
     FROM teachers t
     JOIN users u ON u.id = t.id
     WHERE t.status = 'pending'
     ORDER BY t.created_at ASC"
);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pendingTeachers[] = $row;
    }
}

$assignments = [];
$result = $conn->query(
    "SELECT a.course, a.semester, a.course_title, a.due_date, a.status,
            t.name AS teacher_name
     FROM assignments a
     JOIN teachers t ON a.teacher_id = t.id
     ORDER BY a.created_at DESC"
);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Examiner Appointment System</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="container">
    <h1>Examiner Appointment System</h1>
    <div class="dashboard">
        <h2>Welcome, <?= $username ?></h2>
        <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
        <?php if ($msg !== ''): ?><div class="message"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <h3>Pending Teacher Registrations</h3>
        <table class="teacher-table">
            <thead><tr><th>Name</th><th>Email</th><th>Username</th><th>Designation</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$pendingTeachers): ?>
                <tr><td colspan="5">No pending registrations</td></tr>
            <?php else: foreach ($pendingTeachers as $teacher): ?>
                <tr>
                    <td><?= htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($teacher['email'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($teacher['username'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($teacher['designation'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <form action="admin_dashboard.php" method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="teacher_id" value="<?= (int)$teacher['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="action-btn approve-btn">Approve</button>
                        </form>
                        <form action="admin_dashboard.php" method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="teacher_id" value="<?= (int)$teacher['id'] ?>">
                            <input type="hidden" name="action" value="disallow">
                            <button type="submit" class="action-btn disallow-btn">Disallow</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <h3>Assigned Papers</h3>
        <table class="assignment-table">
            <thead><tr><th>Course</th><th>Semester</th><th>Subject</th><th>Teacher</th><th>Due Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$assignments): ?>
                <tr><td colspan="6">No assigned papers</td></tr>
            <?php else: foreach ($assignments as $assignment): ?>
                <tr>
                    <td><?= htmlspecialchars($assignment['course'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$assignment['semester'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($assignment['course_title'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($assignment['teacher_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$assignment['due_date'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($assignment['status'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
