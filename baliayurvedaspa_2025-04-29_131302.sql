-- MySQL dump 10.13  Distrib 8.0.33, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: baliayurvedaspa
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

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

CREATE DATABASE baliayurvedaspa;

--
-- Table structure for table `addons`
--

DROP TABLE IF EXISTS `addons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `regular_rate` decimal(10,2) NOT NULL,
  `vip_elite_rate` decimal(10,2) NOT NULL,
  `duration` int(11) NOT NULL COMMENT 'Duration in minutes',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `addons`
--

/*!40000 ALTER TABLE `addons` DISABLE KEYS */;
INSERT INTO `addons` VALUES (1,'Briefly Massage (per body area)',300.00,200.00,30,1),(2,'Hot Stone Reflex (per body area)',350.00,250.00,30,1),(3,'Bali Hot Stone Compress',200.00,150.00,15,1),(4,'Bali Cupping Therapy',200.00,150.00,15,1),(5,'Bali Ear Candling',350.00,200.00,15,1),(6,'Bali Sauna',350.00,200.00,30,1),(7,'Bali Body Scrub',650.00,350.00,30,1),(8,'Bali Foot Scrub',350.00,200.00,30,1),(9,'Bali Hair Spa',350.00,200.00,30,1);
/*!40000 ALTER TABLE `addons` ENABLE KEYS */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `branch` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'Owner','superadmin@spa.com','admin','Owner'),(2,'Receptionist','malolos@spa.com','admin','Malolos'),(3,'Receptionist','calumpit@spa.com','admin','Calumpit');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;

--
-- Table structure for table `booking_addons`
--

DROP TABLE IF EXISTS `booking_addons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_addons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `addon_id` int(11) NOT NULL,
  `regular_rate` decimal(10,2) NOT NULL,
  `vip_elite_rate` decimal(10,2) NOT NULL,
  `duration` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `addon_id` (`addon_id`),
  CONSTRAINT `booking_addons_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_addons_ibfk_2` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_addons`
--

/*!40000 ALTER TABLE `booking_addons` DISABLE KEYS */;
INSERT INTO `booking_addons` VALUES (39,102,3,200.00,150.00,15),(40,102,4,200.00,150.00,15),(48,112,2,350.00,250.00,30),(49,112,3,200.00,150.00,15),(50,112,5,350.00,200.00,15),(51,112,6,350.00,200.00,30),(52,112,7,650.00,350.00,30),(53,112,8,350.00,200.00,30),(54,112,9,350.00,200.00,30),(55,113,1,300.00,200.00,30),(56,113,2,350.00,250.00,30),(57,113,3,200.00,150.00,15),(58,113,4,200.00,150.00,15),(59,113,5,350.00,200.00,15),(60,113,6,350.00,200.00,30),(61,113,7,650.00,350.00,30),(62,113,8,350.00,200.00,30),(63,113,9,350.00,200.00,30),(64,114,1,300.00,200.00,30),(65,114,2,350.00,250.00,30),(66,114,3,200.00,150.00,15),(67,114,4,200.00,150.00,15),(68,114,5,350.00,200.00,15),(69,114,6,350.00,200.00,30),(70,115,1,300.00,200.00,30),(71,115,2,350.00,250.00,30),(72,115,3,200.00,150.00,15),(73,115,4,200.00,150.00,15),(74,115,5,350.00,200.00,15),(75,115,6,350.00,200.00,30),(76,115,7,650.00,350.00,30),(77,115,8,350.00,200.00,30),(78,115,9,350.00,200.00,30),(85,93,1,300.00,200.00,30),(86,93,2,350.00,250.00,30),(87,110,2,350.00,250.00,30),(88,110,3,200.00,150.00,15),(89,110,5,350.00,200.00,15),(90,110,6,350.00,200.00,30),(91,110,7,650.00,350.00,30),(92,110,8,350.00,200.00,30),(93,110,9,350.00,200.00,30),(94,118,5,350.00,200.00,15),(95,118,6,350.00,200.00,30),(96,119,2,350.00,250.00,30),(97,119,3,200.00,150.00,15),(98,119,4,200.00,150.00,15),(99,122,2,350.00,250.00,30),(100,122,3,200.00,150.00,15),(101,122,4,200.00,150.00,15);
/*!40000 ALTER TABLE `booking_addons` ENABLE KEYS */;

--
-- Table structure for table `booking_therapists`
--

DROP TABLE IF EXISTS `booking_therapists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_therapists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `therapist_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `therapist_id` (`therapist_id`),
  CONSTRAINT `booking_therapists_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_therapists_ibfk_2` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=141 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_therapists`
--

/*!40000 ALTER TABLE `booking_therapists` DISABLE KEYS */;
INSERT INTO `booking_therapists` VALUES (39,88,3,'2025-04-19 11:58:05'),(40,88,2,'2025-04-19 11:58:05'),(41,88,1,'2025-04-19 11:58:05'),(66,107,10,'2025-04-20 05:46:13'),(74,102,11,'2025-04-20 06:23:23'),(75,109,10,'2025-04-20 06:23:35'),(77,111,11,'2025-04-20 06:32:39'),(82,114,11,'2025-04-20 07:44:23'),(93,115,9,'2025-04-20 08:24:22'),(94,113,7,'2025-04-20 08:38:30'),(95,95,11,'2025-04-20 08:40:32'),(96,95,10,'2025-04-20 08:40:32'),(97,95,9,'2025-04-20 08:40:32'),(98,95,8,'2025-04-20 08:40:32'),(110,93,11,'2025-04-20 10:07:13'),(111,110,8,'2025-04-20 10:07:46'),(112,86,7,'2025-04-20 10:08:57'),(113,86,6,'2025-04-20 10:08:57'),(114,86,5,'2025-04-20 10:08:57'),(115,86,4,'2025-04-20 10:08:57'),(116,116,7,'2025-04-20 10:29:39'),(117,117,11,'2025-04-20 15:28:20'),(118,97,11,'2025-04-21 08:52:48'),(119,97,10,'2025-04-21 08:52:48'),(120,97,9,'2025-04-21 08:52:48'),(121,97,8,'2025-04-21 08:52:48'),(122,118,2,'2025-04-21 13:27:07'),(123,112,2,'2025-04-22 04:23:55'),(124,119,11,'2025-04-22 05:14:54'),(125,120,7,'2025-04-22 05:15:07'),(126,120,6,'2025-04-22 05:15:07'),(127,120,5,'2025-04-22 05:15:07'),(132,122,3,'2025-04-23 04:14:45'),(133,121,7,'2025-04-23 04:14:49'),(134,121,6,'2025-04-23 04:14:49'),(135,121,5,'2025-04-23 04:14:49'),(136,123,7,'2025-04-23 04:15:00'),(137,123,6,'2025-04-23 04:15:00'),(138,124,1,'2025-04-23 14:03:12'),(139,127,7,'2025-04-23 14:03:35'),(140,128,11,'2025-04-23 15:49:41');
/*!40000 ALTER TABLE `booking_therapists` ENABLE KEYS */;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `time_end` time DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `vip_elite_amount` decimal(10,2) NOT NULL,
  `bed_used` int(11) DEFAULT 1,
  `number_of_clients` int(11) DEFAULT 1,
  `total_duration` int(11) NOT NULL COMMENT 'Total duration in minutes',
  `has_membership_card` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('Pending','Active','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `receipt_number` varchar(20) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `allow_reschedule` tinyint(1) DEFAULT 1,
  `membership_code` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `user_id` (`user_id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `bookings_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (86,1,10,1,'2025-04-24','11:30:00','13:29:00',1400.00,700.00,4,4,120,1,'Cancelled','MLS-000001',' [Auto-cancelled at 2025-04-24 11:45:18] [Auto-cancelled at 2025-04-24 11:47:03]','2025-04-19 11:57:43',1,''),(88,1,11,1,'2025-04-20','11:00:00','12:14:00',1000.00,500.00,3,3,75,1,'Completed','MLS-000002',NULL,'2025-04-19 11:58:05',1,'12345678'),(93,1,2,2,'2025-04-20','11:00:00','12:59:00',1050.00,800.00,1,1,120,1,'Completed','CLP-000001',NULL,'2025-04-19 14:21:29',1,''),(95,1,11,2,'2025-04-20','17:00:00','18:14:00',1000.00,500.00,4,4,75,0,'Cancelled','CLP-000002',' [Auto-cancelled at 2025-04-24 11:45:18] [Auto-cancelled at 2025-04-24 11:47:03]','2025-04-19 14:23:16',0,NULL),(97,1,11,2,'2025-04-20','11:00:00','12:14:00',1000.00,500.00,4,4,75,1,'Completed','CLP-000003',NULL,'2025-04-19 14:48:25',0,''),(102,1,2,2,'2025-04-28','12:00:00','13:29:00',800.00,650.00,1,1,90,0,'Completed','CLP-000004',NULL,'2025-04-19 16:26:56',1,NULL),(107,1,7,2,'2025-04-21','20:00:00','21:29:00',1000.00,500.00,1,1,90,1,'Completed','CLP-000005',NULL,'2025-04-20 05:45:51',0,NULL),(109,1,1,2,'2025-04-28','12:00:00','12:59:00',300.00,250.00,1,1,60,0,'Completed','CLP-000006',NULL,'2025-04-20 05:53:33',1,NULL),(110,1,1,2,'2025-04-21','20:30:00','00:29:00',2900.00,1800.00,1,1,240,0,'Completed','CLP-000007',NULL,'2025-04-20 06:25:23',0,NULL),(111,1,1,2,'2025-04-21','20:30:00','21:29:00',300.00,250.00,1,1,60,0,'Cancelled','CLP-000008',' [Auto-cancelled at 2025-04-24 11:45:18] [Auto-cancelled at 2025-04-24 11:47:03]','2025-04-20 06:32:39',0,NULL),(112,1,2,1,'2025-04-22','11:00:00','14:59:00',3000.00,1900.00,1,1,240,0,'Pending','MLS-000003',NULL,'2025-04-20 06:48:44',1,NULL),(113,1,2,1,'2025-04-21','17:00:00','21:44:00',3500.00,2250.00,1,1,285,0,'Cancelled','MLS-000004',' [Auto-cancelled at 2025-04-24 11:47:03]','2025-04-20 07:42:55',1,NULL),(114,1,4,2,'2025-04-22','20:30:00','00:14:00',2500.00,1700.00,1,1,225,0,'Completed','CLP-000009',NULL,'2025-04-20 07:43:31',1,NULL),(115,1,2,2,'2025-04-21','18:30:00','23:14:00',3500.00,2250.00,1,1,285,0,'Cancelled','CLP-000010',NULL,'2025-04-20 07:44:48',0,NULL),(116,1,11,1,'2025-04-23','11:00:00','12:14:00',1000.00,500.00,1,1,75,1,'Pending','MLS-000005',NULL,'2025-04-20 10:29:39',1,NULL),(117,2,11,2,'2025-04-30','11:30:00','12:44:00',1000.00,500.00,1,1,75,0,'Completed','CLP-000011',NULL,'2025-04-20 15:28:20',1,NULL),(118,3,2,1,'2025-05-01','11:00:00','12:44:00',1100.00,750.00,1,1,105,0,'Cancelled','MLS-000006','ediwow [Auto-cancelled at 2025-04-24 11:47:03]','2025-04-21 13:27:07',1,NULL),(119,1,1,2,'2025-04-22','19:00:00','20:59:00',1050.00,800.00,1,1,120,0,'Cancelled','CLP-000012',' [Auto-cancelled at 2025-04-24 11:47:03] [Auto-cancelled at 2025-04-24 11:48:17]','2025-04-22 05:14:54',1,NULL),(120,1,7,1,'2025-04-30','14:00:00','15:29:00',1000.00,500.00,1,3,90,1,'Completed','MLS-000007',NULL,'2025-04-22 05:15:07',1,NULL),(121,5,9,1,'2025-05-01','12:30:00','14:29:00',1200.00,600.00,3,3,120,0,'Cancelled','MLS-000008','','2025-04-23 04:14:20',1,NULL),(122,5,5,1,'2025-04-29','18:00:00','19:59:00',1200.00,950.00,1,1,120,0,'Pending','MLS-000009',NULL,'2025-04-23 04:14:35',1,NULL),(123,5,11,1,'2025-04-28','11:30:00','12:44:00',1000.00,500.00,2,2,75,0,'Cancelled','MLS-000010',NULL,'2025-04-23 04:15:00',1,NULL),(124,1,5,1,'2025-04-29','11:00:00','11:59:00',450.00,400.00,1,1,60,0,'Pending','MLS-000011',NULL,'2025-04-23 14:03:12',1,NULL),(127,1,2,1,'2025-04-26','20:30:00','21:29:00',400.00,350.00,1,1,60,0,'Pending','MLS-000012',NULL,'2025-04-23 14:03:35',1,NULL),(128,1,1,2,'2025-04-24','11:30:00','12:30:00',300.00,250.00,1,1,60,0,'Active','CLP-000013',NULL,'2025-04-23 15:49:41',1,NULL);
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `bed_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (1,'Malolos',10),(2,'Calumpit',6);
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;

--
-- Table structure for table `membership_cards`
--

DROP TABLE IF EXISTS `membership_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `membership_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `card_type` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `membership_cards`
--

/*!40000 ALTER TABLE `membership_cards` DISABLE KEYS */;
INSERT INTO `membership_cards` VALUES (1,'VIP (Personalized)',300.00,'Personalized membership card for individual use'),(2,'ELITE (Family)',600.00,'Family membership card for multiple users');
/*!40000 ALTER TABLE `membership_cards` ENABLE KEYS */;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `regular_rate` decimal(10,2) NOT NULL,
  `vip_elite_rate` decimal(10,2) NOT NULL,
  `duration` int(11) DEFAULT NULL COMMENT 'Duration in minutes',
  `category` enum('Regular','Body Healing') NOT NULL DEFAULT 'Regular',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (1,'Ayurveda Massage','Relaxing Massage',300.00,250.00,60,'Regular',1),(2,'Bali Ayurveda Massage','Combination Signature Massage',400.00,350.00,60,'Regular',1),(3,'Balinese Massage','Deep Pressure Massage',450.00,400.00,60,'Regular',1),(4,'Bali Hot Stone Massage','Therapeutic Massage',750.00,550.00,90,'Regular',1),(5,'Bali Foot Massage','',450.00,400.00,60,'Regular',1),(6,'Upper Back Healing Package','Bali Ayurveda + Upperback Massage + Cupping Therapy',1000.00,500.00,90,'Body Healing',1),(7,'Lowerback Healing Package','Bali Ayurveda + Lower back Massage + Bali Hot Compress',1000.00,500.00,90,'Body Healing',1),(8,'Deep Muscle Pain Healing Package','Balinese + Cranio Sacral + Sauna Bath',1000.00,500.00,90,'Body Healing',1),(9,'Ultimate Healing Package','Bali Ayurveda + Cranio Sacral Massage + Sauna Bath + Hotstone Compress or Cupping Therapy',1200.00,600.00,120,'Body Healing',1),(10,'Body Spa Ritual Healing Package','Bali Ayurveda + Sauna Bath + Hair spa + Body Scrub + Hotstone Compress or Cupping Therapy',1400.00,700.00,120,'Body Healing',1),(11,'Foot Massage Healing Package','Foot scrub + Foot Massage + Hot Stone Reflex',1000.00,500.00,75,'Body Healing',1);
/*!40000 ALTER TABLE `services` ENABLE KEYS */;

--
-- Table structure for table `therapist_availability`
--

DROP TABLE IF EXISTS `therapist_availability`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `therapist_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `therapist_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` enum('vacation','sickness','other') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `therapist_id` (`therapist_id`),
  CONSTRAINT `therapist_availability_ibfk_1` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `therapist_availability`
--

/*!40000 ALTER TABLE `therapist_availability` DISABLE KEYS */;
INSERT INTO `therapist_availability` VALUES (12,1,'2025-04-26','2025-04-26','vacation','2025-04-23 14:02:17');
/*!40000 ALTER TABLE `therapist_availability` ENABLE KEYS */;

--
-- Table structure for table `therapists`
--

DROP TABLE IF EXISTS `therapists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `therapists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `therapists_ibfk_1` (`branch_id`),
  CONSTRAINT `therapists_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `therapists`
--

/*!40000 ALTER TABLE `therapists` DISABLE KEYS */;
INSERT INTO `therapists` VALUES (1,'Aurea Lema',1,'Head Therapist',1),(2,'Mildred Enriquez',1,'Senior Therapist',1),(3,'Esperenza Bulaong',1,'Senior Therapist',1),(4,'Lea Mendiola',1,'Licensed Massage Therapist',1),(5,'Myla Natividad',1,'Licensed Massage Therapist',1),(6,'Maria',1,'Therapist',1),(7,'Sarah',1,'Therapist',1),(8,'Angie Fabunan',2,'Licensed Massage Therapist',1),(9,'Reyzie',2,'Licensed Massage Therapist',1),(10,'Michelle',2,'Licensed Massage Therapist',1),(11,'Trixie',2,'Licensed Massage Therapist',1);
/*!40000 ALTER TABLE `therapists` ENABLE KEYS */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `otp_hash` varchar(255) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Aries','ariesytprem22@gmail.com','$2y$10$4UGTuiZXRPp8VekO8.L/zOWydXsvTnb/KNVxOna0Z5niSDFB5OWoG',1,'$2y$10$5bisEQfAthEAQJ5FNCgXEecvnY6NZEgiQleYv9803hWvvY1erjfgO','2025-04-25 02:27:06','2025-04-14 02:15:10','2025-04-25 00:22:06'),(2,'Dannn','202210328@btech.ph.education','$2y$10$rvKMIvLdnZaU3EaeU.eQfulpH4HaYcv4B6oQG23aGCUsGCFOVpRve',1,'$2y$10$VQJdGFlhVA4362A8wCAeHOjmSBdWp2iqcPtgeq88cehQW9OYEmCcy','2025-04-17 14:11:42','2025-04-17 12:06:42','2025-04-22 08:41:10'),(3,'Dannn','gutierrez.daniel0325@gmail.com','$2y$10$P/099Cl2TbXfJ0nTd/e2KOp86lJ6E2XLxqZS5zZbY7t6XUvTUwv/W',1,'$2y$10$dI0HLr3P9OuTmQDDUySKp.RdfSgYEIi07NZbiNgwtwZXaOcf0UaVW','2025-04-17 14:13:09','2025-04-17 12:08:09','2025-04-17 12:08:45'),(5,'Aries Bautista','ariesdave253@gmail.com','$2y$10$3iQEXKCncFHxQTIWy8GelO/PPkA5dXpW7AJfffU8LaxdlRo5t3pL.',1,'$2y$10$qw173nudNdPC4QhStL6ZN.tkNyccz9BwasB2gkHVK3sXnKvBtaOsy','2025-04-23 06:18:33','2025-04-23 04:13:33','2025-04-23 04:14:04');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;

--
-- Dumping routines for database 'baliayurvedaspa'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-04-29 13:13:06
