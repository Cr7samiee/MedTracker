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

-- System Logs (DFD: View System Report)
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_type ENUM('AUTH', 'SMS', 'EMAIL', 'SYSTEM') NOT NULL,
    message TEXT NOT NULL,
    status ENUM('SUCCESS', 'ERROR', 'RETRIED') DEFAULT 'SUCCESS',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
