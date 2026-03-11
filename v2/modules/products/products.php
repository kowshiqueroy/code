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
        $vid      = (int)($variantIds[$i] ?? 0);
        $barcode  = $vid ? null : 'BC-' . strtoupper(substr(md5(uniqid()), 0, 10));
        $vdata    = [
            'product_id' => $id,
            'size'       => trim($size),
            'color'      => trim($colors[$i] ?? ''),
            'cost'       => (float)($costs[$i] ?? 0),
            'price'      => (float)($prices[$i] ?? 0),
            'quantity'   => (int)($quantities[$i] ?? 0),
        ];
        if ($vid) {
            dbUpdate('product_variants', $vdata, 'id = ?', [$vid]);
        } else {
            $vdata['barcode']    = $barcode;
            $vdata['created_at'] = now();
            dbInsert('product_variants', $vdata);
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
        <div class="card-title mt-2">Variants</div>
        <div id="variantsContainer">
          <?php
          $initVars = $editVars ?: [['id'=>'','size'=>'','color'=>'','cost'=>'','price'=>'','quantity'=>'']];
          foreach ($initVars as $v): ?>
          <div class="variant-row" style="background:var(--surface2);border-radius:8px;padding:12px;margin-bottom:8px">
            <input type="hidden" name="variant_id[]" value="<?= $v['id'] ?>">
            <div class="form-row cols-3">
              <div class="form-group" style="margin-bottom:8px">
                <label class="form-label">Size</label>
                <input type="text" name="variant_size[]" class="form-control" value="<?= e($v['size']) ?>">
              </div>
              <div class="form-group" style="margin-bottom:8px">
                <label class="form-label">Color</label>
                <input type="text" name="variant_color[]" class="form-control" value="<?= e($v['color']) ?>">
              </div>
              <div class="form-group" style="margin-bottom:8px">
                <label class="form-label">Quantity</label>
                <input type="number" name="variant_qty[]" class="form-control" min="0" value="<?= $v['quantity'] ?>">
              </div>
            </div>
            <div class="form-row cols-2">
              <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Cost Price</label>
                <input type="number" name="variant_cost[]" class="form-control" step="0.01" min="0" value="<?= $v['cost'] ?>">
              </div>
              <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Selling Price</label>
                <input type="number" name="variant_price[]" class="form-control" step="0.01" min="0" value="<?= $v['price'] ?>">
              </div>
            </div>
            <button type="button" class="btn btn-danger btn-sm mt-1" onclick="removeVariant(this)">Remove</button>
          </div>
          <?php endforeach ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addVariantRow()">+ Add Variant</button>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost modal-close">Cancel</button>
      <button type="submit" form="productForm" class="btn btn-primary">Save Product</button>
    </div>
  </div>
</div>

<script>
function addVariantRow() {
  const c = document.getElementById('variantsContainer');
  const tpl = `<div class="variant-row" style="background:var(--surface2);border-radius:8px;padding:12px;margin-bottom:8px">
    <input type="hidden" name="variant_id[]" value="">
    <div class="form-row cols-3">
      <div class="form-group" style="margin-bottom:8px"><label class="form-label">Size</label><input type="text" name="variant_size[]" class="form-control"></div>
      <div class="form-group" style="margin-bottom:8px"><label class="form-label">Color</label><input type="text" name="variant_color[]" class="form-control"></div>
      <div class="form-group" style="margin-bottom:8px"><label class="form-label">Quantity</label><input type="number" name="variant_qty[]" class="form-control" min="0" value="0"></div>
    </div>
    <div class="form-row cols-2">
      <div class="form-group" style="margin-bottom:0"><label class="form-label">Cost Price</label><input type="number" name="variant_cost[]" class="form-control" step="0.01" min="0" value="0"></div>
      <div class="form-group" style="margin-bottom:0"><label class="form-label">Selling Price</label><input type="number" name="variant_price[]" class="form-control" step="0.01" min="0" value="0"></div>
    </div>
    <button type="button" class="btn btn-danger btn-sm mt-1" onclick="removeVariant(this)">Remove</button>
  </div>`;
  c.insertAdjacentHTML('beforeend', tpl);
}
function removeVariant(btn) {
  if (document.querySelectorAll('.variant-row').length > 1) {
    btn.closest('.variant-row').remove();
  }
}
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
