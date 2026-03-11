<?php
// ============================================================
// modules/pos/pos.php — POS selling interface
// ============================================================

// ── AJAX endpoints ────────────────────────────────────────────
$action = $_GET['action'] ?? '';
if ($action === 'get_variants') {
    header('Content-Type: application/json');
    $pid = (int)($_GET['product_id'] ?? 0);
    $rows = dbFetchAll(
        "SELECT v.id AS variant_id, p.name, v.size, v.color, v.price, v.quantity, v.barcode
         FROM product_variants v
         JOIN products p ON p.id = v.product_id
         WHERE v.product_id = ? AND v.quantity > 0",
        [$pid]
    );
    echo json_encode($rows);
    exit;
}

if ($action === 'barcode_lookup') {
    header('Content-Type: application/json');
    $bc  = trim($_GET['barcode'] ?? '');
    $row = dbFetch(
        "SELECT v.id AS variant_id, p.name, v.size, v.color, v.price, v.quantity
         FROM product_variants v JOIN products p ON p.id = v.product_id
         WHERE v.barcode = ?",
        [$bc]
    );
    echo json_encode($row ?: (object)[]);
    exit;
}

if ($action === 'lookup_customer') {
    header('Content-Type: application/json');
    $phone = trim($_GET['phone'] ?? '');
    $c     = dbFetch('SELECT id, name, points FROM customers WHERE phone = ?', [$phone]);
    echo json_encode($c ?: (object)[]);
    exit;
}

// ── Process sale submission ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'finalize_sale') {
    requireLogin();

    $cartJson   = $_POST['cart_json']     ?? '[]';
    $cartItems  = json_decode($cartJson, true) ?: [];

    if (empty($cartItems)) { flash('error', 'Cart is empty.'); redirect('pos'); }

    $customerId  = (int)($_POST['customer_id'] ?? 0) ?: null;
    $status      = ($_POST['submit_type'] ?? '') === 'draft' ? 'draft' : 'completed';
    $discPct     = (float)($_POST['discount_pct']   ?? 0);
    $vatRate     = (float)($_POST['vat_rate']        ?? 0);
    $pointsUsed  = (int)($_POST['points_used']       ?? 0);
    $payMethod   = $_POST['payment_method'] ?? 'cash';
    $notes       = trim($_POST['notes'] ?? '');

    $subtotal    = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cartItems));
    $discAmt     = (float)($_POST['discount_amount'] ?? 0);
    $ptVal       = (float)($_POST['points_value']    ?? 0);
    $vatAmt      = (float)($_POST['vat_amount']      ?? 0);
    $total       = (float)($_POST['total_amount']    ?? 0);

    $invoiceNo   = generateInvoiceNo();
    $userId      = currentUser()['id'];

    db()->beginTransaction();
    try {
        $saleId = dbInsert('sales', [
            'invoice_no'      => $invoiceNo,
            'customer_id'     => $customerId,
            'user_id'         => $userId,
            'subtotal'        => $subtotal,
            'discount_amount' => $discAmt,
            'discount_pct'    => $discPct,
            'points_used'     => $pointsUsed,
            'points_value'    => $ptVal,
            'vat_rate'        => $vatRate,
            'vat_amount'      => $vatAmt,
            'total'           => $total,
            'payment_method'  => $payMethod,
            'status'          => $status,
            'notes'           => $notes,
            'created_at'      => now(),
        ]);

        foreach ($cartItems as $item) {
            dbInsert('sale_items', [
                'sale_id'      => $saleId,
                'variant_id'   => $item['variant_id'],
                'product_name' => $item['name'],
                'size'         => $item['size'] ?? '',
                'color'        => $item['color'] ?? '',
                'qty'          => $item['qty'],
                'unit_price'   => $item['price'],
                'total_price'  => $item['price'] * $item['qty'],
            ]);

            // Deduct stock
            dbQuery(
                'UPDATE product_variants SET quantity = quantity - ? WHERE id = ?',
                [$item['qty'], $item['variant_id']]
            );
        }

        // Award loyalty points
        if ($customerId && $status === 'completed') {
            $earnedPts = (int)floor($total * 100 * POINTS_RATE);
            dbQuery(
                'UPDATE customers SET points = points + ? - ? WHERE id = ?',
                [$earnedPts, $pointsUsed, $customerId]
            );
        }

        // Auto-add to finance if cash
        if ($status === 'completed') {
            dbInsert('finance_entries', [
                'type'        => 'income',
                'category'    => 'Sale',
                'amount'      => $total,
                'description' => 'Sale ' . $invoiceNo,
                'ref_sale_id' => $saleId,
                'user_id'     => $userId,
                'entry_date'  => today(),
                'created_at'  => now(),
            ]);
        }

        db()->commit();
        logAction('SALE', 'pos', $saleId, "Invoice $invoiceNo — $status");

        if ($status === 'completed') {
            redirect('invoice', ['id' => $saleId]);
        } else {
            flash('success', 'Draft saved.');
            redirect('pos');
        }

    } catch (Exception $e) {
        db()->rollBack();
        flash('error', 'Error: ' . $e->getMessage());
        redirect('pos');
    }
}

// ── Load products for grid ────────────────────────────────────
$categories = dbFetchAll('SELECT * FROM categories ORDER BY name');
$products   = dbFetchAll(
    "SELECT p.id, p.product_id, p.name, p.category_id,
            MIN(v.price) AS min_price,
            SUM(v.quantity) AS total_stock
     FROM products p
     LEFT JOIN product_variants v ON v.product_id = p.id
     WHERE p.active = 1
     GROUP BY p.id
     ORDER BY p.name"
);

$pageTitle = 'Point of Sale';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-2">
  <h1>🛒 Point of Sale</h1>
</div>

<div class="pos-layout">

  <!-- ── Product Grid ──────────────────────────────────────── -->
  <div>
    <!-- Filters -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
      <input type="text" id="productSearch" class="form-control" placeholder="Search products…" style="flex:1;min-width:140px">
      <select id="categoryFilter" class="form-control" style="max-width:160px">
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
        <?php endforeach ?>
      </select>
      <input type="text" id="barcodeInput" class="form-control" placeholder="Barcode scan…" style="max-width:160px"
             onkeydown="if(event.key==='Enter'){searchByBarcode(this.value);this.value=''}">
    </div>

    <!-- Grid -->
    <div class="product-grid">
      <?php foreach ($products as $p): ?>
      <div class="product-tile <?= $p['total_stock'] <= 0 ? 'out-of-stock' : '' ?>"
           data-name="<?= e(strtolower($p['name'])) ?>"
           data-category="<?= $p['category_id'] ?>"
           onclick="<?= $p['total_stock'] > 0 ? "selectVariant({$p['id']})" : '' ?>">
        <div class="tile-name"><?= e($p['name']) ?></div>
        <div class="tile-price"><?= money((float)$p['min_price']) ?></div>
        <div class="tile-stock">Stock: <?= (int)$p['total_stock'] ?></div>
      </div>
      <?php endforeach ?>
      <?php if (!$products): ?>
        <div class="text-muted" style="grid-column:1/-1;padding:24px">No products found. <a href="index.php?page=products">Add products</a>.</div>
      <?php endif ?>
    </div>
  </div>

  <!-- ── Cart Panel ────────────────────────────────────────── -->
  <div class="cart-panel">
    <div class="cart-header">
      🛒 Cart <span id="cartCount" style="color:var(--accent)">0</span> items
    </div>

    <div class="cart-items" id="cartItems">
      <p class="text-muted text-center" style="padding:24px">Cart is empty</p>
    </div>

    <!-- Customer Info -->
    <div style="padding:12px 16px;border-top:1px solid var(--border);border-bottom:1px solid var(--border)">
      <div class="form-row cols-2" style="margin-bottom:8px">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Customer Phone</label>
          <input type="text" id="customerPhone" class="form-control" placeholder="Phone…"
                 onblur="lookupCustomer(this.value)">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Name</label>
          <input type="text" id="customerName" class="form-control" placeholder="Name…">
        </div>
      </div>
      <input type="hidden" id="customerId" name="customer_id">

      <div id="pointsSection" class="hidden">
        <label class="form-label">Points Available: <strong id="pointsBadge">0 pts</strong></label>
        <input type="number" id="pointsUsed" class="form-control" placeholder="Points to redeem" min="0"
               oninput="Cart.updateTotals()">
        <input type="hidden" id="pointsBalance">
      </div>
    </div>

    <!-- Discounts & VAT -->
    <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
      <div class="form-row cols-2">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Discount %</label>
          <input type="number" id="discountPct" class="form-control" value="0" min="0" max="100" step="0.5"
                 oninput="Cart.updateTotals()">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">VAT %</label>
          <input type="number" id="vatRate" class="form-control" value="<?= DEFAULT_VAT * 100 ?>" min="0" max="100" step="0.5"
                 oninput="Cart.updateTotals()">
        </div>
      </div>
    </div>

    <!-- Summary -->
    <div class="cart-summary">
      <div class="summary-row"><span>Subtotal</span><span id="cartSubtotal">$0.00</span></div>
      <div class="summary-row"><span>Discount</span><span id="summaryDiscount">$0.00</span></div>
      <div class="summary-row"><span>Points</span><span id="summaryPoints">$0.00</span></div>
      <div class="summary-row"><span>VAT</span><span id="summaryVat">$0.00</span></div>
      <div class="summary-row total"><span>TOTAL</span><span id="summaryTotal">$0.00</span></div>
    </div>

    <!-- Checkout form -->
    <form method="POST" style="padding:12px 16px" id="checkoutForm">
      <input type="hidden" name="action"          value="finalize_sale">
      <input type="hidden" name="customer_id"     id="hdCustomerId">
      <input type="hidden" name="discount_pct"    id="hdDiscPct">
      <input type="hidden" name="discount_amount" id="hiddenDiscAmt">
      <input type="hidden" name="vat_rate"        id="hdVatRate">
      <input type="hidden" name="vat_amount"      id="hiddenVatAmt">
      <input type="hidden" name="points_used"     id="hdPointsUsed">
      <input type="hidden" name="points_value"    id="hiddenPtVal">
      <input type="hidden" name="total_amount"    id="hiddenTotal">
      <input type="hidden" name="cart_json"       id="hiddenCartJson">

      <div class="form-group">
        <label class="form-label">Payment Method</label>
        <select name="payment_method" class="form-control">
          <option value="cash">Cash</option>
          <option value="card">Card</option>
          <option value="transfer">Transfer</option>
          <option value="mixed">Mixed</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2"></textarea>
      </div>

      <div style="display:flex;gap:8px">
        <button type="submit" name="submit_type" value="draft" class="btn btn-warning" style="flex:1"
                onclick="syncHidden()">Save Draft</button>
        <button type="submit" name="submit_type" value="complete" class="btn btn-success" style="flex:1"
                onclick="syncHidden()">Finalize</button>
      </div>
      <button type="button" class="btn btn-ghost btn-block mt-1" onclick="Cart.clear()">Clear Cart</button>
    </form>
  </div>
</div>

<script>
function syncHidden() {
  document.getElementById('hdCustomerId').value  = document.getElementById('customerId')?.value || '';
  document.getElementById('hdDiscPct').value     = document.getElementById('discountPct')?.value || 0;
  document.getElementById('hdVatRate').value     = document.getElementById('vatRate')?.value || 0;
  document.getElementById('hdPointsUsed').value  = document.getElementById('pointsUsed')?.value || 0;
}
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
