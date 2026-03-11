<?php
// api/products/list.php — Return product list for POS grid
declare(strict_types=1);
require_once dirname(__FILE__, 3) . '/config/config.php';
session_start_secure();
require_login();

header('Content-Type: application/json');
header('Cache-Control: private, max-age=60');

try {
    $sql = "
        SELECT
            p.id,
            p.name,
            p.barcode,
            p.base_price,
            p.vat_pct,
            p.image_url,
            c.name  AS category_name,
            c.icon  AS category_icon,
            -- Active discount
            COALESCE(
                (SELECT d.value FROM discounts d
                 WHERE d.product_id = p.id AND d.is_active = 1
                   AND d.type = 'percent'
                   AND (d.valid_until IS NULL OR d.valid_until >= CURDATE())
                   AND (d.valid_from IS NULL  OR d.valid_from <= CURDATE())
                 ORDER BY d.value DESC LIMIT 1),
                0
            ) AS discount_pct,
            COALESCE(
                (SELECT d.value FROM discounts d
                 WHERE d.product_id = p.id AND d.is_active = 1
                   AND d.type = 'fixed'
                   AND (d.valid_until IS NULL OR d.valid_until >= CURDATE())
                   AND (d.valid_from IS NULL  OR d.valid_from <= CURDATE())
                 ORDER BY d.value DESC LIMIT 1),
                0
            ) AS discount_fixed,
            COALESCE(
                (SELECT SUM(pv.stock_qty) FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1),
                0
            ) AS total_stock
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE p.is_active = 1
        ORDER BY c.name, p.name
    ";

    $products = db()->query($sql)->fetchAll();

    // Calculate display price with discount
    foreach ($products as &$p) {
        $base = (float)$p['base_price'];
        if ($p['discount_pct'] > 0) {
            $p['display_price'] = round($base * (1 - $p['discount_pct'] / 100), 2);
            $p['discount_amt']  = round($base * $p['discount_pct'] / 100, 2);
        } elseif ($p['discount_fixed'] > 0) {
            $p['display_price'] = max(0, $base - $p['discount_fixed']);
            $p['discount_amt']  = (float)$p['discount_fixed'];
        } else {
            $p['display_price'] = $base;
            $p['discount_amt']  = 0;
        }

        // Load variants
        $vStmt = db()->prepare(
            'SELECT id, variant_label, variant_sku, price, stock_qty FROM product_variants
             WHERE product_id = ? AND is_active = 1 ORDER BY variant_label'
        );
        $vStmt->execute([$p['id']]);
        $p['variants'] = $vStmt->fetchAll();
    }
    unset($p);

    json_response(['products' => $products, 'count' => count($products)]);

} catch (PDOException $e) {
    error_log('Products list error: ' . $e->getMessage());
    json_response(['error' => 'Failed to load products'], 500);
}
