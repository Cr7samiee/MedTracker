-- MySQL dump 10.13  Distrib 8.4.9, for Linux (x86_64)
--
-- Host: localhost    Database: medtracker_db
-- ------------------------------------------------------
-- Server version	8.4.9

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `actor_user_id` varchar(50) DEFAULT NULL,
  `actor_role` varchar(50) DEFAULT NULL,
  `action_key` varchar(80) NOT NULL,
  `entity_type` varchar(60) NOT NULL,
  `entity_id` varchar(80) DEFAULT NULL,
  `target_user_id` varchar(50) DEFAULT NULL,
  `details_json` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created_at` (`created_at`),
  KEY `idx_audit_actor` (`actor_user_id`),
  KEY `idx_audit_action` (`action_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `caregiver_patient`
--

DROP TABLE IF EXISTS `caregiver_patient`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `caregiver_patient` (
  `caregiver_id` varchar(50) NOT NULL,
  `patient_id` varchar(50) NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`caregiver_id`,`patient_id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `caregiver_patient_ibfk_1` FOREIGN KEY (`caregiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `caregiver_patient_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `caregiver_patient`
--

LOCK TABLES `caregiver_patient` WRITE;
/*!40000 ALTER TABLE `caregiver_patient` DISABLE KEYS */;
/*!40000 ALTER TABLE `caregiver_patient` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `intake_logs`
--

DROP TABLE IF EXISTS `intake_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `intake_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `medicine_id` int NOT NULL,
  `patient_id` varchar(50) NOT NULL,
  `scheduled_time` datetime NOT NULL,
  `status` enum('Pending','Taken','Skipped') DEFAULT 'Pending',
  `taken_at` datetime DEFAULT NULL,
  `skip_reason` varchar(255) DEFAULT NULL,
  `snooze_until` datetime DEFAULT NULL,
  `snooze_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `medicine_id` (`medicine_id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `intake_logs_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `intake_logs_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `intake_logs`
--

LOCK TABLES `intake_logs` WRITE;
/*!40000 ALTER TABLE `intake_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `intake_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medicines`
--

DROP TABLE IF EXISTS `medicines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medicines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` varchar(50) NOT NULL,
  `prescriber_id` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `dosage` varchar(50) NOT NULL,
  `type` varchar(50) DEFAULT 'Oral Tablet',
  `instructions` text,
  `frequency` varchar(50) NOT NULL,
  `custom_times_json` text,
  `custom_times_effective_at` datetime DEFAULT NULL,
  `treatment_mode` enum('course','ongoing') NOT NULL DEFAULT 'course',
  `prescription_status` enum('active','paused','stopped') NOT NULL DEFAULT 'active',
  `paused_at` datetime DEFAULT NULL,
  `stopped_at` datetime DEFAULT NULL,
  `stop_reason` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `duration_days` int DEFAULT '7',
  `end_date` date DEFAULT NULL,
  `quantity` int DEFAULT '0',
  `manufacture_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `prescriber_id` (`prescriber_id`),
  CONSTRAINT `medicines_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medicines_ibfk_2` FOREIGN KEY (`prescriber_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medicines`
--

LOCK TABLES `medicines` WRITE;
/*!40000 ALTER TABLE `medicines` DISABLE KEYS */;
/*!40000 ALTER TABLE `medicines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_logs`
--

DROP TABLE IF EXISTS `notification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `medicine_id` int DEFAULT NULL,
  `intake_log_id` int DEFAULT NULL,
  `notification_type` varchar(60) NOT NULL,
  `channel` enum('email','sms') NOT NULL,
  `event_key` varchar(150) DEFAULT NULL,
  `recipient` varchar(150) DEFAULT NULL,
  `status` enum('SENT','FAILED','SKIPPED') DEFAULT 'SENT',
  `response_message` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_event_channel` (`event_key`,`channel`),
  KEY `idx_notification_user` (`user_id`),
  KEY `idx_notification_log` (`intake_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_logs`
--

LOCK TABLES `notification_logs` WRITE;
/*!40000 ALTER TABLE `notification_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reminder_settings`
--

DROP TABLE IF EXISTS `reminder_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reminder_settings` (
  `scenario_key` varchar(20) NOT NULL,
  `scenario_label` varchar(50) NOT NULL,
  `dose_count` tinyint NOT NULL,
  `upcoming_minutes` int NOT NULL DEFAULT '5',
  `missed_minutes` int NOT NULL DEFAULT '15',
  `send_due_now` tinyint(1) NOT NULL DEFAULT '1',
  `auto_mark_skipped` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`scenario_key`),
  UNIQUE KEY `dose_count` (`dose_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reminder_settings`
--

LOCK TABLES `reminder_settings` WRITE;
/*!40000 ALTER TABLE `reminder_settings` DISABLE KEYS */;
INSERT INTO `reminder_settings` VALUES ('1x_daily','Once A Day',1,5,15,1,1,'2026-05-01 14:41:25'),('2x_daily','Twice A Day',2,5,15,1,1,'2026-05-01 14:41:25'),('3x_daily','Three Times A Day',3,5,15,1,1,'2026-05-01 14:41:25');
/*!40000 ALTER TABLE `reminder_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `log_type` enum('AUTH','SMS','EMAIL','SYSTEM') NOT NULL,
  `message` text NOT NULL,
  `status` enum('SUCCESS','ERROR','RETRIED') DEFAULT 'SUCCESS',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_logs`
--

LOCK TABLES `system_logs` WRITE;
/*!40000 ALTER TABLE `system_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `gender` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `worker_code` (`worker_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `water_logs`
--

DROP TABLE IF EXISTS `water_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `water_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` varchar(50) NOT NULL,
  `intake_date` date NOT NULL,
  `intake_ml` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_patient_day` (`patient_id`,`intake_date`),
  CONSTRAINT `water_logs_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `water_logs`
--

LOCK TABLES `water_logs` WRITE;
/*!40000 ALTER TABLE `water_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `water_logs` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-01 20:36:01
