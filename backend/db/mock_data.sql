USE medtracker_db;

-- 1. Insert Users (Admin, Doctor, Patient)
INSERT IGNORE INTO users (id, role, name, phone, email, password_hash, plain_password, post, disease) VALUES 
('test_patient', 'User', 'David (Test Patient)', '555-0101', 'david@example.com', '$2y$10$xyz...', 'password123', NULL, 'Hypertension'),
('p1', 'User', 'Sarah J. Miller', '555-0102', 'sarah@example.com', '$2y$10$xyz...', 'password123', NULL, 'Diabetes Type 2'),
('dr_sarah', 'Health Worker', 'Dr. Sarah Chen', '555-0201', 'dr.sarah@example.com', '$2y$10$xyz...', 'password123', 'Cardiology Specialist', NULL),
('admin_dave', 'Admin', 'Admin Dave', '555-0301', 'admin@example.com', '$2y$10$xyz...', 'password123', 'System Administrator', NULL);


-- 2. Insert Medicines (Prescriptions)
-- Let's give 'test_patient' 2 medicines
INSERT INTO medicines (id, patient_id, prescriber_id, name, dosage, type, instructions, frequency, quantity) VALUES
(1, 'test_patient', 'dr_sarah', 'Lisinopril', '10mg', 'Oral Tablet', 'After Breakfast', '1x daily', 30),
(2, 'test_patient', 'dr_sarah', 'Metformin', '500mg', 'Oral Tablet', 'With Lunch', '1x daily', 60),
(3, 'p1', 'dr_sarah', 'Atorvastatin', '20mg', 'Oral Tablet', 'Before Bed', '1x daily', 20);

-- 3. Insert Intake Logs for Today (For schedule.html loading)
INSERT INTO intake_logs (medicine_id, patient_id, scheduled_time, status) VALUES
(1, 'test_patient', CONCAT(CURDATE(), ' 08:00:00'), 'Pending'),
(2, 'test_patient', CONCAT(CURDATE(), ' 13:00:00'), 'Pending'),
(3, 'p1', CONCAT(CURDATE(), ' 20:00:00'), 'Pending');

-- Insert some historical logs for Analytics graph (reports.html)
INSERT INTO intake_logs (medicine_id, patient_id, scheduled_time, status, taken_at) VALUES
(1, 'test_patient', CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 08:00:00'), 'Taken', CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 08:15:00')),
(2, 'test_patient', CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 13:00:00'), 'Taken', CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 13:05:00')),
(1, 'test_patient', CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 08:00:00'), 'Taken', CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 08:00:00')),
(2, 'test_patient', CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 13:00:00'), 'Skipped', NULL);
