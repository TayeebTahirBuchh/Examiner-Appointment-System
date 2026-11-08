# Examiner Appointment System

A PHP and MySQL web application for managing teacher registration, approval, examination-paper assignments, paper submissions, notifications, and PSF-8 bill PDF generation.

## Current application structure

The repository contains role-based pages for:

- **Admin:** reviews teacher registrations and views assignments.
- **Competent Authority:** reviews submitted papers and bills, then approves or rejects submissions.
- **Teacher:** views notifications and assigned papers, uploads two question-paper sets and a marking key, and submits a PSF-8 bill PDF.

The application uses PHP, MySQL, HTML, CSS, JavaScript, and TCPDF.

## Database

The repository did not include an SQL export. The `database/schema.sql` file in this upgrade is an **inferred fresh-install schema**, built from the SQL queries and form fields found in the PHP source. It is not a backup of the original database and contains no original records.

Tables included:

| Table | Purpose |
|---|---|
| `users` | Login credentials and roles |
| `teachers` | Teacher profile and approval status |
| `courses` | Course, semester, subject code and title |
| `assignments` | Assigned paper details, uploaded files and workflow status |
| `notifications` | Teacher notifications |

The upgraded `register.php` and `login.php` align teacher registration and login with this schema. They also support the admin role redirect.

## Requirements

- PHP 8.1 or later recommended
- MySQL 8.0 or MariaDB 10.4+
- Apache (for example, through XAMPP)
- PHP MySQLi extension
- TCPDF library at `tcpdf/tcpdf_main/tcpdf.php`

## Installation

1. Install and start Apache and MySQL in XAMPP.
2. Copy the repository folder into `htdocs`, for example `C:\xampp\htdocs\Examiner-Appointment-System`.
3. Open phpMyAdmin and import `database/schema.sql`.
4. Check `db_connect.php` and set the database host, username, password, and database name for your local environment. Do not commit real production credentials.
5. Ensure the `Uploads/` directory exists and is writable by the web server.
6. Confirm the TCPDF file exists at `tcpdf/tcpdf_main/tcpdf.php`.
7. Open `http://localhost/Examiner-Appointment-System/index.html` to register a teacher.

New teacher accounts start with `pending` status. An administrator must approve the teacher before they can access the teacher dashboard.

## Create the first administrator

The schema intentionally does not ship with a default admin password. Generate a secure password hash using PHP, then insert the admin account in phpMyAdmin.

Run this in a local PHP terminal, replacing the sample password with a strong password you have chosen:

```bash
php -r "echo password_hash('REPLACE_WITH_A_STRONG_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
```

Copy the resulting hash, then run this SQL in phpMyAdmin (replace the username and hash):

```sql
INSERT INTO users (username, password, role)
VALUES ('admin', 'PASTE_THE_GENERATED_HASH_HERE', 'admin');
```

Delete the sample password from any notes or terminal history where appropriate. Do not store a plain-text password in the database.

## Add courses

The `courses` table starts empty. Add course rows in phpMyAdmin using the actual course names, semester numbers, course codes, and titles used by your department. The existing `get_semesters.php` and `get_subjects.php` endpoints read from this table.

Example format only — replace these values with verified department data:

```sql
INSERT INTO courses (course_name, semester, course_code, course_title)
VALUES ('MCA', 1, 'MCA101', 'Example Subject Title');
```

## Important limitations and verification notes

- The SQL schema is inferred from the source code; it has not been tested against the original database because no database export was available.
- The current repository code inspected for this upgrade does not expose a complete assignment-creation workflow. The schema supports assignments, but an authorized screen or script is still needed to create and assign them.
- Seed data for real courses, teachers, authorities, assignments, and notifications is not included.
- The teacher file-upload handler in the existing dashboard needs additional hardening before production use. Browser-provided MIME types are not sufficient validation; server-side MIME inspection, randomized filenames, file-size limits, authorization checks, and storage outside the public web root are recommended.
- The PSF-8 bill currently accepts personal and banking details from form fields. Validate inputs, escape PDF HTML, protect access to generated PDFs, and avoid using example banking details in real submissions.
- Review authorization, CSRF protection, session handling, error reporting, and HTTPS before deploying beyond a local development environment.

## Project status

This repository documents and demonstrates a PHP/MySQL workflow. Do not describe it as production-secure or fully tested until the database has been imported, all roles and workflows have been tested, and the upload/PDF handling has been reviewed.
