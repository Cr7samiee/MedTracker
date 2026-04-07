-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 07, 2026 at 08:39 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `medtracker_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `actor_user_id` varchar(50) DEFAULT NULL,
  `actor_role` varchar(50) DEFAULT NULL,
  `action_key` varchar(80) NOT NULL,
  `entity_type` varchar(60) NOT NULL,
  `entity_id` varchar(80) DEFAULT NULL,
  `target_user_id` varchar(50) DEFAULT NULL,
  `details_json` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `actor_user_id`, `actor_role`, `action_key`, `entity_type`, `entity_id`, `target_user_id`, `details_json`, `created_at`) VALUES
(1, 'H100', 'Health Worker', 'worker_add_prescription', 'medicine', '19', 'U104', '{\"medicine_name\":\"Paracetamol\",\"dosage\":\"200mg\",\"frequency\":\"1x daily\",\"treatment_mode\":\"course\"}', '2026-04-07 06:38:11'),
(2, 'H100', 'Health Worker', 'worker_add_prescription', 'medicine', '20', 'U104', '{\"medicine_name\":\"Paracetamol\",\"dosage\":\"200mg\",\"frequency\":\"1x daily\",\"treatment_mode\":\"course\"}', '2026-04-07 06:38:18');

-- --------------------------------------------------------

--
-- Table structure for table `caregiver_patient`
--

CREATE TABLE `caregiver_patient` (
  `caregiver_id` varchar(50) NOT NULL,
  `patient_id` varchar(50) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `caregiver_patient`
--

INSERT INTO `caregiver_patient` (`caregiver_id`, `patient_id`, `assigned_at`) VALUES
('H100', 'U104', '2026-04-07 04:22:02'),
('H101', 'U101', '2026-04-06 13:19:46');

-- --------------------------------------------------------

--
-- Table structure for table `intake_logs`
--

CREATE TABLE `intake_logs` (
  `id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `patient_id` varchar(50) NOT NULL,
  `scheduled_time` datetime NOT NULL,
  `status` enum('Pending','Taken','Skipped') DEFAULT 'Pending',
  `taken_at` datetime DEFAULT NULL,
  `skip_reason` varchar(255) DEFAULT NULL,
  `snooze_until` datetime DEFAULT NULL,
  `snooze_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `intake_logs`
--

INSERT INTO `intake_logs` (`id`, `medicine_id`, `patient_id`, `scheduled_time`, `status`, `taken_at`, `skip_reason`, `snooze_until`, `snooze_count`, `created_at`) VALUES
(13, 6, 'U101', '2026-04-06 08:00:00', 'Taken', '2026-04-06 18:24:44', NULL, NULL, 0, '2026-04-06 11:39:42'),
(36, 9, 'U101', '2026-04-06 08:00:00', 'Taken', '2026-04-06 15:22:18', NULL, NULL, 0, '2026-04-06 13:21:50'),
(41, 10, 'U101', '2026-04-06 08:00:00', 'Taken', '2026-04-06 18:16:26', NULL, NULL, 0, '2026-04-06 15:57:10'),
(48, 10, 'U101', '2026-04-06 21:50:00', 'Taken', '2026-04-06 18:24:38', NULL, NULL, 0, '2026-04-06 15:58:15'),
(49, 10, 'U101', '2026-04-07 21:50:00', 'Taken', '2026-04-07 11:32:45', NULL, NULL, 0, '2026-04-06 15:58:15'),
(50, 10, 'U101', '2026-04-08 21:50:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 15:58:15'),
(51, 10, 'U101', '2026-04-09 21:50:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 15:58:15'),
(52, 10, 'U101', '2026-04-10 21:50:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 15:58:15'),
(53, 10, 'U101', '2026-04-11 21:50:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 15:58:15'),
(54, 10, 'U101', '2026-04-12 21:50:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 15:58:15'),
(55, 11, 'U101', '2026-04-06 22:13:00', 'Taken', '2026-04-06 18:28:56', NULL, NULL, 0, '2026-04-06 16:27:02'),
(56, 11, 'U101', '2026-04-06 22:15:00', 'Taken', '2026-04-06 18:28:57', NULL, NULL, 0, '2026-04-06 16:27:02'),
(57, 11, 'U101', '2026-04-07 22:13:00', 'Taken', '2026-04-07 11:32:47', NULL, NULL, 0, '2026-04-06 16:27:02'),
(58, 11, 'U101', '2026-04-07 22:15:00', 'Taken', '2026-04-07 11:32:47', NULL, NULL, 0, '2026-04-06 16:27:02'),
(59, 11, 'U101', '2026-04-08 22:13:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:27:02'),
(60, 11, 'U101', '2026-04-08 22:15:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:27:02'),
(61, 11, 'U101', '2026-04-09 22:13:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:27:02'),
(62, 11, 'U101', '2026-04-09 22:15:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:27:02'),
(63, 11, 'U101', '2026-04-10 22:13:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:27:02'),
(64, 11, 'U101', '2026-04-10 22:15:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:27:02'),
(65, 11, 'U101', '2026-04-11 22:13:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:27:02'),
(66, 11, 'U101', '2026-04-11 22:15:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:27:02'),
(67, 11, 'U101', '2026-04-12 22:13:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:27:02'),
(68, 11, 'U101', '2026-04-12 22:15:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:27:02'),
(69, 6, 'U101', '2026-04-06 22:35:00', 'Taken', '2026-04-06 22:51:44', NULL, NULL, 0, '2026-04-06 16:43:20'),
(70, 6, 'U101', '2026-04-07 22:35:00', 'Taken', '2026-04-07 11:32:48', NULL, NULL, 0, '2026-04-06 16:43:20'),
(71, 6, 'U101', '2026-04-08 22:35:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:43:20'),
(72, 6, 'U101', '2026-04-09 22:35:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:43:20'),
(73, 6, 'U101', '2026-04-10 22:35:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:43:20'),
(74, 6, 'U101', '2026-04-11 22:35:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:43:20'),
(75, 6, 'U101', '2026-04-12 22:35:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:43:20'),
(76, 12, 'U101', '2026-04-06 21:32:00', 'Taken', '2026-04-06 22:51:48', NULL, NULL, 0, '2026-04-06 16:46:34'),
(77, 12, 'U101', '2026-04-07 21:32:00', 'Taken', '2026-04-07 11:32:45', NULL, NULL, 0, '2026-04-06 16:46:34'),
(78, 9, 'U101', '2026-04-06 10:33:00', 'Taken', '2026-04-06 22:51:46', NULL, NULL, 0, '2026-04-06 16:47:20'),
(79, 9, 'U101', '2026-04-07 10:33:00', 'Taken', '2026-04-07 11:32:44', NULL, NULL, 0, '2026-04-06 16:47:20'),
(80, 9, 'U101', '2026-04-08 10:33:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:47:20'),
(81, 9, 'U101', '2026-04-09 10:33:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:47:20'),
(82, 9, 'U101', '2026-04-10 10:33:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 16:47:20'),
(83, 13, 'U101', '2026-04-06 08:00:00', 'Taken', '2026-04-06 22:51:49', NULL, NULL, 0, '2026-04-06 16:51:42'),
(84, 13, 'U101', '2026-04-06 13:00:00', 'Taken', '2026-04-06 22:51:47', NULL, NULL, 0, '2026-04-06 16:51:42'),
(85, 13, 'U101', '2026-04-06 22:41:00', 'Taken', '2026-04-06 22:51:44', NULL, NULL, 0, '2026-04-06 16:51:42'),
(86, 14, 'U101', '2026-04-06 22:54:00', 'Skipped', NULL, NULL, NULL, 0, '2026-04-06 17:09:00'),
(87, 14, 'U101', '2026-04-07 22:54:00', 'Taken', '2026-04-07 11:32:49', NULL, NULL, 0, '2026-04-06 17:09:00'),
(88, 14, 'U101', '2026-04-08 22:54:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 17:09:00'),
(89, 14, 'U101', '2026-04-09 22:54:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 17:09:00'),
(90, 14, 'U101', '2026-04-10 22:54:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 17:09:00'),
(91, 14, 'U101', '2026-04-11 22:54:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 17:09:00'),
(92, 14, 'U101', '2026-04-12 22:54:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 17:09:00'),
(93, 15, 'U101', '2026-04-06 08:00:00', 'Taken', '2026-04-06 23:14:24', NULL, NULL, 0, '2026-04-06 17:10:02'),
(100, 16, 'U101', '2026-04-06 23:04:00', 'Taken', '2026-04-06 23:24:54', NULL, NULL, 0, '2026-04-06 17:13:59'),
(107, 17, 'U101', '2026-04-06 23:18:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 17:32:04'),
(108, 17, 'U101', '2026-04-07 23:18:00', 'Taken', '2026-04-07 11:32:50', NULL, NULL, 0, '2026-04-06 17:32:04'),
(109, 17, 'U101', '2026-04-08 23:18:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 17:32:04'),
(110, 17, 'U101', '2026-04-09 23:18:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 17:32:04'),
(111, 17, 'U101', '2026-04-10 23:18:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 17:32:04'),
(112, 17, 'U101', '2026-04-11 23:18:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 17:32:04'),
(113, 17, 'U101', '2026-04-12 23:18:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-06 17:32:04'),
(114, 16, 'U101', '2026-04-07 23:04:00', 'Taken', '2026-04-07 11:32:50', NULL, NULL, 0, '2026-04-07 04:31:01'),
(115, 16, 'U101', '2026-04-08 23:04:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-07 04:31:01'),
(116, 16, 'U101', '2026-04-09 23:04:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-07 04:31:01'),
(117, 16, 'U101', '2026-04-10 23:04:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-07 04:31:01'),
(118, 16, 'U101', '2026-04-11 23:04:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-07 04:31:01'),
(119, 16, 'U101', '2026-04-12 23:04:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-07 04:31:01'),
(120, 16, 'U101', '2026-04-13 23:04:00', 'Pending', NULL, NULL, NULL, 0, '2026-04-07 04:31:01');

-- --------------------------------------------------------

--
-- Table structure for table `medicines`
--

CREATE TABLE `medicines` (
  `id` int(11) NOT NULL,
  `patient_id` varchar(50) NOT NULL,
  `prescriber_id` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `dosage` varchar(50) NOT NULL,
  `type` varchar(50) DEFAULT 'Oral Tablet',
  `instructions` text DEFAULT NULL,
  `frequency` varchar(50) NOT NULL,
  `custom_times_json` text DEFAULT NULL,
  `custom_times_effective_at` datetime DEFAULT NULL,
  `treatment_mode` enum('course','ongoing') NOT NULL DEFAULT 'course',
  `prescription_status` enum('active','paused','stopped') NOT NULL DEFAULT 'active',
  `paused_at` datetime DEFAULT NULL,
  `stopped_at` datetime DEFAULT NULL,
  `stop_reason` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `duration_days` int(11) DEFAULT 7,
  `end_date` date DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `manufacture_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicines`
--

INSERT INTO `medicines` (`id`, `patient_id`, `prescriber_id`, `name`, `dosage`, `type`, `instructions`, `frequency`, `custom_times_json`, `custom_times_effective_at`, `treatment_mode`, `prescription_status`, `paused_at`, `stopped_at`, `stop_reason`, `start_date`, `duration_days`, `end_date`, `quantity`, `manufacture_date`, `expiry_date`, `created_at`) VALUES
(6, 'U101', NULL, 'Sinex', '150 mg', 'Oral Tablet', 'Before Meal', '1x daily', '[\"22:35:00\"]', NULL, 'course', 'active', NULL, NULL, NULL, '2026-04-06', 7, '2026-04-12', 10, NULL, NULL, '2026-04-06 10:52:34'),
(9, 'U101', 'H101', 'Nasal Spray', '500', 'Liquid / Syrup', 'Before Meal', '1x daily', '[\"10:33:00\"]', NULL, 'course', 'active', NULL, NULL, NULL, '2026-04-06', 5, '2026-04-10', 9, NULL, NULL, '2026-04-06 13:21:50'),
(10, 'U101', 'H101', 'Amocilin', '500', 'Oral Tablet', '', '1x daily', '[\"21:50:00\"]', NULL, 'course', 'active', NULL, NULL, NULL, '2026-04-06', 7, '2026-04-12', 2, NULL, NULL, '2026-04-06 15:57:10'),
(11, 'U101', 'H101', 'Brofin', '12', 'Oral Tablet', 'Take after Meal', '2x daily', '[\"22:13:00\",\"22:15:00\"]', NULL, 'course', 'active', NULL, NULL, NULL, '2026-04-06', 7, '2026-04-12', 6, NULL, NULL, '2026-04-06 16:21:58'),
(12, 'U101', 'H101', 'Flexon', '100mg', 'Oral Tablet', 'Take after Meal', '1x daily', '[\"21:32:00\"]', NULL, 'course', 'active', NULL, NULL, NULL, '2026-04-06', 2, '2026-04-07', 10, NULL, NULL, '2026-04-06 16:42:30'),
(13, 'U101', 'H101', 'abc', '12', 'Oral Tablet', 'Before Meal', '3x daily', '[\"08:00:00\",\"13:00:00\",\"22:41:00\"]', NULL, 'course', 'active', NULL, NULL, NULL, '2026-04-06', 1, '2026-04-06', 0, NULL, NULL, '2026-04-06 16:49:54'),
(14, 'U101', 'H101', 'abcde', '100mg', 'Liquid / Syrup', 'Before Meal', '1x daily', '[\"22:54:00\"]', '2026-04-06 22:54:00', 'course', 'active', NULL, NULL, NULL, '2026-04-06', 7, '2026-04-12', 0, NULL, NULL, '2026-04-06 17:07:55'),
(15, 'U101', NULL, 'Flexon', '12', 'Injection', 'Before Meal', '1x daily', NULL, NULL, 'course', 'active', NULL, NULL, NULL, '2026-04-06', 7, '2026-04-12', 0, NULL, NULL, '2026-04-06 17:10:02'),
(16, 'U101', 'H101', 'Flexonaaa', '1', 'Oral Tablet', 'Take after Meal', '1x daily', '[\"23:04:00\"]', '2026-04-06 22:58:59', 'course', 'active', NULL, NULL, NULL, '2026-04-07', 7, '2026-04-13', 9, NULL, NULL, '2026-04-06 17:12:44'),
(17, 'U101', 'H101', 'Sinex', '100mg', 'Oral Tablet', 'Take after Meal', '1x daily', '[\"23:18:00\"]', '2026-04-06 23:17:04', 'course', 'active', NULL, NULL, NULL, '2026-04-06', 7, '2026-04-12', 11, NULL, NULL, '2026-04-06 17:30:39'),
(18, 'U104', 'H100', 'Amoxiclin', '200mg', 'Oral Tablet', 'Take after a meal', '2x daily', NULL, NULL, 'course', 'active', NULL, NULL, NULL, '2026-04-07', 7, '2026-04-13', 10, NULL, NULL, '2026-04-07 04:22:56'),
(19, 'U104', 'H100', 'Paracetamol', '200mg', 'Oral Tablet', 'Take after a meal', '1x daily', NULL, NULL, 'course', 'active', NULL, NULL, NULL, '2026-04-07', 1, '2026-04-07', 3, NULL, NULL, '2026-04-07 06:38:11'),
(20, 'U104', 'H100', 'Paracetamol', '200mg', 'Oral Tablet', 'Take after a meal', '1x daily', NULL, NULL, 'course', 'active', NULL, NULL, NULL, '2026-04-07', 1, '2026-04-07', 3, NULL, NULL, '2026-04-07 06:38:18');

-- --------------------------------------------------------

--
-- Table structure for table `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `medicine_id` int(11) DEFAULT NULL,
  `intake_log_id` int(11) DEFAULT NULL,
  `notification_type` varchar(60) NOT NULL,
  `channel` enum('email','sms') NOT NULL,
  `event_key` varchar(150) DEFAULT NULL,
  `recipient` varchar(150) DEFAULT NULL,
  `status` enum('SENT','FAILED','SKIPPED') DEFAULT 'SENT',
  `response_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_logs`
--

INSERT INTO `notification_logs` (`id`, `user_id`, `medicine_id`, `intake_log_id`, `notification_type`, `channel`, `event_key`, `recipient`, `status`, `response_message`, `created_at`) VALUES
(1, 'U101', 10, NULL, 'NEW_PRESCRIPTION', 'email', 'NEW_PRESCRIPTION|10|U101', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 15:57:17'),
(2, 'U101', 10, NULL, 'NEW_PRESCRIPTION', 'sms', 'NEW_PRESCRIPTION|10|U101', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 15:57:18'),
(3, 'U103', 8, 34, 'MISSED_REMINDER', 'email', 'MISSED_15|34', 'krishna01@gmail.com', 'SENT', 'Email sent.', '2026-04-06 16:00:07'),
(4, 'U103', 8, 34, 'MISSED_REMINDER', 'sms', 'MISSED_15|34', '9810000006', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 16:00:08'),
(5, 'U101', 10, 41, 'MISSED_REMINDER', 'email', 'MISSED_15|41', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 16:00:13'),
(6, 'U101', 10, 41, 'MISSED_REMINDER', 'sms', 'MISSED_15|41', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 16:00:14'),
(7, 'U101', 11, NULL, 'NEW_PRESCRIPTION', 'email', 'NEW_PRESCRIPTION|11|U101', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 16:22:04'),
(8, 'U101', 11, NULL, 'NEW_PRESCRIPTION', 'sms', 'NEW_PRESCRIPTION|11|U101', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 16:22:05'),
(9, 'U101', 12, NULL, 'NEW_PRESCRIPTION', 'email', 'NEW_PRESCRIPTION|12|U101', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 16:42:37'),
(10, 'U101', 12, NULL, 'NEW_PRESCRIPTION', 'sms', 'NEW_PRESCRIPTION|12|U101', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 16:42:38'),
(11, 'U101', 13, NULL, 'NEW_PRESCRIPTION', 'email', 'NEW_PRESCRIPTION|13|U101', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 16:50:00'),
(12, 'U101', 13, NULL, 'NEW_PRESCRIPTION', 'sms', 'NEW_PRESCRIPTION|13|U101', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 16:50:02'),
(13, 'U101', 13, 83, 'MISSED_REMINDER', 'email', 'MISSED_15|83', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 16:54:05'),
(14, 'U101', 13, 83, 'MISSED_REMINDER', 'sms', 'MISSED_15|83', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 16:54:08'),
(15, 'U101', 9, 78, 'MISSED_REMINDER', 'email', 'MISSED_15|78', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 16:54:14'),
(16, 'U101', 9, 78, 'MISSED_REMINDER', 'sms', 'MISSED_15|78', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 16:54:16'),
(17, 'U101', 13, 84, 'MISSED_REMINDER', 'email', 'MISSED_15|84', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 16:54:22'),
(18, 'U101', 13, 84, 'MISSED_REMINDER', 'sms', 'MISSED_15|84', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 16:54:23'),
(19, 'U101', 13, 85, 'DUE_NOW_REMINDER', 'email', 'DUE_NOW|85', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 17:06:07'),
(20, 'U101', 13, 85, 'DUE_NOW_REMINDER', 'sms', 'DUE_NOW|85', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 17:06:09'),
(21, 'U101', 12, 76, 'MISSED_REMINDER', 'email', 'MISSED_15|76', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 17:06:15'),
(22, 'U101', 12, 76, 'MISSED_REMINDER', 'sms', 'MISSED_15|76', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 17:06:16'),
(23, 'U101', 6, 69, 'MISSED_REMINDER', 'email', 'MISSED_15|69', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 17:06:21'),
(24, 'U101', 6, 69, 'MISSED_REMINDER', 'sms', 'MISSED_15|69', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 17:06:22'),
(25, 'U101', 14, NULL, 'NEW_PRESCRIPTION', 'email', 'NEW_PRESCRIPTION|14|U101', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 17:08:00'),
(26, 'U101', 14, NULL, 'NEW_PRESCRIPTION', 'sms', 'NEW_PRESCRIPTION|14|U101', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 17:08:02'),
(27, 'U101', 14, 86, 'DUE_NOW_REMINDER', 'email', 'DUE_NOW|86', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 17:09:07'),
(28, 'U101', 14, 86, 'DUE_NOW_REMINDER', 'sms', 'DUE_NOW|86', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 17:09:08'),
(29, 'U101', 15, 93, 'MISSED_REMINDER', 'email', 'MISSED_15|93', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 17:12:08'),
(30, 'U101', 15, 93, 'MISSED_REMINDER', 'sms', 'MISSED_15|93', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 17:12:09'),
(31, 'U101', 16, NULL, 'NEW_PRESCRIPTION', 'email', 'NEW_PRESCRIPTION|16|U101', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 17:12:50'),
(32, 'U101', 16, NULL, 'NEW_PRESCRIPTION', 'sms', 'NEW_PRESCRIPTION|16|U101', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 17:12:51'),
(33, 'U101', 16, 100, 'UPCOMING_REMINDER', 'email', 'UPCOMING_5|100', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 17:14:07'),
(34, 'U101', 16, 100, 'UPCOMING_REMINDER', 'sms', 'UPCOMING_5|100', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 17:14:08'),
(35, 'U101', 16, 100, 'DUE_NOW_REMINDER', 'email', 'DUE_NOW|100', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 17:19:07'),
(36, 'U101', 16, 100, 'DUE_NOW_REMINDER', 'sms', 'DUE_NOW|100', '9749460915', 'FAILED', 'SMS failed: Twilio returned HTTP 400.', '2026-04-06 17:19:08'),
(37, 'U101', 14, 86, 'MISSED_REMINDER', 'email', 'MISSED_15|86', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 17:30:07'),
(38, 'U101', 14, 86, 'MISSED_REMINDER', 'sms', 'MISSED_15|86', '9749460915', 'SENT', 'SMS sent.', '2026-04-06 17:30:08'),
(39, 'U101', 17, NULL, 'NEW_PRESCRIPTION', 'email', 'NEW_PRESCRIPTION|17|U101', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 17:30:44'),
(40, 'U101', 17, NULL, 'NEW_PRESCRIPTION', 'sms', 'NEW_PRESCRIPTION|17|U101', '9749460915', 'SENT', 'SMS sent.', '2026-04-06 17:30:45'),
(41, 'U101', 17, 107, 'DUE_NOW_REMINDER', 'email', 'DUE_NOW|107', 'cr7samiee@gmail.com', 'SENT', 'Email sent.', '2026-04-06 17:33:07'),
(42, 'U101', 17, 107, 'DUE_NOW_REMINDER', 'sms', 'DUE_NOW|107', '9749460915', 'SENT', 'SMS sent.', '2026-04-06 17:33:08'),
(43, 'U104', 18, NULL, 'NEW_PRESCRIPTION', 'email', 'NEW_PRESCRIPTION|18|U104', 'cr7samiee333@gmail.com', 'SENT', 'Email sent.', '2026-04-07 04:23:03'),
(44, 'U104', 18, NULL, 'NEW_PRESCRIPTION', 'sms', 'NEW_PRESCRIPTION|18|U104', '9820000006', 'FAILED', 'SMS failed: Twilio returned HTTP 400. Twilio code 21608: The number +977982000XXXX is unverified. Trial accounts cannot send messages to unverified numbers; verify +977982000XXXX at twilio.com/user/account/phone-numbers/verified, or purchase a Twilio number to send messages to unverified numbers', '2026-04-07 04:23:05'),
(45, 'U103', NULL, NULL, 'ADMIN_ACCOUNT_DELETE', 'email', 'ADMIN_ACCOUNT_DELETE|U103|20260407100927', 'krishna01@gmail.com', 'SENT', 'Email sent.', '2026-04-07 04:24:33'),
(46, 'A101', NULL, NULL, 'ADMIN_ACCOUNT_DELETE_AUDIT', 'email', 'ADMIN_ACCOUNT_DELETE_AUDIT|U103|20260407100933', 'asmirleodup@gmail.com', 'SENT', 'Email sent.', '2026-04-07 04:24:39'),
(47, 'H101', NULL, NULL, 'ADMIN_PASSWORD_RESET', 'email', 'ADMIN_PASSWORD_RESET|H101|20260407100955', 'ali12@gmail.com', 'SENT', 'Email sent.', '2026-04-07 04:25:01'),
(48, 'A101', NULL, NULL, 'ADMIN_PASSWORD_RESET_AUDIT', 'email', 'ADMIN_PASSWORD_RESET_AUDIT|H101|20260407101001', 'asmirleodup@gmail.com', 'SENT', 'Email sent.', '2026-04-07 04:25:07'),
(49, 'A101', NULL, NULL, 'ADMIN_PASSWORD_RESET', 'email', 'ADMIN_PASSWORD_RESET|A101|20260407101007', 'asmirleodup@gmail.com', 'SENT', 'Email sent.', '2026-04-07 04:25:13'),
(50, 'A101', NULL, NULL, 'ADMIN_PASSWORD_RESET_AUDIT', 'email', 'ADMIN_PASSWORD_RESET_AUDIT|A101|20260407101013', 'asmirleodup@gmail.com', 'SENT', 'Email sent.', '2026-04-07 04:25:19'),
(51, 'A101', NULL, NULL, 'ADMIN_PASSWORD_RESET', 'email', 'ADMIN_PASSWORD_RESET|A101|20260407101020', 'asmirleodup@gmail.com', 'SENT', 'Email sent.', '2026-04-07 04:25:26'),
(52, 'A101', NULL, NULL, 'ADMIN_PASSWORD_RESET_AUDIT', 'email', 'ADMIN_PASSWORD_RESET_AUDIT|A101|20260407101026', 'asmirleodup@gmail.com', 'SENT', 'Email sent.', '2026-04-07 04:25:32'),
(53, 'U102', NULL, NULL, 'ADMIN_ACCOUNT_DELETE', 'email', 'ADMIN_ACCOUNT_DELETE|U102|20260407101053', 'leozayn@gmail.com', 'SENT', 'Email sent.', '2026-04-07 04:25:59'),
(54, 'A101', NULL, NULL, 'ADMIN_ACCOUNT_DELETE_AUDIT', 'email', 'ADMIN_ACCOUNT_DELETE_AUDIT|U102|20260407101059', 'asmirleodup@gmail.com', 'SENT', 'Email sent.', '2026-04-07 04:26:06');

-- --------------------------------------------------------

--
-- Table structure for table `reminder_settings`
--

CREATE TABLE `reminder_settings` (
  `scenario_key` varchar(20) NOT NULL,
  `scenario_label` varchar(50) NOT NULL,
  `dose_count` tinyint(4) NOT NULL,
  `slot_one` time NOT NULL,
  `slot_two` time DEFAULT NULL,
  `slot_three` time DEFAULT NULL,
  `upcoming_minutes` int(11) NOT NULL DEFAULT 30,
  `missed_minutes` int(11) NOT NULL DEFAULT 30,
  `send_due_now` tinyint(1) NOT NULL DEFAULT 1,
  `auto_mark_skipped` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reminder_settings`
--

INSERT INTO `reminder_settings` (`scenario_key`, `scenario_label`, `dose_count`, `slot_one`, `slot_two`, `slot_three`, `upcoming_minutes`, `missed_minutes`, `send_due_now`, `auto_mark_skipped`, `updated_at`) VALUES
('1x_daily', 'Once A Day', 1, '08:00:00', NULL, NULL, 5, 15, 1, 1, '2026-04-07 04:13:48'),
('2x_daily', 'Twice A Day', 2, '08:00:00', '20:00:00', NULL, 5, 15, 1, 1, '2026-04-07 04:14:13'),
('3x_daily', 'Three Times A Day', 3, '08:00:00', '13:00:00', '20:00:00', 5, 15, 1, 1, '2026-04-07 04:14:27');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `log_type` enum('AUTH','SMS','EMAIL','SYSTEM') NOT NULL,
  `message` text NOT NULL,
  `status` enum('SUCCESS','ERROR','RETRIED') DEFAULT 'SUCCESS',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `log_type`, `message`, `status`, `created_at`) VALUES
(6, 'EMAIL', 'NEW_PRESCRIPTION|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 15:57:17'),
(7, 'SMS', 'NEW_PRESCRIPTION|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 15:57:18'),
(8, 'EMAIL', 'MISSED_REMINDER|U103|SENT|Email sent.', 'SUCCESS', '2026-04-06 16:00:07'),
(9, 'SMS', 'MISSED_REMINDER|U103|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 16:00:08'),
(10, 'EMAIL', 'MISSED_REMINDER|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 16:00:13'),
(11, 'SMS', 'MISSED_REMINDER|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 16:00:14'),
(12, 'SYSTEM', 'REMINDER_RUN|sent=2|items=2|upcoming=0|due_now=0|missed=2|auto_skipped=2', 'SUCCESS', '2026-04-06 16:00:14'),
(13, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:01:01'),
(14, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:02:01'),
(15, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:03:01'),
(16, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:04:01'),
(17, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:05:01'),
(18, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:11:21'),
(19, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:12:01'),
(20, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:13:01'),
(21, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:14:01'),
(22, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:15:01'),
(23, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:16:01'),
(24, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:17:01'),
(25, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:18:01'),
(26, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:19:01'),
(27, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:20:01'),
(28, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:22:01'),
(29, 'EMAIL', 'NEW_PRESCRIPTION|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 16:22:04'),
(30, 'SMS', 'NEW_PRESCRIPTION|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 16:22:05'),
(31, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:23:01'),
(32, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:24:01'),
(33, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:25:01'),
(34, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:26:01'),
(35, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:27:01'),
(36, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:28:01'),
(37, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:29:01'),
(38, 'EMAIL', 'NEW_PRESCRIPTION|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 16:42:37'),
(39, 'SMS', 'NEW_PRESCRIPTION|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 16:42:38'),
(40, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 16:46:15'),
(41, 'EMAIL', 'NEW_PRESCRIPTION|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 16:50:00'),
(42, 'SMS', 'NEW_PRESCRIPTION|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 16:50:02'),
(43, 'EMAIL', 'MISSED_REMINDER|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 16:54:05'),
(44, 'SMS', 'MISSED_REMINDER|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 16:54:08'),
(45, 'EMAIL', 'MISSED_REMINDER|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 16:54:14'),
(46, 'SMS', 'MISSED_REMINDER|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 16:54:16'),
(47, 'EMAIL', 'MISSED_REMINDER|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 16:54:22'),
(48, 'SMS', 'MISSED_REMINDER|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 16:54:23'),
(49, 'SYSTEM', 'REMINDER_RUN|sent=3|items=3|upcoming=0|due_now=0|missed=3|auto_skipped=3', 'SUCCESS', '2026-04-06 16:54:23'),
(50, 'EMAIL', 'DUE_NOW_REMINDER|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 17:06:07'),
(51, 'SMS', 'DUE_NOW_REMINDER|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 17:06:09'),
(52, 'EMAIL', 'MISSED_REMINDER|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 17:06:15'),
(53, 'SMS', 'MISSED_REMINDER|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 17:06:16'),
(54, 'EMAIL', 'MISSED_REMINDER|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 17:06:21'),
(55, 'SMS', 'MISSED_REMINDER|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 17:06:22'),
(56, 'SYSTEM', 'REMINDER_RUN|sent=3|items=3|upcoming=0|due_now=1|missed=2|auto_skipped=2', 'SUCCESS', '2026-04-06 17:06:22'),
(57, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:07:02'),
(58, 'EMAIL', 'NEW_PRESCRIPTION|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 17:08:00'),
(59, 'SMS', 'NEW_PRESCRIPTION|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 17:08:02'),
(60, 'SYSTEM', 'REMINDER_RUN|sent=0|items=0|upcoming=0|due_now=0|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:08:02'),
(61, 'EMAIL', 'DUE_NOW_REMINDER|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 17:09:07'),
(62, 'SMS', 'DUE_NOW_REMINDER|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 17:09:08'),
(63, 'SYSTEM', 'REMINDER_RUN|sent=1|items=1|upcoming=0|due_now=1|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:09:08'),
(64, 'SYSTEM', 'REMINDER_RUN|sent=0|items=1|upcoming=0|due_now=1|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:10:02'),
(65, 'EMAIL', 'MISSED_REMINDER|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 17:12:08'),
(66, 'SMS', 'MISSED_REMINDER|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 17:12:09'),
(67, 'SYSTEM', 'REMINDER_RUN|sent=1|items=2|upcoming=0|due_now=1|missed=1|auto_skipped=1', 'SUCCESS', '2026-04-06 17:12:09'),
(68, 'EMAIL', 'NEW_PRESCRIPTION|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 17:12:50'),
(69, 'SMS', 'NEW_PRESCRIPTION|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 17:12:51'),
(70, 'SYSTEM', 'REMINDER_RUN|sent=0|items=1|upcoming=0|due_now=1|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:13:02'),
(71, 'EMAIL', 'UPCOMING_REMINDER|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 17:14:07'),
(72, 'SMS', 'UPCOMING_REMINDER|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 17:14:08'),
(73, 'SYSTEM', 'REMINDER_RUN|sent=1|items=2|upcoming=1|due_now=1|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:14:08'),
(74, 'SYSTEM', 'REMINDER_RUN|sent=0|items=2|upcoming=1|due_now=1|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:15:02'),
(75, 'SYSTEM', 'REMINDER_RUN|sent=0|items=2|upcoming=1|due_now=1|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:16:02'),
(76, 'SYSTEM', 'REMINDER_RUN|sent=0|items=2|upcoming=1|due_now=1|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:17:01'),
(77, 'SYSTEM', 'REMINDER_RUN|sent=0|items=2|upcoming=1|due_now=1|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:18:01'),
(78, 'EMAIL', 'DUE_NOW_REMINDER|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 17:19:07'),
(79, 'SMS', 'DUE_NOW_REMINDER|U101|FAILED|SMS failed: Twilio returned HTTP 400.', 'ERROR', '2026-04-06 17:19:08'),
(80, 'SYSTEM', 'REMINDER_RUN|sent=1|items=2|upcoming=0|due_now=2|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:19:08'),
(81, 'SYSTEM', 'REMINDER_RUN|sent=0|items=2|upcoming=0|due_now=2|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:20:02'),
(82, 'SYSTEM', 'REMINDER_RUN|sent=0|items=2|upcoming=0|due_now=2|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:21:01'),
(83, 'EMAIL', 'MISSED_REMINDER|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 17:30:07'),
(84, 'SMS', 'MISSED_REMINDER|U101|SENT|SMS sent.', 'SUCCESS', '2026-04-06 17:30:08'),
(85, 'SYSTEM', 'REMINDER_RUN|sent=2|items=2|upcoming=0|due_now=1|missed=1|auto_skipped=1', 'SUCCESS', '2026-04-06 17:30:08'),
(86, 'EMAIL', 'NEW_PRESCRIPTION|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 17:30:44'),
(87, 'SMS', 'NEW_PRESCRIPTION|U101|SENT|SMS sent.', 'SUCCESS', '2026-04-06 17:30:45'),
(88, 'SYSTEM', 'REMINDER_RUN|sent=0|items=1|upcoming=0|due_now=1|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:31:01'),
(89, 'SYSTEM', 'REMINDER_RUN|sent=0|items=1|upcoming=0|due_now=1|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:32:01'),
(90, 'EMAIL', 'DUE_NOW_REMINDER|U101|SENT|Email sent.', 'SUCCESS', '2026-04-06 17:33:07'),
(91, 'SMS', 'DUE_NOW_REMINDER|U101|SENT|SMS sent.', 'SUCCESS', '2026-04-06 17:33:08'),
(92, 'SYSTEM', 'REMINDER_RUN|sent=2|items=2|upcoming=0|due_now=2|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:33:08'),
(93, 'SYSTEM', 'REMINDER_RUN|sent=0|items=2|upcoming=0|due_now=2|missed=0|auto_skipped=0', 'SUCCESS', '2026-04-06 17:33:45'),
(94, 'EMAIL', 'NEW_PRESCRIPTION|U104|SENT|Email sent.', 'SUCCESS', '2026-04-07 04:23:03'),
(95, 'SMS', 'NEW_PRESCRIPTION|U104|FAILED|SMS failed: Twilio returned HTTP 400. Twilio code 21608: The number +977982000XXXX is unverified. Trial accounts cannot send messages to unverified numbers; verify +977982000XXXX at twilio.com/user/account/phone-numbers/verified, or purchase a Twilio number to send messages to unverified numbers', 'ERROR', '2026-04-07 04:23:05'),
(96, 'EMAIL', 'ADMIN_ACCOUNT_DELETE|U103|SENT|Email sent.', 'SUCCESS', '2026-04-07 04:24:33'),
(97, 'EMAIL', 'ADMIN_ACCOUNT_DELETE_AUDIT|A101|SENT|Email sent.', 'SUCCESS', '2026-04-07 04:24:39'),
(98, 'EMAIL', 'ADMIN_PASSWORD_RESET|H101|SENT|Email sent.', 'SUCCESS', '2026-04-07 04:25:01'),
(99, 'EMAIL', 'ADMIN_PASSWORD_RESET_AUDIT|A101|SENT|Email sent.', 'SUCCESS', '2026-04-07 04:25:07'),
(100, 'EMAIL', 'ADMIN_PASSWORD_RESET|A101|SENT|Email sent.', 'SUCCESS', '2026-04-07 04:25:13'),
(101, 'EMAIL', 'ADMIN_PASSWORD_RESET_AUDIT|A101|SENT|Email sent.', 'SUCCESS', '2026-04-07 04:25:20'),
(102, 'EMAIL', 'ADMIN_PASSWORD_RESET|A101|SENT|Email sent.', 'SUCCESS', '2026-04-07 04:25:26'),
(103, 'EMAIL', 'ADMIN_PASSWORD_RESET_AUDIT|A101|SENT|Email sent.', 'SUCCESS', '2026-04-07 04:25:32'),
(104, 'EMAIL', 'ADMIN_ACCOUNT_DELETE|U102|SENT|Email sent.', 'SUCCESS', '2026-04-07 04:25:59'),
(105, 'EMAIL', 'ADMIN_ACCOUNT_DELETE_AUDIT|A101|SENT|Email sent.', 'SUCCESS', '2026-04-07 04:26:06');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` varchar(50) NOT NULL,
  `role` enum('Admin','Health Worker','User') NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `plain_password` varchar(255) NOT NULL,
  `post` varchar(100) DEFAULT NULL,
  `worker_code` varchar(20) DEFAULT NULL,
  `relation` varchar(100) DEFAULT NULL,
  `disease` varchar(100) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `name`, `phone`, `email`, `password_hash`, `plain_password`, `post`, `worker_code`, `relation`, `disease`, `dob`, `address`, `reset_token`, `reset_token_expiry`, `created_at`) VALUES
('A101', 'Admin', 'Rohan Khadka', '9800000006', 'asmirleodup@gmail.com', '$2y$10$lIaMKDGXPUjKcR5p2Zcs4uDRbJ2CJtTWekoUDz6/kN28LT000iYXm', 'Med@2351', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 10:31:52'),
('H100', 'Health Worker', 'Krishna Das', '9749460916', 'cr7samiee33@gmail.com', '$2y$10$Ne/vVKp9.Z8TCzTwBtEUnuOZYGdZkGBF2m7/8/S5euPKYNyGEPAcW', '#kCC#123', 'Pharmacist', 'HW-H100', NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 10:31:08'),
('H101', 'Health Worker', 'Zayn Ali', '9800000007', 'ali12@gmail.com', '$2y$10$Cq0ytvigw0KVCroM4VusR.PvnrlDgT21BHsn0Hrg03M/s7wtCABK.', 'Med@7084', 'Nurse', 'HW-H101', NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 13:04:01'),
('U101', 'User', 'Pratham Rimal', '9749460915', 'cr7samiee@gmail.com', '$2y$10$11NLiTLhfb0gnHb/HYQfbOyOKdY0Z4N2.XGxBQizWeZSdER4T0xfC', '#Zayn#123', NULL, NULL, 'Self', NULL, NULL, NULL, NULL, NULL, '2026-04-06 10:30:15'),
('U104', 'User', 'Bishnu Kharel', '9820000006', 'cr7samiee333@gmail.com', '$2y$10$VX1RhEl1AIYWWxLtS7d4quQcxsn8MHi6sKE9ZzD.IkEWkWaRZrXRu', '#Zayn#123', NULL, NULL, 'Mother', NULL, NULL, NULL, NULL, NULL, '2026-04-06 13:02:46');

-- --------------------------------------------------------

--
-- Table structure for table `water_logs`
--

CREATE TABLE `water_logs` (
  `id` int(11) NOT NULL,
  `patient_id` varchar(50) NOT NULL,
  `intake_date` date NOT NULL,
  `intake_ml` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `water_logs`
--

INSERT INTO `water_logs` (`id`, `patient_id`, `intake_date`, `intake_ml`, `created_at`, `updated_at`) VALUES
(3, 'U101', '2026-04-06', 250, '2026-04-06 12:45:18', '2026-04-06 12:45:18'),
(4, 'U101', '2026-04-07', 0, '2026-04-07 04:26:53', '2026-04-07 04:31:32');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_created_at` (`created_at`),
  ADD KEY `idx_audit_actor` (`actor_user_id`),
  ADD KEY `idx_audit_action` (`action_key`);

--
-- Indexes for table `caregiver_patient`
--
ALTER TABLE `caregiver_patient`
  ADD PRIMARY KEY (`caregiver_id`,`patient_id`),
  ADD KEY `idx_caregiver_patient_patient_id` (`patient_id`);

--
-- Indexes for table `intake_logs`
--
ALTER TABLE `intake_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicine_id` (`medicine_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `medicines`
--
ALTER TABLE `medicines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `prescriber_id` (`prescriber_id`);

--
-- Indexes for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_event_channel` (`event_key`,`channel`),
  ADD KEY `idx_notification_user` (`user_id`),
  ADD KEY `idx_notification_log` (`intake_log_id`);

--
-- Indexes for table `reminder_settings`
--
ALTER TABLE `reminder_settings`
  ADD PRIMARY KEY (`scenario_key`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uniq_worker_code` (`worker_code`);

--
-- Indexes for table `water_logs`
--
ALTER TABLE `water_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_patient_day` (`patient_id`,`intake_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `intake_logs`
--
ALTER TABLE `intake_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `medicines`
--
ALTER TABLE `medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT for table `water_logs`
--
ALTER TABLE `water_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `caregiver_patient`
--
ALTER TABLE `caregiver_patient`
  ADD CONSTRAINT `caregiver_patient_ibfk_1` FOREIGN KEY (`caregiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `caregiver_patient_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `intake_logs`
--
ALTER TABLE `intake_logs`
  ADD CONSTRAINT `intake_logs_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `intake_logs_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medicines`
--
ALTER TABLE `medicines`
  ADD CONSTRAINT `medicines_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medicines_ibfk_2` FOREIGN KEY (`prescriber_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `water_logs`
--
ALTER TABLE `water_logs`
  ADD CONSTRAINT `water_logs_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
