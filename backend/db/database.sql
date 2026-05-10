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
    gender VARCHAR(20) DEFAULT NULL,    -- For User (Patient) profile visibility
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
    custom_times_json TEXT DEFAULT NULL,   -- Optional custom dose times per day
    custom_times_effective_at DATETIME DEFAULT NULL, -- Patient time changes take effect from this moment
    treatment_mode ENUM('course', 'ongoing') NOT NULL DEFAULT 'course',
    prescription_status ENUM('active', 'paused', 'stopped') NOT NULL DEFAULT 'active',
    paused_at DATETIME DEFAULT NULL,
    stopped_at DATETIME DEFAULT NULL,
    stop_reason VARCHAR(255) DEFAULT NULL,
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
    skip_reason VARCHAR(255) DEFAULT NULL,
    snooze_until DATETIME DEFAULT NULL,
    snooze_count INT NOT NULL DEFAULT 0,
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

-- System activity audit trail
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id VARCHAR(50) DEFAULT NULL,
    actor_role VARCHAR(50) DEFAULT NULL,
    action_key VARCHAR(80) NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id VARCHAR(80) DEFAULT NULL,
    target_user_id VARCHAR(50) DEFAULT NULL,
    details_json TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created_at (created_at),
    INDEX idx_audit_actor (actor_user_id),
    INDEX idx_audit_action (action_key)
);

-- Admin-managed reminder timing rules for 1x / 2x / 3x daily schedules
CREATE TABLE IF NOT EXISTS reminder_settings (
    scenario_key VARCHAR(20) PRIMARY KEY,
    scenario_label VARCHAR(50) NOT NULL,
    dose_count TINYINT NOT NULL UNIQUE,
    upcoming_minutes INT NOT NULL DEFAULT 5,
    missed_minutes INT NOT NULL DEFAULT 15,
    send_due_now TINYINT(1) NOT NULL DEFAULT 1,
    auto_mark_skipped TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO reminder_settings (scenario_key, scenario_label, dose_count, upcoming_minutes, missed_minutes, send_due_now, auto_mark_skipped)
VALUES
    ('1x_daily', 'Once A Day', 1, 5, 15, 1, 1),
    ('2x_daily', 'Twice A Day', 2, 5, 15, 1, 1),
    ('3x_daily', 'Three Times A Day', 3, 5, 15, 1, 1)
ON DUPLICATE KEY UPDATE
    scenario_label = VALUES(scenario_label),
    dose_count = VALUES(dose_count);

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

-- Consultation messaging between linked patients and health workers
CREATE TABLE IF NOT EXISTS consultation_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id VARCHAR(50) NOT NULL,
    receiver_id VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_consultation_pair (sender_id, receiver_id, created_at),
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Health worker availability for video consultations
CREATE TABLE IF NOT EXISTS doctor_presence (
    worker_id VARCHAR(50) PRIMARY KEY,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'inactive',
    note VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Scheduled video appointments created by health workers
CREATE TABLE IF NOT EXISTS video_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    worker_id VARCHAR(50) NOT NULL,
    patient_id VARCHAR(50) NOT NULL,
    scheduled_at DATETIME NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    status ENUM('scheduled', 'cancelled', 'completed') NOT NULL DEFAULT 'scheduled',
    created_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_video_appointment_worker (worker_id, scheduled_at),
    INDEX idx_video_appointment_patient (patient_id, scheduled_at),
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);
