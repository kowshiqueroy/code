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
         FROM product_variants v JOIN products p ON p.id = v.product_id
         WHERE v.product_id = ? AND v.quantity > 0",
        [$pid]
    );
    echo json_encode($rows); exit;
}
if ($action === 'barcode_lookup') {
    header('Content-Type: application/json');
    $bc  = trim($_GET['barcode'] ?? '');
    $row = dbFetch(
        "SELECT v.id AS variant_id, p.name, v.size, v.color, v.price, v.quantity
         FROM product_variants v JOIN products p ON p.id = v.product_id
         WHERE v.id = ? OR v.barcode = ?",
        [(int)$bc, $bc]
    );
    echo json_encode($row ?: (object)[]); exit;
}
if ($action === 'lookup_customer') {
    header('Content-Type: application/json');
    $phone = trim($_GET['phone'] ?? '');
    $c = dbFetch('SELECT id, name, points FROM customers WHERE phone = ?', [$phone]);
    echo json_encode($c ?: (object)[]); exit;
}

// ── Process sale ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'finalize_sale') {
    requireLogin();
    $cartItems = json_decode($_POST['cart_json'] ?? '[]', true) ?: [];
    if (empty($cartItems)) { flash('error', 'Cart is empty.'); redirect('pos'); }

    $customerId    = (int)($_POST['customer_id'] ?? 0);
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $customerName  = trim($_POST['customer_name'] ?? '');
  

    // Auto-create new customer if phone is provided but no ID matches
    if (!$customerId && $customerPhone) {
        $existing = dbFetch("SELECT id FROM customers WHERE phone = ?", [$customerPhone]);
        if ($existing) {
            $customerId = $existing['id'];
        } else {
            $customerId = dbInsert('customers', [
                'name'       => $customerName ?: 'Walk-in Customer',
                'phone'      => $customerPhone,
                'points'     => 0,
                'created_at' => now()
            ]);
        }
    }

    $status     = ($_POST['submit_type'] ?? '') === 'draft' ? 'draft' : 'completed';
    $discType   = $_POST['discount_type']   ?? 'percent';
    $discVal    = (float)($_POST['discount_val']  ?? 0);
    $vatType    = $_POST['vat_type']        ?? 'percent';
    $vatVal     = (float)($_POST['vat_val']       ?? 0);
    $pointsUsed = (int)($_POST['points_used']     ?? 0);
    $notes      = trim($_POST['notes']            ?? '');

    $payMethods = (array)($_POST['payment_methods'] ?? ['cash']);
    $payMethods = array_values(array_filter($payMethods, fn($m) => in_array($m, ['cash','card','transfer'])));
    $payMethod  = implode(',', $payMethods) ?: 'cash';

    $S          = getAllSettings();
    $ptRate     = (float)($S['points_redeem_rate'] ?? 0.01);
    $subtotal   = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cartItems));
    
    // Safety clamp backend (Double-check to prevent spoofing)
    $discVal = max(0, $discVal);
    if ($discType === 'percent' && $discVal > 100) $discVal = 100;
    
    // Verify points backend
    if ($customerId && $pointsUsed > 0) {
        $actualCustomer = dbFetch('SELECT points FROM customers WHERE id = ?', [$customerId]);
        $actualPoints = $actualCustomer['points'] ?? 0;
        if ($pointsUsed > $actualPoints) $pointsUsed = $actualPoints;
    } else {
        $pointsUsed = 0; 
    }

    $discAmt    = $discType === 'percent' ? $subtotal * ($discVal / 100) : min($discVal, $subtotal);
    $ptVal      = $pointsUsed * $ptRate;
    $afterDisc  = max(0, $subtotal - $discAmt - $ptVal);
    $vatAmt     = $vatType === 'percent' ? $afterDisc * ($vatVal / 100) : $vatVal;
    $total      = max(0, $afterDisc + $vatAmt);

    $invoiceNo  = generateInvoiceNo();
    $userId     = currentUser()['id'];

    db()->beginTransaction();
    try {
        $saleId = dbInsert('sales', [
            'invoice_no'      => $invoiceNo,
            'customer_id'     => $customerId ?: null,
            'user_id'         => $userId,
            'subtotal'        => $subtotal,
            'discount_amount' => $discAmt,
            'discount_pct'    => $discType === 'percent' ? $discVal : 0,
            'points_used'     => $pointsUsed,
            'points_value'    => $ptVal,
            'vat_rate'        => $vatType === 'percent' ? $vatVal : 0,
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
                'size'         => $item['size']  ?? '',
                'color'        => $item['color'] ?? '',
                'qty'          => $item['qty'],
                'unit_price'   => $item['price'],
                'total_price'  => $item['price'] * $item['qty'],
            ]);
            dbQuery('UPDATE product_variants SET quantity = quantity - ? WHERE id = ?', [$item['qty'], $item['variant_id']]);
        }
        
        if ($customerId && $status === 'completed') {
            $earnRate  = (float)($S['points_earn_rate'] ?? 1);
            $earnedPts = (int)floor($total * $earnRate);
            dbQuery('UPDATE customers SET points = GREATEST(0, points + ? - ?) WHERE id = ?', [$earnedPts, $pointsUsed, $customerId]);
        }
        
        if ($status === 'completed') {
            dbInsert('finance_entries', [
                'type'        => 'income',
                'category'    => 'Sale',
                'account'     => 'shop',
                'sub_account' => 'sales',
                'amount'      => $total,
                'description' => 'Sale ' . $invoiceNo.' '.$notes,
                'ref_sale_id' => $saleId,
                'party'       => 'shop',
                'user_id'     => $userId,
                'entry_date'  => today(),
                'created_at'  => now(),
            ]);
        }
        db()->commit();
        logAction('SALE', 'pos', $saleId, "Invoice $invoiceNo — $status");
        if ($status === 'completed') redirect('invoice', ['id' => $saleId]);
        else { flash('success', 'Draft saved.'); redirect('pos'); }
    } catch (Exception $e) {
        db()->rollBack();
        flash('error', 'Error: ' . $e->getMessage());
        redirect('pos');
    }
}

// ── Load page data ────────────────────────────────────────────
$S          = getAllSettings();
$categories = dbFetchAll('SELECT * FROM categories ORDER BY name');
$products   = dbFetchAll(
    "SELECT p.id, p.product_id, p.name, p.category_id,
            MIN(v.price) AS min_price, SUM(v.quantity) AS total_stock
     FROM products p LEFT JOIN product_variants v ON v.product_id = p.id
     WHERE p.active = 1 GROUP BY p.id ORDER BY p.name"
);
$discEnabled  = $S['discount_enabled']  == '1';
$vatEnabled   = $S['vat_enabled']       == '1';
$pointsEnabled= $S['points_enabled']    == '1';
$discType     = $S['discount_type']     ?? 'percent';
$discDefault  = $S['discount_default']  ?? 0;
$vatDefault   = $S['vat_default']       ?? 0;
$cur          = $S['currency_symbol']   ?? '$';

$pageTitle = 'Point of Sale';
require_once BASE_PATH . '/includes/header.php';
?>

<style>
/* ── Modern Dark Theme POS Layout ── */
:root {
  --pos-bg: #121212;
  --pos-panel: #1e1e1e;
  --pos-border: #333333;
  --pos-text: #e0e0e0;
  --pos-text-muted: #888888;
  --pos-accent: #6c5ce7;
  --pos-danger: #ff4757;
  --pos-success: #2ed573;
}

body, html {
  background-color: var(--pos-bg) !important;
  color: var(--pos-text) !important;
/*   overflow: hidden; Lock body scrolling entirely */
}

/* Custom Scrollbars */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #444; border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: #555; }

/* Input overrides for dark mode */
.form-control {
  background-color: #2a2a2a !important;
  color: #fff !important;
  border: 1px solid var(--pos-border) !important;
}
.form-control:focus {
  border-color: var(--pos-accent) !important;
  box-shadow: none !important;
}

/* ── Container Layout ── */
.pos-wrapper {
  display: flex;
  /* Subtract approximate header height and margins. Using vh to force absolute fit 
  height: calc(100vh - 90px); */
  gap: 15px;
  margin-top: 10px;
}

/* Container Fullscreen mode styling */
.pos-wrapper:fullscreen {
  height: 100%;
  width: 100vw;
  padding: 10px;
  margin: 0;
  background-color: var(--pos-bg);
}

/* Left - Product Grid */
.pos-left {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: var(--pos-panel);
  border-radius: 8px;
  border: 1px solid var(--pos-border);
  min-height: 0; /* CRITICAL for Flexbox internal scrolling */
}
.pos-search-bar {
  padding: 12px;
  border-bottom: 1px solid var(--pos-border);
  display: flex;
  gap: 10px;
  background: #1a1a1a;
  flex-shrink: 0;
}
.product-grid {
  flex: 1;
  overflow-y: auto; /* Scrollable grid */
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 10px;
  padding: 12px;
  align-content: start;
}
.product-tile {
  background: #252525;
  border: 1px solid var(--pos-border);
  border-radius: 6px;
  padding: 10px;
  cursor: pointer;
  text-align: center;
  user-select: none;
}
.product-tile:active { transform: scale(0.98); border-color: var(--pos-accent); }
.product-tile.out-of-stock { opacity: 0.4; cursor: not-allowed; }
.tile-name { font-weight: 600; font-size: 0.85rem; margin-bottom: 5px; color: #fff; line-height: 1.2;}
.tile-price { color: var(--pos-success); font-weight: bold; font-size: 0.9rem;}
.tile-stock { font-size: 0.7rem; color: var(--pos-text-muted); margin-top: 4px; }
/* ── Variant Selection Modal ── */
.pos-modal-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex; align-items: center; justify-content: center;
  z-index: 9999;
  opacity: 0; visibility: hidden; transition: 0.2s;
}
.pos-modal-overlay.active {
  opacity: 1; visibility: visible;
}
.pos-modal {
  background: var(--pos-panel);
  border: 1px solid var(--pos-border);
  border-radius: 8px;
  width: 90%; max-width: 400px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.5);
  display: flex; flex-direction: column;
}
.pos-modal-header {
  padding: 15px; border-bottom: 1px solid var(--pos-border);
  display: flex; justify-content: space-between; align-items: center;
}
.pos-modal-title { font-size: 1.1rem; font-weight: bold; color: #fff; margin: 0; }
.pos-modal-close {
  background: transparent; border: none; color: var(--pos-text-muted);
  font-size: 1.5rem; cursor: pointer; line-height: 1;
}
.pos-modal-close:hover { color: var(--pos-danger); }
.pos-modal-body {
  padding: 15px; max-height: 60vh; overflow-y: auto;
  display: flex; flex-direction: column; gap: 8px;
}
.variant-btn {
  background: #252525; border: 1px solid var(--pos-border);
  border-radius: 6px; padding: 12px; color: var(--pos-text);
  display: flex; justify-content: space-between; align-items: center;
  cursor: pointer; text-align: left; transition: 0.1s;
}
.variant-btn:hover { border-color: var(--pos-accent); background: #2a2a2a; }
.variant-btn-info { display: flex; flex-direction: column; }
.variant-btn-price { color: var(--pos-success); font-weight: bold; }
/* Right - Cart Panel */
.cart-panel {
  width: 360px;
  display: flex;
  flex-direction: column;
  background: var(--pos-panel);
  border-radius: 8px;
  border: 1px solid var(--pos-border);
  min-height: 0; /* CRITICAL for Flexbox internal scrolling */
}
.cart-header {
  padding: 12px; 
  background: var(--pos-accent); 
  color: #fff; 
  font-weight: bold;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-shrink: 0;
}
.cart-items {
  flex: 1;
  overflow-y: auto; /* Scrollable cart list */
  border-bottom: 1px solid var(--pos-border);
  padding: 5px;
}
/* Individual Cart Row */
.cart-item-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 8px; border-bottom: 1px solid var(--pos-border); font-size: 0.85rem;
}
.cart-item-row .qty-input {
  width: 45px; text-align: center; padding: 2px; background: #333; border: 1px solid #555; color: #fff; border-radius: 4px;
}

.cart-footer {
  background: #1a1a1a;
  flex-shrink: 0; /* Ensures footer NEVER gets squished or hidden */
}

/* Pay Options Dark Theme */
.pay-opt-wrap {
  background: #252525; border-color: var(--pos-border) !important; color: var(--pos-text);
}
.pay-opt-wrap.pay-selected {
  background: rgba(108, 92, 231, 0.2); border-color: var(--pos-accent) !important; color: #fff;
}

@media (max-width: 900px) {
  body, html { overflow: auto; }
  .pos-wrapper { flex-direction: column; height: auto; }
  .pos-left { min-height: 40vh; flex: none; }
  .cart-panel { width: 100%; min-height: 60vh; flex: none; }
}
</style>

<div class="d-flex justify-between align-center mb-2" style="color:#fff;">
  <h1 style="color:#fff; margin:0; font-size: 1.5rem;">🛒 POS</h1>
  <button class="btn btn-outline btn-sm" style="border-color:#555; color:#ddd; background:green;" onclick="toggleFullscreen()" id="fsBtn">⛶ Fullscreen</button>
</div>

<div class="pos-wrapper" id="posContainer">

  <div class="pos-left">
    <div class="pos-search-bar">
      <input type="text" id="productSearch" class="form-control form-control-sm" placeholder="Search products…" style="flex:1;">
      <select id="categoryFilter" class="form-control form-control-sm" style="max-width:130px">
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
        <?php endforeach ?>
      </select>
      <input type="text" id="barcodeInput" class="form-control form-control-sm" placeholder="Scan Barcode…" style="max-width:130px"
             onkeydown="if(event.key==='Enter'){searchByBarcode(this.value);this.value=''}">
    </div>
    
    <div class="product-grid" id="productGrid">
      <?php foreach ($products as $p): ?>
      <div class="product-tile <?= $p['total_stock'] <= 0 ? 'out-of-stock' : '' ?>"
           data-name="<?= e(strtolower($p['name'])) ?>" data-category="<?= $p['category_id'] ?>"
           onclick="<?= $p['total_stock'] > 0 ? "fetchVariantsAndAdd({$p['id']})" : '' ?>">
        <div class="tile-name"><?= e($p['name']) ?></div>
        <div class="tile-price"><?= $cur . number_format((float)$p['min_price'],2) ?></div>
        <div class="tile-stock">Stock: <?= (int)$p['total_stock'] ?></div>
      </div>
      <?php endforeach ?>
    </div>
  </div>

  <div class="cart-panel">
    <div class="cart-header">
      <span>🛒 Current Cart</span>
      <span id="cartCount" class="badge" style="background:#fff;color:var(--pos-accent);font-size:0.8rem;padding:2px 6px;">0</span>
    </div>
    
    <div class="cart-items" id="cartItemsContainer">
      <p class="text-center" style="padding:24px; color:var(--pos-text-muted);">Cart is empty</p>
    </div>

    <div class="cart-footer">
      <div style="padding:8px 12px; border-bottom:1px solid var(--pos-border)">
        <div class="form-row cols-2" style="margin-bottom:4px">
          <div class="form-group" style="margin-bottom:0">
            <input type="text" id="customerPhone" class="form-control form-control-sm" placeholder="Phone (New/Exsiting)" onblur="lookupCustomer(this.value)">
          </div>
          <div class="form-group" style="margin-bottom:0">
            <input type="text" id="customerName" class="form-control form-control-sm" placeholder="Name…">
          </div>
        </div>
        <input type="hidden" id="customerId">
        
        <?php if ($pointsEnabled): ?>
        <div id="pointsSection" style="margin-top:4px; background:#2c2c36; padding:6px; border-radius:4px; font-size:0.8rem;">
          <div style="display:flex;justify-content:space-between;">
            <span style="color:#ffd700;">⭐ Pts: <strong id="pointsBadge">0</strong></span>
            <label style="cursor:pointer; color:#ccc;">
              <input type="checkbox" id="usePointsToggle" onchange="togglePoints(this.checked)"> Redeem
            </label>
          </div>
          <div id="pointsInputRow" class="hidden" style="margin-top:4px;">
            <input type="number" id="pointsUsed" class="form-control form-control-sm" placeholder="Points to use" min="0" oninput="liveValidatePoints()">
          </div>
        </div>
        <?php endif ?>
      </div>

      <?php if ($discEnabled || $vatEnabled): ?>
      <div style="padding:8px 12px; border-bottom:1px solid var(--pos-border)">
        <div class="form-row cols-2">
          <?php if ($discEnabled): ?>
          <div class="form-group" style="margin-bottom:0">
            <div style="display:flex;gap:4px">
              <span style="font-size:0.75rem; color:#aaa; line-height: 2;">Disc</span>
              <?php if ($discType === 'both'): ?>
              <select id="discountType" class="form-control form-control-sm" style="max-width:45px;padding:2px;" onchange="liveValidateDiscount()">
                <option value="percent">%</option><option value="amount"><?= $cur ?></option>
              </select>
              <?php else: ?>
              <input type="hidden" id="discountType" value="<?= e($discType) ?>">
              <?php endif ?>
              <input type="number" id="discountPct" class="form-control form-control-sm" value="<?= $discDefault ?>" min="0" step="0.01" oninput="liveValidateDiscount()">
            </div>
          </div>
          <?php else: ?>
          <input type="hidden" id="discountType" value="percent"><input type="hidden" id="discountPct" value="0">
          <?php endif ?>

          <?php if ($vatEnabled): ?>
          <div class="form-group" style="margin-bottom:0">
            <div style="display:flex;gap:4px">
              <span style="font-size:0.75rem; color:#aaa; line-height: 2;">VAT</span>
              <select id="vatType" class="form-control form-control-sm" style="max-width:45px;padding:2px;" onchange="liveValidateVAT()">
                <option value="percent">%</option><option value="amount"><?= $cur ?></option>
              </select>
              <input type="number" id="vatRate" class="form-control form-control-sm" value="<?= $vatDefault ?>" min="0" step="0.01" oninput="liveValidateVAT()">
            </div>
          </div>
          <?php else: ?>
          <input type="hidden" id="vatType" value="percent"><input type="hidden" id="vatRate" value="0">
          <?php endif ?>
        </div>
      </div>
      <?php endif ?>

      <div style="padding:8px 12px; font-size:0.85rem;">
        <div style="display:flex;justify-content:space-between;color:#ccc;"><span>Subtotal</span><span id="summarySubtotal"><?= $cur ?>0.00</span></div>
        <?php if ($discEnabled): ?><div style="display:flex;justify-content:space-between;color:var(--pos-danger);"><span>Discount</span><span id="summaryDiscount">-<?= $cur ?>0.00</span></div><?php endif ?>
        <?php if ($pointsEnabled): ?><div style="display:flex;justify-content:space-between;color:#ffd700;"><span>Points Val</span><span id="summaryPoints">-<?= $cur ?>0.00</span></div><?php endif ?>
        <?php if ($vatEnabled): ?><div style="display:flex;justify-content:space-between;color:var(--pos-success);"><span>VAT</span><span id="summaryVat">+<?= $cur ?>0.00</span></div><?php endif ?>
        <div style="display:flex;justify-content:space-between;font-size:1.1rem;font-weight:bold;margin-top:4px;padding-top:4px;border-top:1px dashed #555;color:#fff;">
          <span>TOTAL</span><span id="summaryTotal"><?= $cur ?>0.00</span>
        </div>
      </div>

      <form method="POST" style="padding:8px 12px;" id="checkoutForm">
        <input type="hidden" name="action"         value="finalize_sale">
        <input type="hidden" name="customer_id"    id="hdCustomerId">
        <input type="hidden" name="customer_phone" id="hdCustomerPhone">
        <input type="hidden" name="customer_name"  id="hdCustomerName">
        <input type="hidden" name="discount_type"  id="hdDiscType">
        <input type="hidden" name="discount_val"   id="hdDiscVal">
        <input type="hidden" name="vat_type"       id="hdVatType">
        <input type="hidden" name="vat_val"        id="hdVatVal">
        <input type="hidden" name="points_used"    id="hdPointsUsed">
        <input type="hidden" name="cart_json"      id="hiddenCartJson">

        <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:8px;">
          <?php foreach(['cash'=>'💵 Cash','card'=>'💳 Card','transfer'=>'🏦 Bank'] as $pv=>$pl): ?>
          <label style="display:flex;align-items:center;gap:4px;padding:4px;border-radius:4px;border:1px solid var(--pos-border);cursor:pointer;font-size:.75rem;flex:1;justify-content:center" class="pay-opt-wrap">
            <input type="checkbox" name="payment_methods[]" value="<?= $pv ?>" class="pay-check" style="display:none;" <?= $pv==='cash'?'checked':'' ?>
                   onchange="this.closest('.pay-opt-wrap').classList.toggle('pay-selected',this.checked)">
            <?= $pl ?>
          </label>
          <?php endforeach ?>
        </div>

        <div style="margin-top:8px;">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>
        
        <div style="display:flex;gap:6px">
          <button type="submit" name="submit_type" value="draft"    class="btn btn-sm" style="flex:1;background:#f39c12;color:#fff;border:none;" onclick="return processCheckout()">📋 Draft</button>
          <button type="submit" name="submit_type" value="complete" class="btn btn-sm" style="flex:2;background:var(--pos-success);color:#fff;border:none;" onclick="return processCheckout()">✅ Finalize Sale</button>
        </div>
      </form>
    </div>
  </div>


  <div id="variantModalOverlay" class="pos-modal-overlay">
  <div class="pos-modal">
    <div class="pos-modal-header">
      <h3 class="pos-modal-title" id="variantModalTitle">Select Variant</h3>
      <button class="pos-modal-close" onclick="closeVariantModal()">×</button>
    </div>
    <div class="pos-modal-body" id="variantModalBody">
      </div>
  </div>
</div>
</div>

<script>
// ============================================================================
// 1. CONFIGURATION
// ============================================================================
const CURRENCY       = '<?= addslashes($cur) ?>';
const POINTS_RATE    = <?= (float)($S['points_redeem_rate']??0.01) ?>;
const POINTS_MIN     = <?= (int)($S['points_min_redeem']??0) ?>;
const MAX_REDEEM_PCT = <?= (float)($S['points_max_redeem_pct']??100) ?>;
const DISC_MAX_PCT   = <?= (float)($S['discount_max_percent']??100) ?>;
const DISC_MAX_AMT   = <?= (float)($S['discount_max_amount']??999999) ?>;

// ============================================================================
// 2. THE CART ENGINE
// ============================================================================
const Cart = {
  items: [],
  
  // Add item to cart
  add: function(variant) {
    let existing = this.items.find(i => i.variant_id == variant.variant_id);
    if (existing) {
      if (existing.qty < variant.quantity) {
        existing.qty++;
      } else {
        alert("Not enough stock available!");
      }
    } else {
      this.items.push({
        variant_id: variant.variant_id,
        name: variant.name,
        price: parseFloat(variant.price),
        qty: 1,
        max_qty: parseInt(variant.quantity),
        size: variant.size,
        color: variant.color
      });
    }
    this.render();
  },

  // Update item quantity directly
  updateQty: function(index, qty) {
    qty = parseInt(qty) || 1;
    if (qty > this.items[index].max_qty) {
      alert("Only " + this.items[index].max_qty + " in stock!");
      qty = this.items[index].max_qty;
    }
    if (qty <= 0) {
      this.remove(index);
    } else {
      this.items[index].qty = qty;
      this.render();
    }
  },

  remove: function(index) {
    this.items.splice(index, 1);
    this.render();
  },

  clear: function() {
    this.items = [];
    this.render();
  },

  getAll: function() {
    return this.items;
  },

  getSubtotal: function() {
    return this.items.reduce((sum, item) => sum + (item.price * item.qty), 0);
  },

  // Renders the cart HTML and triggers calculations
  render: function() {
    const container = document.getElementById('cartItemsContainer');
    document.getElementById('cartCount').textContent = this.items.length;
    
    if (this.items.length === 0) {
      container.innerHTML = '<p class="text-center" style="padding:24px; color:var(--pos-text-muted);">Cart is empty</p>';
    } else {
      let html = '';
      this.items.forEach((item, index) => {
        const variantInfo = (item.size || item.color) ? `<br><small style="color:#aaa;">${item.size||''} ${item.color||''}</small>` : '';
        html += `
          <div class="cart-item-row">
            <div style="flex:1; padding-right:10px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
              <span style="color:#fff;">${item.name}</span>${variantInfo}
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
              <span style="color:var(--pos-success);">${CURRENCY}${item.price.toFixed(2)}</span>
              <input type="number" class="qty-input" value="${item.qty}" min="1" onchange="Cart.updateQty(${index}, this.value)">
              <button type="button" class="btn btn-sm" style="background:var(--pos-danger);color:#fff;padding:2px 6px;" onclick="Cart.remove(${index})">×</button>
            </div>
          </div>
        `;
      });
      container.innerHTML = html;
    }
    
    // Automatically recalculate limits and totals every time cart changes
    liveValidateDiscount();
    liveValidateVAT();
    liveValidatePoints(); 
    this.updateTotals();
  },

  // Update the summary numbers visually
  updateTotals: function() {
    const subtotal = this.getSubtotal();
    
    // Discount Calculation
    const discType = document.getElementById('discountType')?.value || 'percent';
    const discVal = parseFloat(document.getElementById('discountPct')?.value) || 0;
    const discAmt = discType === 'percent' ? subtotal * (discVal / 100) : Math.min(discVal, subtotal);
    
    // Points Calculation
    const ptsUsed = parseInt(document.getElementById('pointsUsed')?.value) || 0;
    const ptsValue = ptsUsed * POINTS_RATE;

    const afterDisc = Math.max(0, subtotal - discAmt - ptsValue);

    // VAT Calculation
    const vatType = document.getElementById('vatType')?.value || 'percent';
    const vatVal = parseFloat(document.getElementById('vatRate')?.value) || 0;
    const vatAmt = vatType === 'percent' ? afterDisc * (vatVal / 100) : vatVal;

    const total = afterDisc + vatAmt;

    // Update DOM safely
    if(document.getElementById('summarySubtotal')) document.getElementById('summarySubtotal').textContent = CURRENCY + subtotal.toFixed(2);
    if(document.getElementById('summaryDiscount')) document.getElementById('summaryDiscount').textContent = '-' + CURRENCY + discAmt.toFixed(2);
    if(document.getElementById('summaryPoints')) document.getElementById('summaryPoints').textContent = '-' + CURRENCY + ptsValue.toFixed(2);
    if(document.getElementById('summaryVat')) document.getElementById('summaryVat').textContent = '+' + CURRENCY + vatAmt.toFixed(2);
    if(document.getElementById('summaryTotal')) document.getElementById('summaryTotal').textContent = CURRENCY + total.toFixed(2);
  }
};

// ============================================================================
// 3. UI AND AJAX FUNCTIONS
// ============================================================================

// Toggle Fullscreen explicitly targeting ONLY the POS Container
function toggleFullscreen() {
  const container = document.getElementById('posContainer');
  const btn = document.getElementById('fsBtn');
  if (!document.fullscreenElement) {
    container.requestFullscreen().catch(err => { alert(`Error: ${err.message}`); });
    btn.innerHTML = '🗗 Fullscreen';
  } else {
    document.exitFullscreen();
    btn.innerHTML = '⛶ Fullscreen';
  }
}

// Fetch Customer by Phone
function lookupCustomer(phone) {
  if (!phone) {
    document.getElementById('customerId').value = '';
    document.getElementById('pointsBadge').textContent = '0';
    liveValidatePoints();
    return;
  }
  fetch(`?page=pos&action=lookup_customer&phone=${encodeURIComponent(phone)}`)
    .then(r => r.json())
    .then(data => {
      if (data.id) {
        document.getElementById('customerId').value = data.id;
        document.getElementById('customerName').value = data.name;
        document.getElementById('pointsBadge').textContent = data.points || 0;
      } else {
        document.getElementById('customerId').value = '';
        document.getElementById('pointsBadge').textContent = '0 (New Customer)';
      }
      liveValidatePoints();
    });
}

// Fetch Variant info on grid click and auto-add
// Fetch Variant info on grid click
function fetchVariantsAndAdd(productId) {
  fetch(`?page=pos&action=get_variants&product_id=${productId}`)
    .then(r => r.json())
    .then(data => {
      if(data.length === 0) {
        alert("Out of stock or invalid product.");
      } else if (data.length === 1) {
        // Only one variant, add directly to cart
        Cart.add(data[0]);
      } else {
        // Multiple variants exist, open selection modal
        openVariantModal(data);
      }
    })
    .catch(err => {
      console.error("Error fetching variants:", err);
      alert("Failed to load product details.");
    });
}

// ── Modal Logic ──
function openVariantModal(variants) {
  const overlay = document.getElementById('variantModalOverlay');
  const body = document.getElementById('variantModalBody');
  const title = document.getElementById('variantModalTitle');
  
  // Set the product name as the modal title (using the name of the first variant)
  title.textContent = variants[0].name;
  body.innerHTML = ''; // Clear old buttons
  
  // Generate a button for each variant
  variants.forEach(variant => {
    // Build the label (e.g., "Size: M | Color: Red")
    let attrs = [];
    if (variant.size) attrs.push(`Size: ${variant.size}`);
    if (variant.color) attrs.push(`Color: ${variant.color}`);
    let label = attrs.length > 0 ? attrs.join(' | ') : 'Default Variant';
    
    // Create button
    const btn = document.createElement('button');
    btn.className = 'variant-btn';
    btn.innerHTML = `
      <div class="variant-btn-info">
        <span style="font-weight:600; color:#fff;">${label}</span>
        <span style="font-size:0.75rem; color:#aaa;">Stock: ${variant.quantity}</span>
      </div>
      <div class="variant-btn-price">${CURRENCY}${parseFloat(variant.price).toFixed(2)}</div>
    `;
    
    // On click, add that specific variant to cart and close modal
    btn.onclick = () => {
      Cart.add(variant);
      closeVariantModal();
    };
    
    body.appendChild(btn);
  });
  
  // Show modal
  overlay.classList.add('active');
}

function closeVariantModal() {
  const overlay = document.getElementById('variantModalOverlay');
  overlay.classList.remove('active');
}

// Close modal if user clicks the dark background outside the box
document.getElementById('variantModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeVariantModal();
});

function searchByBarcode(bc) {
  fetch(`?page=pos&action=barcode_lookup&barcode=${encodeURIComponent(bc)}`)
    .then(r => r.json())
    .then(data => {
      if (data.variant_id) Cart.add(data);
      else alert("Barcode not found or out of stock.");
    });
}

// ============================================================================
// 4. LIVE VALIDATION / CLAMPING
// ============================================================================

function liveValidatePoints() {
  const el = document.getElementById('pointsUsed');
  if (!el) return;
  
  let val = parseInt(el.value);
  if (isNaN(val) || val < 0) {
    val = 0; 
  }

  const customerPtsText = document.getElementById('pointsBadge')?.textContent || '0';
  const customerPts = parseInt(customerPtsText) || 0;
  
  const cartSubtotal = Cart.getSubtotal(); 
  const maxRedeemableCash = cartSubtotal * (MAX_REDEEM_PCT / 100);
  const maxRedeemablePts = Math.floor(maxRedeemableCash / POINTS_RATE);
  
  const absoluteMax = Math.min(customerPts, maxRedeemablePts);

  // Apply clamp physically to the input
  if (val > absoluteMax) {
    el.value = absoluteMax; 
  }

  Cart.updateTotals();
}

function liveValidateDiscount() {
  const el = document.getElementById('discountPct');
  const typeEl = document.getElementById('discountType');
  if (!el || !typeEl) return;
  
  let val = parseFloat(el.value);
  if (isNaN(val) || val < 0) { val = 0; }

  const type = typeEl.value;
  if (type === 'percent' && val > DISC_MAX_PCT) {
    el.value = DISC_MAX_PCT; 
  } else if (type === 'amount' && DISC_MAX_AMT > 0 && val > DISC_MAX_AMT) {
    el.value = DISC_MAX_AMT; 
  }
  Cart.updateTotals();
}

function liveValidateVAT() {
  const el = document.getElementById('vatRate');
  const typeEl = document.getElementById('vatType');
  if (!el || !typeEl) return;
  
  let val = parseFloat(el.value);
  if (isNaN(val) || val < 0) { val = 0; }

  const type = typeEl.value;
  if (type === 'percent' && val > 100) {
    el.value = 100; 
  }
  Cart.updateTotals();
}

function togglePoints(on) {
  const row = document.getElementById('pointsInputRow');
  if (row) row.classList.toggle('hidden', !on);
  const el = document.getElementById('pointsUsed');
  if (el) {
    if (!on) el.value = ''; 
    liveValidatePoints(); 
  }
}

// ============================================================================
// 5. SUBMISSION PREPARATION
// ============================================================================
function processCheckout() {
  if (Cart.getAll().length === 0) {
    alert("Cart is empty!");
    return false;
  }

  const ptsEl = document.getElementById('pointsUsed');
  if (ptsEl && ptsEl.value > 0) {
    const pts = parseInt(ptsEl.value);
    if (pts < POINTS_MIN) {
      alert(`You must redeem at least ${POINTS_MIN} points. You entered ${pts}.`);
      ptsEl.focus();
      return false;
    }
  }

  // Populate hidden form elements
  document.getElementById('hdCustomerId').value    = document.getElementById('customerId')?.value || '';
  document.getElementById('hdCustomerPhone').value = document.getElementById('customerPhone')?.value || '';
  document.getElementById('hdCustomerName').value  = document.getElementById('customerName')?.value || '';
  
  document.getElementById('hdDiscType').value      = document.getElementById('discountType')?.value || 'percent';
  document.getElementById('hdDiscVal').value       = document.getElementById('discountPct')?.value || 0;
  document.getElementById('hdVatType').value       = document.getElementById('vatType')?.value || 'percent';
  document.getElementById('hdVatVal').value        = document.getElementById('vatRate')?.value || 0;
  document.getElementById('hdPointsUsed').value    = document.getElementById('pointsUsed')?.value || 0;
  
  document.getElementById('hiddenCartJson').value = JSON.stringify(Cart.getAll());
  
  return true; 
}

// Block negative symbols physically
document.addEventListener('DOMContentLoaded', () => {
  ['pointsUsed','discountPct','vatRate'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('keydown', (e) => {
        if (['-', 'e', 'E', '+'].includes(e.key)) e.preventDefault();
      });
    }
  });
  
  // Initialize payment buttons UI
  document.querySelectorAll('.pay-check:checked').forEach(el => el.closest('.pay-opt-wrap').classList.add('pay-selected'));
  
  // Initial render
  Cart.render();
});

//update product grid based on search and category filter
document.getElementById('productSearch').addEventListener('input', filterProducts);
document.getElementById('categoryFilter').addEventListener('change', filterProducts);
function filterProducts() {
  const searchTerm = document.getElementById('productSearch').value.toLowerCase();
  const categoryId = document.getElementById('categoryFilter').value;
  
  document.querySelectorAll('.product-tile').forEach(tile => {
    const name = tile.dataset.name || '';
    const category = tile.dataset.category || '';
    
    const matchesSearch = name.includes(searchTerm);
    const matchesCategory = !categoryId || category === categoryId;
    
    tile.style.display = (matchesSearch && matchesCategory) ? 'block' : 'none';
  });
}


</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>