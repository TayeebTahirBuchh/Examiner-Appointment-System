<?php
session_start();

// Include database connection
require_once 'db_connect.php';

// Check if user is logged in and is a competent authority
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'competent_authority') {
    header("Location: login.html?msg=Unauthorized access");
    exit();
}

// Fetch submitted assignments
$assignments_query = "SELECT a.id, a.course, a.semester, a.course_title, a.course_code, a.syllabus, a.due_date, a.status, a.question_set1, a.question_set2, a.marking_key, a.bill_pdf, t.name as teacher_name
                     FROM assignments a
                     JOIN teachers t ON a.teacher_id = t.id
                     WHERE a.status IN ('papers_submitted', 'submitted')";
$assignments_result = $conn->query($assignments_query);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $assignment_id = $_POST['assignment_id'];
    $new_status = $_POST['status'];
    
    // Validate status
    if (in_array($new_status, ['approved', 'rejected'])) {
        $update_query = "UPDATE assignments SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $new_status, $assignment_id);
        if ($stmt->execute()) {
            $status_success_message = "Status updated successfully!";
        } else {
            $status_error_message = "Error updating status: " . $conn->error;
        }
    } else {
        $status_error_message = "Invalid status selected.";
    }
}
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
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Authority Dashboard</h2>
                <button class="hamburger" onclick="toggleSidebar()">☰</button>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#" onclick="showSection('submitted-papers')" class="active">Submitted Papers</a></li>
                    <li><a href="logout.php" class="btn btn-logout">Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="dashboard-content">
            <!-- Submitted Papers Section -->
            <section id="submitted-papers" class="card section">
                <h2>Submitted Papers</h2>
                <?php if (isset($status_success_message)): ?>
                    <div class="alert alert-success"><?php echo $status_success_message; ?></div>
                <?php endif; ?>
                <?php if (isset($status_error_message)): ?>
                    <div class="alert alert-error"><?php echo $status_error_message; ?></div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Program</th>
                                <th>Semester</th>
                                <th>Course Title</th>
                                <th>Course Code</th>
                                <th>Syllabus</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Files</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($assignment = $assignments_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assignment['teacher_name']); ?></td>
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
                                    <td>
                                        <form action="competent_authority_dashboard.php" method="POST">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                            <select name="status" required>
                                                <option value="">Select Status</option>
                                                <option value="approved">Approve</option>
                                                <option value="rejected">Reject</option>
                                            </select>
                                            <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
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

        // Show submitted papers by default
        showSection('submitted-papers');
    </script>
</body>
</html>