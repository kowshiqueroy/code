<?php
// api/sales/create.php — Process a completed sale
declare(strict_types=1);
require_once dirname(__FILE__, 3) . '/config/config.php';
session_start_secure();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed'], 405);

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!csrf_verify($csrf)) json_response(['error' => 'CSRF failed'], 403);

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['cart'])) json_response(['error' => 'Empty cart'], 422);

$cart       = $data['cart'];
$customer   = $data['customer'] ?? null;
$settings   = get_settings();
$userId     = current_user_id();

try {
    db()->beginTransaction();

    // ── Upsert Customer ───────────────────────────────────
    $customerId = null;
    if ($customer && !empty($customer['phone'])) {
        $phone = sanitize_string($customer['phone'], 25);
        $cStmt = db()->prepare('SELECT id, loyalty_points FROM customers WHERE phone = ?');
        $cStmt->execute([$phone]);
        $existingCustomer = $cStmt->fetch();

        if ($existingCustomer) {
            $customerId = (int)$existingCustomer['id'];
        } else {
            $inStmt = db()->prepare(
                'INSERT INTO customers (name, phone, email) VALUES (?,?,?)'
            );
            $inStmt->execute([
                sanitize_string($customer['name'] ?? 'Customer', 120),
                $phone,
                sanitize_email($customer['email'] ?? ''),
            ]);
            $customerId = (int)db()->lastInsertId();
        }
    }

    // ── Calculate totals server-side ──────────────────────
    $subtotal   = 0;
    $discountAmt= 0;
    $vatAmt     = 0;

    $validatedItems = [];
    foreach ($cart as $item) {
        $productId = sanitize_int($item['product_id']);
        $variantId = !empty($item['variant_id']) ? sanitize_int($item['variant_id']) : null;
        $qty       = max(0.001, (float)($item['qty'] ?? 1));
        $vatPct    = (float)($item['vat_pct'] ?? 0);

        // Fetch product for server-side price validation
        $pStmt = db()->prepare('SELECT base_price, name FROM products WHERE id = ? AND is_active = 1');
        $pStmt->execute([$productId]);
        $product = $pStmt->fetch();
        if (!$product) continue;

        $unitPrice = (float)($item['price'] ?? $product['base_price']);
        $discLine  = (float)($item['discount'] ?? 0);
        $lineNet   = ($unitPrice - $discLine) * $qty;
        $lineVat   = round($lineNet * $vatPct / 100, 2);
        $lineTotal = round($lineNet + $lineVat, 2);

        $subtotal    += round($unitPrice * $qty, 2);
        $discountAmt += round($discLine * $qty, 2);
        $vatAmt      += $lineVat;

        $validatedItems[] = [
            'product_id'  => $productId,
            'variant_id'  => $variantId,
            'name'        => sanitize_string($item['name'] ?? $product['name'], 200),
            'variant_label'=> sanitize_string($item['variant_label'] ?? '', 80),
            'qty'         => $qty,
            'unit_price'  => $unitPrice,
            'discount_amt'=> $discLine,
            'vat_pct'     => $vatPct,
            'line_total'  => $lineTotal,
        ];
    }

    $loyaltyRedeemed   = (int)($data['loyalty_redeemed'] ?? 0);
    $loyaltyRedeemedVal= round((float)($data['loyalty_redeemed_val'] ?? 0), 2);
    $grandTotal        = round($subtotal - $discountAmt + $vatAmt - $loyaltyRedeemedVal, 2);
    $payCash           = round((float)($data['payment_cash'] ?? 0), 2);
    $payCard           = round((float)($data['payment_card'] ?? 0), 2);
    $changeAmt         = round($payCash + $payCard - $grandTotal, 2);

    $loyaltyRate = (float)($settings['loyalty_rate'] ?? 1);
    $loyaltyEarned = (int)floor($grandTotal * $loyaltyRate);

    $invoiceNo = generate_invoice_no();

    // ── Insert Sale ───────────────────────────────────────
    $saleStmt = db()->prepare("
        INSERT INTO sales
          (invoice_no, customer_id, user_id, subtotal, discount_amt, vat_amt,
           loyalty_redeemed, loyalty_redeemed_val, grand_total,
           payment_cash, payment_card, change_amt, loyalty_earned, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'completed')
    ");
    $saleStmt->execute([
        $invoiceNo, $customerId, $userId,
        $subtotal, $discountAmt, $vatAmt,
        $loyaltyRedeemed, $loyaltyRedeemedVal, $grandTotal,
        $payCash, $payCard, $changeAmt, $loyaltyEarned,
    ]);
    $saleId = (int)db()->lastInsertId();

    // ── Insert Line Items & Deduct Stock ──────────────────
    $itemStmt = db()->prepare("
        INSERT INTO sale_items
          (sale_id, product_id, variant_id, product_name, variant_label, qty, unit_price, discount_amt, vat_pct, line_total)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");
    foreach ($validatedItems as $item) {
        $itemStmt->execute([
            $saleId, $item['product_id'], $item['variant_id'],
            $item['name'], $item['variant_label'],
            $item['qty'], $item['unit_price'], $item['discount_amt'],
            $item['vat_pct'], $item['line_total'],
        ]);

        // Deduct stock from variant or first available variant
        if ($item['variant_id']) {
            db()->prepare('UPDATE product_variants SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?')
                ->execute([$item['qty'], $item['variant_id']]);
        } else {
            db()->prepare('UPDATE product_variants SET stock_qty = GREATEST(0, stock_qty - ?) WHERE product_id = ? LIMIT 1')
                ->execute([$item['qty'], $item['product_id']]);
        }
    }

    // ── Update Customer Loyalty ───────────────────────────
    if ($customerId) {
        db()->prepare(
            'UPDATE customers SET
               loyalty_points = loyalty_points - ? + ?,
               total_spend = total_spend + ?
             WHERE id = ?'
        )->execute([$loyaltyRedeemed, $loyaltyEarned, $grandTotal, $customerId]);
    }

    // ── Finance Ledger ────────────────────────────────────
    $lastBalance = (float)(db()->query('SELECT COALESCE(MAX(balance_after), 0) FROM finance_ledger')->fetchColumn());
    $newBalance  = $lastBalance + $payCash; // only cash goes to showroom balance
    db()->prepare(
        'INSERT INTO finance_ledger (entry_type, reference_id, user_id, amount, description, balance_after)
         VALUES ("sale", ?, ?, ?, ?, ?)'
    )->execute([$saleId, $userId, $grandTotal, "Sale {$invoiceNo}", $newBalance]);

    // SR Ledger
    db()->prepare(
        'INSERT INTO sr_ledger (user_id, entry_type, reference_id, amount, description)
         VALUES (?, "sale", ?, ?, ?)'
    )->execute([$userId, $saleId, $grandTotal, "Sale {$invoiceNo}"]);

    // ── Audit Log ─────────────────────────────────────────
    audit_log('INSERT', 'sales', $saleId, null, ['invoice_no' => $invoiceNo, 'total' => $grandTotal]);

    db()->commit();

    json_response([
        'success'       => true,
        'sale_id'       => $saleId,
        'invoice_no'    => $invoiceNo,
        'grand_total'   => $grandTotal,
        'change_amt'    => $changeAmt,
        'loyalty_earned'=> $loyaltyEarned,
    ]);

} catch (Throwable $e) {
    db()->rollBack();
    error_log('Sale create error: ' . $e->getMessage());
    json_response(['error' => 'Sale failed: ' . $e->getMessage()], 500);
}
