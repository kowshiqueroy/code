-- ============================================================
-- POS & Store Management System — Complete Database Schema
-- Engine: MySQL 8.x+ | Charset: utf8mb4
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ============================================================
-- DATABASE
-- ============================================================
CREATE DATABASE IF NOT EXISTS `pos_store_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `pos_store_db`;

-- ============================================================
-- 1. SETTINGS
-- ============================================================
CREATE TABLE `settings` (
  `id`                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `shop_name`             VARCHAR(150)    NOT NULL DEFAULT 'My Shop',
  `shop_address`          TEXT,
  `shop_phone`            VARCHAR(30),
  `shop_email`            VARCHAR(150),
  `shop_logo_url`         VARCHAR(500),
  `currency_symbol`       VARCHAR(10)     NOT NULL DEFAULT '$',
  `default_vat_percent`   DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
  `loyalty_point_rate`    DECIMAL(8,4)    NOT NULL DEFAULT 1.0000  COMMENT 'Points earned per currency unit spent',
  `loyalty_redeem_rate`   DECIMAL(8,4)    NOT NULL DEFAULT 1.0000  COMMENT 'Currency value per redeemed point',
  `max_discount_percent`  DECIMAL(5,2)    NOT NULL DEFAULT 50.00,
  `invoice_prefix`        VARCHAR(10)     NOT NULL DEFAULT 'INV',
  `thermal_header_text`   TEXT,
  `thermal_footer_text`   TEXT,
  `online_verify_url`     VARCHAR(500)             COMMENT 'Base URL for QR invoice verification',
  `timezone`              VARCHAR(60)     NOT NULL DEFAULT 'UTC',
  `updated_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`id`, `shop_name`, `currency_symbol`, `default_vat_percent`, `loyalty_point_rate`, `loyalty_redeem_rate`, `max_discount_percent`, `invoice_prefix`, `online_verify_url`, `timezone`)
VALUES (1, 'My Store', '$', 0.00, 1.0000, 0.0100, 50.00, 'INV', 'https://yourstore.com/verify/', 'UTC');

-- ============================================================
-- 2. USERS
-- ============================================================
CREATE TABLE `users` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `full_name`     VARCHAR(120)    NOT NULL,
  `username`      VARCHAR(60)     NOT NULL,
  `email`         VARCHAR(150),
  `password_hash` VARCHAR(255)    NOT NULL,
  `role`          ENUM('admin','sr') NOT NULL DEFAULT 'sr',
  `phone`         VARCHAR(30),
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `last_login_at` TIMESTAMP       NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin: password = Admin@1234 (bcrypt)
INSERT INTO `users` (`full_name`, `username`, `email`, `password_hash`, `role`)
VALUES ('System Admin', 'admin', 'admin@store.com',
        '$2y$12$Yq5W6EoIMKwXxm6r.P0D1.VHJm5/sCIBYcZ8TU/WnqX3pmhGbNk7G', 'admin');

-- ============================================================
-- 3. CATEGORIES
-- ============================================================
CREATE TABLE `categories` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)    NOT NULL,
  `slug`        VARCHAR(120)    NOT NULL,
  `shop_type`   ENUM('book','food','cloth','showroom','general') NOT NULL DEFAULT 'general',
  `icon`        VARCHAR(50)              COMMENT 'Emoji or icon class',
  `color`       VARCHAR(7)               COMMENT 'Hex colour for UI',
  `sort_order`  SMALLINT        NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. PRODUCTS
-- ============================================================
CREATE TABLE `products` (
  `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `category_id`       INT UNSIGNED    NOT NULL,
  `name`              VARCHAR(200)    NOT NULL,
  `description`       TEXT,
  `barcode`           VARCHAR(30)     NOT NULL,
  `sku`               VARCHAR(60),
  `base_cost_price`   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `base_sell_price`   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `has_variants`      TINYINT(1)      NOT NULL DEFAULT 0,
  `vat_applicable`    TINYINT(1)      NOT NULL DEFAULT 1,
  `is_active`         TINYINT(1)      NOT NULL DEFAULT 1,
  `image_url`         VARCHAR(500),
  `notes`             TEXT,
  `created_by`        INT UNSIGNED    NOT NULL,
  `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_barcode` (`barcode`),
  KEY `idx_category` (`category_id`),
  KEY `idx_name` (`name`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_product_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. VARIANT TYPES  (e.g. "Size", "Color", "Edition")
-- ============================================================
CREATE TABLE `variant_types` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(80)   NOT NULL,
  `shop_types` VARCHAR(200)           COMMENT 'Comma-separated shop types this applies to',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `variant_types` (`name`, `shop_types`) VALUES
  ('Size',          'cloth,showroom'),
  ('Color',         'cloth,showroom,general'),
  ('Edition',       'book'),
  ('Author',        'book'),
  ('Portion Size',  'food'),
  ('Flavour',       'food'),
  ('Material',      'cloth,showroom'),
  ('Model',         'showroom,general');

-- ============================================================
-- 6. PRODUCT VARIANTS
-- ============================================================
CREATE TABLE `product_variants` (
  `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `product_id`     INT UNSIGNED    NOT NULL,
  `variant_type_id`INT UNSIGNED    NOT NULL,
  `variant_value`  VARCHAR(100)    NOT NULL,
  `barcode`        VARCHAR(30)     NOT NULL,
  `extra_cost`     DECIMAL(12,2)   NOT NULL DEFAULT 0.00  COMMENT 'Added on top of base_cost_price',
  `extra_price`    DECIMAL(12,2)   NOT NULL DEFAULT 0.00  COMMENT 'Added on top of base_sell_price',
  `override_price` DECIMAL(12,2)   NULL                   COMMENT 'If set, fully overrides base + extra',
  `stock_qty`      INT             NOT NULL DEFAULT 0,
  `low_stock_threshold` SMALLINT   NOT NULL DEFAULT 5,
  `is_active`      TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_variant_barcode` (`barcode`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `fk_variant_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_variant_type` FOREIGN KEY (`variant_type_id`) REFERENCES `variant_types` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. DISCOUNTS
-- ============================================================
CREATE TABLE `discounts` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120)    NOT NULL,
  `type`          ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `value`         DECIMAL(10,2)   NOT NULL,
  `applies_to`    ENUM('product','variant','category','all') NOT NULL DEFAULT 'product',
  `target_id`     INT UNSIGNED    NULL COMMENT 'product_id or category_id; NULL for "all"',
  `is_permanent`  TINYINT(1)      NOT NULL DEFAULT 1,
  `start_date`    DATE            NULL,
  `end_date`      DATE            NULL,
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `created_by`    INT UNSIGNED    NOT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_target` (`applies_to`, `target_id`),
  KEY `idx_dates` (`start_date`, `end_date`),
  CONSTRAINT `fk_discount_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. CUSTOMERS
-- ============================================================
CREATE TABLE `customers` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `full_name`       VARCHAR(150)    NOT NULL,
  `phone`           VARCHAR(30),
  `email`           VARCHAR(150),
  `address`         TEXT,
  `loyalty_points`  DECIMAL(12,4)   NOT NULL DEFAULT 0.0000,
  `total_spent`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `total_visits`    INT UNSIGNED    NOT NULL DEFAULT 0,
  `notes`           TEXT,
  `created_by`      INT UNSIGNED    NOT NULL,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_phone` (`phone`),
  KEY `idx_name` (`full_name`),
  CONSTRAINT `fk_customer_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. SALES (ORDERS)
-- ============================================================
CREATE TABLE `sales` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number`      VARCHAR(30)     NOT NULL,
  `customer_id`         INT UNSIGNED    NULL,
  `sr_id`               INT UNSIGNED    NOT NULL,
  `sale_date`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `subtotal`            DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `discount_amount`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `vat_amount`          DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `loyalty_redeemed`    DECIMAL(12,4)   NOT NULL DEFAULT 0.0000  COMMENT 'Points redeemed as currency',
  `grand_total`         DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `amount_tendered`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `change_due`          DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `payment_method`      ENUM('cash','card','split','loyalty','other') NOT NULL DEFAULT 'cash',
  `cash_amount`         DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `card_amount`         DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `other_amount`        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `loyalty_points_earned` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `status`              ENUM('completed','voided','draft','refunded') NOT NULL DEFAULT 'completed',
  `notes`               TEXT,
  `is_offline_sale`     TINYINT(1)      NOT NULL DEFAULT 0,
  `offline_uid`         VARCHAR(100)    NULL COMMENT 'Client-generated UUID for offline dedup',
  `created_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoice` (`invoice_number`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_sr` (`sr_id`),
  KEY `idx_date` (`sale_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_sale_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sale_sr` FOREIGN KEY (`sr_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. SALE ITEMS (LINE ITEMS)
-- ============================================================
CREATE TABLE `sale_items` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id`         BIGINT UNSIGNED NOT NULL,
  `product_id`      INT UNSIGNED    NOT NULL,
  `variant_id`      INT UNSIGNED    NULL,
  `product_name`    VARCHAR(200)    NOT NULL COMMENT 'Snapshot at time of sale',
  `variant_label`   VARCHAR(200)    NULL      COMMENT 'e.g. "Size: L / Color: Red"',
  `barcode`         VARCHAR(30)     NOT NULL,
  `qty`             DECIMAL(12,4)   NOT NULL DEFAULT 1.0000,
  `unit_cost`       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `unit_price`      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `vat_amount`      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `line_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sale` (`sale_id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `fk_item_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. DRAFT SALES (Parked Orders)
-- ============================================================
CREATE TABLE `draft_sales` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `label`       VARCHAR(120)    NOT NULL DEFAULT 'Draft',
  `sr_id`       INT UNSIGNED    NOT NULL,
  `customer_id` INT UNSIGNED    NULL,
  `cart_json`   MEDIUMTEXT      NOT NULL COMMENT 'Full cart state as JSON',
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sr` (`sr_id`),
  CONSTRAINT `fk_draft_sr` FOREIGN KEY (`sr_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. OFFLINE SYNC QUEUE
-- ============================================================
CREATE TABLE `offline_sync_queue` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `offline_uid`     VARCHAR(100)    NOT NULL COMMENT 'UUID generated on client',
  `payload_json`    MEDIUMTEXT      NOT NULL COMMENT 'Full sale + items JSON',
  `sr_id`           INT UNSIGNED    NULL      COMMENT 'Resolved after login; NULL if unknown',
  `status`          ENUM('pending','approved','rejected','error') NOT NULL DEFAULT 'pending',
  `error_message`   TEXT,
  `reviewed_by`     INT UNSIGNED    NULL,
  `reviewed_at`     TIMESTAMP       NULL,
  `merged_sale_id`  BIGINT UNSIGNED NULL COMMENT 'Populated after approval merge',
  `device_info`     VARCHAR(500)    NULL,
  `queued_at`       DATETIME        NOT NULL COMMENT 'Client-side timestamp of original sale',
  `received_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_offline_uid` (`offline_uid`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_queue_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_queue_sale` FOREIGN KEY (`merged_sale_id`) REFERENCES `sales` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. FINANCE LEDGER
-- ============================================================
CREATE TABLE `finance_ledger` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entry_date`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `entry_type`      ENUM('sale_cash','sale_card','cash_in','expense','owner_withdrawal','refund','adjustment','loyalty_redemption') NOT NULL,
  `reference_type`  ENUM('sale','offline_sale','manual') NOT NULL DEFAULT 'manual',
  `reference_id`    BIGINT UNSIGNED NULL COMMENT 'sale_id or offline_sync_queue.id',
  `amount`          DECIMAL(15,2)   NOT NULL COMMENT 'Positive = money in, Negative = money out',
  `description`     VARCHAR(500)    NOT NULL,
  `sr_id`           INT UNSIGNED    NULL COMMENT 'NULL = showroom/admin entry',
  `running_balance` DECIMAL(15,2)   NOT NULL DEFAULT 0.00 COMMENT 'Snapshot of balance after this entry',
  `created_by`      INT UNSIGNED    NOT NULL,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`entry_date`),
  KEY `idx_type` (`entry_type`),
  KEY `idx_sr` (`sr_id`),
  KEY `idx_ref` (`reference_type`, `reference_id`),
  CONSTRAINT `fk_ledger_sr` FOREIGN KEY (`sr_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_ledger_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. SR LEDGER  (per-rep cash tracking)
-- ============================================================
CREATE TABLE `sr_ledger` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sr_id`           INT UNSIGNED    NOT NULL,
  `entry_date`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `entry_type`      ENUM('sale','cash_in','cash_out','expense','commission','adjustment') NOT NULL,
  `reference_type`  ENUM('sale','manual') NOT NULL DEFAULT 'manual',
  `reference_id`    BIGINT UNSIGNED NULL,
  `amount`          DECIMAL(15,2)   NOT NULL COMMENT 'Positive = in, Negative = out',
  `description`     VARCHAR(500),
  `created_by`      INT UNSIGNED    NOT NULL,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sr` (`sr_id`),
  KEY `idx_date` (`entry_date`),
  CONSTRAINT `fk_sr_ledger_sr` FOREIGN KEY (`sr_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_ledger_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. PETTY CASH
-- ============================================================
CREATE TABLE `petty_cash` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `entry_date`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type`        ENUM('expense','income') NOT NULL DEFAULT 'expense',
  `category`    VARCHAR(100),
  `description` VARCHAR(500)    NOT NULL,
  `amount`      DECIMAL(12,2)   NOT NULL,
  `receipt_ref` VARCHAR(100),
  `sr_id`       INT UNSIGNED    NULL,
  `created_by`  INT UNSIGNED    NOT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`entry_date`),
  CONSTRAINT `fk_petty_sr` FOREIGN KEY (`sr_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_petty_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. STOCK ADJUSTMENTS
-- ============================================================
CREATE TABLE `stock_adjustments` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `product_id`    INT UNSIGNED    NOT NULL,
  `variant_id`    INT UNSIGNED    NULL,
  `adjustment_type` ENUM('restock','damage','return','correction','theft') NOT NULL,
  `qty_change`    INT             NOT NULL COMMENT 'Positive = added, Negative = removed',
  `reason`        VARCHAR(500),
  `created_by`    INT UNSIGNED    NOT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `fk_adj_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_adj_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. AUDIT TRAIL / LOGS
-- ============================================================
CREATE TABLE `audit_logs` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED    NULL,
  `action`        VARCHAR(80)     NOT NULL COMMENT 'e.g. INSERT, UPDATE, DELETE, LOGIN, LOGOUT',
  `table_name`    VARCHAR(80)     NOT NULL,
  `record_id`     BIGINT UNSIGNED NULL,
  `before_data`   MEDIUMTEXT      NULL COMMENT 'JSON snapshot before change',
  `after_data`    MEDIUMTEXT      NULL COMMENT 'JSON snapshot after change',
  `ip_address`    VARCHAR(45),
  `user_agent`    VARCHAR(500),
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_table` (`table_name`),
  KEY `idx_date` (`created_at`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 18. CSRF TOKENS
-- ============================================================
CREATE TABLE `csrf_tokens` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `session_id`  VARCHAR(128)    NOT NULL,
  `token`       VARCHAR(64)     NOT NULL,
  `expires_at`  DATETIME        NOT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session` (`session_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 19. BARCODE SEQUENCE (auto-increment barcode generator)
-- ============================================================
CREATE TABLE `barcode_sequence` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=100001 DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
