<?php
session_start();

// Include database connection
require_once 'db_connect.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate inputs
    $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
    $password = $_POST['password'];

    // Validate required fields
    if (empty($username) || empty($password)) {
        $conn->close();
        header("Location: login.html?msg=All fields are required");
        exit();
    }

    // Check users table first
    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $stmt->close();

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            // Handle role-based redirects
            if ($user['role'] === 'teacher') {
                $sql = "SELECT status FROM teachers WHERE username = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows == 1) {
                    $teacher = $result->fetch_assoc();
                    if ($teacher['status'] !== 'approved') {
                        $conn->close();
                        header("Location: login.html?msg=Account not approved or disallowed");
                        exit();
                    }
                }
                $stmt->close();
                error_log("Teacher login: $username, redirecting to teacher_dashboard.php");
                $conn->close();
                header("Location: teacher_dashboard.php");
                exit();
            } elseif ($user['role'] === 'competent_authority') {
                error_log("Competent Authority login: $username, redirecting to competent_authority_dashboard.php");
                $conn->close();
                header("Location: competent_authority_dashboard.php");
                exit();
            } else {
                error_log("Invalid role for user: $username, role: {$user['role']}");
                $conn->close();
                header("Location: login.html?msg=Invalid user role");
                exit();
            }
        } else {
            error_log("Invalid password for user: $username");
            $conn->close();
            header("Location: login.html?msg=Invalid username or password");
            exit();
        }
    }

    // No user found
    $stmt->close();
    error_log("No user found for username: $username");
    $conn->close();
    header("Location: login.html?msg=Invalid username or password");
    exit();
}

// Redirect if accessed directly
$conn->close();
header("Location: login.html");
exit();
?>