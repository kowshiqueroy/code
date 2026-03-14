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
    $sms        = isset($_POST['sms']) ? 1 : 0; // For potential SMS receipt feature
 
  

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

    $recentSale = dbFetch("SELECT id FROM sales WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 20 SECOND) LIMIT 1", [$userId]);
    if ($recentSale) {
        flash('error', 'You made a sale within the last 20 seconds. Please wait before making another sale.');
        redirect('pos');
    } else {
        db()->beginTransaction();
    }
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
        if ($status === 'completed')
          {
            //if SMS option is selected, send SMS receipt to customer and number starts with 01 and 11 digits long (Bangladeshi phone number format)
            if ($sms && $S['api_key_sms']!= '' && $S['sms_enabled'] === '1' && preg_match('/^01\d{9}$/', $customerPhone)) {
                 $points = dbFetch('SELECT points FROM customers WHERE id = ?', [$customerId])['points'] ?? 0;
$url = 'https://api.sms.net.bd/sendsms';
$params = [
    'api_key' => $S['api_key_sms'],
   'msg' => 'Thank you for shopping at ' . $S['shop_name']   .
         ' Total: ' . $total . ' BDT ' .
         '' . (intval($points) > 0 ? ' Got ' . $points . ' Points ' : '').
         ' Contact: ' . ($S['shop_phone'] ?? ''),
    'to'      => $customerPhone, 
];

function request($url, $params) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}


//make demo response for testing without actually sending SMS

//if sms balance is less than 1, simulate an error response
if (($S['sms_balance'] ?? 0) < 1) {
    $response = [
        'error' => 1,
        'msg' => 'Insufficient SMS balance',
    ];
} else {
$response = json_decode(request($url, $params), true);
// $response = [
//     'error' => 0,
//     'msg' => 'SMS sent successfully',
//     'data' => [
//         'request_id' => 'abc123'
//     ]
// ];
}


if (isset($response['error']) && $response['error'] === 0) {
    // SMS accepted by API
    $requestId = $response['data']['request_id'] ?? null;
    $rate = strlen($params['msg']) <= 160 ? 1 : ceil(strlen($params['msg']) / 160);


    logAction('SINGLE_SMS', 'SMS', $rate, "To: $params[to] | Msg: $params[msg]");

    $currentBalance = dbFetch('SELECT value FROM settings WHERE `key` = ?', ['sms_balance'])['value'] ?? 0;
    dbQuery('UPDATE settings SET value = value - ? WHERE `key` = ?', [$rate, 'sms_balance']);
    // Update balance in settings
    

    // Optional: check delivery report later (not immediately)
    /*
    $reportUrl = 'https://api.sms.net.bd/report/request/' . $requestId;
    $reportParams = ['api_key' => '64v0VK2aq7AddQlE40Oh4T4oXpkgL3VBXxlc4W6l'];
    $reportResponse = json_decode(request($reportUrl, $reportParams), true);
    if (isset($reportResponse['error']) && $reportResponse['error'] === 0) {
        logAction('SMS', 'pos', $saleId, 'Delivery report: ' . $reportResponse['data']['status']);
    } else {
        logAction('SMS', 'pos', $saleId, 'Error fetching report: ' . $reportResponse['msg']);
    }
    */
} else {
    logAction('SINGLE_SMS', 'SMS', 1, 'Error sending SMS: ' . ($response['msg'] ?? 'Unknown error'));
}
            }
       

 redirect('invoice', ['id' => $saleId]);
          }
         
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
// Make sure to query brands here
$brands     = dbFetchAll('SELECT * FROM brands ORDER BY name');

$products   = dbFetchAll(
    "SELECT p.id, p.product_id, p.name, p.category_id, p.brand_id,
            MIN(v.price) AS min_price, SUM(v.quantity) AS total_stock
     FROM products p LEFT JOIN product_variants v ON v.product_id = p.id
     WHERE p.active = 1 GROUP BY p.id ORDER BY p.name"
);

// ── Load Existing Sale Data (If ID is provided) ─────────────────
$loadSaleData = null;
if (!empty($_GET['id'])) {
    $saleId = (int)$_GET['id'];
    $sale = dbFetch('SELECT * FROM sales WHERE id = ?', [$saleId]);
    
    if ($sale) {
        // Fetch Customer details if available
        if ($sale['customer_id']) {
            $customer = dbFetch('SELECT name, phone, points FROM customers WHERE id = ?', [$sale['customer_id']]);
            if ($customer) {
                $sale['customer_name'] = $customer['name'];
                $sale['customer_phone'] = $customer['phone'];
                $sale['customer_points'] = $customer['points'];
            }
        }
        
        // Fetch Cart Items
        $items = dbFetchAll(
            'SELECT si.*, v.quantity AS max_qty, v.barcode 
             FROM sale_items si 
             LEFT JOIN product_variants v ON v.id = si.variant_id 
             WHERE si.sale_id = ?', 
            [$saleId]
        );
        $sale['items'] = $items;
        $loadSaleData = $sale;
    }
}

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
/* ── Modern POS 3-Column Theme ── */
:root {
  --pos-bg: #121212;
  --pos-panel: #1e1e1e;
  --pos-panel-alt: #252525;
  --pos-border: #333333;
  --pos-text: #e0e0e0;
  --pos-text-muted: #888888;
  --pos-accent: #6c5ce7;
  --pos-accent-hover: #5a4bcf;
  --pos-danger: #ff4757;
  --pos-success: #2ed573;
  --pos-radius: 10px;
}

body, html {
  background-color: var(--pos-bg) !important;
  color: var(--pos-text) !important;
  /* Assuming your top nav is ~70px, we lock screen scrolling on desktop */
}

/* Custom Scrollbars */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #444; border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: #666; }

/* ── Container Layout (3 Columns) ── */
.pos-wrapper {
  display: flex;
  gap: 15px;
  /* Height lock for desktop to ensure columns scroll independently */
  height: calc(100vh - 90px); 
  margin-top: 10px;
  overflow: hidden;
}

/* Base Column Style */
.pos-col {
  background: var(--pos-panel);
  border-radius: var(--pos-radius);
  border: 1px solid var(--pos-border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* Column 1: Products (Largest) */
.col-products { flex: 2.2; min-width: 0; }

/* ── Column 1: Filters Base ── */
/* ── Mobile Filters Grid Override ── */
  .pos-filters {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important; /* Two equal columns */
    gap: 8px !important;
  }

  /* Nuke the inline flex and min-width styles on mobile */
  .pos-filters .pos-input {
    flex: none !important; 
    min-width: 0 !important; 
    width: 100% !important; 
  }

  /* Make Search bar span the entire top row */
  .pos-filters #productSearch {
    grid-column: 1 / -1 !important; 
  }

  /* Make Barcode input span the entire bottom row for easy tapping */
  .pos-filters #barcodeInput {
    grid-column: 1 / -1 !important; 
  }
/* ── Force Dark Theme on Filters (Override Template Defaults) ── */
.pos-filters {
  background-color: #1a1a1a !important;
  border-bottom: 1px solid var(--pos-border) !important;
  padding: 10px;
}

/* Hyper-specific targeting to beat Bootstrap/external CSS */
.pos-filters input[type="text"],
.pos-filters select,
.pos-filters .pos-input {
  background-color: #2a2a2a !important;
  color: #ffffff !important;
  border: 1px solid var(--pos-border) !important;
  border-radius: 6px !important;
  /* Reset any browser/template shadows or appearances */
  -webkit-appearance: none !important;
  -moz-appearance: none !important;
  appearance: none !important; 
    padding: 10px;
}

/* Fix the placeholder text so it's visible on the dark background */
.pos-filters input::placeholder {
  color: #aaaaaa !important;
  opacity: 1 !important;
}

/* Force the dropdown options themselves to be dark */
.pos-filters select option {
  background-color: #2a2a2a !important;
  color: #ffffff !important;
}

/* Focus states */
.pos-filters input:focus,
.pos-filters select:focus {
  border-color: var(--pos-accent) !important;
  box-shadow: 0 0 0 2px rgba(108, 92, 231, 0.2) !important;
  outline: none !important;
}

/* Column 2: Cart (Medium) */
.col-cart { flex: 1.3; min-width: 0; }
/* ── Column 3: Checkout Container ── */
/* ── Ultra-Compact Checkout Redesign ── */
.col-checkout { 
  flex: 1.2; 
  min-width: 310px; 
  display: grid !important; 
  grid-template-rows: auto 1fr auto !important; /* Header, Scrollable Body, Fixed Footer */
  height: 100% !important;
  overflow: hidden !important;
  background: var(--pos-panel);
  border-radius: var(--pos-radius);
  border: 1px solid var(--pos-border);
}

/* Tighter inputs for checkout */
.pos-input-sm {
  background-color: #2a2a2a !important;
  color: #fff !important;
  border: 1px solid var(--pos-border) !important;
  border-radius: 4px;
  padding: 4px 8px;
  width: 100%;
  font-size: 0.8rem;
  height: 28px;
}
.pos-input-sm:focus { border-color: var(--pos-accent) !important; outline: none; }

/* Tighter Checkout Sections */
.checkout-section { padding: 8px 10px; border-bottom: 1px solid var(--pos-border); }
.checkout-section:last-child { border-bottom: none; }
.checkout-body { overflow-y: auto !important; min-height: 0 !important; }
.checkout-footer { padding: 10px; background: #1a1a1a; border-top: 1px solid var(--pos-border); }

/* Compact Labels & Summary */
.compact-label { display: block; font-size: 0.65rem; color: var(--pos-text-muted); margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
.summary-row { display: flex; justify-content: space-between; margin-bottom: 3px; font-size: 0.8rem; color: #ccc; }
.summary-total-compact { display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: bold; color: #fff; margin-bottom: 8px; }

/* Tighter input groups */
.input-group-tight { display: flex; align-items: stretch; height: 28px; }
.input-group-tight .pos-input-sm:first-child { border-radius: 4px 0 0 4px; border-right: none; width: 40px; text-align: center; padding: 4px 0; }
.input-group-tight .pos-input-sm:last-child { border-radius: 0 4px 4px 0; flex: 1; }

/* Tighter Payment Buttons */
.pay-opt-wrap { padding: 4px; border-radius: 4px; font-size: 0.75rem; flex: 1; text-align: center; border: 1px solid var(--pos-border); cursor: pointer; background: var(--pos-panel-alt); color: var(--pos-text); transition: 0.2s; }
.pay-opt-wrap.pay-selected { background: rgba(108, 92, 231, 0.15); border-color: var(--pos-accent); color: #fff; }
.btn-action-sm { width: 100%; padding: 6px; border: none; border-radius: 4px; font-weight: bold; font-size: 0.85rem; cursor: pointer; color: #fff; height: 32px; }


.product-grid {
  flex: 1;
  overflow-y: auto;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
  gap: 12px;
  padding: 15px;
  align-content: start;
}
.product-tile {
  background: var(--pos-panel-alt);
  border: 1px solid var(--pos-border);
  border-radius: var(--pos-radius);
  padding: 12px;
  cursor: pointer;
  text-align: center;
  user-select: none;
  transition: all 0.15s ease;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 110px;
}
.product-tile:hover {
  border-color: var(--pos-accent);
  background: #2c2c2c;
  transform: translateY(-2px);
}
.product-tile:active { transform: scale(0.96); }
.product-tile.out-of-stock { opacity: 0.4; cursor: not-allowed; filter: grayscale(1); }
.tile-name { font-weight: 600; font-size: 0.88rem; margin-bottom: 6px; color: #fff; line-height: 1.3;}
.tile-meta { display: flex; justify-content: space-between; align-items: center; margin-top: auto;}
.tile-price { color: var(--pos-success); font-weight: bold; font-size: 0.95rem;}
.tile-stock { font-size: 0.7rem; color: var(--pos-text-muted); background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px; }

/* ── Column 2: Cart ── */
.cart-items {
  flex: 1;
  overflow-y: auto;
  padding: 10px;
}
.cart-item-row {
  display: flex; 
  justify-content: space-between; 
  align-items: center;
  padding: 10px; 
  border-bottom: 1px solid var(--pos-border); 
  font-size: 0.85rem;
  background: var(--pos-panel-alt);
  border-radius: 8px;
  margin-bottom: 8px;
}
.cart-item-info { flex: 1; padding-right: 10px; }
.cart-item-title { font-weight: 600; color: #fff; display: block; margin-bottom: 4px; }
.cart-item-meta { font-size: 0.75rem; color: var(--pos-text-muted); }
.cart-item-controls { display: flex; align-items: center; gap: 8px; }
.qty-input {
  width: 45px; text-align: center; padding: 4px; 
  background: #333; border: 1px solid #555; 
  color: #fff; border-radius: 4px; font-weight: bold;
}
.cart-item-price { color: var(--pos-success); font-weight: bold; width: 60px; text-align: right; }
.btn-remove {
  background: rgba(255, 71, 87, 0.1); color: var(--pos-danger);
  border: none; border-radius: 4px; width: 26px; height: 26px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: 0.2s;
}
.btn-remove:hover { background: var(--pos-danger); color: #fff; }

/* ── Column 3: Checkout ── */
.checkout-section { padding: 15px; border-bottom: 1px solid var(--pos-border); }
.checkout-section:last-child { border-bottom: none; }
.form-group { margin-bottom: 10px; }
.form-group label { display: block; font-size: 0.75rem; color: var(--pos-text-muted); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;}

.summary-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.9rem; color: #ccc; }
.summary-total { 
  display: flex; justify-content: space-between; 
  font-size: 1.4rem; font-weight: bold; 
  margin-top: 10px; padding-top: 10px; 
  border-top: 1px dashed var(--pos-border); 
  color: #fff; 
}

.pay-opt-wrap {
  display: flex; align-items: center; justify-content: center; gap: 6px;
  padding: 8px; border-radius: 6px; border: 1px solid var(--pos-border);
  cursor: pointer; font-size: 0.85rem; flex: 1; text-align: center;
  background: var(--pos-panel-alt); color: var(--pos-text);
  transition: 0.2s;
}
.pay-opt-wrap.pay-selected {
  background: rgba(108, 92, 231, 0.15); 
  border-color: var(--pos-accent); 
  color: #fff;
}
.btn-action {
  width: 100%; padding: 12px; border: none; border-radius: 6px;
  font-weight: bold; font-size: 0.95rem; cursor: pointer; color: #fff;
}

/* ── Variant Modal ── */
.pos-modal-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center;
  z-index: 9999; opacity: 0; visibility: hidden; transition: 0.2s;
}
.pos-modal-overlay.active { opacity: 1; visibility: visible; }
.pos-modal {
  background: var(--pos-panel); border: 1px solid var(--pos-border); border-radius: var(--pos-radius);
  width: 90%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); display: flex; flex-direction: column;
}
.pos-modal-header { padding: 15px; border-bottom: 1px solid var(--pos-border); display: flex; justify-content: space-between; align-items: center; }
.pos-modal-title { font-size: 1.1rem; font-weight: bold; color: #fff; margin: 0; }
.pos-modal-close { background: transparent; border: none; color: var(--pos-text-muted); font-size: 1.5rem; cursor: pointer; line-height: 1; }
.pos-modal-body { padding: 15px; max-height: 60vh; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; }
.variant-btn {
  background: var(--pos-panel-alt); border: 1px solid var(--pos-border); border-radius: 6px;
  padding: 12px; color: var(--pos-text); display: flex; justify-content: space-between; align-items: center;
  cursor: pointer; text-align: left; transition: 0.1s;
}
.variant-btn:hover { border-color: var(--pos-accent); background: #2a2a2a; }
.variant-btn-price { color: var(--pos-success); font-weight: bold; }
/* ── Mobile View Overrides ── */
@media (max-width: 992px) {
  /* Restore normal page scrolling */
  body, html { 
    overflow: auto !important; 
    height: auto !important; 
  }
  
  /* Release the strict desktop wrapper height */
  .pos-wrapper { 
    flex-direction: column; 
    height: auto !important; 
    max-height: none !important; 
    overflow: visible !important; 
    margin-bottom: 40px !important; 
  }
  
  /* Make all columns stack and adapt to their content */
  .pos-col { 
    flex: none !important; 
    width: 100% !important; 
    height: auto !important;
    max-height: none !important;
    overflow: visible !important;
  }
  
  /* Give the product grid a reasonable mobile height so they can scroll within it */
  .product-grid { 
    max-height: 50vh; 
    overflow-y: auto !important;
  }
  
  /* Give the cart a fixed scrollable height so it doesn't push checkout too far down */
  .cart-items { 
    max-height: 40vh; 
    overflow-y: auto !important; 
  }
  
  /* Kill the CSS Grid on the checkout column for mobile so it stacks naturally */
  .col-checkout { 
    display: flex !important; 
    flex-direction: column !important;
  }
  
  /* Let the checkout body expand to fit all elements without internal scrolling */
  .checkout-body { 
    overflow-y: visible !important; 
    min-height: auto !important; 
  }
}
</style>


<div class="pos-wrapper" id="posContainer">

  <div class="pos-col col-products">
    <div class="pos-filters">
      <input type="text" id="productSearch" class="pos-input" placeholder="🔍 Search products..." >
      
      <select id="categoryFilter" class="pos-input" >
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
        <?php endforeach ?>
      </select>

      <select id="brandFilter" class="pos-input" >
        <option value="">All Brands</option>
        <?php foreach ($brands as $b): ?>
          <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
        <?php endforeach ?>
      </select>

<input type="text" id="barcodeInput" class="pos-input" placeholder="||| Barcode">
    </div>
    
    <div class="product-grid" id="productGrid">
      <?php foreach ($products as $p): ?>
      <div class="product-tile <?= $p['total_stock'] <= 0 ? 'out-of-stock' : '' ?>"
           data-name="<?= e(strtolower($p['name'])) ?>" 
           data-category="<?= $p['category_id'] ?>"
           data-brand="<?= $p['brand_id'] ?>"
           onclick="<?= $p['total_stock'] > 0 ? "fetchVariantsAndAdd({$p['id']})" : '' ?>">
        <div class="tile-name"><?= e($p['name']) ?></div>
        <div class="tile-meta">
          <span class="tile-price"><?= $cur . number_format((float)$p['min_price'], 2) ?></span>
          <span class="tile-stock"><?= (int)$p['total_stock'] ?> Left</span>
        </div>
      </div>
      <?php endforeach ?>
    </div>
  </div>

  <div class="pos-col col-cart">
    <div class="pos-header">
      <span style="color: var(--pos-accent);">🛒 Shopping Cart</span>
      <span id="cartCount" style="background: var(--pos-accent); color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;">0</span>
    </div>
    
    <div class="cart-items" id="cartItemsContainer">
      <div style="height: 100%; display: flex; align-items: center; justify-content: center; color: var(--pos-text-muted);">
        Scan a barcode or select a product
      </div>
    </div>
  </div>

  <div class="pos-col col-checkout">
    <div class="pos-header" style="padding: 8px 10px;"><span>📄 Checkout</span></div>

    <div class="checkout-body">
      <div class="checkout-section">
        <label class="compact-label">Customer Details</label>
        <div style="display:flex; gap:6px; margin-bottom: 6px;">
          <input type="text" id="customerPhone" class="pos-input-sm" placeholder="Phone" onblur="lookupCustomer(this.value)" style="flex:1" >
          <input type="text" id="customerName" class="pos-input-sm" placeholder="Name..." style="flex:1.5">
        </div>
        <input type="hidden" id="customerId">

        <?php if ($pointsEnabled): ?>
        <div style="display:flex; justify-content: space-between; align-items: center; background: rgba(255, 215, 0, 0.05); padding: 4px 8px; border-radius: 4px; border: 1px solid rgba(255, 215, 0, 0.1);">
          <span style="color:#ffd700; font-size: 0.75rem;">⭐ <strong id="pointsBadge">0</strong></span>
          <div style="display:flex; align-items:center; gap:6px;">
            <label style="cursor:pointer; font-size: 0.7rem; color: #ccc; display:flex; align-items:center; gap:2px; margin:0;">
              <input type="checkbox" id="usePointsToggle" onchange="togglePoints(this.checked)"> Redeem
            </label>
            <input type="number" id="pointsUsed" class="pos-input-sm" placeholder="Pts" min="0" oninput="liveValidatePoints()" style="width: 50px; display:none; text-align:center;">
          </div>
        </div>
        <?php endif ?>
      </div>

      <?php if ($discEnabled || $vatEnabled): ?>
      <div class="checkout-section">
        <div style="display:flex; gap:8px;">
          <?php if ($discEnabled): ?>
          <div style="flex:1; margin:0;">
            <label class="compact-label">Discount</label>
            <div class="input-group-tight">
              <?php if ($discType === 'both'): ?>
              <select id="discountType" class="pos-input-sm" onchange="liveValidateDiscount()">
                <option value="percent">%</option><option value="amount"><?= $cur ?></option>
              </select>
              <?php else: ?>
              <input type="hidden" id="discountType" value="<?= e($discType) ?>">
              <span class="pos-input-sm" style="background:#222; width:30px; text-align:center; border-right:none; border-radius: 4px 0 0 4px; color:#aaa;"><?= $discType==='percent'?'%':$cur ?></span>
              <?php endif ?>
              <input type="number" id="discountPct" class="pos-input-sm" value="<?= $discDefault ?>" min="0" step="0.01" oninput="liveValidateDiscount()">
            </div>
          </div>
          <?php else: ?>
          <input type="hidden" id="discountType" value="percent"><input type="hidden" id="discountPct" value="0">
          <?php endif ?>

          <?php if ($vatEnabled): ?>
          <div style="flex:1; margin:0;">
            <label class="compact-label">VAT</label>
            <div class="input-group-tight">
              <select id="vatType" class="pos-input-sm" onchange="liveValidateVAT()">
                <option value="percent">%</option><option value="amount"><?= $cur ?></option>
              </select>
              <input type="number" id="vatRate" class="pos-input-sm" value="<?= $vatDefault ?>" min="0" step="0.01" oninput="liveValidateVAT()">
            </div>
          </div>
          <?php else: ?>
          <input type="hidden" id="vatType" value="percent"><input type="hidden" id="vatRate" value="0">
          <?php endif ?>
        </div>
      </div>
      <?php endif ?>

      <div class="checkout-section" style="flex: 1; display:flex; flex-direction:column; justify-content:flex-end;">
        <div class="summary-row"><span>Subtotal</span><span id="summarySubtotal"><?= $cur ?>0.00</span></div>
        <?php if ($discEnabled): ?><div class="summary-row" style="color:var(--pos-danger);"><span>Discount</span><span id="summaryDiscount">-<?= $cur ?>0.00</span></div><?php endif ?>
        <?php if ($pointsEnabled): ?><div class="summary-row" style="color:#ffd700;"><span>Points Val</span><span id="summaryPoints">-<?= $cur ?>0.00</span></div><?php endif ?>
        <?php if ($vatEnabled): ?><div class="summary-row" style="color:var(--pos-success);"><span>VAT</span><span id="summaryVat">+<?= $cur ?>0.00</span></div><?php endif ?>
      </div>
    </div>

    <div class="checkout-footer">
      <div class="summary-total-compact">
        <span>TOTAL</span><span id="summaryTotal"><?= $cur ?>0.00</span>
      </div>
      
      <form method="POST" id="checkoutForm" style="margin:0;">
        <input type="hidden" name="action" value="finalize_sale">
        <input type="hidden" name="customer_id" id="hdCustomerId">
        <input type="hidden" name="customer_phone" id="hdCustomerPhone">
        <input type="hidden" name="customer_name" id="hdCustomerName">
        <input type="hidden" name="discount_type" id="hdDiscType">
        <input type="hidden" name="discount_val" id="hdDiscVal">
        <input type="hidden" name="vat_type" id="hdVatType">
        <input type="hidden" name="vat_val" id="hdVatVal">
        <input type="hidden" name="points_used" id="hdPointsUsed">
        <input type="hidden" name="cart_json" id="hiddenCartJson">

        <div style="display:flex; gap:4px; margin-bottom: 6px;">
          <?php foreach(['cash'=>'💵 Cash','card'=>'💳 Card','transfer'=>'🏦 Bank'] as $pv=>$pl): ?>
          <label class="pay-opt-wrap <?= $pv==='cash'?'pay-selected':'' ?>">
            <input type="checkbox" name="payment_methods[]" value="<?= $pv ?>" class="pay-check" style="display:none;" <?= $pv==='cash'?'checked':'' ?>
                   onchange="this.closest('.pay-opt-wrap').classList.toggle('pay-selected',this.checked)">
            <?= $pl ?>
          </label>
          <?php endforeach ?>
        </div>

        <input type="text" name="notes" class="pos-input-sm" placeholder="Order note..." style="margin-bottom:6px;">
        
        <div style="display:flex; gap:6px;">
          <label class="pay-opt-wrap">
            <input type="checkbox" name="sms" class="pay-check" style="display:none;" onchange="this.closest('.pay-opt-wrap').classList.toggle('pay-selected',this.checked)" checked>
            <span class="pay-opt" style="font-size:10px;"><?= intval($S['sms_balance']) ?> SMS</span>
          </label>
          <button type="submit" name="submit_type" value="draft" class="btn-action-sm" style="background:#f39c12; flex:1;" onclick="return processCheckout()">📋 Draft</button>
          <button type="submit" name="submit_type" value="complete" class="btn-action-sm" style="background:var(--pos-success); flex:2;" onclick="return processCheckout()">✅ Finalize</button>
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
      <div class="pos-modal-body" id="variantModalBody"></div>
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

// Inject Load Sale Data (If present)
const loadedSale = <?= $loadSaleData ? json_encode($loadSaleData) : 'null' ?>;

// ============================================================================
// 2. THE CART ENGINE
// ============================================================================
const Cart = {
  items: [],
  
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
        qty: parseInt(variant.qty) || 1, // Accounts for pre-loaded quantity
        max_qty: parseInt(variant.max_qty || variant.quantity),
        size: variant.size,
        color: variant.color
      });
    }
    this.render();
  },

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

  getAll: function() { return this.items; },
  getSubtotal: function() { return this.items.reduce((sum, item) => sum + (item.price * item.qty), 0); },

  render: function() {
    const container = document.getElementById('cartItemsContainer');
    document.getElementById('cartCount').textContent = this.items.length;
    
    if (this.items.length === 0) {
      container.innerHTML = '<div style="height: 100%; display: flex; align-items: center; justify-content: center; color: var(--pos-text-muted);">Cart is empty</div>';
    } else {
      let html = '';
      this.items.forEach((item, index) => {
        const variantInfo = (item.size || item.color) ? `<span class="cart-item-meta">${item.size||''} ${item.color||''}</span>` : '';
        html += `
          <div class="cart-item-row">
            <div class="cart-item-info">
              <span class="cart-item-title">${item.name}</span>
              ${variantInfo}
            </div>
            <div class="cart-item-controls">
              <span class="cart-item-price">${CURRENCY}${item.price.toFixed(2)}</span>
              <input type="number" class="qty-input" value="${item.qty}" min="1" onchange="Cart.updateQty(${index}, this.value)">
              <button type="button" class="btn-remove" onclick="Cart.remove(${index})">×</button>
            </div>
          </div>
        `;
      });
      container.innerHTML = html;
    }
    
    liveValidateDiscount();
    liveValidateVAT();
    liveValidatePoints(); 
    this.updateTotals();
  },

  updateTotals: function() {
    const subtotal = this.getSubtotal();
    
    const discType = document.getElementById('discountType')?.value || 'percent';
    const discVal = parseFloat(document.getElementById('discountPct')?.value) || 0;
    const discAmt = discType === 'percent' ? subtotal * (discVal / 100) : Math.min(discVal, subtotal);
    
    const ptsUsed = parseInt(document.getElementById('pointsUsed')?.value) || 0;
    const ptsValue = ptsUsed * POINTS_RATE;

    const afterDisc = Math.max(0, subtotal - discAmt - ptsValue);

    const vatType = document.getElementById('vatType')?.value || 'percent';
    const vatVal = parseFloat(document.getElementById('vatRate')?.value) || 0;
    const vatAmt = vatType === 'percent' ? afterDisc * (vatVal / 100) : vatVal;

    const total = afterDisc + vatAmt;

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

function lookupCustomer(phone) {
  if (!phone) {
    document.getElementById('customerId').value = '';
    if(document.getElementById('pointsBadge')) document.getElementById('pointsBadge').textContent = '0';
    liveValidatePoints();
    return;
  }
  fetch(`?page=pos_edit&action=lookup_customer&phone=${encodeURIComponent(phone)}`)
    .then(r => r.json())
    .then(data => {
      if (data.id) {
        document.getElementById('customerId').value = data.id;
        document.getElementById('customerName').value = data.name;
        if(document.getElementById('pointsBadge')) document.getElementById('pointsBadge').textContent = data.points || 0;
      } else {
        document.getElementById('customerId').value = '';
        if(document.getElementById('pointsBadge')) document.getElementById('pointsBadge').textContent = '0 (New)';
      }
      liveValidatePoints();
    });
}

function fetchVariantsAndAdd(productId) {
  fetch(`?page=pos_edit&action=get_variants&product_id=${productId}`)
    .then(r => r.json())
    .then(data => {
      if(data.length === 0) {
        alert("Out of stock or invalid product.");
      } else if (data.length === 1) {
        Cart.add(data[0]);
      } else {
        openVariantModal(data);
      }
    })
    .catch(err => {
      console.error("Error fetching variants:", err);
      alert("Failed to load product details.");
    });
}

// Modal Logic
function openVariantModal(variants) {
  const overlay = document.getElementById('variantModalOverlay');
  const body = document.getElementById('variantModalBody');
  const title = document.getElementById('variantModalTitle');
  
  title.textContent = variants[0].name;
  body.innerHTML = ''; 
  
  variants.forEach(variant => {
    let attrs = [];
    if (variant.size) attrs.push(`Size: ${variant.size}`);
    if (variant.color) attrs.push(`Color: ${variant.color}`);
    let label = attrs.length > 0 ? attrs.join(' | ') : 'Default Variant';
    
    const btn = document.createElement('button');
    btn.className = 'variant-btn';
    btn.innerHTML = `
      <div>
        <span style="font-weight:600; color:#fff; display:block; margin-bottom: 2px;">${label}</span>
        <span style="font-size:0.75rem; color:var(--pos-text-muted);">Stock: ${variant.quantity}</span>
      </div>
      <div class="variant-btn-price">${CURRENCY}${parseFloat(variant.price).toFixed(2)}</div>
    `;
    
    btn.onclick = () => {
      Cart.add(variant);
      closeVariantModal();
    };
    body.appendChild(btn);
  });
  
  overlay.classList.add('active');
}

function closeVariantModal() {
  document.getElementById('variantModalOverlay').classList.remove('active');
}

document.getElementById('variantModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeVariantModal();
});

function searchByBarcode(bc) {
  fetch(`?page=pos_edit&action=barcode_lookup&barcode=${encodeURIComponent(bc)}`)
    .then(r => r.json())
    .then(data => {
      if (data.variant_id) {
        Cart.add(data);
      } else {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.id = 'toastContainer';
        toast.role = 'alert';
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.setAttribute('data-bs-autohide', 'true');
        toast.setAttribute('data-bs-delay', '3000');

        toast.style.position = 'fixed';
        toast.style.bottom = '10px';
        toast.style.left = '50%';
        toast.style.transform = 'translateX(-50%)';
        toast.style.backgroundColor = '#fff';
        toast.style.borderRadius = '5px';
        toast.style.padding = '10px';

        toast.innerHTML = `
          <div class="toast-body">
            Barcode not found or out of stock.
          </div>
        `;

        document.body.appendChild(toast);

        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();

       
      }
    });
}




const input = document.getElementById('barcodeInput');
let timer;

input.addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    searchByBarcode(input.value);
    input.value = '';
  }
});

input.addEventListener('input', () => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    if (input.value.trim() !== '') {
      searchByBarcode(input.value);
      input.value = '';
    }
  }, 3000);
});
// Validation & Submissions
function liveValidatePoints() {
  const el = document.getElementById('pointsUsed');
  if (!el) return;
  
  let val = parseInt(el.value);
  if (isNaN(val) || val < 0) { val = 0; }

  const customerPts = parseInt(document.getElementById('pointsBadge')?.textContent) || 0;
  const cartSubtotal = Cart.getSubtotal(); 
  const maxRedeemableCash = cartSubtotal * (MAX_REDEEM_PCT / 100);
  const maxRedeemablePts = Math.floor(maxRedeemableCash / POINTS_RATE);
  
  const absoluteMax = Math.min(customerPts, maxRedeemablePts);

  if (val > absoluteMax) { el.value = absoluteMax; }
  Cart.updateTotals();
}

function liveValidateDiscount() {
  const el = document.getElementById('discountPct');
  const typeEl = document.getElementById('discountType');
  if (!el || !typeEl) return;
  
  let val = parseFloat(el.value);
  if (isNaN(val) || val < 0) val = 0;

  const type = typeEl.value;
  if (type === 'percent' && val > DISC_MAX_PCT) { el.value = DISC_MAX_PCT; } 
  else if (type === 'amount' && DISC_MAX_AMT > 0 && val > DISC_MAX_AMT) { el.value = DISC_MAX_AMT; }
  Cart.updateTotals();
}

function liveValidateVAT() {
  const el = document.getElementById('vatRate');
  const typeEl = document.getElementById('vatType');
  if (!el || !typeEl) return;
  
  let val = parseFloat(el.value);
  if (isNaN(val) || val < 0) val = 0;

  if (typeEl.value === 'percent' && val > 100) { el.value = 100; }
  Cart.updateTotals();
}

function togglePoints(on) {
  const el = document.getElementById('pointsUsed');
  if (el) {
    if(on) { 
      el.style.display = 'block'; 
    } else { 
      el.style.display = 'none'; 
      el.value = ''; 
      liveValidatePoints(); 
    }
  }
}

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

  document.getElementById('hdCustomerId').value    = document.getElementById('customerId')?.value || '';
  document.getElementById('hdCustomerPhone').value = document.getElementById('customerPhone')?.value || '';
  //if customer phone is entered but not start with 01 and total 11 digit then dont allow to submit the form and show an alert message
  const phoneEl = document.getElementById('customerPhone');
  if (phoneEl && phoneEl.value.trim() !== '') {
    const phoneVal = phoneEl.value.trim();
    const phonePattern = /^01\d{9}$/;
    if (!phonePattern.test(phoneVal)) {
      alert("Please enter a valid phone number starting with '01' and 11 digits long.");
      phoneEl.focus();
      return false;
    }
  }
  document.getElementById('hdCustomerName').value  = document.getElementById('customerName')?.value || '';
  
  document.getElementById('hdDiscType').value      = document.getElementById('discountType')?.value || 'percent';
  document.getElementById('hdDiscVal').value       = document.getElementById('discountPct')?.value || 0;
  document.getElementById('hdVatType').value       = document.getElementById('vatType')?.value || 'percent';
  document.getElementById('hdVatVal').value        = document.getElementById('vatRate')?.value || 0;
  document.getElementById('hdPointsUsed').value    = document.getElementById('pointsUsed')?.value || 0;
  
  document.getElementById('hiddenCartJson').value = JSON.stringify(Cart.getAll());
  
  return true; 
}

// Setup Event Listeners & Initialize
document.addEventListener('DOMContentLoaded', () => {
  ['pointsUsed','discountPct','vatRate'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('keydown', (e) => {
        if (['-', 'e', 'E', '+'].includes(e.key)) e.preventDefault();
      });
    }
  });

  // Check if there is data to preload
  if (loadedSale) {
    if (loadedSale.items && loadedSale.items.length > 0) {
      loadedSale.items.forEach(item => {
        Cart.add({
          variant_id: item.variant_id,
          name: item.product_name,
          price: item.unit_price,
          qty: item.qty,
          max_qty: item.max_qty || item.qty,
          size: item.size || '',
          color: item.color || ''
        });
      });
    }

    if (loadedSale.customer_id) {
      document.getElementById('customerId').value = loadedSale.customer_id;
      document.getElementById('customerPhone').value = loadedSale.customer_phone || '';
      document.getElementById('customerName').value = loadedSale.customer_name || '';
      const ptsBadge = document.getElementById('pointsBadge');
      if (ptsBadge) ptsBadge.textContent = loadedSale.customer_points || 0;
    }

    if (loadedSale.points_used > 0) {
      const pToggle = document.getElementById('usePointsToggle');
      const pInput = document.getElementById('pointsUsed');
      if (pToggle && pInput) {
        pToggle.checked = true;
        togglePoints(true);
        pInput.value = loadedSale.points_used;
      }
    }

    const dType = document.getElementById('discountType');
    const dPct = document.getElementById('discountPct');
    if (dType && dPct) {
      if (parseFloat(loadedSale.discount_pct) > 0) {
        dType.value = 'percent';
        dPct.value = parseFloat(loadedSale.discount_pct);
      } else if (parseFloat(loadedSale.discount_amount) > 0) {
        dType.value = 'amount';
        dPct.value = parseFloat(loadedSale.discount_amount);
      }
    }

    const vType = document.getElementById('vatType');
    const vRate = document.getElementById('vatRate');
    if (vType && vRate) {
      if (parseFloat(loadedSale.vat_rate) > 0) {
        vType.value = 'percent';
        vRate.value = parseFloat(loadedSale.vat_rate);
      } else if (parseFloat(loadedSale.vat_amount) > 0) {
        vType.value = 'amount';
        vRate.value = parseFloat(loadedSale.vat_amount);
      }
    }

    const notesInput = document.querySelector('input[name="notes"]');
    if (notesInput) {
      notesInput.value = loadedSale.notes || '';
    }
  }

  Cart.render();

const navToggle  = document.getElementById('navToggle');
const sideNav    = document.getElementById('sideNav');
const navOverlay = document.getElementById('navOverlay');

// Function to open/close
function toggleMenu() {
  sideNav.classList.toggle('open');
  navOverlay.classList.toggle('open');
}

// Function to force close
function closeMenu() {
  sideNav.classList.remove('open');
  navOverlay.classList.remove('open');
}

// Event Listeners
navToggle?.addEventListener('click', (e) => {
  e.preventDefault();
  toggleMenu();
});

navOverlay?.addEventListener('click', closeMenu);

// Close if a link is clicked
document.querySelectorAll('.nav-link').forEach(link => {
  link.addEventListener('click', closeMenu);
});

  // Search and Filter logic
  const pSearch = document.getElementById('productSearch');
  const cFilter = document.getElementById('categoryFilter');
  const bFilter = document.getElementById('brandFilter'); // NEW Brand Filter

  function filterProducts() {
    const searchTerm = pSearch.value.toLowerCase();
    const categoryId = cFilter.value;
    const brandId    = bFilter.value;
    
    document.querySelectorAll('.product-tile').forEach(tile => {
      const name = tile.dataset.name || '';
      const category = tile.dataset.category || '';
      const brand = tile.dataset.brand || '';
      
      const matchesSearch = name.includes(searchTerm);
      const matchesCategory = !categoryId || category === categoryId;
      const matchesBrand = !brandId || brand === brandId;
      
      tile.style.display = (matchesSearch && matchesCategory && matchesBrand) ? 'flex' : 'none';
    });
  }

  if(pSearch) pSearch.addEventListener('input', filterProducts);
  if(cFilter) cFilter.addEventListener('change', filterProducts);
  if(bFilter) bFilter.addEventListener('change', filterProducts);
});

document.addEventListener('DOMContentLoaded', () => {
  const discTypeEl = document.getElementById('discountType');
  const vatTypeEl = document.getElementById('vatType');

  discTypeEl.value = localStorage.getItem('discountType') || discTypeEl.value;
  vatTypeEl.value = localStorage.getItem('vatType') || vatTypeEl.value;

  discTypeEl.addEventListener('change', () => localStorage.setItem('discountType', discTypeEl.value));
  vatTypeEl.addEventListener('change', () => localStorage.setItem('vatType', vatTypeEl.value));
});

//on type enter on the keyboard go  to customerPhone to customerName to notes and on notes enter submit the form
document.addEventListener('keydown', function(e) {
  const active = document.activeElement;
  if (e.key === 'Enter') {
    if (active.id === 'customerPhone') {
      e.preventDefault();
      document.getElementById('customerName').focus();
    } else if (active.id === 'customerName') {
      e.preventDefault();
      document.querySelector('input[name="notes"]').focus();
    } else if (active.name === 'notes') {
      e.preventDefault();
      document.querySelector('button[name="submit_type"][value="complete"]').click();
    }
  }
});

//default focus on the barcode input when the page loads
document.addEventListener('DOMContentLoaded', () => {
  const barcodeInput = document.getElementById('barcodeInput');
  if (barcodeInput) {
    barcodeInput.focus();
  }
});

</script>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>