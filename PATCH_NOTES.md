# Protected Document Download Upgrade

## Files in this package

- `download_file.php` — new authenticated document-download endpoint.
- `teacher_dashboard.php` — replaces direct upload URLs with protected download links.
- `competent_authority_dashboard.php` — replaces direct upload URLs with protected download links.
- `Uploads/.htaccess` — denies direct HTTP access to files stored in `Uploads/`.
- `README.md` and `SETUP.md` — document the protected download workflow and Apache requirement.

## Access rules

- A teacher can download documents only for assignments assigned to their own teacher account.
- A Competent Authority or Admin can download assignment documents for departmental review.
- The requested database field is restricted to `syllabus`, `question_set1`, `question_set2`, `marking_key`, or `bill_pdf`.
- File paths are restricted to a simple PDF filename within `Uploads/`; path traversal is rejected.
- The resolved file must remain inside the real `Uploads/` directory and pass MIME/signature validation.
- Downloads are sent as attachments with private/no-cache headers and `nosniff`.

## Installation

1. Add `download_file.php` to the repository root.
2. Replace `teacher_dashboard.php`.
3. Replace `competent_authority_dashboard.php`.
4. Add `.htaccess` inside the `Uploads` directory. The repository package includes `Uploads/.htaccess`.
5. Replace `README.md` and `SETUP.md` with the versions in this package.
6. Ensure Apache allows `.htaccess` overrides for the project directory (AllowOverride All or an equivalent configuration).
7. Ensure PHP Fileinfo is enabled.

## Important migration note

The `.htaccess` rule protects direct access on Apache. If the application is deployed on Nginx, IIS, or another server, configure an equivalent deny rule at the web-server level. Do not assume `.htaccess` is honored by non-Apache servers.

The application stores upload paths as relative strings such as `Uploads/file.pdf`; the database schema does not need to change.

## Existing uploaded files

Previously uploaded files remain in the `Uploads/` directory. The new download endpoint can serve them if they are PDFs stored directly in that directory and their database paths match the expected format.

## Testing status

PHP syntax was checked. This package has not been tested against the user's live XAMPP/MySQL installation. Test with sample documents and non-sensitive accounts before using real examination papers or financial data.
