-- ============================================================
-- ELEGANCE SALON - Database Schema
-- Database: db-saloon
-- Version: 1.1.0  (PART 2 - Authentication & Admin Dashboard)
--
-- IMPORTANT:
--   This file creates the database from scratch for a NEW install.
--   If you already imported an earlier version, DO NOT re-run this
--   file. Instead run the ALTER TABLE statements in
--   database/upgrade-part2.sql to add the PART 2 columns safely.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- DATABASE
-- ============================================================
CREATE DATABASE IF NOT EXISTS `db-saloon` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db-saloon`;

DROP TABLE IF EXISTS `site_settings`;
DROP TABLE IF EXISTS `commissions`;
DROP TABLE IF EXISTS `invoices`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `feedback`;
DROP TABLE IF EXISTS `gallery`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `clients`;
DROP TABLE IF EXISTS `staff`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

-- ============================================================
-- ROLES
-- ============================================================
CREATE TABLE `roles` (
    `role_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_name` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`role_id`),
    UNIQUE KEY `uk_role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`role_name`, `description`) VALUES
('admin', 'System administrator with full access'),
('stylist', 'Salon stylist / service provider'),
('client', 'Registered client / customer'),
('receptionist', 'Receptionist / front desk user');

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE `users` (
    `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` INT UNSIGNED NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `avatar` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `remember_token` VARCHAR(255) DEFAULT NULL,
    `remember_expires` DATETIME DEFAULT NULL,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `uk_user_email` (`email`),
    UNIQUE KEY `uk_user_username` (`username`),
    UNIQUE KEY `uk_user_remember_token` (`remember_token`),
    KEY `fk_users_role` (`role_id`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default accounts (change these passwords after first login)
--   Admin        admin@elegancesalon.com       / admin123
--   Receptionist reception@elegancesalon.com   / reception123
--   Stylist      stylist@elegancesalon.com     / stylist123
--   Stylist 2    stylist2@elegancesalon.com    / stylist123
INSERT INTO `users` (`user_id`, `role_id`, `first_name`, `last_name`, `username`, `email`, `password`, `phone`, `is_active`) VALUES
(1, 1, 'Admin',       'User',    'admin',       'admin@elegancesalon.com',     '$2y$10$8IpCYA8w/phZUhocRDtTHuTbmuYslpRcKyI3Yi2rhUNnlH8pkYKBq', '1234567890',   1),
(2, 4, 'Reception',   'Desk',    'receptionist','reception@elegancesalon.com', '$2y$10$WuJBowubPDLFIlxlJFhT0OXelIKprboTyf8fPNT95Iy9VmI5797bu', '+15550002002', 1),
(3, 2, 'Ava',         'Mitchell','stylist',     'stylist@elegancesalon.com',   '$2y$10$nsJw.YyST5aZzLYZrx1AT.3rNSEpl8693Xo70tGcnYPEXfKoMyAu.', '+15550003003', 1),
(4, 2, 'Liam',        'Carter',  'stylist2',    'stylist2@elegancesalon.com',  '$2y$10$nsJw.YyST5aZzLYZrx1AT.3rNSEpl8693Xo70tGcnYPEXfKoMyAu.', '+15550004004', 1);

-- ============================================================
-- STAFF (Stylist profiles)
-- ============================================================
CREATE TABLE `staff` (
    `staff_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `specialty` VARCHAR(255) DEFAULT NULL,
    `bio` TEXT DEFAULT NULL,
    `experience_years` INT UNSIGNED DEFAULT 0,
    `commission_rate` DECIMAL(5,2) DEFAULT 0.00,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`staff_id`),
    KEY `fk_staff_user` (`user_id`),
    CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `staff` (`staff_id`, `user_id`, `specialty`, `bio`, `experience_years`, `commission_rate`, `is_available`) VALUES
(1, 3, 'Hair Coloring & Styling', 'Senior stylist specializing in balayage, color correction and modern cuts.', 8, 30.00, 1),
(2, 4, 'Bridal & Makeup', 'Makeup artist and bridal specialist with a passion for timeless looks.', 5, 25.00, 1);

-- ============================================================
-- SERVICES
-- ============================================================
CREATE TABLE `services` (
    `service_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `service_name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `duration_minutes` INT UNSIGNED DEFAULT 30,
    `category` VARCHAR(100) DEFAULT NULL,
    `image` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_popular` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`service_id`),
    KEY `idx_service_category` (`category`),
    KEY `idx_service_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `services` (`service_id`, `service_name`, `description`, `price`, `duration_minutes`, `category`, `is_popular`) VALUES
(1, 'Hair Styling', 'Professional hair styling including cuts, blow-dry, and updos tailored to your look.', 45.00, 45, 'Hair', 1),
(2, 'Hair Coloring', 'Expert coloring services including highlights, balayage, and full color transformations.', 85.00, 90, 'Hair', 0),
(3, 'Facial Treatment', 'Rejuvenating facial treatments customized for your skin type for a radiant glow.', 55.00, 60, 'Skin', 0),
(4, 'Manicure', 'Luxurious manicure services including shaping, cuticle care, and premium polish.', 30.00, 40, 'Nails', 0),
(5, 'Pedicure', 'Relaxing pedicure with exfoliation, massage, and flawless finish.', 35.00, 45, 'Nails', 0),
(6, 'Hair Treatment', 'Deep conditioning, keratin, and restorative treatments for healthy hair.', 65.00, 60, 'Hair', 1),
(7, 'Bridal Makeup', 'Complete bridal makeup package for your special day.', 200.00, 120, 'Makeup', 0),
(8, 'Hair Spa', 'Luxurious hair spa treatment for ultimate relaxation and hair health.', 50.00, 50, 'Hair', 0);

-- ============================================================
-- CLIENTS
-- ============================================================
CREATE TABLE `clients` (
    `client_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `loyalty_points` INT UNSIGNED DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`client_id`),
    KEY `fk_clients_user` (`user_id`),
    KEY `idx_client_email` (`email`),
    CONSTRAINT `fk_clients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `clients` (`client_id`, `user_id`, `first_name`, `last_name`, `email`, `phone`, `loyalty_points`) VALUES
(1, NULL, 'Sophia', 'Loren',  'sophia@example.com', '+15550001001', 120),
(2, NULL, 'Olivia', 'Brooks', 'olivia@example.com', '+15550001002', 60),
(3, NULL, 'Amelia', 'Clark',  'amelia@example.com', '+15550001003', 240);

-- ============================================================
-- APPOINTMENTS
-- ============================================================
CREATE TABLE `appointments` (
    `appointment_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id` INT UNSIGNED NOT NULL,
    `staff_id` INT UNSIGNED DEFAULT NULL,
    `service_id` INT UNSIGNED NOT NULL,
    `appointment_date` DATE NOT NULL,
    `appointment_time` TIME NOT NULL,
    `status` ENUM('pending','confirmed','in_progress','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
    `notes` TEXT DEFAULT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`appointment_id`),
    KEY `fk_appointments_client` (`client_id`),
    KEY `fk_appointments_staff` (`staff_id`),
    KEY `fk_appointments_service` (`service_id`),
    KEY `idx_appointment_date` (`appointment_date`),
    KEY `idx_appointment_status` (`status`),
    CONSTRAINT `fk_appointments_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_appointments_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_appointments_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample appointments. Dates are relative to CURDATE() so the dashboard
-- always has meaningful "today" and monthly revenue data on a fresh install.
INSERT INTO `appointments` (`appointment_id`, `client_id`, `staff_id`, `service_id`, `appointment_date`, `appointment_time`, `status`, `notes`, `total_amount`) VALUES
(1, 1, 1, 1, CURDATE(), '10:00:00', 'confirmed', 'Regular trim and blow-dry.', 45.00),
(2, 2, 2, 3, CURDATE(), '11:30:00', 'pending', 'First facial visit.', 55.00),
(3, 3, 1, 2, CURDATE(), '14:00:00', 'completed', 'Full color transformation.', 85.00),
(4, 1, 2, 6, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '13:00:00', 'confirmed', 'Keratin treatment.', 65.00),
(5, 2, 1, 7, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '09:30:00', 'pending', 'Bridal trial session.', 200.00),
(6, 3, 1, 4, DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '15:00:00', 'completed', NULL, 30.00),
(7, 1, 2, 5, DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '16:00:00', 'completed', NULL, 35.00),
(8, 2, 1, 6, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '11:00:00', 'completed', NULL, 65.00),
(9, 3, 2, 8, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), '12:00:00', 'completed', NULL, 50.00),
(10, 1, 1, 2, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '10:30:00', 'completed', NULL, 85.00),
(11, 3, 2, 3, DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '14:30:00', 'completed', NULL, 55.00),
(12, 2, 1, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '10:00:00', 'cancelled', 'Client rescheduled.', 45.00),
(13, 1, 2, 4, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '12:00:00', 'no_show', 'Client did not arrive.', 30.00);

-- ============================================================
-- GALLERY
-- ============================================================
CREATE TABLE `gallery` (
    `gallery_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `image_url` VARCHAR(500) NOT NULL,
    `category` VARCHAR(100) NOT NULL DEFAULT 'All',
    `sort_order` INT UNSIGNED DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`gallery_id`),
    KEY `idx_gallery_category` (`category`),
    KEY `idx_gallery_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FEEDBACK / REVIEWS
-- ============================================================
CREATE TABLE `feedback` (
    `feedback_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id` INT UNSIGNED NOT NULL,
    `service_id` INT UNSIGNED DEFAULT NULL,
    `rating` TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `comment` TEXT DEFAULT NULL,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `is_approved` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`feedback_id`),
    KEY `fk_feedback_client` (`client_id`),
    KEY `fk_feedback_service` (`service_id`),
    CONSTRAINT `fk_feedback_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_feedback_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `feedback` (`client_id`, `service_id`, `rating`, `comment`, `is_featured`, `is_approved`) VALUES
(1, 1, 5, 'Absolutely love my new haircut. The stylists are true artists!', 1, 1),
(3, 2, 5, 'The balayage turned out stunning. Highly recommended.', 1, 1),
(2, 3, 4, 'Relaxing facial and very professional staff.', 0, 1);

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE `notifications` (
    `notification_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`notification_id`),
    KEY `fk_notifications_user` (`user_id`),
    KEY `idx_notification_read` (`is_read`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `is_read`) VALUES
(NULL, 'Welcome to Elegance Salon', 'Your management portal is ready. Explore the dashboard to get started.', 'info', 0),
(NULL, 'Low stock reminder', 'Some inventory items have reached their reorder level.', 'warning', 0);

-- ============================================================
-- INVENTORY
-- ============================================================
CREATE TABLE `inventory` (
    `inventory_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `reorder_level` INT UNSIGNED DEFAULT 10,
    `supplier_id` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`inventory_id`),
    KEY `idx_inventory_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `inventory` (`item_name`, `description`, `category`, `quantity`, `unit_price`, `reorder_level`) VALUES
('Professional Shampoo', 'Salon-grade cleansing shampoo.', 'Hair Care', 4, 12.50, 10),
('Hair Color Developer', 'Volume 20 developer for color services.', 'Hair Care', 18, 8.00, 10),
('Nail Polish Set', 'Premium gel polish collection.', 'Nails', 7, 22.00, 10),
('Facial Cleanser', 'Gentle daily cleanser for treatments.', 'Skin Care', 25, 15.00, 10),
('Styling Gel', 'Strong-hold professional styling gel.', 'Hair Care', 12, 9.50, 10),
('Cotton Pads', 'Disposable cotton pads.', 'Supplies', 6, 4.00, 10);

-- ============================================================
-- SUPPLIERS
-- ============================================================
CREATE TABLE `suppliers` (
    `supplier_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_name` VARCHAR(255) NOT NULL,
    `contact_person` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `suppliers` (`company_name`, `contact_person`, `email`, `phone`, `address`) VALUES
('Lux Beauty Supplies', 'Daniel Reed', 'sales@luxbeauty.example', '+15550009001', '88 Trade Street, NY'),
('Glow Cosmetics Co.', 'Nina Park', 'orders@glowcosmetics.example', '+15550009002', '24 Market Road, NJ');

ALTER TABLE `inventory`
    ADD CONSTRAINT `fk_inventory_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================================
-- PAYMENTS
-- ============================================================
CREATE TABLE `payments` (
    `payment_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `appointment_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('cash','card','online','other') NOT NULL DEFAULT 'cash',
    `payment_status` ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    `transaction_ref` VARCHAR(255) DEFAULT NULL,
    `paid_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`payment_id`),
    KEY `fk_payments_appointment` (`appointment_id`),
    KEY `idx_payment_status` (`payment_status`),
    CONSTRAINT `fk_payments_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payments` (`appointment_id`, `amount`, `payment_method`, `payment_status`, `transaction_ref`, `paid_at`) VALUES
(1, 45.00, 'cash',   'pending',   NULL, NULL),
(2, 55.00, 'card',   'pending',   NULL, NULL),
(3, 85.00, 'card',   'completed', 'TXN-1003', DATE_ADD(CURDATE(), INTERVAL 0 DAY)),
(5, 200.00,'online', 'pending',   NULL, NULL),
(6, 30.00, 'cash',   'completed', 'TXN-1006', DATE_SUB(CURDATE(), INTERVAL 1 MONTH)),
(7, 35.00, 'cash',   'completed', 'TXN-1007', DATE_SUB(CURDATE(), INTERVAL 1 MONTH)),
(8, 65.00, 'online', 'completed', 'TXN-1008', DATE_SUB(CURDATE(), INTERVAL 2 MONTH)),
(9, 50.00, 'card',   'completed', 'TXN-1009', DATE_SUB(CURDATE(), INTERVAL 3 MONTH)),
(10, 85.00,'cash',   'completed', 'TXN-1010', DATE_SUB(CURDATE(), INTERVAL 4 MONTH)),
(11, 55.00,'card',   'completed', 'TXN-1011', DATE_SUB(CURDATE(), INTERVAL 5 MONTH));

-- ============================================================
-- INVOICES
-- ============================================================
CREATE TABLE `invoices` (
    `invoice_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(50) NOT NULL,
    `client_id` INT UNSIGNED NOT NULL,
    `appointment_id` INT UNSIGNED DEFAULT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    `issued_at` DATE DEFAULT NULL,
    `due_at` DATE DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`invoice_id`),
    UNIQUE KEY `uk_invoice_number` (`invoice_number`),
    KEY `fk_invoices_client` (`client_id`),
    KEY `fk_invoices_appointment` (`appointment_id`),
    KEY `idx_invoice_status` (`status`),
    CONSTRAINT `fk_invoices_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_invoices_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `invoices` (`invoice_number`, `client_id`, `appointment_id`, `subtotal`, `tax_rate`, `tax_amount`, `discount`, `total`, `status`, `issued_at`, `due_at`) VALUES
('INV-1003', 3, 3, 85.00, 0.00, 0.00, 0.00, 85.00, 'paid', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
('INV-1006', 3, 6, 30.00, 0.00, 0.00, 0.00, 30.00, 'paid', DATE_SUB(CURDATE(), INTERVAL 1 MONTH), DATE_SUB(CURDATE(), INTERVAL 24 DAY)),
('INV-1007', 1, 7, 35.00, 0.00, 0.00, 0.00, 35.00, 'paid', DATE_SUB(CURDATE(), INTERVAL 1 MONTH), DATE_SUB(CURDATE(), INTERVAL 24 DAY));

-- ============================================================
-- COMMISSIONS
-- ============================================================
CREATE TABLE `commissions` (
    `commission_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id` INT UNSIGNED NOT NULL,
    `appointment_id` INT UNSIGNED NOT NULL,
    `service_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `commission_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending','approved','paid') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`commission_id`),
    KEY `fk_commissions_staff` (`staff_id`),
    KEY `fk_commissions_appointment` (`appointment_id`),
    CONSTRAINT `fk_commissions_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_commissions_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `commissions` (`staff_id`, `appointment_id`, `service_amount`, `commission_rate`, `commission_amount`, `status`) VALUES
(1, 3, 85.00, 30.00, 25.50, 'pending'),
(2, 7, 35.00, 25.00, 8.75,  'approved'),
(1, 8, 65.00, 30.00, 19.50, 'paid');

-- ============================================================
-- SITE SETTINGS
-- ============================================================
CREATE TABLE `site_settings` (
    `setting_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_id`),
    UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('salon_name', 'Elegance Salon'),
('salon_email', 'info@elegancesalon.com'),
('salon_phone', '+1 (555) 123-4567'),
('salon_address', '123 Luxury Avenue, Beauty District, NY 10001'),
('opening_hours', 'Mon-Sat: 9:00 AM - 8:00 PM | Sun: 10:00 AM - 6:00 PM'),
('facebook_url', '#'),
('instagram_url', '#'),
('whatsapp_number', '15551234567');

COMMIT;
