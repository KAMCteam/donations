-- ---------------------------------------------------------------------------
-- donations — full schema for the transplant platform
--
-- Generated from the CodeIgniter migrations in app/Database/Migrations, which
-- are the source of truth. This file is that same schema as one importable
-- dump, for phpMyAdmin or the mysql client:
--
--     mysql -u <user> -p <database> < app/Database/schema/donations_schema.sql
--
-- The equivalent from the project root is:
--
--     php spark migrate
--     php spark db:seed DatabaseSeeder
--
-- Either way you end up in the same place: the `migrations` rows are included
-- below, so importing this file and then running `php spark migrate` is a
-- no-op rather than an attempt to create everything twice.
--
-- Contents: structure, the organ programmes and the lab catalogue, and nothing
-- else. No patients, no staff accounts, no physicians or coordinators — those
-- are yours to enter.
--
-- Regenerate with (from the project root, against a freshly migrated db):
--     php spark migrate && php spark db:seed DatabaseSeeder
--     bash app/Database/schema/regenerate.sh
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `coordinators` (
  `coordinator_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `coordinator_name` varchar(150) NOT NULL,
  PRIMARY KEY (`coordinator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `dashboard_stats` AS SELECT
 1 AS `organ`,
  1 AS `program_label`,
  1 AS `total_recipients`,
  1 AS `unmatched_recipients`,
  1 AS `total_donors`,
  1 AS `unmatched_donors`,
  1 AS `total_pairs`,
  1 AS `active_or_scheduled_pairs` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `donors` AS SELECT
 1 AS `mrn`,
  1 AS `name`,
  1 AS `city`,
  1 AS `phone_number`,
  1 AS `gender`,
  1 AS `age`,
  1 AS `blood_group`,
  1 AS `organs`,
  1 AS `type`,
  1 AS `status`,
  1 AS `urgency`,
  1 AS `mrp_id`,
  1 AS `coordinator_id`,
  1 AS `hospital`,
  1 AS `diagnosis`,
  1 AS `donation_type`,
  1 AS `relationship`,
  1 AS `dialysis`,
  1 AS `entry_date`,
  1 AS `note`,
  1 AS `created_at`,
  1 AS `updated_at`,
  1 AS `is_urgent`,
  1 AS `urgency_rank`,
  1 AS `program_label`,
  1 AS `program_description`,
  1 AS `mrp_name`,
  1 AS `mrp_code`,
  1 AS `coordinator_name`,
  1 AS `labs_completed`,
  1 AS `labs_total`,
  1 AS `is_matched` */;
SET character_set_client = @saved_cs_client;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lab_parents` (
  `parent_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_name` varchar(100) NOT NULL,
  PRIMARY KEY (`parent_id`),
  UNIQUE KEY `parent_name` (`parent_name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lab_results` (
  `result_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) unsigned NOT NULL,
  `lab_id` int(11) unsigned NOT NULL,
  `status` enum('pending','completed','flagged') NOT NULL DEFAULT 'pending',
  `result` varchar(255) DEFAULT NULL,
  `result_date` date DEFAULT NULL,
  `lab_comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`result_id`),
  UNIQUE KEY `patient_id_lab_id` (`patient_id`,`lab_id`),
  KEY `lab_results_lab_id_foreign` (`lab_id`),
  KEY `status` (`status`),
  CONSTRAINT `lab_results_lab_id_foreign` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`lab_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `lab_results_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`mrn`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `labs` (
  `lab_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `lab_name` varchar(150) NOT NULL,
  `result_shape` varchar(100) DEFAULT NULL,
  `lab_parent_id` int(11) unsigned DEFAULT NULL,
  `patient_type` enum('recipient','donor','both') NOT NULL DEFAULT 'both',
  `organ_type` enum('kidney','liver') NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`lab_id`),
  KEY `labs_lab_parent_id_foreign` (`lab_parent_id`),
  KEY `organ_type_patient_type` (`organ_type`,`patient_type`),
  CONSTRAINT `labs_lab_parent_id_foreign` FOREIGN KEY (`lab_parent_id`) REFERENCES `lab_parents` (`parent_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mrp` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `mrp_id` varchar(20) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mrp_id` (`mrp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `organ_programs` (
  `code` varchar(30) NOT NULL,
  `label` varchar(60) NOT NULL,
  `description` varchar(150) NOT NULL,
  `icon` varchar(60) DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pairs` (
  `pair_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `recipient_mrn` int(11) unsigned NOT NULL,
  `donor_mrn` int(11) unsigned NOT NULL,
  `programs` enum('R_LRD','R_LURD','R_DD','R_PE','D_D') DEFAULT NULL,
  `match_status` enum('pending','confirmed','active','scheduled','on_hold','completed','closed','paired_exchange') NOT NULL DEFAULT 'pending',
  `relationship` varchar(150) DEFAULT NULL,
  `matched_on` date DEFAULT NULL,
  `surgery_on` date DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`pair_id`),
  KEY `recipient_mrn` (`recipient_mrn`),
  KEY `donor_mrn` (`donor_mrn`),
  KEY `match_status` (`match_status`),
  CONSTRAINT `pairs_donor_mrn_foreign` FOREIGN KEY (`donor_mrn`) REFERENCES `patients` (`mrn`) ON UPDATE CASCADE,
  CONSTRAINT `pairs_recipient_mrn_foreign` FOREIGN KEY (`recipient_mrn`) REFERENCES `patients` (`mrn`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `pairs_overview` AS SELECT
 1 AS `pair_id`,
  1 AS `match_status`,
  1 AS `programs`,
  1 AS `relationship`,
  1 AS `matched_on`,
  1 AS `surgery_on`,
  1 AS `note`,
  1 AS `created_at`,
  1 AS `organ`,
  1 AS `r_mrn`,
  1 AS `r_name`,
  1 AS `r_age`,
  1 AS `r_gender`,
  1 AS `r_blood_group`,
  1 AS `r_phone_number`,
  1 AS `r_dialysis`,
  1 AS `r_entry_date`,
  1 AS `r_urgency`,
  1 AS `r_mrp_name`,
  1 AS `r_labs_completed`,
  1 AS `r_labs_total`,
  1 AS `d_mrn`,
  1 AS `d_name`,
  1 AS `d_age`,
  1 AS `d_gender`,
  1 AS `d_blood_group`,
  1 AS `d_phone_number`,
  1 AS `d_donation_type`,
  1 AS `d_mrp_name`,
  1 AS `d_labs_completed`,
  1 AS `d_labs_total` */;
SET character_set_client = @saved_cs_client;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `patients` (
  `mrn` int(11) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `phone_number` varchar(30) DEFAULT NULL,
  `gender` enum('M','F') DEFAULT NULL,
  `age` tinyint(3) unsigned DEFAULT NULL,
  `blood_group` enum('A','B','AB','O') NOT NULL,
  `organs` enum('kidney','liver') NOT NULL,
  `type` enum('recipient','donor') NOT NULL,
  `status` enum('pending','ready','active','on_hold','declined','completed','cancelled') NOT NULL DEFAULT 'pending',
  `urgency` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `mrp_id` int(11) unsigned DEFAULT NULL,
  `coordinator_id` int(11) unsigned DEFAULT NULL,
  `hospital` varchar(150) DEFAULT NULL,
  `diagnosis` varchar(255) DEFAULT NULL,
  `donation_type` enum('living','deceased') DEFAULT NULL,
  `relationship` varchar(150) DEFAULT NULL,
  `dialysis` date DEFAULT NULL,
  `entry_date` date NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `is_urgent` tinyint(1) GENERATED ALWAYS AS (`urgency` in ('critical','high')) STORED COMMENT 'The old 0/1 urgency, derived from the four-step one',
  `urgency_rank` tinyint(1) GENERATED ALWAYS AS (field(`urgency`,'critical','high','medium','low')) STORED COMMENT '1 = critical .. 4 = low; ORDER BY this ASC for most-urgent-first',
  PRIMARY KEY (`mrn`),
  KEY `patients_mrp_id_foreign` (`mrp_id`),
  KEY `patients_coordinator_id_foreign` (`coordinator_id`),
  KEY `organs_type` (`organs`,`type`),
  KEY `blood_group` (`blood_group`),
  KEY `status` (`status`),
  KEY `patients_urgency_rank` (`urgency_rank`),
  CONSTRAINT `patients_coordinator_id_foreign` FOREIGN KEY (`coordinator_id`) REFERENCES `coordinators` (`coordinator_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `patients_mrp_id_foreign` FOREIGN KEY (`mrp_id`) REFERENCES `mrp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `recipients` AS SELECT
 1 AS `mrn`,
  1 AS `name`,
  1 AS `city`,
  1 AS `phone_number`,
  1 AS `gender`,
  1 AS `age`,
  1 AS `blood_group`,
  1 AS `organs`,
  1 AS `type`,
  1 AS `status`,
  1 AS `urgency`,
  1 AS `mrp_id`,
  1 AS `coordinator_id`,
  1 AS `hospital`,
  1 AS `diagnosis`,
  1 AS `donation_type`,
  1 AS `relationship`,
  1 AS `dialysis`,
  1 AS `entry_date`,
  1 AS `note`,
  1 AS `created_at`,
  1 AS `updated_at`,
  1 AS `is_urgent`,
  1 AS `urgency_rank`,
  1 AS `program_label`,
  1 AS `program_description`,
  1 AS `mrp_name`,
  1 AS `mrp_code`,
  1 AS `coordinator_name`,
  1 AS `labs_completed`,
  1 AS `labs_total`,
  1 AS `is_matched` */;
SET character_set_client = @saved_cs_client;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('coordinator','physician','admin') NOT NULL DEFAULT 'coordinator',
  `mrp_id` int(11) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_id` (`staff_id`),
  KEY `staff_mrp_id_foreign` (`mrp_id`),
  CONSTRAINT `staff_mrp_id_foreign` FOREIGN KEY (`mrp_id`) REFERENCES `mrp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `waiting_list` AS SELECT
 1 AS `mrn`,
  1 AS `name`,
  1 AS `city`,
  1 AS `phone_number`,
  1 AS `gender`,
  1 AS `age`,
  1 AS `blood_group`,
  1 AS `organs`,
  1 AS `type`,
  1 AS `status`,
  1 AS `urgency`,
  1 AS `mrp_id`,
  1 AS `coordinator_id`,
  1 AS `hospital`,
  1 AS `diagnosis`,
  1 AS `donation_type`,
  1 AS `relationship`,
  1 AS `dialysis`,
  1 AS `entry_date`,
  1 AS `note`,
  1 AS `created_at`,
  1 AS `updated_at`,
  1 AS `is_urgent`,
  1 AS `urgency_rank`,
  1 AS `mrp_name`,
  1 AS `mrp_code`,
  1 AS `coordinator_name`,
  1 AS `score`,
  1 AS `score_entry_only`,
  1 AS `labs_completed`,
  1 AS `labs_total` */;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `dashboard_stats`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`donations`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `dashboard_stats` AS select `op`.`code` AS `organ`,`op`.`label` AS `program_label`,(select count(0) from `patients` `p` where `p`.`organs` = `op`.`code` and `p`.`type` = 'recipient') AS `total_recipients`,(select count(0) from `patients` `p` where `p`.`organs` = `op`.`code` and `p`.`type` = 'recipient' and !exists(select 1 from `pairs` `pr` where `pr`.`recipient_mrn` = `p`.`mrn` and `pr`.`match_status` <> 'closed' limit 1)) AS `unmatched_recipients`,(select count(0) from `patients` `p` where `p`.`organs` = `op`.`code` and `p`.`type` = 'donor') AS `total_donors`,(select count(0) from `patients` `p` where `p`.`organs` = `op`.`code` and `p`.`type` = 'donor' and !exists(select 1 from `pairs` `pr` where `pr`.`donor_mrn` = `p`.`mrn` and `pr`.`match_status` <> 'closed' limit 1)) AS `unmatched_donors`,(select count(0) from (`pairs` `pr` join `patients` `p` on(`p`.`mrn` = `pr`.`recipient_mrn`)) where `p`.`organs` = `op`.`code`) AS `total_pairs`,(select count(0) from (`pairs` `pr` join `patients` `p` on(`p`.`mrn` = `pr`.`recipient_mrn`)) where `p`.`organs` = `op`.`code` and `pr`.`match_status` in ('active','scheduled')) AS `active_or_scheduled_pairs` from `organ_programs` `op` where `op`.`is_active` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `donors`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`donations`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `donors` AS select `p`.`mrn` AS `mrn`,`p`.`name` AS `name`,`p`.`city` AS `city`,`p`.`phone_number` AS `phone_number`,`p`.`gender` AS `gender`,`p`.`age` AS `age`,`p`.`blood_group` AS `blood_group`,`p`.`organs` AS `organs`,`p`.`type` AS `type`,`p`.`status` AS `status`,`p`.`urgency` AS `urgency`,`p`.`mrp_id` AS `mrp_id`,`p`.`coordinator_id` AS `coordinator_id`,`p`.`hospital` AS `hospital`,`p`.`diagnosis` AS `diagnosis`,`p`.`donation_type` AS `donation_type`,`p`.`relationship` AS `relationship`,`p`.`dialysis` AS `dialysis`,`p`.`entry_date` AS `entry_date`,`p`.`note` AS `note`,`p`.`created_at` AS `created_at`,`p`.`updated_at` AS `updated_at`,`p`.`is_urgent` AS `is_urgent`,`p`.`urgency_rank` AS `urgency_rank`,`op`.`label` AS `program_label`,`op`.`description` AS `program_description`,`m`.`name` AS `mrp_name`,`m`.`mrp_id` AS `mrp_code`,`c`.`coordinator_name` AS `coordinator_name`,(select count(0) from `lab_results` `lr` where `lr`.`patient_id` = `p`.`mrn` and `lr`.`status` = 'completed') AS `labs_completed`,(select count(0) from `lab_results` `lr` where `lr`.`patient_id` = `p`.`mrn`) AS `labs_total`,exists(select 1 from `pairs` `pr` where `pr`.`donor_mrn` = `p`.`mrn` and `pr`.`match_status` <> 'closed' limit 1) AS `is_matched` from (((`patients` `p` left join `organ_programs` `op` on(`op`.`code` = `p`.`organs`)) left join `mrp` `m` on(`m`.`id` = `p`.`mrp_id`)) left join `coordinators` `c` on(`c`.`coordinator_id` = `p`.`coordinator_id`)) where `p`.`type` = 'donor' */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `pairs_overview`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`donations`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `pairs_overview` AS select `pr`.`pair_id` AS `pair_id`,`pr`.`match_status` AS `match_status`,`pr`.`programs` AS `programs`,`pr`.`relationship` AS `relationship`,`pr`.`matched_on` AS `matched_on`,`pr`.`surgery_on` AS `surgery_on`,`pr`.`note` AS `note`,`pr`.`created_at` AS `created_at`,`r`.`organs` AS `organ`,`r`.`mrn` AS `r_mrn`,`r`.`name` AS `r_name`,`r`.`age` AS `r_age`,`r`.`gender` AS `r_gender`,`r`.`blood_group` AS `r_blood_group`,`r`.`phone_number` AS `r_phone_number`,`r`.`dialysis` AS `r_dialysis`,`r`.`entry_date` AS `r_entry_date`,`r`.`urgency` AS `r_urgency`,`rm`.`name` AS `r_mrp_name`,(select count(0) from `lab_results` `lr` where `lr`.`patient_id` = `r`.`mrn` and `lr`.`status` = 'completed') AS `r_labs_completed`,(select count(0) from `lab_results` `lr` where `lr`.`patient_id` = `r`.`mrn`) AS `r_labs_total`,`d`.`mrn` AS `d_mrn`,`d`.`name` AS `d_name`,`d`.`age` AS `d_age`,`d`.`gender` AS `d_gender`,`d`.`blood_group` AS `d_blood_group`,`d`.`phone_number` AS `d_phone_number`,`d`.`donation_type` AS `d_donation_type`,`dm`.`name` AS `d_mrp_name`,(select count(0) from `lab_results` `lr` where `lr`.`patient_id` = `d`.`mrn` and `lr`.`status` = 'completed') AS `d_labs_completed`,(select count(0) from `lab_results` `lr` where `lr`.`patient_id` = `d`.`mrn`) AS `d_labs_total` from ((((`pairs` `pr` join `patients` `r` on(`r`.`mrn` = `pr`.`recipient_mrn`)) join `patients` `d` on(`d`.`mrn` = `pr`.`donor_mrn`)) left join `mrp` `rm` on(`rm`.`id` = `r`.`mrp_id`)) left join `mrp` `dm` on(`dm`.`id` = `d`.`mrp_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `recipients`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`donations`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `recipients` AS select `p`.`mrn` AS `mrn`,`p`.`name` AS `name`,`p`.`city` AS `city`,`p`.`phone_number` AS `phone_number`,`p`.`gender` AS `gender`,`p`.`age` AS `age`,`p`.`blood_group` AS `blood_group`,`p`.`organs` AS `organs`,`p`.`type` AS `type`,`p`.`status` AS `status`,`p`.`urgency` AS `urgency`,`p`.`mrp_id` AS `mrp_id`,`p`.`coordinator_id` AS `coordinator_id`,`p`.`hospital` AS `hospital`,`p`.`diagnosis` AS `diagnosis`,`p`.`donation_type` AS `donation_type`,`p`.`relationship` AS `relationship`,`p`.`dialysis` AS `dialysis`,`p`.`entry_date` AS `entry_date`,`p`.`note` AS `note`,`p`.`created_at` AS `created_at`,`p`.`updated_at` AS `updated_at`,`p`.`is_urgent` AS `is_urgent`,`p`.`urgency_rank` AS `urgency_rank`,`op`.`label` AS `program_label`,`op`.`description` AS `program_description`,`m`.`name` AS `mrp_name`,`m`.`mrp_id` AS `mrp_code`,`c`.`coordinator_name` AS `coordinator_name`,(select count(0) from `lab_results` `lr` where `lr`.`patient_id` = `p`.`mrn` and `lr`.`status` = 'completed') AS `labs_completed`,(select count(0) from `lab_results` `lr` where `lr`.`patient_id` = `p`.`mrn`) AS `labs_total`,exists(select 1 from `pairs` `pr` where `pr`.`recipient_mrn` = `p`.`mrn` and `pr`.`match_status` <> 'closed' limit 1) AS `is_matched` from (((`patients` `p` left join `organ_programs` `op` on(`op`.`code` = `p`.`organs`)) left join `mrp` `m` on(`m`.`id` = `p`.`mrp_id`)) left join `coordinators` `c` on(`c`.`coordinator_id` = `p`.`coordinator_id`)) where `p`.`type` = 'recipient' */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `waiting_list`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`donations`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `waiting_list` AS select `p`.`mrn` AS `mrn`,`p`.`name` AS `name`,`p`.`city` AS `city`,`p`.`phone_number` AS `phone_number`,`p`.`gender` AS `gender`,`p`.`age` AS `age`,`p`.`blood_group` AS `blood_group`,`p`.`organs` AS `organs`,`p`.`type` AS `type`,`p`.`status` AS `status`,`p`.`urgency` AS `urgency`,`p`.`mrp_id` AS `mrp_id`,`p`.`coordinator_id` AS `coordinator_id`,`p`.`hospital` AS `hospital`,`p`.`diagnosis` AS `diagnosis`,`p`.`donation_type` AS `donation_type`,`p`.`relationship` AS `relationship`,`p`.`dialysis` AS `dialysis`,`p`.`entry_date` AS `entry_date`,`p`.`note` AS `note`,`p`.`created_at` AS `created_at`,`p`.`updated_at` AS `updated_at`,`p`.`is_urgent` AS `is_urgent`,`p`.`urgency_rank` AS `urgency_rank`,`m`.`name` AS `mrp_name`,`m`.`mrp_id` AS `mrp_code`,`c`.`coordinator_name` AS `coordinator_name`,0.1 * timestampdiff(MONTH,`p`.`entry_date`,curdate()) + 0.1 * timestampdiff(MONTH,`p`.`dialysis`,curdate()) AS `score`,0.1 * timestampdiff(MONTH,`p`.`entry_date`,curdate()) AS `score_entry_only`,(select count(0) from `lab_results` `lr` where `lr`.`patient_id` = `p`.`mrn` and `lr`.`status` = 'completed') AS `labs_completed`,(select count(0) from `lab_results` `lr` where `lr`.`patient_id` = `p`.`mrn`) AS `labs_total` from ((`patients` `p` left join `mrp` `m` on(`m`.`id` = `p`.`mrp_id`)) left join `coordinators` `c` on(`c`.`coordinator_id` = `p`.`coordinator_id`)) where `p`.`type` = 'recipient' and !(`p`.`mrn` in (select `pairs`.`recipient_mrn` from `pairs` where `pairs`.`match_status` <> 'closed')) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- ---------------------------------------------------------------------------
-- Reference data: the lab catalogue (LabCatalogueSeeder), and the migration
-- log so the framework knows this schema is already at the latest version.
-- ---------------------------------------------------------------------------

/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `organ_programs` WRITE;
/*!40000 ALTER TABLE `organ_programs` DISABLE KEYS */;
INSERT INTO `organ_programs` (`code`, `label`, `description`, `icon`, `sort_order`, `is_active`) VALUES ('kidney','Kidney','Renal transplant program','kidney.svg',1,1),
('liver','Liver','Hepatic transplant program','liver.svg',2,1);
/*!40000 ALTER TABLE `organ_programs` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `lab_parents` WRITE;
/*!40000 ALTER TABLE `lab_parents` DISABLE KEYS */;
INSERT INTO `lab_parents` (`parent_id`, `parent_name`) VALUES (8,'Cardiac'),
(2,'Hepatic Function'),
(7,'Histopathology'),
(6,'Imaging'),
(4,'Immunology'),
(9,'Psychosocial'),
(1,'Renal Function'),
(3,'Scoring'),
(5,'Virology');
/*!40000 ALTER TABLE `lab_parents` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `labs` WRITE;
/*!40000 ALTER TABLE `labs` DISABLE KEYS */;
INSERT INTO `labs` (`lab_id`, `lab_name`, `result_shape`, `lab_parent_id`, `patient_type`, `organ_type`, `sort_order`, `is_active`) VALUES (1,'eGFR / Creatinine',NULL,1,'both','kidney',1,1),
(2,'LFTs (ALT / AST / Bilirubin)',NULL,2,'both','liver',2,1),
(3,'INR / Coagulation',NULL,2,'both','liver',3,1),
(4,'MELD Score',NULL,3,'recipient','liver',4,1),
(5,'Crossmatch','PO/NE',4,'both','kidney',5,1),
(6,'HLA Typing',NULL,4,'both','kidney',6,1),
(7,'Crossmatch','PO/NE',4,'both','liver',7,1),
(8,'Virology Panel (HIV, HBV, HCV)','PO/NE',5,'both','kidney',8,1),
(9,'Virology Panel (HIV, HBV, HCV)','PO/NE',5,'both','liver',9,1),
(10,'Renal Ultrasound',NULL,6,'both','kidney',10,1),
(11,'Renal CT Angiogram',NULL,6,'donor','kidney',11,1),
(12,'Liver CT / MRI',NULL,6,'both','liver',12,1),
(13,'Liver Volumetry (CT)',NULL,6,'donor','liver',13,1),
(14,'Liver Biopsy',NULL,7,'donor','liver',14,1),
(15,'Cardiac Clearance','C/NC',8,'both','kidney',15,1),
(16,'Cardiac Clearance','C/NC',8,'both','liver',16,1),
(17,'Psychiatric Evaluation','C/NC',9,'donor','kidney',17,1),
(18,'Psychiatric Evaluation','C/NC',9,'donor','liver',18,1);
/*!40000 ALTER TABLE `labs` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` (`id`, `version`, `class`, `group`, `namespace`, `time`, `batch`) VALUES (9,'2026-09-17-000100','App\\Database\\Migrations\\CreateReferenceTables','default','App',1789635714,1),
(10,'2026-09-17-000200','App\\Database\\Migrations\\CreatePatients','default','App',1789635714,1),
(11,'2026-09-17-000300','App\\Database\\Migrations\\CreatePairs','default','App',1789635714,1),
(12,'2026-09-17-000400','App\\Database\\Migrations\\CreateLabResults','default','App',1789635714,1),
(13,'2026-09-17-000500','App\\Database\\Migrations\\CreateStaff','default','App',1789635714,1),
(14,'2026-09-17-000600','App\\Database\\Migrations\\CreateWaitingListView','default','App',1789635714,1),
(15,'2026-09-17-000700','App\\Database\\Migrations\\CreateOrganPrograms','default','App',1789635714,1),
(16,'2026-09-17-000800','App\\Database\\Migrations\\CreateScreenViews','default','App',1789635714,1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


SET FOREIGN_KEY_CHECKS = 1;
