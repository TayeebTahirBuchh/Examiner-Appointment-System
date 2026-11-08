-- Examiner Appointment System
-- Inferred schema based on the PHP files in the repository.
-- Import this file into phpMyAdmin after creating the database.
-- This creates an EMPTY database structure; it does not restore original records.

CREATE DATABASE IF NOT EXISTS examiner_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE examiner_system;

-- Safe to re-import: existing tables are not dropped.

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(80) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'competent_authority') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A teacher profile shares its primary key with the matching users account.
-- This ensures session user IDs and assignment teacher IDs refer to the same person.
CREATE TABLE IF NOT EXISTS teachers (
    id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    designation ENUM(
        'Assistant Professor',
        'Senior Assistant Professor',
        'Lecturer'
    ) NOT NULL,
    status ENUM('pending', 'approved', 'disallowed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teachers_email (email),
    CONSTRAINT fk_teachers_user
        FOREIGN KEY (id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_name VARCHAR(100) NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    course_code VARCHAR(40) NOT NULL,
    course_title VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_course_semester_code (course_name, semester, course_code),
    KEY idx_courses_lookup (course_name, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id INT UNSIGNED NOT NULL,
    course VARCHAR(100) NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    course_title VARCHAR(255) NOT NULL,
    course_code VARCHAR(40) NOT NULL,
    syllabus VARCHAR(500) DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    status ENUM(
        'assigned',
        'papers_submitted',
        'submitted',
        'approved',
        'rejected'
    ) NOT NULL DEFAULT 'assigned',
    question_set1 VARCHAR(500) DEFAULT NULL,
    question_set2 VARCHAR(500) DEFAULT NULL,
    marking_key VARCHAR(500) DEFAULT NULL,
    bill_pdf VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_assignments_teacher (teacher_id),
    KEY idx_assignments_status (status),
    CONSTRAINT fk_assignments_teacher
        FOREIGN KEY (teacher_id) REFERENCES teachers(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_teacher (teacher_id),
    CONSTRAINT fk_notifications_teacher
        FOREIGN KEY (teacher_id) REFERENCES teachers(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add course rows through phpMyAdmin or a future course-management screen.
-- Create the first admin account using the instructions in SETUP.md.
