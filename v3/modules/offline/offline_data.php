<?php
// ============================================================
// modules/offline/offline_data.php — Offline API endpoints
// Serves product catalog for offline use + accepts synced sales
// ============================================================

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// ── Serve catalog ─────────────────────────────────────────────
if ($action === 'catalog') {
    $products = dbFetchAll(
        "SELECT p.id, p.product_id, p.name, p.category_id,
                MIN(v.price) AS min_price,
                SUM(v.quantity) AS total_stock
         FROM products p
         LEFT JOIN product_variants v ON v.product_id = p.id
         WHERE p.active = 1
         GROUP BY p.id ORDER BY p.name"
    );

    // Attach variants to each product
    foreach ($products as &$p) {
        $p['variants'] = dbFetchAll(
            'SELECT id, size, color, price, quantity, barcode FROM product_variants WHERE product_id = ? AND quantity > 0',
            [$p['id']]
        );
    }

    $customers = dbFetchAll('SELECT id, name, phone, points FROM customers ORDER BY name');

    // Settings
    $rawSettings = dbFetchAll('SELECT `key`, `value` FROM settings');
    $settings    = [];
    foreach ($rawSettings as $r) $settings[$r['key']] = $r['value'];

    echo json_encode([
        'products'  => $products,
        'customers' => $customers,
        'settings'  => $settings,
    ]);
    exit;
}

// ── Sync a single offline sale ────────────────────────────────
if ($action === 'sync_sale' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $sale = json_decode($raw, true);

    if (!$sale || empty($sale['items'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid sale data']);
        exit;
    }

    // Check if already synced (idempotency via offline id stored in notes)
    $offlineId = $sale['id'] ?? '';
    $existing  = dbFetch("SELECT id FROM sales WHERE notes LIKE ?", ["%[offline:$offlineId]%"]);
    if ($existing) {
        echo json_encode(['success' => true, 'message' => 'Already synced']);
        exit;
    }

    db()->beginTransaction();
    try {
        $userId     = $_SESSION['user_id'] ?? 1; // fallback to admin
        $customerId = null;

        // Match or create customer
        if (!empty($sale['customer_phone'])) {
            $cust = dbFetch('SELECT id FROM customers WHERE phone = ?', [$sale['customer_phone']]);
            if ($cust) {
                $customerId = $cust['id'];
            } elseif (!empty($sale['customer_name']) && $sale['customer_name'] !== 'Walk-in') {
                $customerId = dbInsert('customers', [
                    'name'       => $sale['customer_name'],
                    'phone'      => $sale['customer_phone'],
                    'points'     => 0,
                    'created_at' => now(),
                ]);
            }
        }

        $invoiceNo = 'OFF-' . strtoupper(substr(md5($offlineId), 0, 8));

        // Payment methods stored as CSV
        $payMethods = implode(',', (array)($sale['payment_methods'] ?? ['cash']));

        $saleId = dbInsert('sales', [
            'invoice_no'      => $invoiceNo,
            'customer_id'     => $customerId,
            'user_id'         => $userId,
            'subtotal'        => (float)($sale['subtotal']        ?? 0),
            'discount_amount' => (float)($sale['discount_amount'] ?? 0),
            'discount_pct'    => $sale['discount_type'] === 'percent' ? (float)($sale['discount_val'] ?? 0) : 0,
            'points_used'     => (int)($sale['points_used']   ?? 0),
            'points_value'    => (float)($sale['points_value'] ?? 0),
            'vat_rate'        => $sale['vat_type'] === 'percent' ? (float)($sale['vat_val'] ?? 0) : 0,
            'vat_amount'      => (float)($sale['vat_amount']   ?? 0),
            'total'           => (float)($sale['total']        ?? 0),
            'payment_method'  => substr($payMethods, 0, 50),
            'status'          => 'completed',
            'notes'           => "[offline:$offlineId] " . ($sale['notes'] ?? ''),
            'created_at'      => $sale['created_at'] ?? now(),
        ]);

        foreach ($sale['items'] as $item) {
            dbInsert('sale_items', [
                'sale_id'      => $saleId,
                'variant_id'   => $item['variant_id'],
                'product_name' => $item['name'],
                'size'         => $item['size']  ?? '',
                'color'        => $item['color'] ?? '',
                'qty'          => (int)$item['qty'],
                'unit_price'   => (float)$item['price'],
                'total_price'  => (float)$item['price'] * (int)$item['qty'],
            ]);

            // Deduct stock
            dbQuery(
                'UPDATE product_variants SET quantity = GREATEST(0, quantity - ?) WHERE id = ?',
                [$item['qty'], $item['variant_id']]
            );
        }

        // Award points
        if ($customerId) {
            $earnRate  = (float)(dbFetch("SELECT `value` FROM settings WHERE `key`='points_earn_rate'")['value'] ?? 1);
            $earnedPts = (int)floor((float)$sale['total'] * $earnRate);
            $ptUsed    = (int)($sale['points_used'] ?? 0);
            dbQuery('UPDATE customers SET points = GREATEST(0, points + ? - ?) WHERE id = ?',
                [$earnedPts, $ptUsed, $customerId]);
        }

        // Finance entry
        dbInsert('finance_entries', [
            'type'        => 'income',
            'category'    => 'Sale',
            'amount'      => (float)$sale['total'],
            'description' => "Offline sale synced: $invoiceNo",
            'ref_sale_id' => $saleId,
            'user_id'     => $userId,
            'entry_date'  => date('Y-m-d', strtotime($sale['created_at'] ?? 'now')),
            'created_at'  => now(),
        ]);

        db()->commit();
        logAction('SYNC', 'offline', $saleId, "Synced offline sale $offlineId");

        echo json_encode(['success' => true, 'sale_id' => $saleId, 'invoice_no' => $invoiceNo]);

    } catch (Exception $e) {
        db()->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── Admin: list unsynced (offline) sales ──────────────────────
if ($action === 'list_offline') {
    requireRole(ROLE_ADMIN);
    $sales = dbFetchAll(
        "SELECT id, invoice_no, total, status, notes, created_at FROM sales WHERE notes LIKE '[offline:%' ORDER BY id DESC LIMIT 100"
    );
    echo json_encode($sales);
    exit;
}

// ── Admin: delete an offline-synced sale ─────────────────────
if ($action === 'delete_offline' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(ROLE_ADMIN);
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = (int)($data['id'] ?? 0);
    $sale = dbFetch("SELECT id FROM sales WHERE id = ? AND notes LIKE '[offline:%'", [$id]);
    if ($sale) {
        dbDelete('sales',        'id = ?', [$id]);
        dbDelete('sale_items',   'sale_id = ?', [$id]);
        dbDelete('finance_entries', 'ref_sale_id = ?', [$id]);
        logAction('DELETE', 'offline', $id, 'Admin deleted offline sale');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Not found or already synced to normal sale']);
    }
    exit;
}

echo json_encode(['error' => 'Unknown action']);
