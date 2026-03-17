<?php
require_once __DIR__ . '/includes/bootstrap.php';
$sql = "
CREATE TABLE IF NOT EXISTS product_entries (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    product_id    INT NOT NULL,
    variant_id    INT NOT NULL,
    product_name  VARCHAR(200),
    variant_name  VARCHAR(100),
    size          VARCHAR(50),
    color         VARCHAR(50),
    cost          DECIMAL(12,2) NOT NULL DEFAULT 0,
    price         DECIMAL(12,2) NOT NULL DEFAULT 0,
    regular       DECIMAL(12,2) NOT NULL DEFAULT 0,
    qty_added     INT NOT NULL DEFAULT 0,
    memo_number   VARCHAR(100),
    memo_date     DATE,
    user_id       INT,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);";
try {
    db()->exec($sql);
    echo "Table 'product_entries' created successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
