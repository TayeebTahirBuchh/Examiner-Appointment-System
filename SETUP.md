# Local setup guide

This guide is for a fresh local installation. It does not recover data from the unavailable original laptop.

## 1. Install XAMPP
Install XAMPP on a working computer. Start Apache and MySQL.

## 2. Place the project
Copy the repository into XAMPP's `htdocs` folder.

Example:
`C:\xampp\htdocs\Examiner-Appointment-System`

## 3. Create the database
Open `http://localhost/phpmyadmin`, choose **Import**, select `database/schema.sql`, and run the import.

The schema creates an empty `examiner_system` database. It does not recreate original records.

## 4. Configure the connection
Open `db_connect.php`. Set:
- Host: usually `localhost`
- Username: your local MySQL username
- Password: your local MySQL password
- Database: `examiner_system`

Keep real credentials out of public GitHub repositories.

## 5. Create the Uploads folder
Create `Uploads/` in the project root and make sure Apache/PHP can write to it.

## 6. Check TCPDF
Confirm this file exists:
`tcpdf/tcpdf_main/tcpdf.php`

If it is missing, install/restore the expected TCPDF library before using PDF generation.

## 7. Create an admin account
Generate a password hash in a PHP terminal:

```bash
php -r "echo password_hash('REPLACE_WITH_A_STRONG_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
```

Then insert it in phpMyAdmin:

```sql
INSERT INTO users (username, password, role)
VALUES ('admin', 'PASTE_THE_GENERATED_HASH_HERE', 'admin');
```

## 8. Register and approve a teacher
Open:
`http://localhost/Examiner-Appointment-System/index.html`

Register a teacher. The account is created with `pending` status. Log in as admin and approve it.

## 9. Add course records
Add verified course/semester/course-code/course-title rows to `courses` in phpMyAdmin. The semester and subject endpoints depend on these rows.

## 10. Test each role
- Admin: login, see pending teachers, approve/disallow a teacher.
- Teacher: login after approval, view notifications and assignments, submit paper PDFs, submit a PSF-8 bill.
- Competent Authority: review submitted assignments and approve/reject them.

### Known gap
The inspected repository does not currently provide a complete assignment-creation form or script. You will need that workflow to populate `assignments` for teacher testing. Do not manually invent real department assignments; use test records only.
