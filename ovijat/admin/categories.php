<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    $r = db()->prepare("SELECT image FROM product_categories WHERE id=?"); $r->execute([$id]); $r=$r->fetch();
    if ($r && $r['image']) @unlink(UPLOAD_DIR.'products/'.$r['image']);
    db()->prepare("DELETE FROM product_categories WHERE id=?")->execute([$id]);
    flash('Category deleted.','success'); redirect(SITE_URL.'/admin/categories.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $nameEn = sanitizeText($_POST['name_en'] ?? '');
    $nameBn = sanitizeText($_POST['name_bn'] ?? '');
    $parent = (int)($_POST['parent_id'] ?? 0) ?: null;
    $active = (int)($_POST['active'] ?? 1);
    $sort   = (int)($_POST['sort_order'] ?? 0);
    $editId = (int)($_POST['edit_id'] ?? 0);

    $oldImg = '';
    if ($editId) { $r=db()->prepare("SELECT image FROM product_categories WHERE id=?"); $r->execute([$editId]); $r=$r->fetch(); $oldImg=$r['image']??''; }
    $image = $oldImg;
    if (!empty($_FILES['image']['name'])) { $new=processUploadedImage($_FILES['image'],'product','products',$oldImg); if($new) $image=$new; }

    if ($editId) {
        db()->prepare("UPDATE product_categories SET name_en=?,name_bn=?,parent_id=?,image=?,active=?,sort_order=? WHERE id=?")
            ->execute([$nameEn,$nameBn,$parent,$image,$active,$sort,$editId]);
        flash('Category updated.','success');
    } else {
        db()->prepare("INSERT INTO product_categories (name_en,name_bn,parent_id,image,active,sort_order) VALUES (?,?,?,?,?,?)")
            ->execute([$nameEn,$nameBn,$parent,$image,$active,$sort]);
        flash('Category added.','success');
    }
    redirect(SITE_URL.'/admin/categories.php');
}

$editing = null;
if ($action === 'edit' && $id) { $s=db()->prepare("SELECT * FROM product_categories WHERE id=?"); $s->execute([$id]); $editing=$s->fetch(); }
$topCats = db()->query("SELECT * FROM product_categories WHERE parent_id IS NULL ORDER BY sort_order")->fetchAll();
$allCats = db()->query("SELECT c.*, p.name_en parent_name FROM product_categories c LEFT JOIN product_categories p ON c.parent_id=p.id ORDER BY COALESCE(c.parent_id,c.id), c.sort_order")->fetchAll();

require_once __DIR__ . '/partials/admin_header.php';
?>
<div class="admin-page-header"><h1>🏷️ Product Categories</h1><p>Manage parent categories and sub-categories for the product catalog.</p></div>
<div class="admin-panel">
  <h2 class="admin-section-title"><?= $editing ? 'Edit Category' : 'Add Category' ?></h2>
  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="edit_id" value="<?= $editing['id'] ?? 0 ?>">
    <div class="form-row">
      <div class="form-group"><label>Name (English) *</label><input type="text" name="name_en" class="form-input" required value="<?= e($editing['name_en'] ?? '') ?>"></div>
      <div class="form-group"><label>Name (বাংলা)</label><input type="text" name="name_bn" class="form-input" value="<?= e($editing['name_bn'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Parent Category (leave blank for top-level)</label>
        <select name="parent_id" class="form-input">
          <option value="">— Top Level Category —</option>
          <?php foreach ($topCats as $tc): ?>
            <option value="<?= $tc['id'] ?>" <?= ($editing['parent_id'] ?? '') == $tc['id'] ? 'selected' : '' ?>><?= e($tc['name_en']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Category Image (600×600px)</label>
        <?php if ($editing && $editing['image']): ?><div class="current-media"><img src="<?= imgUrl($editing['image'],'products','product') ?>" style="max-height:60px;border-radius:4px;"><small>Current</small></div><?php endif; ?>
        <input type="file" name="image" class="form-input" accept="image/*">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Status</label><select name="active" class="form-input"><option value="1" <?= ($editing['active']??1)==1?'selected':''?>>Active</option><option value="0" <?= ($editing['active']??1)==0?'selected':''?>>Inactive</option></select></div>
      <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" class="form-input" value="<?= (int)($editing['sort_order'] ?? 0) ?>"></div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $editing ? '💾 Update' : '➕ Add' ?></button>
      <?php if ($editing): ?><a href="<?= SITE_URL ?>/admin/categories.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>
<div class="admin-panel">
  <h2 class="admin-section-title">All Categories</h2>
  <table class="admin-table">
    <thead><tr><th>Img</th><th>Name</th><th>Parent</th><th>Status</th><th>Sort</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($allCats as $c): ?>
        <tr>
          <td><img src="<?= imgUrl($c['image']??'','products','product') ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;"></td>
          <td><?= $c['parent_id'] ? '&nbsp;&nbsp;&nbsp;↳ ' : '' ?><strong><?= e($c['name_en']) ?></strong><br><small><?= e($c['name_bn']) ?></small></td>
          <td><?= e($c['parent_name'] ?? '—') ?></td>
          <td><span class="badge <?= $c['active'] ? 'badge-green':'badge-gray' ?>"><?= $c['active']?'Active':'Off'?></span></td>
          <td><?= $c['sort_order'] ?></td>
          <td class="table-actions">
            <a href="?action=edit&id=<?= $c['id'] ?>" class="btn-mini">Edit</a>
            <a href="?action=delete&id=<?= $c['id'] ?>&csrf_token=<?= csrf_token() ?>" class="btn-mini btn-danger" onclick="return confirm('Delete category? This will affect all products.')">Del</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/partials/admin_footer.php'; ?>
