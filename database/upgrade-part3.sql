-- ============================================================
-- ELEGANCE SALON - PART 3 UPGRADE
-- USER / RECEPTIONIST DASHBOARD
-- Safe, idempotent ALTERs. Does not destroy existing data.
-- Compatible with MySQL 5.7+ / MariaDB 10.2+ (uses IF NOT EXISTS).
-- Run AFTER importing database/db-saloon.sql (PART 1 + PART 2).
-- ============================================================

USE `db-saloon`;

-- ------------------------------------------------------------------
-- 1. CLIENTS - extra profile fields used by the receptionist panel
-- ------------------------------------------------------------------
ALTER TABLE `clients`
    ADD COLUMN IF NOT EXISTS `dob` DATE NULL AFTER `phone`,
    ADD COLUMN IF NOT EXISTS `gender` ENUM('male','female','other') NULL AFTER `dob`,
    ADD COLUMN IF NOT EXISTS `address` VARCHAR(255) NULL AFTER `gender`,
    ADD COLUMN IF NOT EXISTS `preferred_staff_id` INT(10) UNSIGNED NULL AFTER `address`,
    ADD COLUMN IF NOT EXISTS `preferred_service_id` INT(10) UNSIGNED NULL AFTER `preferred_staff_id`,
    ADD COLUMN IF NOT EXISTS `last_visit` DATE NULL AFTER `loyalty_points`;

ALTER TABLE `clients`
    ADD INDEX IF NOT EXISTS `idx_clients_name` (`first_name`, `last_name`);

-- ------------------------------------------------------------------
-- 2. APPOINTMENTS - end_time enables robust overlap checking
-- ------------------------------------------------------------------
ALTER TABLE `appointments`
    ADD COLUMN IF NOT EXISTS `end_time` TIME NULL AFTER `appointment_time`;

-- Back-fill end_time for existing rows using each service duration.
UPDATE `appointments` a
JOIN `services` s ON s.service_id = a.service_id
SET a.end_time = TIMESTAMPADD(MINUTE, IFNULL(s.duration_minutes, 30), a.appointment_time)
WHERE a.end_time IS NULL;

-- ------------------------------------------------------------------
-- 3. PAYMENTS - extra methods + statuses used by receptionist
--    Keeps existing values so no data is destroyed.
-- ------------------------------------------------------------------
ALTER TABLE `payments`
    MODIFY COLUMN `payment_method` ENUM('cash','card','online','other','bank_transfer','jazzcash','easypaisa') NOT NULL DEFAULT 'cash',
    MODIFY COLUMN `payment_status` ENUM('pending','completed','failed','refunded','paid','partial') NOT NULL DEFAULT 'pending';

-- ------------------------------------------------------------------
-- 5. STAFF - working schedule shown on the receptionist stylists page
-- ------------------------------------------------------------------
ALTER TABLE `staff`
    ADD COLUMN IF NOT EXISTS `working_days` VARCHAR(120) NULL AFTER `commission_rate`,
    ADD COLUMN IF NOT EXISTS `working_hours` VARCHAR(80) NULL AFTER `working_days`;

-- Seed display values for existing stylists (safe UPDATE, admins may change later).
UPDATE `staff`
SET `working_days` = 'Mon - Sat', `working_hours` = '09:00 AM - 08:00 PM'
WHERE `working_days` IS NULL;

-- ------------------------------------------------------------------
-- 6. NOTIFICATIONS - wider titles/messages safe for reminders
--    (no structural change required on MariaDB; included for parity)
-- ------------------------------------------------------------------
-- Nothing required - notifications table is already compatible.