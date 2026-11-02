-- Database: apple_service_center
CREATE DATABASE IF NOT EXISTS `apple_service_center` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `apple_service_center`;

-- Users / Staff table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'technician', 'receptionist') DEFAULT 'receptionist',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default staff accounts (demo credentials for local setup)
INSERT INTO `users` (`username`, `password`, `full_name`, `role`) VALUES
('Supervisor', '$2y$10$kzTuC/szQkg5eV0ui41DUeFNat12.deXy9foUD3mgshN22AFrUh3y', 'System Supervisor', 'admin'),
('admin', '$2y$10$rLCIPJdXnS2pqTwG1PqhOOClwZk8Y9Q1o17N73PEAUwMyk6eKq96.', 'System Administrator', 'admin'),
('Staff', '$2y$10$vwgi2PrEhC37Pdxodav.puEmwdcLLXGgnmJ5OK5inisrwg3A5L3za', 'Service Staff', 'technician')
ON DUPLICATE KEY UPDATE
  `password`=VALUES(`password`),
  `full_name`=VALUES(`full_name`),
  `role`=VALUES(`role`);

-- Service Tickets / Queues table
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_number` VARCHAR(20) NOT NULL UNIQUE,
  `legacy_ticket_number` VARCHAR(20) DEFAULT NULL UNIQUE,
  `customer_name` VARCHAR(100) NOT NULL,
  `phone_number` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `address` TEXT NOT NULL,
  `brand` VARCHAR(50) DEFAULT 'Apple',
  `model` VARCHAR(100) NOT NULL,
  `serial_number` VARCHAR(100) NOT NULL,
  `imei` VARCHAR(100) DEFAULT NULL,
  `color` VARCHAR(50) DEFAULT NULL,
  `accessories` VARCHAR(255) DEFAULT NULL,
  `problem_description` TEXT NOT NULL,
  `warranty_status` ENUM('Pending assessment', 'Under Warranty', 'Not Warranty') DEFAULT 'Pending assessment',
  `priority` ENUM('Low', 'Normal', 'High', 'Urgent') DEFAULT 'Normal',
  `assigned_technician` VARCHAR(100) DEFAULT NULL,
  `estimated_cost` DECIMAL(10,2) DEFAULT NULL,
  `diagnosis` TEXT DEFAULT NULL,
  `repair_notes` TEXT DEFAULT NULL,
  `customer_signature` MEDIUMTEXT DEFAULT NULL,
  `repair_stage` ENUM('Received', 'Diagnosis', 'Parts order approved', 'Parts arrived', 'Ongoing repair', 'Ready for pickup') DEFAULT 'Received',
  `queue_status` ENUM('Waiting', 'Called', 'Serving', 'Completed', 'Skipped') DEFAULT 'Waiting',
  `queue_counter` VARCHAR(30) DEFAULT NULL,
  `queue_called_at` TIMESTAMP NULL DEFAULT NULL,
  `release_queue_joined_at` TIMESTAMP NULL DEFAULT NULL,
  `release_ticket_number` VARCHAR(20) DEFAULT NULL UNIQUE,
  `release_signature` MEDIUMTEXT DEFAULT NULL,
  `released_at` TIMESTAMP NULL DEFAULT NULL,
  `service_type` ENUM('new_service', 'for_release') DEFAULT 'new_service',
  `status` ENUM('Pending', 'In Diagnostics', 'Repairing', 'Ready for Release', 'Completed', 'Cancelled') DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Email Logs table
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `recipient_email` VARCHAR(100) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `status` ENUM('Sent', 'Failed') DEFAULT 'Sent',
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE
);
