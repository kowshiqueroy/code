<?php
// ============================================================
// modules/products/products.php — Product management module
// ============================================================

// ── Handle form actions ───────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'save_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();

    $id     = (int)($_POST['product_id_db'] ?? 0);
    $data   = [
        'category_id' => (int)$_POST['category_id'] ?: null,
        'name'        => trim($_POST['name']),
        'description' => trim($_POST['description'] ?? ''),
        'active'      => 1,
    ];

    if ($id) {
        dbUpdate('products', $data, 'id = ?', [$id]);
        logAction('UPDATE', 'products', $id, 'Updated product: ' . $data['name']);
        flash('success', 'Product updated.');
    } else {
        $data['product_id']  = generateProductId();
        $data['created_at']  = now();
        $id = dbInsert('products', $data);
        logAction('CREATE', 'products', $id, 'Created product: ' . $data['name']);
        flash('success', 'Product created.');
    }

    // Save variants
    $variantIds    = $_POST['variant_id']    ?? [];
    $sizes         = $_POST['variant_size']  ?? [];
    $colors        = $_POST['variant_color'] ?? [];
    $costs         = $_POST['variant_cost']  ?? [];
    $prices        = $_POST['variant_price'] ?? [];
    $quantities    = $_POST['variant_qty']   ?? [];

    // Delete removed variants (only those not in submitted list)
    $existingVids  = array_filter(array_map('intval', $variantIds));
    if ($existingVids) {
        $in = implode(',', $existingVids);
        dbQuery("DELETE FROM product_variants WHERE product_id = ? AND id NOT IN ($in)", [$id]);
    } else {
        dbQuery("DELETE FROM product_variants WHERE product_id = ?", [$id]);
    }

 foreach ($sizes as $i => $size) {
    $vid   = (int)($variantIds[$i] ?? 0);
    $vdata = [
        'product_id' => $id,
        'size'       => trim($size),
        'color'      => trim($colors[$i] ?? ''),
        'cost'       => (float)($costs[$i] ?? 0),
        'price'      => (float)($prices[$i] ?? 0),
        'quantity'   => (int)($quantities[$i] ?? 0),
    ];

    if ($vid) {
        // 1. Update existing variant (barcode already exists)
        dbUpdate('product_variants', $vdata, 'id = ?', [$vid]);
    } else {
        // 2. Insert new variant FIRST
        $vdata['created_at'] = now();
        
        // Assuming your dbInsert function returns the last insert ID
        $newVid = dbInsert('product_variants', $vdata);
        
        // 3. Generate barcode using the ACTUAL newly created ID and update the row
        if ($newVid) {
            $barcode = str_pad((string)($newVid % 10000000000), 10, '0', STR_PAD_LEFT);
            dbUpdate('product_variants', ['barcode' => $barcode], 'id = ?', [$newVid]);
        }
    }
}

    redirect('products');
}

if ($action === 'delete' && canDelete()) {
    $id = (int)($_GET['id'] ?? 0);
    dbDelete('products', 'id = ?', [$id]);
    logAction('DELETE', 'products', $id, 'Deleted product');
    flash('success', 'Product deleted.');
    redirect('products');
}

// ── Load data ─────────────────────────────────────────────────
$categories = dbFetchAll('SELECT * FROM categories ORDER BY name');
$search     = trim($_GET['q'] ?? '');
$catFilter  = (int)($_GET['cat'] ?? 0);

$where  = '1=1';
$params = [];
if ($search) { $where .= ' AND p.name LIKE ?'; $params[] = "%$search%"; }
if ($catFilter) { $where .= ' AND p.category_id = ?'; $params[] = $catFilter; }

$products = dbFetchAll(
    "SELECT p.*, c.name AS category_name,
            COUNT(v.id) AS variant_count,
            SUM(v.quantity) AS total_stock
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN product_variants v ON v.product_id = p.id
     WHERE $where
     GROUP BY p.id
     ORDER BY p.created_at DESC",
    $params
);

// Edit mode
$editing  = null;
$editVars = [];
if (!empty($_GET['edit'])) {
    $editing  = dbFetch('SELECT * FROM products WHERE id = ?', [(int)$_GET['edit']]);
    $editVars = dbFetchAll('SELECT * FROM product_variants WHERE product_id = ? ORDER BY id', [(int)$_GET['edit']]);
}

$pageTitle = 'Products';
require_once BASE_PATH . '/includes/header.php';
?>

<!-- ── Page header ───────────────────────────────────────────── -->
<div class="d-flex justify-between align-center mb-2 flex-wrap gap-2">
  <h1>📦 Products</h1>
  <button class="btn btn-primary" onclick="openModal('productModal')">+ Add Product</button>
</div>

<!-- ── Filters ───────────────────────────────────────────────── -->
<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
  <input type="hidden" name="page" value="products">
  <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search products…" class="form-control" style="max-width:200px">
  <select name="cat" class="form-control" style="max-width:160px">
    <option value="">All Categories</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $catFilter == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
    <?php endforeach ?>
  </select>
  <button type="submit" class="btn btn-ghost">Filter</button>
  <a href="index.php?page=products" class="btn btn-ghost">Reset</a>
</form>

<!-- ── Products table ────────────────────────────────────────── -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Product ID</th><th>Name</th><th>Category</th>
          <th>Variants</th><th>Total Stock</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$products): ?>
          <tr><td colspan="6" class="text-muted text-center">No products found.</td></tr>
        <?php endif ?>
        <?php foreach ($products as $p): ?>
        <tr>
          <td>
            <div class="barcode-text" style="font-size:1.2rem"><?= e($p['product_id']) ?></div>
            <div class="barcode-id"><?= e($p['product_id']) ?></div>
          </td>
          <td><strong><?= e($p['name']) ?></strong></td>
          <td><?= e($p['category_name'] ?? '—') ?></td>
          <td><span class="badge badge-info"><?= $p['variant_count'] ?></span></td>
          <td><?= (int)$p['total_stock'] ?></td>
          <td>
            <a href="index.php?page=products&edit=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">✏️ Edit</a>
            <?php if (canDelete()): ?>
            <a href="index.php?page=products&action=delete&id=<?= $p['id'] ?>"
               class="btn btn-danger btn-sm"
               data-confirm="Delete this product and all variants?">🗑️</a>
            <?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Add / Edit Product Modal ──────────────────────────────── -->
<div class="modal-backdrop <?= $editing ? 'open' : '' ?>" id="productModal">
  <div class="modal" style="max-width:680px">
    <div class="modal-header">
      <span class="modal-title"><?= $editing ? 'Edit Product' : 'Add Product' ?></span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="productForm">
        <input type="hidden" name="action" value="save_product">
        <input type="hidden" name="product_id_db" value="<?= $editing['id'] ?? '' ?>">

        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Product Name *</label>
            <input type="text" name="name" class="form-control" required value="<?= e($editing['name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-control">
              <option value="">— None —</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($editing['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control"><?= e($editing['description'] ?? '') ?></textarea>
        </div>

        <!-- ── Variants ─────────────────────────────────────── -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px;margin-bottom:6px">
          <div class="card-title" style="margin:0">Variants</div>
          <div style="display:flex;gap:6px">
            <button type="button" class="btn btn-ghost btn-sm" onclick="openBulkAdd()">⚡ Bulk Add</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addVariantRow()">+ Single</button>
          </div>
        </div>
        <!-- Quick bulk add bar -->
        <div id="bulkAddPanel" style="display:none;background:var(--surface2);border-radius:8px;padding:10px;margin-bottom:10px">
          <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:6px">Quick-add: enter sizes and colors (comma-separated) to generate all combinations</div>
          <div class="form-row cols-2" style="margin-bottom:6px">
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Sizes (comma-separated)</label>
              <input type="text" id="bulkSizes" class="form-control" placeholder="S, M, L, XL">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Colors (comma-separated)</label>
              <input type="text" id="bulkColors" class="form-control" placeholder="Red, Blue, Black">
            </div>
          </div>
          <div class="form-row cols-3" style="margin-bottom:8px">
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Default Cost</label>
              <input type="number" id="bulkCost" class="form-control" step="0.01" value="0">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Default Price</label>
              <input type="number" id="bulkPrice" class="form-control" step="0.01" value="0">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Default Qty</label>
              <input type="number" id="bulkQty" class="form-control" value="0" min="0">
            </div>
          </div>
          <button type="button" class="btn btn-primary btn-sm" onclick="generateBulk()">Generate Variants</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('bulkAddPanel').style.display='none'">Cancel</button>
        </div>

        <div id="variantsContainer">
          <?php
          $initVars = $editVars ?: [['id'=>'','size'=>'','color'=>'','cost'=>'','price'=>'','quantity'=>'']];
          foreach ($initVars as $v): ?>
          <div class="variant-row" style="background:var(--surface2);border-radius:8px;padding:10px;margin-bottom:6px;display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr auto;gap:6px;align-items:end">
            <input type="hidden" name="variant_id[]" value="<?= $v['id'] ?>">
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Size</label>
              <input type="text" name="variant_size[]" class="form-control" value="<?= e($v['size']??'') ?>" list="sizeList">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Color</label>
              <input type="text" name="variant_color[]" class="form-control" value="<?= e($v['color']??'') ?>" list="colorList">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Cost</label>
              <input type="number" name="variant_cost[]" class="form-control" step="0.01" min="0" value="<?= $v['cost']??0 ?>">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Price *</label>
              <input type="number" name="variant_price[]" class="form-control" step="0.01" min="0" required value="<?= $v['price']??0 ?>">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Qty</label>
              <input type="number" name="variant_qty[]" class="form-control" min="0" value="<?= $v['quantity']??0 ?>">
            </div>
            <button type="button" class="btn btn-danger btn-sm btn-icon" onclick="removeVariant(this)" title="Remove" style="margin-bottom:0">✕</button>
          </div>
          <?php endforeach ?>
        </div>
        <datalist id="sizeList">
          <option>XS</option><option>S</option><option>M</option><option>L</option>
          <option>XL</option><option>XXL</option><option>Free Size</option>
          <option>36</option><option>37</option><option>38</option><option>39</option><option>40</option><option>41</option><option>42</option>
        </datalist>
        <datalist id="colorList">
          <option>Black</option><option>White</option><option>Red</option><option>Blue</option>
          <option>Green</option><option>Yellow</option><option>Pink</option><option>Grey</option>
          <option>Brown</option><option>Navy</option><option>Beige</option>
        </datalist>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost modal-close">Cancel</button>
      <button type="submit" form="productForm" class="btn btn-primary">Save Product</button>
    </div>
  </div>
</div>

<script>
function variantRowHtml(size='', color='', cost='0', price='0', qty='0') {
  return `<div class="variant-row" style="background:var(--surface2);border-radius:8px;padding:10px;margin-bottom:6px;display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr auto;gap:6px;align-items:end">
    <input type="hidden" name="variant_id[]" value="">
    <div class="form-group" style="margin-bottom:0"><label class="form-label">Size</label>
      <input type="text" name="variant_size[]" class="form-control" value="${esc(size)}" list="sizeList"></div>
    <div class="form-group" style="margin-bottom:0"><label class="form-label">Color</label>
      <input type="text" name="variant_color[]" class="form-control" value="${esc(color)}" list="colorList"></div>
    <div class="form-group" style="margin-bottom:0"><label class="form-label">Cost</label>
      <input type="number" name="variant_cost[]" class="form-control" step="0.01" min="0" value="${cost}"></div>
    <div class="form-group" style="margin-bottom:0"><label class="form-label">Price *</label>
      <input type="number" name="variant_price[]" class="form-control" step="0.01" min="0" required value="${price}"></div>
    <div class="form-group" style="margin-bottom:0"><label class="form-label">Qty</label>
      <input type="number" name="variant_qty[]" class="form-control" min="0" value="${qty}"></div>
    <button type="button" class="btn btn-danger btn-sm btn-icon" onclick="removeVariant(this)" title="Remove">✕</button>
  </div>`;
}
function addVariantRow() {
  document.getElementById('variantsContainer').insertAdjacentHTML('beforeend', variantRowHtml());
}
function removeVariant(btn) {
  if (document.querySelectorAll('.variant-row').length > 1) btn.closest('.variant-row').remove();
}
function openBulkAdd() {
  document.getElementById('bulkAddPanel').style.display = '';
}
function generateBulk() {
  const sizes  = document.getElementById('bulkSizes').value.split(',').map(s=>s.trim()).filter(Boolean);
  const colors = document.getElementById('bulkColors').value.split(',').map(s=>s.trim()).filter(Boolean);
  const cost   = document.getElementById('bulkCost').value;
  const price  = document.getElementById('bulkPrice').value;
  const qty    = document.getElementById('bulkQty').value;

  if (!sizes.length && !colors.length) { alert('Enter at least sizes or colors.'); return; }

  const sArr = sizes.length  ? sizes  : [''];
  const cArr = colors.length ? colors : [''];
  const container = document.getElementById('variantsContainer');

  sArr.forEach(s => cArr.forEach(c => {
    container.insertAdjacentHTML('beforeend', variantRowHtml(s, c, cost, price, qty));
  }));
  document.getElementById('bulkAddPanel').style.display = 'none';
}
function esc(s) { return String(s||'').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
