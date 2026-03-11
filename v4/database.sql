-- ============================================================
-- POS & Store Management System — Full Database Schema
-- Engine: MySQL 8.0+ / MariaDB 10.6+
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `pos_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `pos_db`;

-- ============================================================
-- 1. USERS & ROLES
-- ============================================================
CREATE TABLE `users` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(120)  NOT NULL,
  `email`         VARCHAR(180)  NOT NULL UNIQUE,
  `password_hash` VARCHAR(255)  NOT NULL,
  `role`          ENUM('admin','sr') NOT NULL DEFAULT 'sr',
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `last_login`    DATETIME      NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. SHOP SETTINGS
-- ============================================================
CREATE TABLE `settings` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key`           VARCHAR(80)   NOT NULL UNIQUE,
  `value`         TEXT          NULL,
  `updated_by`    INT UNSIGNED  NULL,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO `settings` (`key`, `value`) VALUES
  ('shop_name',          'My Shop'),
  ('shop_address',       ''),
  ('shop_phone',         ''),
  ('shop_logo_url',      ''),
  ('default_vat',        '0'),
  ('loyalty_rate',       '1'),        -- points per currency unit
  ('loyalty_redeem',     '100'),      -- points needed for 1 currency unit
  ('max_discount_pct',   '30'),
  ('invoice_prefix',     'INV'),
  ('currency_symbol',    '৳'),
  ('thermal_width_mm',   '80'),
  ('invoice_note',       'Thank you for shopping with us!');

-- ============================================================
-- 3. CATEGORIES
-- ============================================================
CREATE TABLE `categories` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(120)  NOT NULL,
  `shop_type`     ENUM('book','food','cloth','showroom','general') NOT NULL DEFAULT 'general',
  `icon`          VARCHAR(60)   NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 4. PRODUCTS
-- ============================================================
CREATE TABLE `products` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id`   INT UNSIGNED  NOT NULL,
  `name`          VARCHAR(200)  NOT NULL,
  `description`   TEXT          NULL,
  `barcode`       VARCHAR(30)   NOT NULL UNIQUE,
  `base_price`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cost_price`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `vat_pct`       DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `image_url`     VARCHAR(300)  NULL,
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `created_by`    INT UNSIGNED  NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_barcode` (`barcode`),
  INDEX `idx_category` (`category_id`)
) ENGINE=InnoDB;

-- ============================================================
-- 5. PRODUCT VARIANTS
-- ============================================================
CREATE TABLE `product_variants` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id`    INT UNSIGNED  NOT NULL,
  `variant_label` VARCHAR(80)   NOT NULL,   -- e.g. "Size: L / Color: Red"
  `variant_sku`   VARCHAR(50)   NOT NULL UNIQUE,
  `price`         DECIMAL(12,2) NULL,       -- NULL = inherit base_price
  `cost_price`    DECIMAL(12,2) NULL,
  `stock_qty`     INT           NOT NULL DEFAULT 0,
  `reorder_level` INT           NOT NULL DEFAULT 5,
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  INDEX `idx_product` (`product_id`)
) ENGINE=InnoDB;

-- ============================================================
-- 6. DISCOUNTS
-- ============================================================
CREATE TABLE `discounts` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id`    INT UNSIGNED  NULL,       -- NULL = store-wide
  `variant_id`    INT UNSIGNED  NULL,
  `type`          ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `value`         DECIMAL(10,2) NOT NULL,
  `valid_from`    DATE          NULL,
  `valid_until`   DATE          NULL,       -- NULL = permanent
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `created_by`    INT UNSIGNED  NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 7. CUSTOMERS (CRM)
-- ============================================================
CREATE TABLE `customers` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(120)  NOT NULL,
  `phone`         VARCHAR(25)   NOT NULL UNIQUE,
  `email`         VARCHAR(180)  NULL,
  `address`       TEXT          NULL,
  `loyalty_points` INT          NOT NULL DEFAULT 0,
  `total_spend`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_phone` (`phone`)
) ENGINE=InnoDB;

-- ============================================================
-- 8. SALES (HEADER)
-- ============================================================
CREATE TABLE `sales` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_no`    VARCHAR(40)   NOT NULL UNIQUE,
  `customer_id`   INT UNSIGNED  NULL,
  `user_id`       INT UNSIGNED  NOT NULL,        -- SR / cashier
  `sale_date`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `subtotal`      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `discount_amt`  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `vat_amt`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `loyalty_redeemed` INT        NOT NULL DEFAULT 0,
  `loyalty_redeemed_val` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `grand_total`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `payment_cash`  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `payment_card`  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `payment_other` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `change_amt`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `loyalty_earned` INT          NOT NULL DEFAULT 0,
  `status`        ENUM('completed','draft','voided') NOT NULL DEFAULT 'completed',
  `notes`         TEXT          NULL,
  `is_offline_sync` TINYINT(1)  NOT NULL DEFAULT 0,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_invoice` (`invoice_no`),
  INDEX `idx_date`    (`sale_date`),
  INDEX `idx_user`    (`user_id`)
) ENGINE=InnoDB;

-- ============================================================
-- 9. SALE ITEMS (LINE ITEMS)
-- ============================================================
CREATE TABLE `sale_items` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sale_id`       INT UNSIGNED  NOT NULL,
  `product_id`    INT UNSIGNED  NOT NULL,
  `variant_id`    INT UNSIGNED  NULL,
  `product_name`  VARCHAR(200)  NOT NULL,   -- snapshot
  `variant_label` VARCHAR(80)   NULL,
  `qty`           DECIMAL(10,3) NOT NULL DEFAULT 1.000,
  `unit_price`    DECIMAL(12,2) NOT NULL,
  `discount_amt`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `vat_pct`       DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `line_total`    DECIMAL(15,2) NOT NULL,
  FOREIGN KEY (`sale_id`)     REFERENCES `sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`variant_id`)  REFERENCES `product_variants`(`id`) ON DELETE SET NULL,
  INDEX `idx_sale` (`sale_id`)
) ENGINE=InnoDB;

-- ============================================================
-- 10. OFFLINE SYNC QUEUE
-- ============================================================
CREATE TABLE `offline_sync_queue` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `local_id`      VARCHAR(60)   NOT NULL,   -- UUID generated on device
  `payload`       LONGTEXT      NOT NULL,   -- full JSON of sale + items
  `device_info`   VARCHAR(200)  NULL,
  `queued_at`     DATETIME      NOT NULL,
  `synced_at`     DATETIME      NULL,
  `status`        ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by`   INT UNSIGNED  NULL,
  `review_note`   TEXT          NULL,
  `sale_id`       INT UNSIGNED  NULL,       -- set after admin confirms
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`sale_id`)     REFERENCES `sales`(`id`) ON DELETE SET NULL,
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB;

-- ============================================================
-- 11. FINANCE LEDGER
-- ============================================================
CREATE TABLE `finance_ledger` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `entry_type`    ENUM('sale','expense','cash_in','cash_out','withdrawal','adjustment') NOT NULL,
  `reference_id`  INT UNSIGNED  NULL,       -- sale_id or NULL
  `user_id`       INT UNSIGNED  NOT NULL,
  `amount`        DECIMAL(15,2) NOT NULL,
  `description`   VARCHAR(300)  NULL,
  `balance_after` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `entry_date`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_type` (`entry_type`),
  INDEX `idx_date` (`entry_date`)
) ENGINE=InnoDB;

-- ============================================================
-- 12. SR LEDGER (per-rep tracking)
-- ============================================================
CREATE TABLE `sr_ledger` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT UNSIGNED  NOT NULL,
  `entry_type`    ENUM('sale','expense','cash_in','cash_out') NOT NULL,
  `reference_id`  INT UNSIGNED  NULL,
  `amount`        DECIMAL(15,2) NOT NULL,
  `description`   VARCHAR(300)  NULL,
  `entry_date`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_date` (`user_id`, `entry_date`)
) ENGINE=InnoDB;

-- ============================================================
-- 13. AUDIT TRAIL
-- ============================================================
CREATE TABLE `audit_log` (
  `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT UNSIGNED  NULL,
  `action`        VARCHAR(60)   NOT NULL,   -- INSERT / UPDATE / DELETE / LOGIN
  `table_name`    VARCHAR(60)   NOT NULL,
  `record_id`     VARCHAR(40)   NULL,
  `before_data`   LONGTEXT      NULL,       -- JSON
  `after_data`    LONGTEXT      NULL,       -- JSON
  `ip_address`    VARCHAR(45)   NULL,
  `user_agent`    VARCHAR(300)  NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_action`     (`action`),
  INDEX `idx_table`      (`table_name`),
  INDEX `idx_created`    (`created_at`)
) ENGINE=InnoDB;

-- ============================================================
-- 14. DRAFT SALES (parked carts)
-- ============================================================
CREATE TABLE `sale_drafts` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT UNSIGNED  NOT NULL,
  `label`         VARCHAR(100)  NOT NULL DEFAULT 'Draft',
  `cart_data`     LONGTEXT      NOT NULL,   -- JSON of cart state
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- DEFAULT ADMIN USER  (password: Admin@1234)
-- ============================================================
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`) VALUES
  ('System Admin', 'admin@pos.local', '$2y$12$sampleHashReplaceOnSetup', 'admin');

-- ============================================================
-- DEFAULT CATEGORIES
-- ============================================================
INSERT INTO `categories` (`name`, `shop_type`, `icon`) VALUES
  ('Fiction',          'book',     '📚'),
  ('Non-Fiction',      'book',     '📖'),
  ('Stationery',       'book',     '✏️'),
  ('Main Course',      'food',     '🍽️'),
  ('Beverages',        'food',     '☕'),
  ('Snacks',           'food',     '🍿'),
  ('Men\'s Wear',      'cloth',    '👔'),
  ('Women\'s Wear',    'cloth',    '👗'),
  ('Footwear',         'cloth',    '👟'),
  ('Electronics',      'showroom', '💻'),
  ('Furniture',        'showroom', '🛋️'),
  ('General',          'general',  '🏷️');
