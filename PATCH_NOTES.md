# Assignment Workflow Upgrade

## Files in this package

- `assign_paper.php` — new Competent Authority page to assign a course to an approved teacher, upload the syllabus PDF, set a due date, create the assignment, and send a teacher notification.
- `competent_authority_dashboard.php` — adds the assignment link and retains the submitted-paper review workflow. Status updates include a CSRF token and only apply to assignments awaiting review.
- `admin_dashboard.php` — fixes the mismatch with the inferred schema by retrieving the username from `users` through a join, rather than expecting `teachers.username`. Adds CSRF protection to teacher approval actions.

## Database expectations

This package is written for the current root-level `schema.sql` in the repository. It expects:
- `users`
- `teachers`
- `courses`
- `assignments`
- `notifications`

The `courses` table must contain course records before assignments can be created. The package does not invent MCA/BCA course rows because the actual course catalogue should match the department's approved syllabus.

## Upload handling

The new syllabus upload handler checks the actual MIME type using PHP Fileinfo, verifies the PDF signature, limits the file to 10 MB, generates a random server-side filename, and saves it under `Uploads/`.

Ensure PHP Fileinfo is enabled and the web server can write to the `Uploads` directory. For production, configure the upload directory so uploaded files cannot execute as scripts and are not publicly exposed without authorization.

## Installation

1. Back up the existing PHP files.
2. Upload and replace the two dashboard PHP files.
3. Add `assign_paper.php` to the repository root.
4. Confirm `schema.sql` has been imported and that the `courses` table contains the correct course catalogue.
5. Test using a non-production database first.

## Scope / testing

This package has been reviewed against the repository's current schema and PHP queries, but it has not been run against the user's local database or server. Test teacher approval, assignment creation, notification delivery, syllabus download, and authority review before relying on it.
