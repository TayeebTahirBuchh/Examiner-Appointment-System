<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'competent_authority') {
    header('Location: login.html?msg=' . urlencode('Unauthorized access'));
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$message = '';
$error = '';

$teachers = $conn->query(
    "SELECT t.id, t.name, t.email
     FROM teachers t
     JOIN users u ON u.id = t.id
     WHERE t.status = 'approved' AND u.role = 'teacher'
     ORDER BY t.name"
);
$courses = $conn->query(
    "SELECT id, course_name, semester, course_code, course_title
     FROM courses
     ORDER BY course_name, semester, course_title"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed. Refresh the page and try again.';
    } else {
        $teacherId = filter_var($_POST['teacher_id'] ?? null, FILTER_VALIDATE_INT);
        $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
        $dueDate = trim($_POST['due_date'] ?? '');

        if (!$teacherId || !$courseId || $dueDate === '') {
            $error = 'Please select an approved teacher, a course, and a due date.';
        } else {
            $teacherStmt = $conn->prepare(
                "SELECT t.id FROM teachers t
                 JOIN users u ON u.id = t.id
                 WHERE t.id = ? AND t.status = 'approved' AND u.role = 'teacher'
                 LIMIT 1"
            );
            $teacherStmt->bind_param('i', $teacherId);
            $teacherStmt->execute();
            $teacherExists = $teacherStmt->get_result()->num_rows === 1;
            $teacherStmt->close();

            $courseStmt = $conn->prepare(
                'SELECT course_name, semester, course_code, course_title
                 FROM courses WHERE id = ? LIMIT 1'
            );
            $courseStmt->bind_param('i', $courseId);
            $courseStmt->execute();
            $course = $courseStmt->get_result()->fetch_assoc();
            $courseStmt->close();

            $dateObject = DateTime::createFromFormat('Y-m-d', $dueDate);
            $validDate = $dateObject && $dateObject->format('Y-m-d') === $dueDate;

            if (!$teacherExists || !$course || !$validDate) {
                $error = 'The selected teacher, course, or due date is invalid.';
            } elseif (!isset($_FILES['syllabus']) || $_FILES['syllabus']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Please upload the syllabus PDF.';
            } elseif ($_FILES['syllabus']['size'] <= 0 || $_FILES['syllabus']['size'] > 10 * 1024 * 1024) {
                $error = 'The syllabus must be a PDF no larger than 10 MB.';
            } else {
                $tmpPath = $_FILES['syllabus']['tmp_name'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($tmpPath);
                $handle = fopen($tmpPath, 'rb');
                $signature = $handle ? fread($handle, 5) : '';
                if ($handle) {
                    fclose($handle);
                }

                if ($mime !== 'application/pdf' || $signature !== '%PDF-') {
                    $error = 'The uploaded syllabus is not a valid PDF file.';
                } else {
                    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'Uploads';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
                        $error = 'The upload directory could not be created.';
                    } else {
                        $filename = 'syllabus_' . bin2hex(random_bytes(16)) . '.pdf';
                        $absolutePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
                        $relativePath = 'Uploads/' . $filename;

                        if (!move_uploaded_file($tmpPath, $absolutePath)) {
                            $error = 'The syllabus could not be saved. Check folder permissions.';
                        } else {
                            try {
                                $conn->begin_transaction();

                                $insert = $conn->prepare(
                                    "INSERT INTO assignments
                                     (teacher_id, course, semester, course_title, course_code, syllabus, due_date, status)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, 'assigned')"
                                );
                                $semester = (int)$course['semester'];
                                $insert->bind_param(
                                    'isissss',
                                    $teacherId,
                                    $course['course_name'],
                                    $semester,
                                    $course['course_title'],
                                    $course['course_code'],
                                    $relativePath,
                                    $dueDate
                                );
                                $insert->execute();
                                $insert->close();

                                $notificationText = 'A new examination paper assignment has been assigned: '
                                    . $course['course_title'] . ' (' . $course['course_code']
                                    . '). Due date: ' . $dueDate . '.';
                                $notification = $conn->prepare(
                                    'INSERT INTO notifications (teacher_id, message) VALUES (?, ?)'
                                );
                                $notification->bind_param('is', $teacherId, $notificationText);
                                $notification->execute();
                                $notification->close();

                                $conn->commit();
                                $message = 'Assignment created and teacher notified successfully.';
                            } catch (Throwable $exception) {
                                $conn->rollback();
                                if (is_file($absolutePath)) {
                                    unlink($absolutePath);
                                }
                                error_log('Assignment creation failed: ' . $exception->getMessage());
                                $error = 'The assignment could not be created. Please check the database and try again.';
                            }
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Examination Paper</title>
    <link rel="stylesheet" href="css/competent.css">
    <style>
        .assign-wrap{max-width:760px;margin:32px auto;padding:24px;background:#fff;border-radius:12px;box-shadow:0 2px 14px rgba(0,0,0,.08)}
        .assign-wrap label{display:block;margin:16px 0 6px;font-weight:600}
        .assign-wrap select,.assign-wrap input{width:100%;padding:11px;border:1px solid #ccc;border-radius:6px;box-sizing:border-box}
        .assign-wrap button{margin-top:20px;padding:12px 20px;border:0;border-radius:6px;cursor:pointer}
        .notice{padding:12px;border-radius:6px;margin:12px 0}
        .success{background:#e8f7ec;color:#175b2a}.error{background:#fdecec;color:#8a1c1c}
        .top-links{max-width:760px;margin:20px auto}
    </style>
</head>
<body>
    <div class="top-links">
        <a href="competent_authority_dashboard.php">← Back to Authority Dashboard</a>
    </div>
    <main class="assign-wrap">
        <h1>Assign Examination Paper</h1>
        <p>Choose an approved teacher, select a course, upload its syllabus, and set the submission deadline.</p>

        <?php if ($message !== ''): ?>
            <div class="notice success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if (!$teachers || $teachers->num_rows === 0): ?>
            <div class="notice error">No approved teachers are available. Approve a teacher account first.</div>
        <?php elseif (!$courses || $courses->num_rows === 0): ?>
            <div class="notice error">No courses are available. Add course records to the courses table before assigning papers.</div>
        <?php else: ?>
        <form method="POST" action="assign_paper.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <label for="teacher_id">Approved Teacher</label>
            <select id="teacher_id" name="teacher_id" required>
                <option value="">Select teacher</option>
                <?php while ($teacher = $teachers->fetch_assoc()): ?>
                    <option value="<?= (int)$teacher['id'] ?>">
                        <?= htmlspecialchars($teacher['name'] . ' — ' . $teacher['email'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="course_id">Course / Subject</label>
            <select id="course_id" name="course_id" required>
                <option value="">Select course</option>
                <?php while ($courseRow = $courses->fetch_assoc()): ?>
                    <option value="<?= (int)$courseRow['id'] ?>">
                        <?= htmlspecialchars(
                            $courseRow['course_name'] . ' — Semester ' . $courseRow['semester']
                            . ' — ' . $courseRow['course_title'] . ' (' . $courseRow['course_code'] . ')',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="due_date">Submission Due Date</label>
            <input type="date" id="due_date" name="due_date" required>

            <label for="syllabus">Syllabus PDF (maximum 10 MB)</label>
            <input type="file" id="syllabus" name="syllabus" accept="application/pdf,.pdf" required>

            <button type="submit">Create Assignment</button>
        </form>
        <?php endif; ?>
    </main>
</body>
</html>
