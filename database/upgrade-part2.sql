-- ============================================================
-- ELEGANCE SALON - PART 2 Database Upgrade
-- Database: db-saloon
--
-- Run this ONLY if you imported an EARLIER version of db-saloon.sql
-- that is missing the PART 2 authentication columns / roles.
--
-- A fresh install should import database/db-saloon.sql instead.
-- These statements are safe for existing data (no DROP / no data loss).
--
-- Tested against MariaDB shipped with XAMPP 8.x.
-- ============================================================

USE `db-saloon`;

-- ------------------------------------------------------------
-- 1. Add the receptionist role (safe to re-run)
-- ------------------------------------------------------------
INSERT IGNORE INTO `roles` (`role_name`, `description`)
VALUES ('receptionist', 'Receptionist / front desk user');

-- ------------------------------------------------------------
-- 2. Authentication columns on `users`
--    (username, remember-me token support)
-- ------------------------------------------------------------
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `username` VARCHAR(50) DEFAULT NULL AFTER `last_name`,
    ADD COLUMN IF NOT EXISTS `remember_token` VARCHAR(255) DEFAULT NULL AFTER `is_active`,
    ADD COLUMN IF NOT EXISTS `remember_expires` DATETIME DEFAULT NULL AFTER `remember_token`;

-- Unique indexes (skip these if the index already exists).
ALTER TABLE `users` ADD UNIQUE KEY `uk_user_username` (`username`);
ALTER TABLE `users` ADD UNIQUE KEY `uk_user_remember_token` (`remember_token`);

-- ------------------------------------------------------------
-- 3. Back-fill usernames for accounts created before PART 2
--    (only where username is still NULL)
-- ------------------------------------------------------------
UPDATE `users`
SET `username` = LOWER(CONCAT(
        SUBSTRING_INDEX(`email`, '@', 1),
        CASE WHEN `user_id` > 1 THEN `user_id` ELSE '' END
    ))
WHERE `username` IS NULL OR `username` = '';

-- ------------------------------------------------------------
-- 4. Reset the default admin password to a known value
--    Password below = admin123
-- ------------------------------------------------------------
UPDATE `users`
SET `password` = '$2y$10$8IpCYA8w/phZUhocRDtTHuTbmuYslpRcKyI3Yi2rhUNnlH8pkYKBq'
WHERE `email` = 'admin@elegancesalon.com';
