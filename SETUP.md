# Setup Guide

This guide explains how to run the Examiner Appointment System locally using XAMPP.

## 1. Requirements

- XAMPP with Apache, PHP, and MySQL/MariaDB
- A web browser
- The project files from this repository

## 2. Copy the project

1. Download or clone this repository.
2. Place the project folder inside XAMPP's `htdocs` directory.

Example:

```text
C:\xampp\htdocs\Examiner-Appointment-System\
```

The folder name can differ, but use the matching URL in your browser.

## 3. Start XAMPP services

Open the XAMPP Control Panel and start:

- Apache
- MySQL

## 4. Create the database by importing the schema

1. Open `http://localhost/phpmyadmin`.
2. Select the **Import** tab.
3. Choose the file named **`schema.sql` from the root of the project folder**.
4. Click **Import** or **Go**.

The schema creates the `examiner_system` database and its initial tables.

**Correct file path:** `Examiner-Appointment-System/schema.sql`

Do not look for `database/schema.sql`. The schema file is in the repository root.

## 5. Check the database connection

Open `db_connect.php` and confirm the local connection settings match your XAMPP setup.

A typical local XAMPP configuration is:

```php
$host = "localhost";
$username = "root";
$password = "";
$database = "examiner_system";
```

If your MySQL account has a password or uses a different host/port, update the settings accordingly.

For a public deployment, do not keep production credentials directly in a publicly accessible repository. Use environment variables or a private configuration file outside the web root.

## 6. Open the application

If the project folder is named `Examiner-Appointment-System`, open:

```text
http://localhost/Examiner-Appointment-System/
```

If the project uses a different folder name, replace the URL path accordingly.

## 7. Administrator account

The schema does not include a known default administrator password.

Create an administrator account using a securely generated password hash and insert the account into the `users` table with the `admin` role. Use PHP's `password_hash()` to generate the hash, for example:

```php
<?php
echo password_hash("Choose-A-Strong-Password", PASSWORD_DEFAULT);
```

Run this temporary script locally, copy the generated hash, and insert the account using phpMyAdmin. Delete the temporary script immediately after use.

Example SQL (replace the sample values with your own):

```sql
INSERT INTO users (username, password, role)
VALUES ('admin', 'PASTE_PASSWORD_HASH_HERE', 'admin');
```

Never store a plain-text password in the database.

## 8. Important notes

- The included `schema.sql` is an inferred fresh-install schema based on the application's code. It is not the original database export.
- Importing the schema creates tables but does not restore existing users, teachers, assignments, or notifications.
- If you already have a database containing important data, back it up before importing or changing any schema.
- The project may require additional configuration or code fixes depending on the exact PHP and MySQL versions.
- Confirm that the TCPDF library exists at the path expected by `teacher_dashboard.php` before testing PDF generation.

## Troubleshooting

### Database connection failed

- Confirm MySQL is running in XAMPP.
- Confirm the database name is `examiner_system`.
- Check the username, password, host, and port in `db_connect.php`.

### Unknown database

Import the root-level `schema.sql` file through phpMyAdmin.

### Login does not work

- Confirm the user exists in the `users` table.
- Confirm the stored password is a hash created with `password_hash()`.
- Confirm the role value is one of the roles supported by the schema and application.
- Teacher accounts may require approval, depending on the registration workflow.

### PDF generation fails

- Confirm TCPDF is present in the repository at the path referenced by the PHP code.
- Check PHP error logs for missing-file or permission errors.
