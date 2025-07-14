CREATE DATABASE IF NOT EXISTS `4658325_attachment` CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `4658325_attachment`;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','student','hr','ict','registry') NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Students Table
CREATE TABLE IF NOT EXISTS `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `institution` varchar(100) NOT NULL,
  `course` varchar(100) NOT NULL,
  `year_of_study` varchar(50) NOT NULL,
  `side_hustle` text DEFAULT NULL,
  `preferred_department` enum('hr','ict','registry') NOT NULL,
  `application_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Applications Table
CREATE TABLE IF NOT EXISTS `applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `application_letter_path` varchar(255) DEFAULT NULL,
  `insurance_path` varchar(255) DEFAULT NULL,
  `cv_path` varchar(255) DEFAULT NULL,
  `introduction_letter_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','forwarded','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `forwarded_to` enum('hr','ict','registry') DEFAULT NULL,
  `hr_notes` text DEFAULT NULL,
  `ict_notes` text DEFAULT NULL,
  `registry_notes` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- System Settings Table
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_window_open` tinyint(1) NOT NULL DEFAULT 0,
  `hr_slots` int(11) NOT NULL DEFAULT 0,
  `ict_slots` int(11) NOT NULL DEFAULT 0,
  `registry_slots` int(11) NOT NULL DEFAULT 0,
  `hr_slots_remaining` int(11) NOT NULL DEFAULT 0,
  `ict_slots_remaining` int(11) NOT NULL DEFAULT 0,
  `registry_slots_remaining` int(11) NOT NULL DEFAULT 0,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `applications` 
ADD COLUMN IF NOT EXISTS `hr_notes` TEXT AFTER `forwarded_to`,
ADD COLUMN IF NOT EXISTS `ict_notes` TEXT AFTER `hr_notes`,
ADD COLUMN IF NOT EXISTS `registry_notes` TEXT AFTER `ict_notes`;