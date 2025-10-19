<?php
session_start();

// Include database connection
require_once 'db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html?msg=Unauthorized access");
    exit();
}

// Fetch username for display
$sql = "SELECT username FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$username = htmlspecialchars($user['username']);
$stmt->close();

// Handle teacher approval/disapproval
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['teacher_id'])) {
    $teacher_id = filter_var($_POST['teacher_id'], FILTER_SANITIZE_NUMBER_INT);
    $action = filter_var($_POST['action'], FILTER_SANITIZE_STRING);

    if ($action === 'approve' || $action === 'disallow') {
        $status = $action === 'approve' ? 'approved' : 'disallowed';
        $sql = "UPDATE teachers SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $teacher_id);
        if ($stmt->execute()) {
            $msg = "Teacher " . ($action === 'approve' ? "approved" : "disallowed") . " successfully";
        } else {
            $msg = "Error updating teacher status: " . $conn->error;
        }
        $stmt->close();
    } else {
        $msg = "Invalid action";
    }
}

// Fetch pending teachers
$pending_teachers = [];
$sql = "SELECT id, name, email, username, designation FROM teachers WHERE status = 'pending'";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $pending_teachers[] = $row;
}

// Fetch assigned papers
$assignments = [];
$sql = "SELECT a.course, a.semester, a.course_title, a.due_date, a.status, t.name AS teacher_name 
        FROM assignments a JOIN teachers t ON a.teacher_id = t.id";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}

$conn->close();
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
            <h2>Welcome, <?php echo $username; ?></h2>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
            <?php if (isset($msg)) : ?>
                <div class="message"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            <h3>Pending Teacher Registrations</h3>
            <table class="teacher-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Designation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_teachers)) : ?>
                        <tr>
                            <td colspan="5">No pending registrations</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($pending_teachers as $teacher) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['designation']); ?></td>
                                <td>
                                    <form action="admin_dashboard.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="action-btn approve-btn">Approve</button>
                                    </form>
                                    <form action="admin_dashboard.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                        <input type="hidden" name="action" value="disallow">
                                        <button type="submit" class="action-btn disallow-btn">Disallow</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <h3>Assigned Papers</h3>
            <table class="assignment-table">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Semester</th>
                        <th>Subject</th>
                        <th>Teacher</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assignments)) : ?>
                        <tr>
                            <td colspan="6">No assigned papers</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($assignments as $assignment) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($assignment['course']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['semester']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['course_title']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['teacher_name']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['due_date']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>