-- ============================================================
-- POS & Store Management System — Full Database Schema
-- Engine: MySQL 8.0+ | Charset: utf8mb4
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ------------------------------------------------------------
-- DATABASE
-- ------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `pos_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `pos_db`;

-- ============================================================
-- 1. SETTINGS / SHOP CONFIGURATION
-- ============================================================
CREATE TABLE `settings` (
  `id`                    TINYINT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `shop_name`             VARCHAR(150)        NOT NULL DEFAULT 'My Shop',
  `shop_type`             ENUM('bookshop','foodshop','clothshop','showroom','general') NOT NULL DEFAULT 'general',
  `address`               TEXT,
  `phone`                 VARCHAR(30),
  `email`                 VARCHAR(150),
  `logo_url`              VARCHAR(500),
  `currency_symbol`       VARCHAR(10)         NOT NULL DEFAULT '৳',
  `currency_code`         VARCHAR(5)          NOT NULL DEFAULT 'BDT',
  `default_vat_pct`       DECIMAL(5,2)        NOT NULL DEFAULT 0.00,
  `loyalty_earn_rate`     DECIMAL(8,4)        NOT NULL DEFAULT 1.0000  COMMENT 'Points earned per 1 unit of currency spent',
  `loyalty_redeem_rate`   DECIMAL(8,4)        NOT NULL DEFAULT 1.0000  COMMENT 'Currency value per 1 point redeemed',
  `global_max_discount_pct` DECIMAL(5,2)      NOT NULL DEFAULT 30.00,
  `invoice_prefix`        VARCHAR(10)         NOT NULL DEFAULT 'INV',
  `invoice_footer_note`   TEXT,
  `thermal_width_mm`      SMALLINT UNSIGNED   NOT NULL DEFAULT 80,
  `receipt_copies`        TINYINT UNSIGNED    NOT NULL DEFAULT 2,
  `timezone`              VARCHAR(60)         NOT NULL DEFAULT 'Asia/Dhaka',
  `created_at`            TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Global shop settings — single row';

INSERT INTO `settings` (`id`) VALUES (1);

-- ============================================================
-- 2. USERS
-- ============================================================
CREATE TABLE `users` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100)    NOT NULL,
  `username`      VARCHAR(60)     NOT NULL UNIQUE,
  `password_hash` VARCHAR(255)    NOT NULL,
  `role`          ENUM('admin','sr') NOT NULL DEFAULT 'sr',
  `email`         VARCHAR(150),
  `phone`         VARCHAR(30),
  `pin`           VARCHAR(10)                 COMMENT 'Optional 4-6 digit quick PIN (hashed)',
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `last_login`    TIMESTAMP       NULL,
  `created_by`    INT UNSIGNED,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_users_role` (`role`),
  CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin (password: Admin@1234 — change immediately)
INSERT INTO `users` (`name`,`username`,`password_hash`,`role`,`created_by`)
VALUES ('System Admin','admin','$2y$12$placeholder_change_on_setup','admin',NULL);

-- ============================================================
-- 3. AUDIT LOG
-- ============================================================
CREATE TABLE `audit_logs` (
  `id`            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED,
  `action`        ENUM('INSERT','UPDATE','DELETE','LOGIN','LOGOUT','SYNC_CONFIRM','SYNC_REJECT','SETTING_CHANGE') NOT NULL,
  `table_name`    VARCHAR(80),
  `record_id`     BIGINT UNSIGNED,
  `before_data`   JSON,
  `after_data`    JSON,
  `ip_address`    VARCHAR(45),
  `user_agent`    VARCHAR(300),
  `notes`         TEXT,
  `created_at`    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user`  (`user_id`),
  KEY `idx_audit_table` (`table_name`, `record_id`),
  KEY `idx_audit_ts`    (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Immutable audit trail';

-- ============================================================
-- 4. CUSTOMERS (CRM)
-- ============================================================
CREATE TABLE `customers` (
  `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`              VARCHAR(150)    NOT NULL,
  `phone`             VARCHAR(30)     UNIQUE,
  `email`             VARCHAR(150),
  `address`           TEXT,
  `loyalty_points`    DECIMAL(12,4)   NOT NULL DEFAULT 0.0000,
  `total_spent`       DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `total_visits`      INT UNSIGNED    NOT NULL DEFAULT 0,
  `notes`             TEXT,
  `created_by`        INT UNSIGNED,
  `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cust_phone` (`phone`),
  CONSTRAINT `fk_cust_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Walk-in customer placeholder
INSERT INTO `customers` (`id`,`name`,`phone`) VALUES (1,'Walk-in Customer',NULL);

-- ============================================================
-- 5. CATEGORIES
-- ============================================================
CREATE TABLE `categories` (
  `id`          SMALLINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)        NOT NULL UNIQUE,
  `icon`        VARCHAR(50)                   COMMENT 'Emoji or icon class',
  `color`       VARCHAR(7)          NOT NULL DEFAULT '#607D8B',
  `sort_order`  SMALLINT UNSIGNED   NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)          NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. PRODUCTS
-- ============================================================
CREATE TABLE `products` (
  `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `category_id`       SMALLINT UNSIGNED,
  `name`              VARCHAR(200)    NOT NULL,
  `description`       TEXT,
  `sku`               VARCHAR(80)     UNIQUE,
  `barcode`           VARCHAR(30)     NOT NULL UNIQUE COMMENT 'Auto-generated numeric barcode',
  `base_cost_price`   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `base_sell_price`   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `base_stock`        DECIMAL(12,3)   NOT NULL DEFAULT 0.000 COMMENT 'Qty — decimal for weight-based items',
  `unit`              VARCHAR(20)     NOT NULL DEFAULT 'pcs',
  `low_stock_alert`   DECIMAL(12,3)   NOT NULL DEFAULT 5.000,
  `has_variants`      TINYINT(1)      NOT NULL DEFAULT 0,
  `track_stock`       TINYINT(1)      NOT NULL DEFAULT 1,
  `is_active`         TINYINT(1)      NOT NULL DEFAULT 1,
  `image_url`         VARCHAR(500),
  `notes`             TEXT,
  `created_by`        INT UNSIGNED,
  `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prod_cat`     (`category_id`),
  KEY `idx_prod_barcode` (`barcode`),
  KEY `idx_prod_active`  (`is_active`),
  FULLTEXT KEY `ft_prod_name` (`name`,`description`),
  CONSTRAINT `fk_prod_cat`    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prod_user`   FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. VARIANT ATTRIBUTE TYPES  (Size, Color, Edition …)
-- ============================================================
CREATE TABLE `variant_attributes` (
  `id`        SMALLINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `name`      VARCHAR(60)         NOT NULL UNIQUE  COMMENT 'e.g. Size, Color, Edition, Portion',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `variant_attributes` (`name`) VALUES
  ('Size'),('Color'),('Edition'),('Portion Size'),('Weight'),('Format'),('Flavour');

-- ============================================================
-- 8. PRODUCT VARIANTS
-- ============================================================
CREATE TABLE `product_variants` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `product_id`    INT UNSIGNED    NOT NULL,
  `attribute_id`  SMALLINT UNSIGNED,
  `value`         VARCHAR(100)    NOT NULL COMMENT 'e.g. Large, Red, 3rd Edition',
  `barcode`       VARCHAR(30)     NOT NULL UNIQUE,
  `cost_price`    DECIMAL(12,2),
  `sell_price`    DECIMAL(12,2),
  `stock`         DECIMAL(12,3)   NOT NULL DEFAULT 0.000,
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_variant_prod_attr_val` (`product_id`,`attribute_id`,`value`),
  KEY `idx_var_barcode` (`barcode`),
  CONSTRAINT `fk_var_product`   FOREIGN KEY (`product_id`)   REFERENCES `products`(`id`)           ON DELETE CASCADE,
  CONSTRAINT `fk_var_attribute` FOREIGN KEY (`attribute_id`) REFERENCES `variant_attributes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. DISCOUNTS / PROMOTIONS
-- ============================================================
CREATE TABLE `discounts` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120)    NOT NULL,
  `type`          ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `value`         DECIMAL(10,2)   NOT NULL,
  `applies_to`    ENUM('product','variant','category','cart') NOT NULL DEFAULT 'product',
  `ref_id`        INT UNSIGNED             COMMENT 'product_id / variant_id / category_id depending on applies_to',
  `is_permanent`  TINYINT(1)      NOT NULL DEFAULT 1,
  `start_date`    DATE,
  `end_date`      DATE,
  `min_qty`       DECIMAL(10,3),
  `min_cart_value` DECIMAL(12,2),
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `created_by`    INT UNSIGNED,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_disc_applies` (`applies_to`,`ref_id`),
  CONSTRAINT `fk_disc_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 10. SALES (HEADER)
-- ============================================================
CREATE TABLE `sales` (
  `id`                BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `invoice_number`    VARCHAR(40)         NOT NULL UNIQUE,
  `customer_id`       INT UNSIGNED        NOT NULL DEFAULT 1,
  `sr_id`             INT UNSIGNED,
  `sale_date`         DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `subtotal`          DECIMAL(14,2)       NOT NULL DEFAULT 0.00,
  `discount_amount`   DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
  `vat_amount`        DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
  `points_redeemed`   DECIMAL(12,4)       NOT NULL DEFAULT 0.0000,
  `points_redeemed_value` DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `total_amount`      DECIMAL(14,2)       NOT NULL DEFAULT 0.00,
  `amount_paid`       DECIMAL(14,2)       NOT NULL DEFAULT 0.00,
  `change_due`        DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
  `payment_method`    ENUM('cash','card','mobile','split','loyalty','other') NOT NULL DEFAULT 'cash',
  `payment_details`   JSON                         COMMENT 'Split breakdown, card ref, mobile ref, etc.',
  `points_earned`     DECIMAL(12,4)       NOT NULL DEFAULT 0.0000,
  `status`            ENUM('completed','refunded','partial_refund','void') NOT NULL DEFAULT 'completed',
  `is_draft`          TINYINT(1)          NOT NULL DEFAULT 0,
  `draft_name`        VARCHAR(80),
  `notes`             TEXT,
  `qr_token`          VARCHAR(64)         UNIQUE   COMMENT 'Token for online invoice verification',
  `source`            ENUM('pos','offline_sync') NOT NULL DEFAULT 'pos',
  `created_at`        TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sale_invoice`  (`invoice_number`),
  KEY `idx_sale_customer` (`customer_id`),
  KEY `idx_sale_sr`       (`sr_id`),
  KEY `idx_sale_date`     (`sale_date`),
  KEY `idx_sale_status`   (`status`),
  CONSTRAINT `fk_sale_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`),
  CONSTRAINT `fk_sale_sr`       FOREIGN KEY (`sr_id`)       REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 11. SALE ITEMS (LINE ITEMS)
-- ============================================================
CREATE TABLE `sale_items` (
  `id`              BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `sale_id`         BIGINT UNSIGNED     NOT NULL,
  `product_id`      INT UNSIGNED        NOT NULL,
  `variant_id`      INT UNSIGNED,
  `product_name`    VARCHAR(200)        NOT NULL COMMENT 'Snapshot at time of sale',
  `variant_label`   VARCHAR(200),
  `barcode`         VARCHAR(30),
  `qty`             DECIMAL(12,3)       NOT NULL DEFAULT 1.000,
  `unit_cost`       DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
  `unit_price`      DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
  `discount_pct`    DECIMAL(5,2)        NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
  `line_total`      DECIMAL(14,2)       NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_si_sale`    (`sale_id`),
  KEY `idx_si_product` (`product_id`),
  CONSTRAINT `fk_si_sale`    FOREIGN KEY (`sale_id`)    REFERENCES `sales`(`id`)            ON DELETE CASCADE,
  CONSTRAINT `fk_si_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
  CONSTRAINT `fk_si_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 12. OFFLINE SYNC QUEUE
-- ============================================================
CREATE TABLE `offline_sync_queue` (
  `id`                BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `offline_uuid`      VARCHAR(64)         NOT NULL UNIQUE COMMENT 'UUID generated client-side',
  `payload`           LONGTEXT            NOT NULL COMMENT 'Full JSON of sale + items',
  `device_info`       VARCHAR(300),
  `offline_created_at` DATETIME           NOT NULL,
  `received_at`       TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status`            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by`       INT UNSIGNED,
  `reviewed_at`       TIMESTAMP           NULL,
  `review_notes`      TEXT,
  `merged_sale_id`    BIGINT UNSIGNED,
  PRIMARY KEY (`id`),
  KEY `idx_osq_status`  (`status`),
  CONSTRAINT `fk_osq_reviewer` FOREIGN KEY (`reviewed_by`)    REFERENCES `users`(`id`)  ON DELETE SET NULL,
  CONSTRAINT `fk_osq_sale`     FOREIGN KEY (`merged_sale_id`) REFERENCES `sales`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Holds offline orders pending admin review';

-- ============================================================
-- 13. FINANCE LEDGER (SHOWROOM CASH FLOW)
-- ============================================================
CREATE TABLE `finance_ledger` (
  `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `entry_date`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type`          ENUM('sale_cash','sale_card','expense','cash_in','cash_out','owner_withdrawal','opening_balance','adjustment') NOT NULL,
  `amount`        DECIMAL(14,2)       NOT NULL COMMENT 'Always positive; direction = type',
  `reference_id`  BIGINT UNSIGNED              COMMENT 'sale_id or expense_id if applicable',
  `reference_type` VARCHAR(40),
  `description`   VARCHAR(300)        NOT NULL,
  `sr_id`         INT UNSIGNED                 COMMENT 'SR who created the entry',
  `balance_after` DECIMAL(14,2)       NOT NULL DEFAULT 0.00 COMMENT 'Running balance snapshot',
  `created_by`    INT UNSIGNED,
  `created_at`    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fl_type`   (`type`),
  KEY `idx_fl_date`   (`entry_date`),
  KEY `idx_fl_sr`     (`sr_id`),
  CONSTRAINT `fk_fl_sr`   FOREIGN KEY (`sr_id`)      REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fl_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Master showroom cash flow ledger';

-- ============================================================
-- 14. SR LEDGER (PER SALES REP TRACKING)
-- ============================================================
CREATE TABLE `sr_ledger` (
  `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `sr_id`         INT UNSIGNED        NOT NULL,
  `entry_date`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type`          ENUM('sale','expense','cash_in','cash_out','adjustment') NOT NULL,
  `amount`        DECIMAL(12,2)       NOT NULL,
  `sale_id`       BIGINT UNSIGNED,
  `description`   VARCHAR(300),
  `created_by`    INT UNSIGNED,
  `created_at`    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_srl_sr`   (`sr_id`),
  KEY `idx_srl_date` (`entry_date`),
  CONSTRAINT `fk_srl_sr`   FOREIGN KEY (`sr_id`)      REFERENCES `users`(`id`),
  CONSTRAINT `fk_srl_sale` FOREIGN KEY (`sale_id`)    REFERENCES `sales`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_srl_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Per-SR transaction ledger';

-- ============================================================
-- 15. EXPENSES (PETTY CASH)
-- ============================================================
CREATE TABLE `expenses` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `category`      VARCHAR(80)     NOT NULL DEFAULT 'General',
  `description`   VARCHAR(300)    NOT NULL,
  `amount`        DECIMAL(12,2)   NOT NULL,
  `expense_date`  DATE            NOT NULL,
  `receipt_url`   VARCHAR(500),
  `sr_id`         INT UNSIGNED,
  `created_by`    INT UNSIGNED,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_exp_date` (`expense_date`),
  CONSTRAINT `fk_exp_sr`   FOREIGN KEY (`sr_id`)      REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exp_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 16. STOCK MOVEMENTS (AUDIT TRAIL FOR INVENTORY)
-- ============================================================
CREATE TABLE `stock_movements` (
  `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `product_id`    INT UNSIGNED        NOT NULL,
  `variant_id`    INT UNSIGNED,
  `type`          ENUM('sale','return','purchase','adjustment','transfer') NOT NULL,
  `qty_change`    DECIMAL(12,3)       NOT NULL COMMENT 'Positive = in, Negative = out',
  `qty_before`    DECIMAL(12,3)       NOT NULL,
  `qty_after`     DECIMAL(12,3)       NOT NULL,
  `reference_id`  BIGINT UNSIGNED,
  `reference_type` VARCHAR(40),
  `notes`         TEXT,
  `created_by`    INT UNSIGNED,
  `created_at`    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sm_product` (`product_id`),
  KEY `idx_sm_date`    (`created_at`),
  CONSTRAINT `fk_sm_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
  CONSTRAINT `fk_sm_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sm_user`    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 17. SESSIONS (PHP SESSION STORE — optional DB-backed)
-- ============================================================
CREATE TABLE `user_sessions` (
  `session_id`    VARCHAR(128)    NOT NULL,
  `user_id`       INT UNSIGNED    NOT NULL,
  `data`          TEXT,
  `last_activity` INT UNSIGNED    NOT NULL,
  `ip_address`    VARCHAR(45),
  PRIMARY KEY (`session_id`),
  KEY `idx_sess_user` (`user_id`),
  CONSTRAINT `fk_sess_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Optional DB-backed PHP sessions';

-- ============================================================
-- HELPFUL VIEWS
-- ============================================================

CREATE OR REPLACE VIEW `v_product_stock` AS
SELECT
  p.id,
  p.name,
  p.barcode,
  p.base_stock   AS stock,
  NULL           AS variant_id,
  NULL           AS variant_value,
  p.base_sell_price AS sell_price,
  p.base_cost_price AS cost_price,
  c.name         AS category
FROM products p
LEFT JOIN categories c ON c.id = p.category_id
WHERE p.has_variants = 0 AND p.is_active = 1
UNION ALL
SELECT
  p.id,
  CONCAT(p.name,' — ',va.name,': ',pv.value) AS name,
  pv.barcode,
  pv.stock,
  pv.id          AS variant_id,
  pv.value       AS variant_value,
  COALESCE(pv.sell_price, p.base_sell_price) AS sell_price,
  COALESCE(pv.cost_price, p.base_cost_price) AS cost_price,
  c.name         AS category
FROM products p
JOIN product_variants pv ON pv.product_id = p.id
LEFT JOIN variant_attributes va ON va.id = pv.attribute_id
LEFT JOIN categories c ON c.id = p.category_id
WHERE p.has_variants = 1 AND p.is_active = 1 AND pv.is_active = 1;


CREATE OR REPLACE VIEW `v_daily_summary` AS
SELECT
  DATE(sale_date)          AS sale_day,
  COUNT(*)                 AS total_transactions,
  SUM(total_amount)        AS gross_revenue,
  SUM(discount_amount)     AS total_discounts,
  SUM(vat_amount)          AS total_vat,
  SUM(total_amount)        AS net_revenue,
  SUM(CASE WHEN payment_method='cash'   THEN total_amount ELSE 0 END) AS cash_total,
  SUM(CASE WHEN payment_method='card'   THEN total_amount ELSE 0 END) AS card_total,
  SUM(CASE WHEN payment_method='mobile' THEN total_amount ELSE 0 END) AS mobile_total
FROM sales
WHERE status = 'completed' AND is_draft = 0
GROUP BY DATE(sale_date);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================
ALTER TABLE `sales`       ADD INDEX `idx_sale_qr_token` (`qr_token`);
ALTER TABLE `products`    ADD INDEX `idx_prod_sku`      (`sku`);
ALTER TABLE `customers`   ADD INDEX `idx_cust_name`     (`name`(50));
