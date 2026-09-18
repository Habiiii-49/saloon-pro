-- ============================================================
-- ELEGANCE SALON - PART 5 UPGRADE
-- INVENTORY MANAGEMENT + SUPPLIERS + PURCHASE ORDERS
-- Safe, idempotent statements. Does not destroy existing data.
-- Compatible with MariaDB 10.4+ / MySQL 5.7+ (IF NOT EXISTS).
-- Run AFTER importing database/db-saloon.sql and any earlier
-- upgrade-*.sql files.
-- ============================================================

USE `db-saloon`;

-- ------------------------------------------------------------------
-- 1. INVENTORY CATEGORIES (new table)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_inventory_category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `inventory_categories` (`name`, `description`) VALUES
('Hair Care', 'Shampoos, conditioners, treatments and styling products'),
('Hair Color', 'Hair dyes, developers and color accessories'),
('Skin Care', 'Cleansers, moisturizers and skincare products'),
('Makeup', 'Cosmetic and makeup products'),
('Nails', 'Nail polishes and nail care products'),
('Facial', 'Facial masks, oils and treatment products'),
('Bridal', 'Bridal and special occasion products'),
('Spa', 'Spa therapy and relaxation products'),
('Cleaning', 'Salon sanitization and cleaning supplies'),
('Equipment', 'Salon tools and equipment'),
('Other', 'Miscellaneous salon products')
ON DUPLICATE KEY UPDATE `name` = `name`;

-- ------------------------------------------------------------------
-- 2. SUPPLIERS - extra profile fields
-- ------------------------------------------------------------------
ALTER TABLE `suppliers`
    ADD COLUMN IF NOT EXISTS `supplier_name` VARCHAR(255) NULL AFTER `company_name`,
    ADD COLUMN IF NOT EXISTS `city` VARCHAR(100) NULL AFTER `address`,
    ADD COLUMN IF NOT EXISTS `business_id` VARCHAR(100) NULL AFTER `city`,
    ADD COLUMN IF NOT EXISTS `notes` TEXT NULL AFTER `business_id`,
    ADD COLUMN IF NOT EXISTS `status` ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER `notes`;

UPDATE `suppliers` SET `supplier_name` = `company_name` WHERE `supplier_name` IS NULL OR `supplier_name` = '';

-- ------------------------------------------------------------------
-- 3. INVENTORY - full product management columns
-- ------------------------------------------------------------------
ALTER TABLE `inventory`
    ADD COLUMN IF NOT EXISTS `sku` VARCHAR(64) NULL AFTER `item_name`,
    ADD COLUMN IF NOT EXISTS `barcode` VARCHAR(64) NULL AFTER `sku`,
    ADD COLUMN IF NOT EXISTS `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `unit_price`,
    ADD COLUMN IF NOT EXISTS `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `cost_price`,
    ADD COLUMN IF NOT EXISTS `minimum_stock` INT(10) UNSIGNED NOT NULL DEFAULT 10 AFTER `quantity`,
    ADD COLUMN IF NOT EXISTS `maximum_stock` INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `minimum_stock`,
    ADD COLUMN IF NOT EXISTS `unit` VARCHAR(20) NOT NULL DEFAULT 'Piece' AFTER `maximum_stock`,
    ADD COLUMN IF NOT EXISTS `expiry_date` DATE NULL AFTER `unit`,
    ADD COLUMN IF NOT EXISTS `status` ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER `expiry_date`,
    ADD COLUMN IF NOT EXISTS `category_id` INT(10) UNSIGNED NULL AFTER `category`;

-- Back-fill from existing values (never overwrites meaningful data).
UPDATE `inventory` SET `cost_price` = `unit_price` WHERE `cost_price` = 0;
UPDATE `inventory` SET `minimum_stock` = COALESCE(`reorder_level`, 10) WHERE `minimum_stock` = 10;

ALTER TABLE `inventory`
    ADD INDEX IF NOT EXISTS `idx_inventory_name` (`item_name`),
    ADD INDEX IF NOT EXISTS `idx_inventory_status` (`status`),
    ADD INDEX IF NOT EXISTS `idx_inventory_min_stock` (`minimum_stock`),
    ADD UNIQUE INDEX IF NOT EXISTS `uk_inventory_sku` (`sku`);

-- Link category_id -> inventory_categories (guarded, MariaDB-safe).
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory'
                    AND CONSTRAINT_NAME = 'fk_inventory_category');
SET @ddl = IF(@fk_exists = 0,
    'ALTER TABLE `inventory` ADD CONSTRAINT `fk_inventory_category` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- 4. PURCHASE ORDERS
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `po_number` VARCHAR(30) NOT NULL,
    `supplier_id` INT(10) UNSIGNED NULL,
    `order_date` DATE NOT NULL,
    `expected_date` DATE NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `grand_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('draft','pending','ordered','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
    `notes` TEXT NULL,
    `created_by` INT(10) UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_po_number` (`po_number`),
    KEY `idx_po_supplier` (`supplier_id`),
    KEY `idx_po_status` (`status`),
    KEY `idx_po_order_date` (`order_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- 5. PURCHASE ORDER ITEMS
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_order_id` INT UNSIGNED NOT NULL,
    `product_id` INT(10) UNSIGNED NOT NULL,
    `ordered_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `received_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `unit_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_poi_purchase` (`purchase_order_id`),
    KEY `idx_poi_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- 6. INVENTORY TRANSACTIONS (audit trail)
--    quantity is a SIGNED delta: previous_stock + quantity = new_stock
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_transactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT(10) UNSIGNED NOT NULL,
    `supplier_id` INT(10) UNSIGNED NULL,
    `purchase_order_id` INT UNSIGNED NULL,
    `user_id` INT(10) UNSIGNED NULL,
    `transaction_type` ENUM('stock_in','stock_out','adjustment','purchase_received','purchase_return','manual_correction') NOT NULL DEFAULT 'manual_correction',
    `quantity` INT NOT NULL DEFAULT 0,
    `previous_stock` INT NOT NULL DEFAULT 0,
    `new_stock` INT NOT NULL DEFAULT 0,
    `unit_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `reference` VARCHAR(100) NULL,
    `reason` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tx_product` (`product_id`),
    KEY `idx_tx_type` (`transaction_type`),
    KEY `idx_tx_created` (`created_at`),
    KEY `idx_tx_supplier` (`supplier_id`),
    KEY `idx_tx_po` (`purchase_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- 7. INVENTORY NOTIFICATIONS (deduplicated low-stock / PO alerts)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_notifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT(10) UNSIGNED NULL,
    `type` ENUM('low_stock','out_of_stock','purchase_received','purchase_partial','other') NOT NULL DEFAULT 'other',
    `message` VARCHAR(500) NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `notified` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_inv_notif_product` (`product_id`),
    KEY `idx_inv_notif_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;