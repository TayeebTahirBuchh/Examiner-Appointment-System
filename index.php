<?php
require_once __DIR__ . '/session_bootstrap.php';
$_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['registration_csrf'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Registration - Examiner Appointment System</title>
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
<div class="container">
    <h1>Examiner Appointment System</h1>
    <div class="form-box" id="register-box">
        <h2>Teacher Registration</h2>
        <?php if (isset($_GET['msg'])): ?>
            <div class="message"><?= htmlspecialchars((string)$_GET['msg'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endif; ?>
        <form action="register.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="input-group"><label for="name">Full Name</label><input type="text" id="name" name="name" maxlength="150" required></div>
            <div class="input-group"><label for="email">Email</label><input type="email" id="email" name="email" maxlength="190" required></div>
            <div class="input-group"><label for="username">Username</label><input type="text" id="username" name="username" minlength="3" maxlength="80" pattern="[A-Za-z0-9_.-]+" required></div>
            <div class="input-group"><label for="password">Password</label><input type="password" id="password" name="password" minlength="8" maxlength="4096" required></div>
            <div class="input-group">
                <label for="designation">Designation</label>
                <select id="designation" name="designation" required>
                    <option value="" disabled selected>Select your designation</option>
                    <option value="Assistant Professor">Assistant Professor</option>
                    <option value="Senior Assistant Professor">Senior Assistant Professor</option>
                    <option value="Lecturer">Lecturer</option>
                </select>
            </div>
            <button type="submit">Register</button>
        </form>
        <p>Already registered? <a href="login.html">Login here</a></p>
    </div>
</div>
</body>
</html>
