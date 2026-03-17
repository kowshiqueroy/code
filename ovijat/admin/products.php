<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    $r=db()->prepare("SELECT image FROM products WHERE id=?"); $r->execute([$id]); $r=$r->fetch();
 
    db()->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
       if ($r && $r['image']) @unlink(UPLOAD_DIR.'products/'.$r['image']);
    flash('Product deleted.','success'); redirect(SITE_URL.'/admin/products.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $catId  = (int)($_POST['category_id'] ?? 0);
    $nameEn = sanitizeText($_POST['name_en'] ?? '');
    $nameBn = sanitizeText($_POST['name_bn'] ?? '');
    $descEn = sanitizeText($_POST['desc_en'] ?? '');
    $descBn = sanitizeText($_POST['desc_bn'] ?? '');
    $weight = sanitizeText($_POST['weight'] ?? '');
    $active = (int)($_POST['active'] ?? 1);
    $sort   = (int)($_POST['sort_order'] ?? 0);
    $editId = (int)($_POST['edit_id'] ?? 0);

    if (!$catId || !$nameEn) { 
        flash('Category and English name are required.','error'); 
        redirect(SITE_URL.'/admin/products.php?action='.($editId?'edit':'list').'&id='.$editId); 
    }

    $oldImg = '';
    if ($editId) { $r=db()->prepare("SELECT image FROM products WHERE id=?"); $r->execute([$editId]); $r=$r->fetch(); $oldImg=$r['image']??''; }
    $image = $oldImg;
    if (!empty($_FILES['image']['name'])) { $new=processUploadedImage($_FILES['image'],'product','products',$oldImg); if($new) $image=$new; }

    if ($editId) {
        db()->prepare("UPDATE products SET category_id=?,name_en=?,name_bn=?,desc_en=?,desc_bn=?,weight=?,image=?,active=?,sort_order=? WHERE id=?")
            ->execute([$catId,$nameEn,$nameBn,$descEn,$descBn,$weight,$image,$active,$sort,$editId]);
        flash('Product updated.','success');
    } else {
        db()->prepare("INSERT INTO products (category_id,name_en,name_bn,desc_en,desc_bn,weight,image,active,sort_order) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$catId,$nameEn,$nameBn,$descEn,$descBn,$weight,$image,$active,$sort]);
        flash('Product added.','success');
    }
    redirect(SITE_URL.'/admin/products.php');
}

$editing = null;
if ($action === 'edit' && $id) { $s=db()->prepare("SELECT * FROM products WHERE id=?"); $s->execute([$id]); $editing=$s->fetch(); }

// Paginate
$page  = max(1,(int)($_GET['p'] ?? 1));
$perPg = 20;
$total = db()->query("SELECT COUNT(*) FROM products")->fetchColumn();
$pg    = paginate($total, $perPg, $page);
$products = db()->query("SELECT p.*, c.name_en cat_en FROM products p LEFT JOIN product_categories c ON p.category_id=c.id ORDER BY p.sort_order LIMIT {$pg['limit']} OFFSET {$pg['offset']}")->fetchAll();
$allCatsList = db()->query("SELECT * FROM product_categories ORDER BY sort_order")->fetchAll();

// Build a hierarchical tree of categories
$catTree = [];
$catLookup = [];
foreach ($allCatsList as $c) {
    $c['children'] = [];
    $catLookup[$c['id']] = $c;
}
foreach ($catLookup as $id => &$c) {
    if ($c['parent_id']) {
        $catLookup[$c['parent_id']]['children'][] = &$c;
    } else {
        $catTree[] = &$c;
    }
}

// Recursive function to print the dropdown options
function renderCategoryOptions($categories, $level = 0, $selectedId = 0) {
    foreach ($categories as $cat) {
        $hasChildren = !empty($cat['children']);
        
        // Add indentation based on how deep the child is
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level) . ($level > 0 ? '↳ ' : '');
        
        // Disable it if it has children (meaning it's a parent)
        $disabled = $hasChildren ? 'disabled style="font-weight:bold; color:#888;"' : '';
        $selected = ($selectedId == $cat['id']) ? 'selected' : '';
        
        echo "<option value=\"{$cat['id']}\" $disabled $selected>{$indent}" . e($cat['name_en']) . "</option>";
        
        // If this category has children, run the function again for the children
        if ($hasChildren) {
            renderCategoryOptions($cat['children'], $level + 1, $selectedId);
        }
    }
}
require_once __DIR__ . '/partials/admin_header.php';
?>
<div class="admin-page-header">
  <h1>📦 Products</h1>
  <p>Manage all products. Images auto-resized to 600×600px (square crop).</p>
</div>

<div class="admin-panel" id="productForm">
  <h2 class="admin-section-title"><?= $editing ? 'Edit Product: '.e($editing['name_en']) : 'Add New Product' ?></h2>
  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="edit_id" value="<?= $editing['id'] ?? 0 ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Category *</label>
       <select name="category_id" class="form-input" required>
  <option value="">— Select Category —</option>
  <?php renderCategoryOptions($catTree, 0, $editing['category_id'] ?? 0); ?>
</select>
      </div>
      <div class="form-group"><label>Weight / Size (e.g. 1 kg, 500 ml)</label><input type="text" name="weight" class="form-input" maxlength="80" value="<?= e($editing['weight'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Name (English) *</label><input type="text" name="name_en" class="form-input" required value="<?= e($editing['name_en'] ?? '') ?>"></div>
      <div class="form-group"><label>Name (বাংলা)</label><input type="text" name="name_bn" class="form-input" value="<?= e($editing['name_bn'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Description (English)</label><textarea name="desc_en" class="form-textarea" rows="4"><?= e($editing['desc_en'] ?? '') ?></textarea></div>
      <div class="form-group"><label>Description (বাংলা)</label><textarea name="desc_bn" class="form-textarea" rows="4"><?= e($editing['desc_bn'] ?? '') ?></textarea></div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Product Image (600×600px square, auto-cropped)</label>
        <?php if ($editing && $editing['image']): ?>
          <div class="current-media">
            <img src="<?= imgUrl($editing['image'],'products','product') ?>" style="width:60px;height:60px;object-fit:cover;border-radius:4px;">
            <small>Current</small>
          </div>
        <?php endif; ?>
        <input type="file" name="image" class="form-input" accept="image/*">
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="active" class="form-input">
          <option value="1" <?= ($editing['active']??1)==1?'selected':''?>>Active</option>
          <option value="0" <?= ($editing['active']??1)==0?'selected':''?>>Inactive</option>
        </select>
        <label style="margin-top:12px">Sort Order</label>
        <input type="number" name="sort_order" class="form-input" value="<?= (int)($editing['sort_order']??0) ?>">
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $editing ? '💾 Update Product' : '➕ Add Product' ?></button>
      <?php if ($editing): ?>
        <a href="<?= SITE_URL ?>/admin/products.php" class="btn btn-ghost">Cancel Edit</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="admin-panel">
  <div class="panel-header-row" style="margin-bottom:1rem;">
    <h2 class="admin-section-title" style="margin:0;">All Products (<?= $total ?>)</h2>
  </div>
  <table class="admin-table">
    <thead><tr><th>Img</th><th>Name</th><th>Category</th><th>Weight</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td><img src="<?= imgUrl($p['image']??'','products','product') ?>" style="width:48px;height:48px;object-fit:cover;border-radius:4px;"></td>
          <td><strong><?= e($p['name_en']) ?></strong><br><small><?= e($p['name_bn']) ?></small></td>
          <td><?= e($p['cat_en']) ?></td>
          <td><?= e($p['weight']) ?: '—' ?></td>
          <td><span class="badge <?= $p['active']?'badge-green':'badge-gray'?>"><?= $p['active']?'Active':'Off'?></span></td>
          <td class="table-actions">
            <a href="?action=edit&id=<?= $p['id'] ?>" class="btn-mini">Edit</a>
            <a href="?action=delete&id=<?= $p['id'] ?>&csrf_token=<?= csrf_token() ?>" class="btn-mini btn-danger" onclick="return confirm('Delete product?')">Del</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($pg['pages'] > 1): ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <a href="?p=<?= $i ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/admin_footer.php'; ?>