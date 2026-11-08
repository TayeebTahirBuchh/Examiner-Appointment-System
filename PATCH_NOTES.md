# Authentication and Database Error-Handling Upgrade

## Included files

- `login.php` — safer session-cookie settings, strict role allow-list, generic invalid-credential response, session ID rotation after login, and logged server-side query errors.
- `register.php` — input length and username format validation, bounded password length, password hashing, prepared statements, and transaction-safe registration.
- `logout.php` — clears session data, expires the session cookie, and destroys the session.
- `db_connect.php` — supports environment-based database configuration and no longer displays raw database errors to visitors.

## Installation

Replace the four matching files in the repository root.

Local XAMPP defaults remain:
- DB_HOST=localhost
- DB_USER=root
- DB_PASSWORD=(blank)
- DB_NAME=examiner_system

For another environment, set the corresponding environment variables rather than committing production credentials.

## Important limitations

- This package does not add login rate limiting or a CAPTCHA.
- Existing dashboard pages still start sessions themselves. For consistent cookie settings on every authenticated endpoint, a later pass should route every PHP entry point through one shared session bootstrap.
- The current static registration page does not issue a CSRF token. A complete CSRF improvement requires updating the registration page to obtain a server-generated token.
- Logout remains compatible with the repository's existing logout link. Converting logout to POST with CSRF protection requires changing the dashboard navigation forms too.
- PHP syntax checks are not a substitute for integration testing with the actual MySQL database and XAMPP environment.
