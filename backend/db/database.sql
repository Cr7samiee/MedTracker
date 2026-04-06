CREATE DATABASE IF NOT EXISTS medtracker_db;
USE medtracker_db;

-- Base users table (handles Authentication for all actors)
-- Roles: Admin, Health Worker, User (Patient)
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(50) PRIMARY KEY,
    role ENUM('Admin', 'Health Worker', 'User') NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    plain_password VARCHAR(255) NOT NULL,
    post VARCHAR(100) DEFAULT NULL,    -- For Health Worker (e.g., Cardiology Specialist)
    worker_code VARCHAR(20) DEFAULT NULL UNIQUE, -- Shareable code for patient linking
    relation VARCHAR(100) DEFAULT NULL, -- For Caregivers
    disease VARCHAR(100) DEFAULT NULL,  -- For User (Patient) ERD
    dob DATE DEFAULT NULL,              -- For User (Patient) ERD
    address VARCHAR(255) DEFAULT NULL,  -- For User (Patient) ERD
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_token_expiry DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Medicines / Prescriptions (DFD: Manage Medicine Profile)
-- ERD: Prescribed by Health Worker (wid), Taken by Patient (pid)
CREATE TABLE IF NOT EXISTS medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id VARCHAR(50) NOT NULL,       -- Matches User's ID
    prescriber_id VARCHAR(50) DEFAULT NULL,-- Matches Health Worker's ID
    name VARCHAR(100) NOT NULL,
    dosage VARCHAR(50) NOT NULL,
    type VARCHAR(50) DEFAULT 'Oral Tablet', -- ERD: Type
    instructions TEXT,                     -- ERD: Instructions
    frequency VARCHAR(50) NOT NULL,        -- e.g., 2x daily
    start_date DATE DEFAULT NULL,          -- Prescription start date
    duration_days INT DEFAULT 7,           -- Total treatment window in days
    end_date DATE DEFAULT NULL,            -- Calculated treatment end date
    quantity INT DEFAULT 0,                -- ERD: Quantity (Stock)
    manufacture_date DATE DEFAULT NULL,    -- ERD: Manufacture_Date
    expiry_date DATE DEFAULT NULL,         -- ERD: Expirity Date
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (prescriber_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Intake Records / Schedules (DFD: Manage Intake Record, "mark taken/skipped")
CREATE TABLE IF NOT EXISTS intake_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    patient_id VARCHAR(50) NOT NULL,
    scheduled_time DATETIME NOT NULL,
    status ENUM('Pending', 'Taken', 'Skipped') DEFAULT 'Pending',
    taken_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Caregiver mappings (ERD: Cares / Oversees)
CREATE TABLE IF NOT EXISTS caregiver_patient (
    caregiver_id VARCHAR(50) NOT NULL,
    patient_id VARCHAR(50) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (caregiver_id, patient_id),
    FOREIGN KEY (caregiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Hydration / Water Tracking
CREATE TABLE IF NOT EXISTS water_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id VARCHAR(50) NOT NULL,
    intake_date DATE NOT NULL,
    intake_ml INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_patient_day (patient_id, intake_date),
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notification delivery logs for reminder dedupe and audit trail
CREATE TABLE IF NOT EXISTS notification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    medicine_id INT DEFAULT NULL,
    intake_log_id INT DEFAULT NULL,
    notification_type VARCHAR(60) NOT NULL,
    channel ENUM('email', 'sms') NOT NULL,
    event_key VARCHAR(150) DEFAULT NULL,
    recipient VARCHAR(150) DEFAULT NULL,
    status ENUM('SENT', 'FAILED', 'SKIPPED') DEFAULT 'SENT',
    response_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_event_channel (event_key, channel),
    INDEX idx_notification_user (user_id),
    INDEX idx_notification_log (intake_log_id)
);

-- System Logs (DFD: View System Report)
-- Message examples:
-- OVERUSE_ATTEMPT|U101|15|Paracetamol
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_type ENUM('AUTH', 'SMS', 'EMAIL', 'SYSTEM') NOT NULL,
    message TEXT NOT NULL,
    status ENUM('SUCCESS', 'ERROR', 'RETRIED') DEFAULT 'SUCCESS',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
