<?php
session_start();

// Include database connection
require_once 'db_connect.php';
require_once 'tcpdf/tcpdf_main/tcpdf.php'; // TCPDF for PDF generation

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.html?msg=Unauthorized access");
    exit();
}

// Get teacher ID
$teacher_id = $_SESSION['user_id'];

// Fetch notifications count
$notification_query = "SELECT COUNT(*) as count FROM notifications WHERE teacher_id = ?";
$stmt = $conn->prepare($notification_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$notification_result = $stmt->get_result();
$notification_count = $notification_result->fetch_assoc()['count'];

// Fetch assigned papers
$assignments_query = "SELECT a.id, a.course, a.semester, a.course_title, a.course_code, a.syllabus, a.due_date, a.status, a.question_set1, a.question_set2, a.marking_key, a.bill_pdf 
                     FROM assignments a 
                     WHERE a.teacher_id = ?";
$stmt = $conn->prepare($assignments_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$assignments_result = $stmt->get_result();

// Handle paper submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_papers'])) {
    $assignment_id = $_POST['assignment_id'];
    
    // Handle file uploads
    $upload_dir = 'Uploads/';
    $allowed_types = ['application/pdf'];
    
    // Initialize file paths
    $question_set1 = '';
    $question_set2 = '';
    $marking_key = '';
    
    // Process question_set1
    if (isset($_FILES['question_set1']) && $_FILES['question_set1']['error'] === UPLOAD_ERR_OK) {
        $file_type = $_FILES['question_set1']['type'];
        if (in_array($file_type, $allowed_types)) {
            $filename = time() . '_set1.pdf';
            $destination = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['question_set1']['tmp_name'], $destination)) {
                $question_set1 = $destination;
            }
        }
    }
    
    // Process question_set2
    if (isset($_FILES['question_set2']) && $_FILES['question_set2']['error'] === UPLOAD_ERR_OK) {
        $file_type = $_FILES['question_set2']['type'];
        if (in_array($file_type, $allowed_types)) {
            $filename = time() . '_set2.pdf';
            $destination = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['question_set2']['tmp_name'], $destination)) {
                $question_set2 = $destination;
            }
        }
    }
    
    // Process marking_key
    if (isset($_FILES['marking_key']) && $_FILES['marking_key']['error'] === UPLOAD_ERR_OK) {
        $file_type = $_FILES['marking_key']['type'];
        if (in_array($file_type, $allowed_types)) {
            $filename = time() . '_key.pdf';
            $destination = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['marking_key']['tmp_name'], $destination)) {
                $marking_key = $destination;
            }
        }
    }
    
    // Update assignment in database (papers only)
    $update_query = "UPDATE assignments 
                     SET question_set1 = ?, question_set2 = ?, marking_key = ?, status = 'papers_submitted' 
                     WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sssi", $question_set1, $question_set2, $marking_key, $assignment_id);
    if ($stmt->execute()) {
        $paper_success_message = "Papers submitted successfully!";
    } else {
        $paper_error_message = "Error submitting papers: " . $conn->error;
    }
}

// Handle PSF-8 bill submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_bill'])) {
    $assignment_id = $_POST['assignment_id'];
    
    // Process PSF-8 form data
    $full_name = $_POST['full_name'];
    $address = $_POST['address'];
    $examination = $_POST['examination'];
    $identification_mark = $_POST['identification_mark'];
    $subject = $_POST['subject'];
    $course_no = $_POST['course_no'];
    $no_of_papers = $_POST['no_of_papers'];
    $bank_name = $_POST['bank_name'];
    $account_no = $_POST['account_no'];
    $branch_code = $_POST['branch_code'];
    $ifsc_code = $_POST['ifsc_code'];
    $pan_number = $_POST['pan_number'];
    $remuneration = $_POST['remuneration'];
    $contingencies = $_POST['contingencies'];
    $total = $_POST['total'];
    
    // Generate PSF-8 PDF
    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor(PDF_AUTHOR);
    $pdf->SetTitle('PSF-8 Bill');
    $pdf->SetHeaderData('', 0, PDF_HEADER_TITLE, PDF_HEADER_STRING);
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(PDF_MARGIN_BOTTOM);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->AddPage();
    
    // PDF content
    $html = "
    <h2 style='text-align: center;'>PSF-8 Bill</h2>
    <p><strong>Full Name:</strong> $full_name</p>
    <p><strong>Address:</strong> $address</p>
    <p><strong>Examination:</strong> $examination</p>
    <p><strong>Identification Mark:</strong> $identification_mark</p>
    <p><strong>Subject:</strong> $subject</p>
    <p><strong>Course No.:</strong> $course_no</p>
    <p><strong>No. of Papers:</strong> $no_of_papers</p>
    <p><strong>Bank Name:</strong> $bank_name</p>
    <p><strong>Account No.:</strong> $account_no</p>
    <p><strong>Branch Code:</strong> $branch_code</p>
    <p><strong>IFSC Code:</strong> $ifsc_code</p>
    <p><strong>PAN Number:</strong> $pan_number</p>
    <p><strong>Remuneration:</strong> $remuneration</p>
    <p><strong>Contingencies:</strong> $contingencies</p>
    <p><strong>Total:</strong> $total</p>
    ";
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $bill_filename = time() . '_psf8.pdf';
    $bill_path = 'Uploads/' . $bill_filename;
    $pdf->Output($bill_path, 'F');
    
    // Update assignment in database (bill only)
    $update_query = "UPDATE assignments 
                     SET bill_pdf = ?, status = 'submitted' 
                     WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $bill_path, $assignment_id);
    if ($stmt->execute()) {
        $bill_success_message = "PSF-8 bill submitted successfully!";
    } else {
        $bill_error_message = "Error submitting bill: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="css/teacher.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Teacher Dashboard</h2>
                <button class="hamburger" onclick="toggleSidebar()">☰</button>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#" onclick="showSection('notifications')" class="active">Notifications (<?php echo $notification_count; ?>)</a></li>
                    <li><a href="#" onclick="showSection('assigned-papers')">Assigned Papers</a></li>
                    <li><a href="#" onclick="showSection('submit-papers')">Submit Papers</a></li>
                    <li><a href="#" onclick="showSection('psf8-bill')">PSF-8 Bill</a></li>
                    <li><a href="logout.php" class="btn btn-logout">Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="dashboard-content">
            <!-- Notifications Section -->
            <section id="notifications" class="card section">
                <h2>Notifications</h2>
                <?php
                $notifications_query = "SELECT message FROM notifications WHERE teacher_id = ?";
                $stmt = $conn->prepare($notifications_query);
                $stmt->bind_param("i", $teacher_id);
                $stmt->execute();
                $notifications_result = $stmt->get_result();
                
                if ($notifications_result->num_rows > 0) {
                    while ($notification = $notifications_result->fetch_assoc()) {
                        echo "<div class='notification'>";
                        echo "<p>" . nl2br(htmlspecialchars($notification['message'])) . "</p>";
                        echo "</div>";
                    }
                } else {
                    echo "<p>No notifications available.</p>";
                }
                ?>
            </section>

            <!-- Assigned Papers Section -->
            <section id="assigned-papers" class="card section" style="display: none;">
                <h2>Assigned Papers</h2>
                <?php if (isset($paper_success_message)): ?>
                    <div class="alert alert-success"><?php echo $paper_success_message; ?></div>
                <?php endif; ?>
                <?php if (isset($paper_error_message)): ?>
                    <div class="alert alert-error"><?php echo $paper_error_message; ?></div>
                <?php endif; ?>
                <?php if (isset($bill_success_message)): ?>
                    <div class="alert alert-success"><?php echo $bill_success_message; ?></div>
                <?php endif; ?>
                <?php if (isset($bill_error_message)): ?>
                    <div class="alert alert-error"><?php echo $bill_error_message; ?></div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Semester</th>
                                <th>Course Title</th>
                                <th>Course Code</th>
                                <th>Syllabus</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Files</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($assignment = $assignments_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assignment['course']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['semester']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['course_title']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['course_code']); ?></td>
                                    <td><a href="<?php echo htmlspecialchars($assignment['syllabus']); ?>" target="_blank">View Syllabus</a></td>
                                    <td><?php echo htmlspecialchars($assignment['due_date']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['status']); ?></td>
                                    <td>
                                        <div class="submitted-files">
                                            <?php if ($assignment['question_set1']): ?>
                                                <a href="<?php echo htmlspecialchars($assignment['question_set1']); ?>" target="_blank">Set 1</a>
                                            <?php endif; ?>
                                            <?php if ($assignment['question_set2']): ?>
                                                <a href="<?php echo htmlspecialchars($assignment['question_set2']); ?>" target="_blank">Set 2</a>
                                            <?php endif; ?>
                                            <?php if ($assignment['marking_key']): ?>
                                                <a href="<?php echo htmlspecialchars($assignment['marking_key']); ?>" target="_blank">Key</a>
                                            <?php endif; ?>
                                            <?php if ($assignment['bill_pdf']): ?>
                                                <a href="<?php echo htmlspecialchars($assignment['bill_pdf']); ?>" target="_blank">Bill</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Submit Papers Section -->
            <section id="submit-papers" class="card section" style="display: none;">
                <h2>Submit Papers</h2>
                <form action="teacher_dashboard.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="assignment_id">Select Assignment:</label>
                        <select name="assignment_id" id="assignment_id" required>
                            <option value="">Select an assignment</option>
                            <?php
                            // Re-fetch assignments for dropdown
                            $stmt = $conn->prepare($assignments_query);
                            $stmt->bind_param("i", $teacher_id);
                            $stmt->execute();
                            $dropdown_result = $stmt->get_result();
                            while ($assignment = $dropdown_result->fetch_assoc()) {
                                if ($assignment['status'] === 'assigned' || $assignment['status'] === 'papers_submitted') {
                                    echo "<option value='{$assignment['id']}'>{$assignment['course_title']} ({$assignment['course_code']})</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="question_set1">Question Set 1 (PDF):</label>
                        <input type="file" name="question_set1" accept=".pdf" required>
                    </div>
                    <div class="form-group">
                        <label for="question_set2">Question Set 2 (PDF):</label>
                        <input type="file" name="question_set2" accept=".pdf" required>
                    </div>
                    <div class="form-group">
                        <label for="marking_key">Marking Key (PDF):</label>
                        <input type="file" name="marking_key" accept=".pdf" required>
                    </div>
                    <button type="submit" name="submit_papers" class="btn btn-primary">Submit Papers</button>
                </form>
            </section>

            <!-- PSF-8 Bill Section -->
            <section id="psf8-bill" class="card section" style="display: none;">
                <h2>PSF-8 Bill</h2>
                <form action="teacher_dashboard.php" method="POST">
                    <div class="form-group">
                        <label for="assignment_id_bill">Select Assignment:</label>
                        <select name="assignment_id" id="assignment_id_bill" required>
                            <option value="">Select an assignment</option>
                            <?php
                            // Re-fetch assignments for bill dropdown
                            $stmt = $conn->prepare($assignments_query);
                            $stmt->bind_param("i", $teacher_id);
                            $stmt->execute();
                            $dropdown_result = $stmt->get_result();
                            while ($assignment = $dropdown_result->fetch_assoc()) {
                                if ($assignment['status'] === 'papers_submitted') {
                                    echo "<option value='{$assignment['id']}'>{$assignment['course_title']} ({$assignment['course_code']})</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="full_name">Full Name:</label>
                        <input type="text" name="full_name" value="Dr. Gulfam Ahamad" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address:</label>
                        <textarea name="address" required>Dept. of Computer Science, BGSBU, Rajouri</textarea>
                    </div>
                    <div class="form-group">
                        <label for="examination">Examination:</label>
                        <input type="text" name="examination" value="Semester, 2024" required>
                    </div>
                    <div class="form-group">
                        <label for="identification_mark">Identification Mark:</label>
                        <input type="text" name="identification_mark" value="DEC-246" required>
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject:</label>
                        <input type="text" name="subject" value="Computer Applications in Economics" required>
                    </div>
                    <div class="form-group">
                        <label for="course_no">Course No.:</label>
                        <input type="text" name="course_no" value="EC-246" required>
                    </div>
                    <div class="form-group">
                        <label for="no_of_papers">No. of Papers:</label>
                        <input type="number" name="no_of_papers" value="2" required>
                    </div>
                    <div class="form-group">
                        <label for="bank_name">Bank Name:</label>
                        <input type="text" name="bank_name" value="Test Bank" required>
                    </div>
                    <div class="form-group">
                        <label for="account_no">Account No.:</label>
                        <input type="text" name="account_no" value="1234567890" required>
                    </div>
                    <div class="form-group">
                        <label for="branch_code">Branch Code:</label>
                        <input type="text" name="branch_code" value="TEST123" required>
                    </div>
                    <div class="form-group">
                        <label for="ifsc_code">IFSC Code:</label>
                        <input type="text" name="ifsc_code" value="TEST0001234" required>
                    </div>
                    <div class="form-group">
                        <label for="pan_number">PAN Number:</label>
                        <input type="text" name="pan_number" value="ABCDE1234F" required>
                    </div>
                    <div class="form-group">
                        <label for="remuneration">Remuneration:</label>
                        <input type="number" name="remuneration" value="5000" required>
                    </div>
                    <div class="form-group">
                        <label for="contingencies">Contingencies:</label>
                        <input type="number" name="contingencies" value="200" required>
                    </div>
                    <div class="form-group">
                        <label for="total">Total:</label>
                        <input type="number" name="total" value="5200" required>
                    </div>
                    <button type="submit" name="submit_bill" class="btn btn-primary">Submit Bill</button>
                </form>
            </section>
        </main>
    </div>

    <script>
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(section => {
                section.style.display = 'none';
            });
            // Show selected section
            document.getElementById(sectionId).style.display = 'block';
            // Update active class on sidebar links
            document.querySelectorAll('.sidebar-nav a').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector(`a[onclick="showSection('${sectionId}')"]`).classList.add('active');
        }

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        // Show notifications by default
        showSection('notifications');
    </script>
</body>
</html>