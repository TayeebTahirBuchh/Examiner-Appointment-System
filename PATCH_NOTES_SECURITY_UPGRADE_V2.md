# Security Upgrade 2

## Files in this package
- `session_bootstrap.php` — consistent secure session-cookie settings.
- `login.php` — session ID rotation and persistent login throttling.
- `register.php` — validates a server-generated registration CSRF token.
- `index.php` — registration page that creates the CSRF token.
- `logout.php` — requires POST and a valid CSRF token.
- `login_attempts.sql` — creates the table required for rate limiting.

## Installation steps

1. Back up the repository and database.
2. Upload the new `session_bootstrap.php`, `login.php`, `register.php`, `index.php`, and `logout.php`.
3. Delete the old root `index.html` registration page. Registration must now open through `index.php`.
4. Open phpMyAdmin, select `examiner_system`, and import `login_attempts.sql`.
5. In each of these existing files, replace the first `session_start();` line with:
   `require_once __DIR__ . '/session_bootstrap.php';`
   Files: `admin_dashboard.php`, `assign_paper.php`, `competent_authority_dashboard.php`, `teacher_dashboard.php`, and `download_file.php`.
6. In `download_file.php`, ensure the Content-Disposition line is exactly:
   `header('Content-Disposition: attachment; filename="' . $downloadName . '"');`
7. In `login.html`, change the **Register here** link from `index.html` to `index.php`. Change any other registration links or redirects that still point to `index.html` to `index.php`.
8. Change every dashboard logout link into a POST form containing the session CSRF token:
   `<form method="POST" action="logout.php"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><button type="submit">Logout</button></form>`
   Ensure each dashboard initializes `$_SESSION['csrf_token']` before rendering the form.
9. Test registration, login, lockout after 5 failed attempts, successful login reset, logout, all dashboard roles, and protected file downloads using dummy accounts.

## Important notes
- The login limiter allows 5 failed attempts per username/IP in a rolling 15-minute window. It requires the `login_attempts` table.
- A reverse proxy deployment should configure trusted client IP handling carefully. Do not blindly trust arbitrary `X-Forwarded-For` headers.
- This is a code-level upgrade, not a live XAMPP/MySQL integration test.
- Keep HTTPS enabled in production. On plain local HTTP, the session cookie's `Secure` flag is false so local XAMPP works.
- This package does not add password reset, MFA, or CAPTCHA.
