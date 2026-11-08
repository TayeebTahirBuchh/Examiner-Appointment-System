# Examiner Appointment System

A PHP and MySQL web application for managing external examination question-paper assignments, teacher submissions, and administrative review.

## Roles

- **Admin** — manages teacher account approval and views assignment activity.
- **Competent Authority** — assigns examination work and reviews submitted papers.
- **Teacher** — views assigned work, submits question papers and marking keys, and generates a PSF-8 bill.

## Technology

- PHP and MySQL
- HTML, CSS and JavaScript
- TCPDF for PSF-8 PDF generation

## Key files

- `index.html`, `login.html` — application entry and login pages
- `login.php`, `logout.php`, `register.php` — authentication and registration
- `admin_dashboard.php` — admin dashboard
- `assign_paper.php` — assignment creation for Competent Authority
- `competent_authority_dashboard.php` — submitted-paper review
- `teacher_dashboard.php` — teacher dashboard and submissions
- `download_file.php` — role- and assignment-checked document downloads
- `db_connect.php` — database connection
- `schema.sql` — inferred fresh-install database schema
- `SETUP.md` — installation guide
- `PATCH_NOTES.md` — upgrade and security notes

## Setup

Follow [SETUP.md](SETUP.md). Import `schema.sql` from the repository root.

The schema is an inferred fresh-install schema based on the PHP code. It is not an export of the original database and does not restore historical records.

## Protected documents

Uploaded PDFs are stored under `Uploads/`. Dashboard links now use `download_file.php`, which checks the user's role and assignment ownership before serving a document. The `Uploads/.htaccess` file blocks direct web access to uploaded files when Apache honors `.htaccess` rules.

For Apache, ensure `AllowOverride` permits the `Uploads/.htaccess` rule. For Nginx or IIS, configure an equivalent server-level deny rule. Do not deploy sensitive papers or bank details until the server-level access restriction has been tested.

## Security notes

- Use HTTPS in deployed environments.
- Keep production database credentials outside public source code.
- Keep `Uploads/` inaccessible directly over HTTP.
- Review session security, CSRF protection, role checks, and file validation before production deployment.
- Test using dummy accounts and sample documents before uploading confidential examination material.
