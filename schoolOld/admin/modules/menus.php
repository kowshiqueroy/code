<?php
/**
 * Admin Menus Module — Manage navigation structure
 */
$admin_title = 'Menus';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_menu'])) {
        $title_en = trim($_POST['title_en'] ?? '');
        $title_bn = trim($_POST['title_bn'] ?? '');
        $slug     = preg_replace('/[^a-z0-9-]/','',strtolower($_POST['slug']??''));
        $url      = trim($_POST['url'] ?? '');
        $parent   = (int)($_POST['parent_id'] ?? 0);
        $sort     = (int)($_POST['sort_order'] ?? 0);
        $target   = in_array($_POST['target']??'_self',['_self','_blank']) ? $_POST['target'] : '_self';
        if ($title_en) {
            db()->prepare("INSERT INTO menus (parent_id,title_en,title_bn,slug,url,sort_order,target,menu_location,is_active) VALUES (?,?,?,?,?,?,?,'main',1)")
               ->execute([$parent,$title_en,$title_bn,$slug,$url,$sort,$target]);
            $msg = 'Menu item added.';
        }
    }
    // Update sort orders
    if (!empty($_POST['menu_order'])) {
        $stmt = db()->prepare("UPDATE menus SET sort_order=? WHERE id=?");
        foreach ($_POST['menu_order'] as $id => $order) {
            $stmt->execute([(int)$order, (int)$id]);
        }
        $msg = 'Menu order updated.';
    }
    // Update active status
    if (isset($_POST['update_active'])) {
        $stmt = db()->prepare("UPDATE menus SET is_active=? WHERE id=?");
        foreach ((array)$_POST['active_ids'] as $id) {
            $stmt->execute([1, (int)$id]);
        }
        $all = array_column(db()->query("SELECT id FROM menus")->fetchAll(), 'id');
        $active = array_map('intval', (array)$_POST['active_ids']);
        foreach (array_diff($all, $active) as $inactive_id) {
            db()->prepare("UPDATE menus SET is_active=0 WHERE id=?")->execute([$inactive_id]);
        }
        $msg = 'Menu visibility updated.';
    }
}
if (isset($_GET['delete'])) {
    db()->prepare("DELETE FROM menus WHERE id=? OR parent_id=?")->execute([(int)$_GET['delete'],(int)$_GET['delete']]);
    header('Location: /admin/?action=menus&msg=deleted'); exit;
}

$all_menus = db()->query("SELECT * FROM menus WHERE menu_location='main' ORDER BY parent_id, sort_order")->fetchAll();
$top_menus = array_filter($all_menus, fn($m) => $m['parent_id'] == 0);
$pages_list = db()->query("SELECT id,slug,title_en FROM pages WHERE is_published=1 ORDER BY title_en")->fetchAll();
?>

<?php if ($msg || isset($_GET['msg'])): ?>
<div class="alert alert-success">✅ <?= h($msg ?: ucfirst(str_replace('_',' ',$_GET['msg']??''))) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">
  <!-- Menu List -->
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title">🗂️ Navigation Menu</div>
      <div style="font-size:.8rem;color:var(--muted)">Drag to reorder (coming soon) | Toggle visibility</div>
    </div>
    <form method="POST">
      <input type="hidden" name="update_active" value="1">
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>Order</th><th>Visible</th><th>Menu Item</th><th>URL / Slug</th><th>Parent</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($all_menus as $m): ?>
            <tr style="<?= $m['parent_id']>0?'background:var(--bg)':'' ?>">
              <td style="width:60px">
                <input type="number" name="menu_order[<?= $m['id'] ?>]" value="<?= (int)$m['sort_order'] ?>"
                       style="width:55px;padding:4px 6px;border:1px solid var(--border);border-radius:4px;font-size:.82rem">
              </td>
              <td>
                <input type="checkbox" name="active_ids[]" value="<?= $m['id'] ?>" <?= $m['is_active']?'checked':'' ?>>
              </td>
              <td>
                <?= $m['parent_id']>0 ? '<span style="color:var(--muted);margin-right:4px">↳</span>' : '' ?>
                <strong><?= h($m['title_en']) ?></strong>
                <?php if ($m['title_bn']): ?><br><span style="font-size:.75rem;color:var(--muted)"><?= h($m['title_bn']) ?></span><?php endif; ?>
              </td>
              <td style="font-size:.8rem">
                <?php if ($m['slug']): ?><code>/?page=<?= h($m['slug']) ?></code><?php endif; ?>
                <?php if ($m['url']): ?><span style="color:var(--primary)"><?= h($m['url']) ?></span><?php endif; ?>
              </td>
              <td style="font-size:.82rem;color:var(--muted)"><?= $m['parent_id']>0 ? 'Sub-item' : 'Top-level' ?></td>
              <td>
                <a href="/admin/?action=menus&delete=<?= $m['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete?')">🗑️</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:10px">
        <button type="submit" class="btn btn-primary btn-sm">💾 Save Changes</button>
      </div>
    </form>
  </div>

  <!-- Add Menu Item -->
  <div class="panel">
    <div class="panel-header"><div class="panel-title">➕ Add Menu Item</div></div>
    <div class="panel-body">
      <form method="POST">
        <input type="hidden" name="add_menu" value="1">
        <div class="form-group">
          <label class="form-label">Label (English) *</label>
          <input type="text" name="title_en" class="form-control" required placeholder="e.g. Our Achievements">
        </div>
        <div class="form-group">
          <label class="form-label">Label (Bangla)</label>
          <input type="text" name="title_bn" class="form-control" placeholder="আমাদের অর্জন">
        </div>
        <div class="form-group">
          <label class="form-label">Link to Page</label>
          <select name="slug" class="form-control form-select" onchange="this.nextElementSibling.value=''">
            <option value="">— Select page —</option>
            <?php foreach ($pages_list as $p): ?>
            <option value="<?= h($p['slug']) ?>"><?= h($p['title_en']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Or Custom URL</label>
          <input type="text" name="url" class="form-control" placeholder="https://... or /?page=...">
          <div class="form-hint">Leave blank if linking to a page above</div>
        </div>
        <div class="form-group">
          <label class="form-label">Parent Menu (for sub-items)</label>
          <select name="parent_id" class="form-control form-select">
            <option value="0">— Top Level —</option>
            <?php foreach ($top_menus as $m): ?>
            <option value="<?= $m['id'] ?>"><?= h($m['title_en']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" value="<?= count($all_menus)+1 ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Open in</label>
            <select name="target" class="form-control form-select">
              <option value="_self">Same tab</option>
              <option value="_blank">New tab</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">➕ Add Item</button>
      </form>
    </div>
  </div>
</div>
