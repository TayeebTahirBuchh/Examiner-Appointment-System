<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'competent_authority') {
    header('Location: login.html?msg=' . urlencode('Unauthorized access'));
    exit();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$statusMessage = '';
$statusError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $statusError = 'Security check failed. Refresh the page and try again.';
    } else {
        $assignmentId = filter_var($_POST['assignment_id'] ?? null, FILTER_VALIDATE_INT);
        $newStatus = $_POST['status'] ?? '';
        if (!$assignmentId || !in_array($newStatus, ['approved', 'rejected'], true)) {
            $statusError = 'Invalid assignment or status.';
        } else {
            $stmt = $conn->prepare(
                "UPDATE assignments
                 SET status = ?
                 WHERE id = ? AND status IN ('papers_submitted', 'submitted')"
            );
            $stmt->bind_param('si', $newStatus, $assignmentId);
            if ($stmt->execute() && $stmt->affected_rows === 1) {
                $statusMessage = 'Assignment status updated successfully.';
            } else {
                $statusError = 'The assignment was not updated. It may already have been reviewed.';
            }
            $stmt->close();
        }
    }
}

$assignments = $conn->query(
    "SELECT a.id, a.course, a.semester, a.course_title, a.course_code, a.syllabus,
            a.due_date, a.status, a.question_set1, a.question_set2, a.marking_key,
            a.bill_pdf, t.name AS teacher_name
     FROM assignments a
     JOIN teachers t ON a.teacher_id = t.id
     WHERE a.status IN ('papers_submitted', 'submitted')
     ORDER BY a.due_date ASC, a.id DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competent Authority Dashboard</title>
    <link rel="stylesheet" href="css/competent.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Authority Dashboard</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="assign_paper.php">Assign a Paper</a></li>
                    <li><a href="competent_authority_dashboard.php" class="active">Submitted Papers</a></li>
                    <li>
                        <form action="logout.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-logout">Logout</button>
                        </form>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="dashboard-content">
            <section class="card section">
                <h2>Submitted Papers</h2>
                <?php if ($statusMessage !== ''): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if ($statusError !== ''): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($statusError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Teacher</th><th>Program</th><th>Semester</th>
                                <th>Course Title</th><th>Course Code</th><th>Syllabus</th>
                                <th>Due Date</th><th>Status</th><th>Files</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($assignments && $assignments->num_rows > 0): ?>
                            <?php while ($assignment = $assignments->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($assignment['teacher_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($assignment['course'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)$assignment['semester'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($assignment['course_title'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($assignment['course_code'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if (!empty($assignment['syllabus'])): ?>
                                        <a href="download_file.php?assignment_id=<?= (int) $assignment['id'] ?>&amp;file=syllabus">Download syllabus</a>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars((string)$assignment['due_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($assignment['status'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php foreach (['question_set1' => 'Set 1', 'question_set2' => 'Set 2', 'marking_key' => 'Marking Key', 'bill_pdf' => 'PSF-8 Bill'] as $field => $label): ?>
                                        <?php if (!empty($assignment[$field])): ?>
                                            <a href="download_file.php?assignment_id=<?= (int) $assignment['id'] ?>&amp;file=<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a><br>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <form method="POST" action="competent_authority_dashboard.php">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="assignment_id" value="<?= (int)$assignment['id'] ?>">
                                        <select name="status" required>
                                            <option value="">Select status</option>
                                            <option value="approved">Approve</option>
                                            <option value="rejected">Reject</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="10">No papers are waiting for review.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
