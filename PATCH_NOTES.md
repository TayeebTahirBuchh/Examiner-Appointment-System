# Teacher Dashboard Security and Workflow Upgrade

## File
- `teacher_dashboard.php` — replace the existing file in the repository root.

## Changes
- Verifies teacher role and uses the session user ID for all assignment ownership checks.
- Adds CSRF-token validation to both paper and PSF-8 submission forms.
- Validates uploaded PDFs using PHP Fileinfo MIME detection and the `%PDF-` signature, not the browser-supplied MIME type.
- Limits each PDF to 10 MB and uses cryptographically random filenames.
- Updates assignments only when the assignment belongs to the logged-in teacher and is in an allowed workflow status.
- Prevents PSF-8 submission until all three paper files have been submitted.
- Escapes all user-supplied PSF-8 text before inserting it into the generated PDF.
- Calculates the PSF-8 total on the server as remuneration plus contingencies.
- Removes hardcoded sample identity and banking information from the form.
- Displays notifications and assignment statuses with escaped output.

## Requirements
- PHP 8.0+ is recommended (the code uses modern PHP features).
- PHP Fileinfo must be enabled.
- The TCPDF library must exist at `tcpdf/tcpdf_main/tcpdf.php`.
- The web server must be allowed to write to the `Uploads/` directory.
- The current inferred `schema.sql` must be imported and the logged-in teacher must have a matching row in `teachers`.

## Important security limitation
The existing application stores uploaded documents in a web-accessible `Uploads/` directory and links directly to those paths. This replacement improves upload validation and assignment ownership at submission time, but it does not yet add authenticated file-download authorization. Before production use, move uploads outside the public web root or add a protected download endpoint and update all dashboard links. Do not store real bank/PAN details or confidential examination papers in a publicly deployed test environment until that is addressed.

## Testing status
The replacement was syntax-checked, but it has not been executed against the user's local MySQL database, TCPDF installation, or XAMPP server. Test with dummy data and dummy bank details first.
