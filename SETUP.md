# Setup Guide

This guide explains how to run the Examiner Appointment System locally using XAMPP.

## 1. Requirements

- XAMPP with Apache, PHP, and MySQL/MariaDB
- PHP Fileinfo enabled
- A web browser
- TCPDF at `tcpdf/tcpdf_main/tcpdf.php` for PSF-8 PDF generation

## 2. Copy the project

Place the project folder inside XAMPP's `htdocs` directory.

Example:

```text
C:\xampp\htdocs\Examiner-Appointment-System\
```

## 3. Start XAMPP

Start Apache and MySQL in the XAMPP Control Panel.

## 4. Import the database

1. Open `http://localhost/phpmyadmin`.
2. Select **Import**.
3. Choose `schema.sql` from the repository root.
4. Click **Import** or **Go**.

The schema creates the `examiner_system` database and its initial tables. It is an inferred fresh-install schema, not a backup of original records.

## 5. Configure the database

Check `db_connect.php` and ensure the connection settings match your local XAMPP installation.

Typical local values:

```php
$host = "localhost";
$username = "root";
$password = "";
$database = "examiner_system";
```

Do not use publicly committed credentials in production.

## 6. Configure protected uploads

The application stores PDF files in the `Uploads/` directory.

This repository includes `Uploads/.htaccess`, which denies direct web access to uploaded files. Documents must be downloaded through `download_file.php`, which checks the logged-in user's role and assignment ownership.

For Apache:

1. Ensure `AllowOverride` permits `.htaccess` rules for the project.
2. Ensure Apache's authorization module is enabled.
3. Keep `Uploads/.htaccess` in place.
4. Confirm that requesting a file directly under `/Uploads/` returns **403 Forbidden**.
5. Confirm that an authorized user can download the same file through `download_file.php`.

If using Nginx or IIS, `.htaccess` is not applied. Configure an equivalent server-level rule that denies direct HTTP access to the `Uploads` directory.

The PHP process must have permission to create and write files in `Uploads/`.

## 7. Open the application

If the folder is named `Examiner-Appointment-System`, visit:

```text
http://localhost/Examiner-Appointment-System/
```

## 8. Create an administrator

Generate a password hash locally with PHP:

```php
<?php
echo password_hash("Choose-A-Strong-Password", PASSWORD_DEFAULT);
```

Insert the generated hash through phpMyAdmin:

```sql
INSERT INTO users (username, password, role)
VALUES ('admin', 'PASTE_PASSWORD_HASH_HERE', 'admin');
```

Replace the placeholder with your generated hash. Never store a plain-text password. Delete the temporary PHP script after use.

## 9. Important notes

- The `courses` table must contain the department's approved course catalogue before the Competent Authority can assign papers.
- Teacher accounts must be approved before they can log in.
- The teacher dashboard expects TCPDF at `tcpdf/tcpdf_main/tcpdf.php`.
- Test using dummy accounts, sample PDFs, and non-sensitive bank details first.
- Do not upload real confidential examination papers until direct access to `Uploads/` has been tested and blocked at the web-server level.
- Back up existing files and databases before replacing application files.

## Troubleshooting

### Database connection failed

Confirm MySQL is running and check the database name and credentials in `db_connect.php`.

### Direct URL to an uploaded PDF still opens

Check that Apache permits `.htaccess` overrides and that `Uploads/.htaccess` exists. If using Nginx or IIS, add a server-level deny rule; `.htaccess` is Apache-specific.

### Protected download says file not found

Confirm the assignment row contains a relative path like `Uploads/random-name.pdf`, and that the corresponding PDF exists inside the project's `Uploads` directory.

### PSF-8 generation fails

Confirm TCPDF exists at `tcpdf/tcpdf_main/tcpdf.php` and inspect PHP error logs.
