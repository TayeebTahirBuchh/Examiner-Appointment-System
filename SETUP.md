Setup Guide

This guide explains how to run the Examiner Appointment System locally using XAMPP.

1. Requirements

* XAMPP with Apache, PHP, and MySQL/MariaDB
* A web browser
* Project files from this repository

2. Copy the project

1. Download or clone this repository.
2. Place the project folder inside XAMPP’s htdocs directory.

Example:

C:\xampp\htdocs\Examiner-Appointment-System\

3. Start XAMPP

Open XAMPP Control Panel and start:

* Apache
* MySQL

4. Import the database schema

1. Open http://localhost/phpmyadmin.
2. Select the Import tab.
3. Choose schema.sql from the root of the project folder.
4. Click Import or Go.

The schema creates the examiner_system database and its initial tables.

Important: The file is named schema.sql and is located directly in the repository root. Do not look for database/schema.sql.

5. Configure the database connection

Open db_connect.php and confirm the settings match your XAMPP configuration.

Typical local XAMPP settings:

$host = "localhost";
$username = "root";
$password = "";
$database = "examiner_system";

If your MySQL account uses a password or a different port, update the settings accordingly.

For production deployment, do not store production credentials in publicly committed source code. Use environment variables or a private configuration file.

6. Open the application

If your project folder is named Examiner-Appointment-System, visit:

http://localhost/Examiner-Appointment-System/

7. Create an administrator account

The schema does not provide a known default administrator password.

Generate a password hash locally using PHP:

<?php
echo password_hash("Choose-A-Strong-Password", PASSWORD_DEFAULT);

Copy the generated hash and insert the administrator account through phpMyAdmin:

INSERT INTO users (username, password, role)
VALUES ('admin', 'PASTE_PASSWORD_HASH_HERE', 'admin');

Replace the placeholder with your generated hash. Never store a plain-text password.

Delete the temporary PHP script after generating the hash.

8. Important notes

* The included schema.sql is an inferred fresh-install schema based on the application’s PHP code. It is not an export of the original database.
* Importing it creates the database structure but does not restore previous users, teachers, assignments, or notifications.
* Back up any existing database before importing or changing its schema.
* Confirm that TCPDF exists at the path expected by teacher_dashboard.php before testing PDF generation.
* Review upload validation, authorization, and session security before deploying publicly.

Troubleshooting

Database connection failed

* Confirm MySQL is running.
* Confirm the database name is examiner_system.
* Check the credentials and port in db_connect.php.

Unknown database

Import the root-level schema.sql file through phpMyAdmin.

Login does not work

* Confirm the user exists in the users table.
* Confirm the password is stored as a hash created with password_hash().
* Confirm the account role is supported by the application.
* Teacher accounts may require approval.

PDF generation fails

* Confirm TCPDF is present at the path referenced by the PHP code.
* Check PHP error logs for missing files or permission errors.
