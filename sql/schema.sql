-- PharSayo Database Schema
-- Final Version for Production Deployment

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS pharsayo;
USE pharsayo;

-- 1. Users Table (Admin, Doctor, Patient)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `age` INT NULL,
    `role` ENUM('admin', 'doctor', 'patient') NOT NULL DEFAULT 'patient',
    `username` VARCHAR(64) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `language_preference` ENUM('fil', 'en') DEFAULT 'fil',
    `verification_file` VARCHAR(255) DEFAULT NULL,
    `account_status` ENUM('pending', 'active', 'suspended') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Medications (The prescription list)
CREATE TABLE IF NOT EXISTS `medications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `generic_name` VARCHAR(255) NULL,
    `dosage` VARCHAR(255) NULL,
    `frequency` VARCHAR(255) NULL,
    `frequency_fil` TEXT NULL,
    `purpose_en` TEXT NULL,
    `purpose_fil` TEXT NULL,
    `precautions_en` TEXT NULL,
    `precautions_fil` TEXT NULL,
    `notes` TEXT NULL,
    `prescribed_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`prescribed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Schedules (Specific times for intake)
CREATE TABLE IF NOT EXISTS `schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `medication_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `reminder_time` TIME NOT NULL,
    `days_of_week` VARCHAR(100) DEFAULT 'Mon,Tue,Wed,Thu,Fri,Sat,Sun',
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `notes` TEXT NULL,
    FOREIGN KEY (`medication_id`) REFERENCES `medications`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Adherence Logs (Tracking if meds were taken)
CREATE TABLE IF NOT EXISTS `adherence_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `medication_id` INT NOT NULL,
    `scheduled_time` DATETIME NOT NULL,
    `taken` BOOLEAN DEFAULT FALSE,
    `responded_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`medication_id`) REFERENCES `medications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Doctor-Patient Relationship
CREATE TABLE IF NOT EXISTS `doctor_patient` (
    `doctor_id` INT NOT NULL,
    `patient_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`doctor_id`, `patient_id`),
    FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEED DATA --

-- Default administrator. Username: admin | Password: admin123
-- Hash is for 'admin123'
INSERT IGNORE INTO `users` (`name`, `role`, `username`, `phone`, `password_hash`, `language_preference`, `account_status`) VALUES
('System Admin', 'admin', 'admin', '09999999999', '$2y$10$jxhmkMWixOEWq5TmHuhwYOWvRCG3BJRASACC5SqJ.Y6f8a4wY8wxO', 'en', 'active');
