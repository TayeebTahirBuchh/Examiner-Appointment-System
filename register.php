<?php
session_start();

// Include database connection
require_once 'db_connect.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate inputs
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    $designation = filter_var($_POST['designation'], FILTER_SANITIZE_STRING);

    // Validate required fields
    if (empty($name) || empty($email) || empty($username) || empty($password) || empty($designation)) {
        header("Location: index.html?msg=All fields are required");
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.html?msg=Invalid email format");
        exit();
    }

    // Validate designation (ensure it's one of the allowed options)
    $valid_designations = ['Assistant Professor', 'Senior Assistant Professor', 'Lecturer'];
    if (!in_array($designation, $valid_designations)) {
        header("Location: index.html?msg=Invalid designation selected");
        exit();
    }

    // Check for duplicate email or username
    $sql = "SELECT id FROM teachers WHERE email = ? OR username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt->close();
        header("Location: index.html?msg=Email or username already exists");
        exit();
    }
    $stmt->close();

    // Hash the password
    $password_hashed = password_hash($password, PASSWORD_DEFAULT);

    // Insert teacher into database
    $sql = "INSERT INTO teachers (name, email, username, password, designation, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $name, $email, $username, $password_hashed, $designation);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header("Location: login.html?msg=Registration successful. Await admin approval.");
        exit();
    } else {
        $stmt->close();
        $conn->close();
        header("Location: index.html?msg=Error during registration: " . $conn->error);
        exit();
    }
} else {
    // Redirect if accessed directly
    header("Location: index.html");
    exit();
}
?>