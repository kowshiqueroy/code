<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    db()->prepare("DELETE FROM ticker_items WHERE id=?")->execute([$id]);
    flash('Ticker item deleted.','success');
    redirect(SITE_URL.'/admin/ticker.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $textEn = sanitizeText($_POST['text_en'] ?? '');
    $textBn = sanitizeText($_POST['text_bn'] ?? '');
    $active = (int)($_POST['active'] ?? 1);
    $sort   = (int)($_POST['sort_order'] ?? 0);
    $start  = $_POST['start_date'] ?: null;
    $end    = $_POST['end_date'] ?: null;
    $editId = (int)($_POST['edit_id'] ?? 0);

    if ($editId) {
        db()->prepare("UPDATE ticker_items SET text_en=?,text_bn=?,active=?,sort_order=?,start_date=?,end_date=? WHERE id=?")
            ->execute([$textEn,$textBn,$active,$sort,$start,$end,$editId]);
        flash('Ticker updated.','success');
    } else {
        db()->prepare("INSERT INTO ticker_items (text_en,text_bn,active,sort_order,start_date,end_date) VALUES (?,?,?,?,?,?)")
            ->execute([$textEn,$textBn,$active,$sort,$start,$end]);
        flash('Ticker item added.','success');
    }
    redirect(SITE_URL.'/admin/ticker.php');
}

$editing = null;
if ($action === 'edit' && $id) {
    $s = db()->prepare("SELECT * FROM ticker_items WHERE id=?"); $s->execute([$id]); $editing = $s->fetch();
}
$items = db()->query("SELECT * FROM ticker_items ORDER BY sort_order")->fetchAll();

require_once __DIR__ . '/partials/admin_header.php';
?>
<div class="admin-page-header"><h1>📢 News Ticker</h1><p>Manage scrolling news items shown at the top of the site. Enable/disable globally via Site Settings.</p></div>
<div class="admin-panel">
  <h2 class="admin-section-title"><?= $editing ? 'Edit Item' : 'Add Ticker Item' ?></h2>
  <form method="POST" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="edit_id" value="<?= $editing['id'] ?? 0 ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Text (English) *</label>
        <input type="text" name="text_en" class="form-input" required maxlength="500" value="<?= e($editing['text_en'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Text (বাংলা)</label>
        <input type="text" name="text_bn" class="form-input" maxlength="500" value="<?= e($editing['text_bn'] ?? '') ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Status</label>
        <select name="active" class="form-input">
          <option value="1" <?= ($editing['active'] ?? 1) == 1 ? 'selected' : '' ?>>Active</option>
          <option value="0" <?= ($editing['active'] ?? 1) == 0 ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" class="form-input" value="<?= (int)($editing['sort_order'] ?? 0) ?>"></div>
      <div class="form-group"><label>Start Date (optional)</label><input type="date" name="start_date" class="form-input" value="<?= e($editing['start_date'] ?? '') ?>"></div>
      <div class="form-group"><label>End Date (optional)</label><input type="date" name="end_date" class="form-input" value="<?= e($editing['end_date'] ?? '') ?>"></div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $editing ? '💾 Update' : '➕ Add' ?></button>
      <?php if ($editing): ?><a href="<?= SITE_URL ?>/admin/ticker.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>
<div class="admin-panel">
  <h2 class="admin-section-title">All Ticker Items</h2>
  <?php if ($items): ?>
    <table class="admin-table">
      <thead><tr><th>Text (EN)</th><th>Date Range</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= e(mb_substr($it['text_en'],0,60)) ?>…</td>
            <td><?= $it['start_date'] ? date('d M y',strtotime($it['start_date'])) : '—' ?> → <?= $it['end_date'] ? date('d M y',strtotime($it['end_date'])) : '—' ?></td>
            <td><span class="badge <?= $it['active'] ? 'badge-green' : 'badge-gray' ?>"><?= $it['active'] ? 'Active' : 'Off' ?></span></td>
            <td class="table-actions">
              <a href="?action=edit&id=<?= $it['id'] ?>" class="btn-mini">Edit</a>
              <a href="?action=delete&id=<?= $it['id'] ?>&csrf_token=<?= csrf_token() ?>" class="btn-mini btn-danger" onclick="return confirm('Delete?')">Del</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?><p class="empty-msg">No ticker items yet.</p><?php endif; ?>
</div>
<?php require_once __DIR__ . '/partials/admin_footer.php'; ?>
