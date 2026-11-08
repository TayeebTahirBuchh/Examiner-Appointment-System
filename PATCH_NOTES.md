# Source file changes in this upgrade

## `register.php`
- Writes a password-hashed account to `users`.
- Creates a linked teacher profile in `teachers` using the same ID.
- Uses a database transaction so the two inserts succeed or fail together.
- Validates email, designation, required fields, and minimum password length.
- Uses prepared statements.

## `login.php`
- Authenticates against `users`.
- Checks teacher approval using the linked teacher ID.
- Regenerates the session ID after successful authentication.
- Redirects all three supported roles: teacher, competent authority, and admin.

## Compatibility note
These two PHP files are designed for the `database/schema.sql` included in this package. Back up your existing files before replacing them. Other PHP files were not rewritten in this package.
