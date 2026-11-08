<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: login.html?msg=' . urlencode('Unauthorized access'));
    exit();
}

$teacherId = (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$paperSuccessMessage = '';
$paperErrorMessage = '';
$billSuccessMessage = '';
$billErrorMessage = '';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function removeLocalUpload(?string $relativePath): void
{
    if (!$relativePath || !str_starts_with($relativePath, 'Uploads/')) {
        return;
    }

    $base = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Uploads');
    $candidate = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

    if ($base && $candidate && str_starts_with($candidate, $base . DIRECTORY_SEPARATOR) && is_file($candidate)) {
        @unlink($candidate);
    }
}

/**
 * Validate and store one PDF. Returns its relative path or throws RuntimeException.
 */
function storePdf(array $file, string $label): string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException($label . ': invalid upload.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'file exceeds the server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'file exceeds the form upload limit',
            UPLOAD_ERR_PARTIAL => 'file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'file is required',
            UPLOAD_ERR_NO_TMP_DIR => 'server temporary folder is missing',
            UPLOAD_ERR_CANT_WRITE => 'server could not write the file',
            UPLOAD_ERR_EXTENSION => 'a server extension stopped the upload',
        ];
        throw new RuntimeException($label . ': ' . ($messages[$file['error']] ?? 'upload failed') . '.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException($label . ': upload could not be verified.');
    }

    if ($file['size'] <= 0 || $file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException($label . ': PDF must be larger than 0 bytes and no larger than 10 MB.');
    }

    if (!class_exists('finfo')) {
        throw new RuntimeException('PHP Fileinfo is required to validate PDF uploads.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $handle = fopen($file['tmp_name'], 'rb');
    $signature = $handle ? fread($handle, 5) : '';
    if ($handle) {
        fclose($handle);
    }

    if ($mime !== 'application/pdf' || $signature !== '%PDF-') {
        throw new RuntimeException($label . ': only genuine PDF files are accepted.');
    }

    $uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'Uploads';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('The upload directory could not be created.');
    }

    $filename = bin2hex(random_bytes(20)) . '.pdf';
    $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException($label . ': the server could not save the file.');
    }

    return 'Uploads/' . $filename;
}

function getOwnedAssignment(mysqli $conn, int $assignmentId, int $teacherId): ?array
{
    $stmt = $conn->prepare(
        'SELECT id, status, question_set1, question_set2, marking_key, bill_pdf
         FROM assignments
         WHERE id = ? AND teacher_id = ?
         LIMIT 1'
    );
    $stmt->bind_param('ii', $assignmentId, $teacherId);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $assignment;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $paperErrorMessage = 'Security check failed. Refresh the page and try again.';
        $billErrorMessage = $paperErrorMessage;
    } else {
        $assignmentId = filter_var($_POST['assignment_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$assignmentId || $assignmentId < 1) {
            if (isset($_POST['submit_papers'])) {
                $paperErrorMessage = 'Please select a valid assignment.';
            } elseif (isset($_POST['submit_bill'])) {
                $billErrorMessage = 'Please select a valid assignment.';
            }
        } elseif (isset($_POST['submit_papers'])) {
            $assignment = getOwnedAssignment($conn, $assignmentId, $teacherId);

            if (!$assignment) {
                $paperErrorMessage = 'That assignment was not found under your teacher account.';
            } elseif (!in_array($assignment['status'], ['assigned', 'papers_submitted'], true) || !empty($assignment['bill_pdf'])) {
                $paperErrorMessage = 'This assignment is no longer open for paper submission.';
            } else {
                $newFiles = [];
                try {
                    $newFiles['question_set1'] = storePdf($_FILES['question_set1'] ?? [], 'Question Set 1');
                    $newFiles['question_set2'] = storePdf($_FILES['question_set2'] ?? [], 'Question Set 2');
                    $newFiles['marking_key'] = storePdf($_FILES['marking_key'] ?? [], 'Marking Key');

                    $conn->begin_transaction();
                    $stmt = $conn->prepare(
                        "UPDATE assignments
                         SET question_set1 = ?, question_set2 = ?, marking_key = ?, status = 'papers_submitted'
                         WHERE id = ? AND teacher_id = ? AND status IN ('assigned', 'papers_submitted')
                           AND bill_pdf IS NULL"
                    );
                    $stmt->bind_param(
                        'sssii',
                        $newFiles['question_set1'],
                        $newFiles['question_set2'],
                        $newFiles['marking_key'],
                        $assignmentId,
                        $teacherId
                    );
                    $stmt->execute();
                    $updated = $stmt->affected_rows;
                    $stmt->close();

                    if ($updated !== 1) {
                        throw new RuntimeException('The assignment changed while you were submitting. Refresh and try again.');
                    }

                    $conn->commit();

                    removeLocalUpload($assignment['question_set1']);
                    removeLocalUpload($assignment['question_set2']);
                    removeLocalUpload($assignment['marking_key']);

                    $paperSuccessMessage = 'Question papers and marking key submitted successfully.';
                } catch (Throwable $exception) {
                    try {
                        $conn->rollback();
                    } catch (Throwable $ignored) {
                    }
                    foreach ($newFiles as $path) {
                        removeLocalUpload($path);
                    }
                    error_log('Teacher paper submission failed: ' . $exception->getMessage());
                    $paperErrorMessage = $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : 'The papers could not be submitted. Please try again or contact the administrator.';
                }
            }
        } elseif (isset($_POST['submit_bill'])) {
            $assignment = getOwnedAssignment($conn, $assignmentId, $teacherId);

            if (!$assignment) {
                $billErrorMessage = 'That assignment was not found under your teacher account.';
            } elseif ($assignment['status'] !== 'papers_submitted' || empty($assignment['question_set1']) || empty($assignment['question_set2']) || empty($assignment['marking_key'])) {
                $billErrorMessage = 'Submit all three paper PDFs first. The PSF-8 bill is available after paper submission.';
            } else {
                $fields = [
                    'full_name' => trim($_POST['full_name'] ?? ''),
                    'address' => trim($_POST['address'] ?? ''),
                    'examination' => trim($_POST['examination'] ?? ''),
                    'identification_mark' => trim($_POST['identification_mark'] ?? ''),
                    'subject' => trim($_POST['subject'] ?? ''),
                    'course_no' => trim($_POST['course_no'] ?? ''),
                    'bank_name' => trim($_POST['bank_name'] ?? ''),
                    'account_no' => trim($_POST['account_no'] ?? ''),
                    'branch_code' => trim($_POST['branch_code'] ?? ''),
                    'ifsc_code' => strtoupper(trim($_POST['ifsc_code'] ?? '')),
                    'pan_number' => strtoupper(trim($_POST['pan_number'] ?? '')),
                ];
                $noOfPapers = filter_var($_POST['no_of_papers'] ?? null, FILTER_VALIDATE_INT);
                $remuneration = filter_var($_POST['remuneration'] ?? null, FILTER_VALIDATE_FLOAT);
                $contingencies = filter_var($_POST['contingencies'] ?? null, FILTER_VALIDATE_FLOAT);

                foreach ($fields as $value) {
                    if ($value === '' || mb_strlen($value) > 2500) {
                        $billErrorMessage = 'Please complete all PSF-8 fields. Text fields must be no longer than 2,500 characters.';
                        break;
                    }
                }

                if ($billErrorMessage === '' && (!$noOfPapers || $noOfPapers < 1 || $noOfPapers > 100)) {
                    $billErrorMessage = 'Number of papers must be between 1 and 100.';
                }
                if ($billErrorMessage === '' && ($remuneration === false || $contingencies === false || $remuneration < 0 || $contingencies < 0 || $remuneration > 10000000 || $contingencies > 10000000)) {
                    $billErrorMessage = 'Enter valid non-negative remuneration and contingency amounts.';
                }
                if ($billErrorMessage === '' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $fields['ifsc_code'])) {
                    $billErrorMessage = 'Enter a valid 11-character IFSC code.';
                }
                if ($billErrorMessage === '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $fields['pan_number'])) {
                    $billErrorMessage = 'Enter a valid PAN number.';
                }
                if ($billErrorMessage === '' && !preg_match('/^[0-9]{6,20}$/', $fields['account_no'])) {
                    $billErrorMessage = 'Bank account number must contain 6 to 20 digits.';
                }

                if ($billErrorMessage === '') {
                    $tcpdfPath = __DIR__ . DIRECTORY_SEPARATOR . 'tcpdf' . DIRECTORY_SEPARATOR . 'tcpdf_main' . DIRECTORY_SEPARATOR . 'tcpdf.php';

                    if (!is_file($tcpdfPath)) {
                        $billErrorMessage = 'TCPDF was not found at tcpdf/tcpdf_main/tcpdf.php. Please check the library folder.';
                    } else {
                        require_once $tcpdfPath;
                        $billPath = null;

                        try {
                            $uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'Uploads';
                            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
                                throw new RuntimeException('The upload directory could not be created.');
                            }

                            $filename = 'psf8_' . bin2hex(random_bytes(20)) . '.pdf';
                            $absolutePath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
                            $billPath = 'Uploads/' . $filename;

                            $total = round((float) $remuneration + (float) $contingencies, 2);
                            $money = static fn($amount): string => number_format((float) $amount, 2);

                            $pdf = new TCPDF();
                            $pdf->SetCreator('Examiner Appointment System');
                            $pdf->SetAuthor('Examiner Appointment System');
                            $pdf->SetTitle('PSF-8 Bill');
                            $pdf->SetMargins(15, 15, 15);
                            $pdf->SetAutoPageBreak(true, 15);
                            $pdf->AddPage();

                            $html = '<h2 style="text-align:center;">PSF-8 Bill</h2>'
                                . '<table cellpadding="5" border="1">'
                                . '<tr><td width="35%"><b>Full Name</b></td><td>' . e($fields['full_name']) . '</td></tr>'
                                . '<tr><td><b>Address</b></td><td>' . nl2br(e($fields['address'])) . '</td></tr>'
                                . '<tr><td><b>Examination</b></td><td>' . e($fields['examination']) . '</td></tr>'
                                . '<tr><td><b>Identification Mark</b></td><td>' . e($fields['identification_mark']) . '</td></tr>'
                                . '<tr><td><b>Subject</b></td><td>' . e($fields['subject']) . '</td></tr>'
                                . '<tr><td><b>Course No.</b></td><td>' . e($fields['course_no']) . '</td></tr>'
                                . '<tr><td><b>No. of Papers</b></td><td>' . (int) $noOfPapers . '</td></tr>'
                                . '<tr><td><b>Bank Name</b></td><td>' . e($fields['bank_name']) . '</td></tr>'
                                . '<tr><td><b>Account No.</b></td><td>' . e($fields['account_no']) . '</td></tr>'
                                . '<tr><td><b>Branch Code</b></td><td>' . e($fields['branch_code']) . '</td></tr>'
                                . '<tr><td><b>IFSC Code</b></td><td>' . e($fields['ifsc_code']) . '</td></tr>'
                                . '<tr><td><b>PAN Number</b></td><td>' . e($fields['pan_number']) . '</td></tr>'
                                . '<tr><td><b>Remuneration</b></td><td>₹ ' . $money($remuneration) . '</td></tr>'
                                . '<tr><td><b>Contingencies</b></td><td>₹ ' . $money($contingencies) . '</td></tr>'
                                . '<tr><td><b>Total</b></td><td>₹ ' . $money($total) . '</td></tr>'
                                . '</table>';

                            $pdf->writeHTML($html, true, false, true, false, '');
                            $pdf->Output($absolutePath, 'F');

                            $conn->begin_transaction();
                            $stmt = $conn->prepare(
                                "UPDATE assignments
                                 SET bill_pdf = ?, status = 'submitted'
                                 WHERE id = ? AND teacher_id = ? AND status = 'papers_submitted'"
                            );
                            $stmt->bind_param('sii', $billPath, $assignmentId, $teacherId);
                            $stmt->execute();
                            $updated = $stmt->affected_rows;
                            $stmt->close();

                            if ($updated !== 1) {
                                throw new RuntimeException('The assignment changed while the bill was being generated. Refresh and try again.');
                            }

                            $conn->commit();
                            $billSuccessMessage = 'PSF-8 bill generated and submitted successfully. The total was calculated automatically.';
                        } catch (Throwable $exception) {
                            try {
                                $conn->rollback();
                            } catch (Throwable $ignored) {
                            }
                            if ($billPath) {
                                removeLocalUpload($billPath);
                            }
                            error_log('PSF-8 generation failed: ' . $exception->getMessage());
                            $billErrorMessage = $exception instanceof RuntimeException
                                ? $exception->getMessage()
                                : 'The PSF-8 bill could not be generated. Please try again or contact the administrator.';
                        }
                    }
                }
            }
        }
    }
}

$notificationCount = 0;
$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM notifications WHERE teacher_id = ?');
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$notificationCount = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

$notifications = [];
$stmt = $conn->prepare(
    'SELECT message, created_at FROM notifications WHERE teacher_id = ? ORDER BY created_at DESC'
);
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

$assignments = [];
$stmt = $conn->prepare(
    'SELECT id, course, semester, course_title, course_code, syllabus, due_date,
            status, question_set1, question_set2, marking_key, bill_pdf
     FROM assignments
     WHERE teacher_id = ?
     ORDER BY due_date ASC, id DESC'
);
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}
$stmt->close();

$paperAssignments = array_filter(
    $assignments,
    static fn($assignment) => in_array($assignment['status'], ['assigned', 'papers_submitted'], true)
        && empty($assignment['bill_pdf'])
);
$billAssignments = array_filter(
    $assignments,
    static fn($assignment) => $assignment['status'] === 'papers_submitted'
        && !empty($assignment['question_set1'])
        && !empty($assignment['question_set2'])
        && !empty($assignment['marking_key'])
);
$initialSection = isset($_POST['submit_bill']) ? 'psf8-bill'
    : (isset($_POST['submit_papers']) ? 'submit-papers' : 'notifications');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="css/teacher.css">
    <style>
        .status-pill{display:inline-block;padding:4px 9px;border-radius:14px;background:#eef1f5;font-size:.85rem}
        .form-note{font-size:.9rem;color:#555;margin:6px 0 12px}
        .alert{padding:12px;margin:12px 0;border-radius:6px}
        .alert-success{background:#e8f7ec;color:#175b2a}
        .alert-error{background:#fdecec;color:#8a1c1c}
        .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
        .form-group textarea{width:100%;min-height:76px}
        .file-links a{display:inline-block;margin:2px 8px 2px 0}
    </style>
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Teacher Dashboard</h2>
            <button class="hamburger" type="button" onclick="toggleSidebar()" aria-label="Toggle navigation">☰</button>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="#notifications" data-section="notifications" class="active">Notifications (<?= $notificationCount ?>)</a></li>
                <li><a href="#assigned-papers" data-section="assigned-papers">Assigned Papers</a></li>
                <li><a href="#submit-papers" data-section="submit-papers">Submit Papers</a></li>
                <li><a href="#psf8-bill" data-section="psf8-bill">PSF-8 Bill</a></li>
                <li><a href="logout.php" class="btn btn-logout">Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard-content">
        <section id="notifications" class="card section">
            <h2>Notifications</h2>
            <?php if (!$notifications): ?>
                <p>No notifications available.</p>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification">
                        <p><?= nl2br(e($notification['message'])) ?></p>
                        <small><?= e($notification['created_at']) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section id="assigned-papers" class="card section" style="display:none;">
            <h2>Assigned Papers</h2>
            <?php if ($paperSuccessMessage !== ''): ?><div class="alert alert-success"><?= e($paperSuccessMessage) ?></div><?php endif; ?>
            <?php if ($paperErrorMessage !== ''): ?><div class="alert alert-error"><?= e($paperErrorMessage) ?></div><?php endif; ?>
            <?php if ($billSuccessMessage !== ''): ?><div class="alert alert-success"><?= e($billSuccessMessage) ?></div><?php endif; ?>
            <?php if ($billErrorMessage !== ''): ?><div class="alert alert-error"><?= e($billErrorMessage) ?></div><?php endif; ?>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Program</th><th>Semester</th><th>Course Title</th><th>Course Code</th>
                        <th>Syllabus</th><th>Due Date</th><th>Status</th><th>Submitted Files</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$assignments): ?>
                        <tr><td colspan="8">No assignments have been allocated to you.</td></tr>
                    <?php else: foreach ($assignments as $assignment): ?>
                        <tr>
                            <td><?= e($assignment['course']) ?></td>
                            <td><?= e($assignment['semester']) ?></td>
                            <td><?= e($assignment['course_title']) ?></td>
                            <td><?= e($assignment['course_code']) ?></td>
                            <td>
                                <?php if (!empty($assignment['syllabus'])): ?>
                                    <a href="download_file.php?assignment_id=<?= (int) $assignment['id'] ?>&amp;file=syllabus">Download Syllabus</a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td><?= e($assignment['due_date']) ?></td>
                            <td><span class="status-pill"><?= e(ucwords(str_replace('_', ' ', $assignment['status']))) ?></span></td>
                            <td class="file-links">
                                <?php foreach (['question_set1' => 'Set 1', 'question_set2' => 'Set 2', 'marking_key' => 'Marking Key', 'bill_pdf' => 'PSF-8 Bill'] as $field => $label): ?>
                                    <?php if (!empty($assignment[$field])): ?>
                                        <a href="download_file.php?assignment_id=<?= (int) $assignment['id'] ?>&amp;file=<?= e($field) ?>"><?= e($label) ?></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (empty($assignment['question_set1']) && empty($assignment['question_set2']) && empty($assignment['marking_key']) && empty($assignment['bill_pdf'])): ?>—<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="submit-papers" class="card section" style="display:none;">
            <h2>Submit Papers</h2>
            <?php if ($paperSuccessMessage !== ''): ?><div class="alert alert-success"><?= e($paperSuccessMessage) ?></div><?php endif; ?>
            <?php if ($paperErrorMessage !== ''): ?><div class="alert alert-error"><?= e($paperErrorMessage) ?></div><?php endif; ?>
            <?php if (!$paperAssignments): ?>
                <p>No assignments are currently open for paper submission.</p>
            <?php else: ?>
                <form action="teacher_dashboard.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <div class="form-group">
                        <label for="assignment_id">Select Assignment:</label>
                        <select name="assignment_id" id="assignment_id" required>
                            <option value="">Select an assignment</option>
                            <?php foreach ($paperAssignments as $assignment): ?>
                                <option value="<?= (int) $assignment['id'] ?>">
                                    <?= e($assignment['course_title'] . ' (' . $assignment['course_code'] . ') — ' . $assignment['status']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="form-note">Upload three genuine PDF files. Maximum size: 10 MB per file.</p>
                    <div class="form-group">
                        <label for="question_set1">Question Set 1 (PDF):</label>
                        <input type="file" id="question_set1" name="question_set1" accept="application/pdf,.pdf" required>
                    </div>
                    <div class="form-group">
                        <label for="question_set2">Question Set 2 (PDF):</label>
                        <input type="file" id="question_set2" name="question_set2" accept="application/pdf,.pdf" required>
                    </div>
                    <div class="form-group">
                        <label for="marking_key">Marking Key (PDF):</label>
                        <input type="file" id="marking_key" name="marking_key" accept="application/pdf,.pdf" required>
                    </div>
                    <button type="submit" name="submit_papers" value="1" class="btn btn-primary">Submit Papers</button>
                </form>
            <?php endif; ?>
        </section>

        <section id="psf8-bill" class="card section" style="display:none;">
            <h2>PSF-8 Bill</h2>
            <?php if ($billSuccessMessage !== ''): ?><div class="alert alert-success"><?= e($billSuccessMessage) ?></div><?php endif; ?>
            <?php if ($billErrorMessage !== ''): ?><div class="alert alert-error"><?= e($billErrorMessage) ?></div><?php endif; ?>
            <?php if (!$billAssignments): ?>
                <p>Submit all three paper PDFs first. Eligible assignments will appear here.</p>
            <?php else: ?>
                <form action="teacher_dashboard.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <div class="form-group">
                        <label for="assignment_id_bill">Select Assignment:</label>
                        <select name="assignment_id" id="assignment_id_bill" required>
                            <option value="">Select an assignment</option>
                            <?php foreach ($billAssignments as $assignment): ?>
                                <option value="<?= (int) $assignment['id'] ?>"><?= e($assignment['course_title'] . ' (' . $assignment['course_code'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="form-note">Enter your own details. No sample personal or banking details are pre-filled. The total is calculated automatically.</p>
                    <div class="form-grid">
                        <div class="form-group"><label for="full_name">Full Name:</label><input id="full_name" type="text" name="full_name" maxlength="150" required></div>
                        <div class="form-group"><label for="examination">Examination:</label><input id="examination" type="text" name="examination" maxlength="200" required></div>
                        <div class="form-group"><label for="identification_mark">Identification Mark:</label><input id="identification_mark" type="text" name="identification_mark" maxlength="100" required></div>
                        <div class="form-group"><label for="subject">Subject:</label><input id="subject" type="text" name="subject" maxlength="200" required></div>
                        <div class="form-group"><label for="course_no">Course No.:</label><input id="course_no" type="text" name="course_no" maxlength="80" required></div>
                        <div class="form-group"><label for="no_of_papers">No. of Papers:</label><input id="no_of_papers" type="number" name="no_of_papers" min="1" max="100" step="1" required></div>
                        <div class="form-group"><label for="bank_name">Bank Name:</label><input id="bank_name" type="text" name="bank_name" maxlength="150" required></div>
                        <div class="form-group"><label for="account_no">Account Number:</label><input id="account_no" type="text" name="account_no" inputmode="numeric" pattern="[0-9]{6,20}" maxlength="20" required></div>
                        <div class="form-group"><label for="branch_code">Branch Code:</label><input id="branch_code" type="text" name="branch_code" maxlength="30" required></div>
                        <div class="form-group"><label for="ifsc_code">IFSC Code:</label><input id="ifsc_code" type="text" name="ifsc_code" pattern="[A-Za-z]{4}0[A-Za-z0-9]{6}" maxlength="11" required></div>
                        <div class="form-group"><label for="pan_number">PAN Number:</label><input id="pan_number" type="text" name="pan_number" pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]" maxlength="10" required></div>
                        <div class="form-group"><label for="remuneration">Remuneration (₹):</label><input id="remuneration" type="number" name="remuneration" min="0" max="10000000" step="0.01" required></div>
                        <div class="form-group"><label for="contingencies">Contingencies (₹):</label><input id="contingencies" type="number" name="contingencies" min="0" max="10000000" step="0.01" required></div>
                        <div class="form-group"><label for="total_preview">Calculated Total (₹):</label><input id="total_preview" type="text" readonly placeholder="Calculated automatically"></div>
                    </div>
                    <div class="form-group">
                        <label for="address">Address:</label>
                        <textarea id="address" name="address" maxlength="1000" required></textarea>
                    </div>
                    <button type="submit" name="submit_bill" value="1" class="btn btn-primary">Generate and Submit PSF-8 Bill</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
</div>

<script>
    function showSection(sectionId) {
        document.querySelectorAll('.section').forEach(section => {
            section.style.display = section.id === sectionId ? 'block' : 'none';
        });
        document.querySelectorAll('.sidebar-nav a[data-section]').forEach(link => {
            link.classList.toggle('active', link.dataset.section === sectionId);
        });
    }

    document.querySelectorAll('.sidebar-nav a[data-section]').forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            showSection(link.dataset.section);
        });
    });

    function toggleSidebar() {
        document.querySelector('.sidebar')?.classList.toggle('active');
    }

    const remunerationInput = document.getElementById('remuneration');
    const contingenciesInput = document.getElementById('contingencies');
    const totalPreview = document.getElementById('total_preview');

    function updateTotalPreview() {
        if (!remunerationInput || !contingenciesInput || !totalPreview) return;
        const remuneration = Number(remunerationInput.value || 0);
        const contingencies = Number(contingenciesInput.value || 0);
        totalPreview.value = (remuneration + contingencies).toFixed(2);
    }

    remunerationInput?.addEventListener('input', updateTotalPreview);
    contingenciesInput?.addEventListener('input', updateTotalPreview);

    showSection(<?= json_encode($initialSection) ?>);
</script>
</body>
</html>
