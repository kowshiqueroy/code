<?php
// ============================================================
// modules/settings/settings.php — Admin settings panel
// ============================================================
requireRole(ROLE_ADMIN);

$action = $_POST['action'] ?? '';

if ($action === 'save_settings') {
    $fields = [
        // Discount settings
        'discount_enabled'       => isset($_POST['discount_enabled']) ? '1' : '0',
        'discount_type'          => in_array($_POST['discount_type'] ?? '', ['percent','amount','both']) ? $_POST['discount_type'] : 'both',
        'discount_max_percent'   => (float)($_POST['discount_max_percent'] ?? 100),
        'discount_max_amount'    => (float)($_POST['discount_max_amount'] ?? 0),
        'discount_default'       => (float)($_POST['discount_default'] ?? 0),
        'product_discount_enabled'=> isset($_POST['product_discount_enabled']) ? '1' : '0',
        // VAT
        'vat_enabled'            => isset($_POST['vat_enabled']) ? '1' : '0',
        'vat_default'            => (float)($_POST['vat_default'] ?? 0),
        'vat_inclusive'          => isset($_POST['vat_inclusive']) ? '1' : '0',
        // Points
        'points_enabled'         => isset($_POST['points_enabled']) ? '1' : '0',
        'points_earn_rate'       => (float)($_POST['points_earn_rate'] ?? 1),    // points per $1
        'points_redeem_rate'     => (float)($_POST['points_redeem_rate'] ?? 0.01), // $ per point
        'points_min_redeem'      => (int)($_POST['points_min_redeem'] ?? 0),
        'points_max_redeem_pct'  => (float)($_POST['points_max_redeem_pct'] ?? 100),
        // Invoice
        'invoice_footer'         => trim($_POST['invoice_footer'] ?? ''),
        'shop_name'              => trim($_POST['shop_name'] ?? APP_NAME),
        'shop_address'           => trim($_POST['shop_address'] ?? ''),
        'shop_phone'             => trim($_POST['shop_phone'] ?? ''),
    ];

    foreach ($fields as $key => $value) {
        $existing = dbFetch('SELECT id FROM settings WHERE `key` = ?', [$key]);
        if ($existing) {
            dbUpdate('settings', ['value' => $value], '`key` = ?', [$key]);
        } else {
            dbInsert('settings', ['key' => $key, 'value' => $value]);
        }
    }
    logAction('UPDATE', 'settings', null, 'Updated system settings');
    flash('success', 'Settings saved.');
    redirect('settings');
}

// Load all settings
$rawSettings = dbFetchAll('SELECT `key`, `value` FROM settings');
$S = [];
foreach ($rawSettings as $r) $S[$r['key']] = $r['value'];

// Defaults
$S += [
    'discount_enabled'        => '1',
    'discount_type'           => 'both',
    'discount_max_percent'    => '100',
    'discount_max_amount'     => '0',
    'discount_default'        => '0',
    'product_discount_enabled'=> '0',
    'vat_enabled'             => '1',
    'vat_default'             => '15',
    'vat_inclusive'           => '0',
    'points_enabled'          => '1',
    'points_earn_rate'        => '1',
    'points_redeem_rate'      => '0.01',
    'points_min_redeem'       => '0',
    'points_max_redeem_pct'   => '100',
    'invoice_footer'          => 'Thank you for your purchase!',
    'shop_name'               => APP_NAME,
    'shop_address'            => '',
    'shop_phone'              => '',
];

$pageTitle = 'Settings';
require_once BASE_PATH . '/includes/header.php';
?>

<h1 style="margin-bottom:16px">⚙️ System Settings</h1>

<form method="POST">
  <input type="hidden" name="action" value="save_settings">

  <!-- Shop Info -->
  <div class="card mb-2">
    <div class="card-title">🏪 Shop Information</div>
    <div class="form-row cols-2">
      <div class="form-group">
        <label class="form-label">Shop Name</label>
        <input type="text" name="shop_name" class="form-control" value="<?= e($S['shop_name']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Phone</label>
        <input type="text" name="shop_phone" class="form-control" value="<?= e($S['shop_phone']) ?>">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Address</label>
      <textarea name="shop_address" class="form-control" rows="2"><?= e($S['shop_address']) ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">Invoice Footer Text</label>
      <input type="text" name="invoice_footer" class="form-control" value="<?= e($S['invoice_footer']) ?>">
    </div>
  </div>

  <!-- Discount Settings -->
  <div class="card mb-2">
    <div class="card-title">🏷️ Discount Settings</div>
    <div class="form-row cols-2">
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="discount_enabled" value="1" <?= $S['discount_enabled']=='1'?'checked':'' ?>>
          <span class="form-label" style="margin:0">Enable Discounts</span>
        </label>
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="product_discount_enabled" value="1" <?= $S['product_discount_enabled']=='1'?'checked':'' ?>>
          <span class="form-label" style="margin:0">Allow Product-level Discount</span>
        </label>
      </div>
    </div>
    <div class="form-row cols-3">
      <div class="form-group">
        <label class="form-label">Discount Input Type</label>
        <select name="discount_type" class="form-control">
          <option value="percent" <?= $S['discount_type']==='percent'?'selected':'' ?>>Percent Only</option>
          <option value="amount"  <?= $S['discount_type']==='amount' ?'selected':'' ?>>Amount Only</option>
          <option value="both"    <?= $S['discount_type']==='both'   ?'selected':'' ?>>Both (user chooses)</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Max Discount %</label>
        <input type="number" name="discount_max_percent" class="form-control" step="0.01" min="0" max="100" value="<?= e($S['discount_max_percent']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Max Discount Amount</label>
        <input type="number" name="discount_max_amount" class="form-control" step="0.01" min="0" value="<?= e($S['discount_max_amount']) ?>">
        <small class="text-muted">0 = unlimited</small>
      </div>
    </div>
    <div class="form-group" style="max-width:200px">
      <label class="form-label">Default Discount %</label>
      <input type="number" name="discount_default" class="form-control" step="0.01" min="0" value="<?= e($S['discount_default']) ?>">
    </div>
  </div>

  <!-- VAT Settings -->
  <div class="card mb-2">
    <div class="card-title">🧾 VAT / Tax Settings</div>
    <div class="form-row cols-3">
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="vat_enabled" value="1" <?= $S['vat_enabled']=='1'?'checked':'' ?>>
          <span class="form-label" style="margin:0">Enable VAT</span>
        </label>
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="vat_inclusive" value="1" <?= $S['vat_inclusive']=='1'?'checked':'' ?>>
          <span class="form-label" style="margin:0">Price Inclusive of VAT</span>
        </label>
      </div>
      <div class="form-group">
        <label class="form-label">Default VAT %</label>
        <input type="number" name="vat_default" class="form-control" step="0.01" min="0" max="100" value="<?= e($S['vat_default']) ?>">
      </div>
    </div>
  </div>

  <!-- Points Settings -->
  <div class="card mb-2">
    <div class="card-title">⭐ Loyalty Points Settings</div>
    <div class="form-row cols-2">
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="points_enabled" value="1" <?= $S['points_enabled']=='1'?'checked':'' ?>>
          <span class="form-label" style="margin:0">Enable Loyalty Points</span>
        </label>
      </div>
    </div>
    <div class="form-row cols-2">
      <div class="form-group">
        <label class="form-label">Points Earned per $1 Spent</label>
        <input type="number" name="points_earn_rate" class="form-control" step="0.01" min="0" value="<?= e($S['points_earn_rate']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">$ Value per Point Redeemed</label>
        <input type="number" name="points_redeem_rate" class="form-control" step="0.001" min="0" value="<?= e($S['points_redeem_rate']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Minimum Points to Redeem</label>
        <input type="number" name="points_min_redeem" class="form-control" min="0" value="<?= e($S['points_min_redeem']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Max Redeem % of Order Total</label>
        <input type="number" name="points_max_redeem_pct" class="form-control" step="1" min="0" max="100" value="<?= e($S['points_max_redeem_pct']) ?>">
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">💾 Save Settings</button>
</form>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
