# Examiner Appointment System

A web-based system for managing external examination question-paper assignments, teacher submissions, and administrative review.

## Overview

The Examiner Appointment System is a PHP and MySQL application intended to support the examination workflow of an academic department.

The repository contains role-based areas for:

- **Admin** — manages teacher accounts and approval status.
- **Competent Authority** — reviews and manages examination-paper assignments.
- **Teacher** — views assigned work and submits examination materials.

The application also includes supporting PHP endpoints for login, registration, course/semester lookups, notifications, and PDF-related functionality.

## Technology Stack

- PHP
- MySQL
- HTML, CSS and JavaScript
- TCPDF for PDF generation

## Repository Structure

Key files currently in the repository include:

- `index.html`, `login.html` — entry and login pages
- `login.php`, `logout.php` — authentication and session handling
- `register.php` — teacher registration
- `admin_dashboard.php` — admin dashboard
- `competent_authority_dashboard.php` — competent authority dashboard
- `teacher_dashboard.php` — teacher dashboard
- `db_connect.php` — database connection
- `get_semesters.php`, `get_subjects.php` — course lookup endpoints
- `schema.sql` — database schema for a fresh installation
- `SETUP.md` — setup instructions
- `PATCH_NOTES.md` — notes about the recent authentication/database updates

> **Important:** Import `schema.sql` from the repository's root directory. It is not located inside a `database/` folder.

## Setup

See [SETUP.md](SETUP.md) for the installation steps, including database creation, importing the schema, and configuring the local PHP environment.

## Database Notes

The included `schema.sql` is an inferred fresh-install schema based on the application's PHP queries and workflow. It is provided to help create a new local database.

It is **not** an export or backup of the original project's database. Importing it creates the database structure; it does not restore original records or historical data.

## Security and Production Readiness

This project is intended as an academic application and should be reviewed before deployment. In particular:

- Configure database credentials outside publicly committed source code.
- Use HTTPS in any deployed environment.
- Validate uploaded files by extension, MIME type, size, and storage location.
- Prevent direct execution of uploaded files.
- Review session security, authorization checks, CSRF protection, and error handling.
- Create and manage administrator accounts securely.

## Future Improvements

- Add a complete assignment-creation workflow.
- Add stronger upload validation and file access controls.
- Add audit logs for approvals, rejections, and submissions.
- Add automated tests and deployment configuration.
