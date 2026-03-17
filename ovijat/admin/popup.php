<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    $r = db()->prepare("SELECT image FROM event_popups WHERE id=?"); $r->execute([$id]); $r = $r->fetch();
    if ($r && $r['image']) @unlink(UPLOAD_DIR.'popup/'.$r['image']);
    db()->prepare("DELETE FROM event_popups WHERE id=?")->execute([$id]);
    flash('Popup deleted.','success'); redirect(SITE_URL.'/admin/popup.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $titleEn = sanitizeText($_POST['title_en'] ?? '');
    $titleBn = sanitizeText($_POST['title_bn'] ?? '');
    $bodyEn  = sanitizeText($_POST['body_en'] ?? '');
    $bodyBn  = sanitizeText($_POST['body_bn'] ?? '');
    $active  = (int)($_POST['active'] ?? 1);
    $start   = $_POST['start_date'] ?? date('Y-m-d');
    $end     = $_POST['end_date'] ?? date('Y-m-d', strtotime('+7 days'));
    $editId  = (int)($_POST['edit_id'] ?? 0);

    $oldImg = '';
    if ($editId) {
        $r = db()->prepare("SELECT image FROM event_popups WHERE id=?"); $r->execute([$editId]); $r=$r->fetch();
        $oldImg = $r['image'] ?? '';
    }
    $image = $oldImg;
    if (!empty($_FILES['image']['name'])) {
        $new = processUploadedImage($_FILES['image'], 'popup', 'popup', $oldImg);
        if ($new) $image = $new;
    }

    if ($editId) {
        db()->prepare("UPDATE event_popups SET title_en=?,title_bn=?,body_en=?,body_bn=?,image=?,active=?,start_date=?,end_date=? WHERE id=?")
            ->execute([$titleEn,$titleBn,$bodyEn,$bodyBn,$image,$active,$start,$end,$editId]);
        flash('Popup updated.','success');
    } else {
        db()->prepare("INSERT INTO event_popups (title_en,title_bn,body_en,body_bn,image,active,start_date,end_date) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$titleEn,$titleBn,$bodyEn,$bodyBn,$image,$active,$start,$end]);
        flash('Popup added.','success');
    }
    redirect(SITE_URL.'/admin/popup.php');
}

$editing = null;
if ($action === 'edit' && $id) {
    $s = db()->prepare("SELECT * FROM event_popups WHERE id=?"); $s->execute([$id]); $editing = $s->fetch();
}
$popups = db()->query("SELECT * FROM event_popups ORDER BY id DESC")->fetchAll();

require_once __DIR__ . '/partials/admin_header.php';
?>
<div class="admin-page-header"><h1>🎉 Event Popups</h1><p>Manage time-gated event popups (Eid, Puja, Promotions). Popup shows once per day per user via localStorage.</p></div>
<div class="admin-panel">
  <h2 class="admin-section-title"><?= $editing ? 'Edit Popup' : 'Add Event Popup' ?></h2>
  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="edit_id" value="<?= $editing['id'] ?? 0 ?>">
    <div class="form-row">
      <div class="form-group"><label>Title (English) *</label><input type="text" name="title_en" class="form-input" required value="<?= e($editing['title_en'] ?? '') ?>"></div>
      <div class="form-group"><label>Title (বাংলা)</label><input type="text" name="title_bn" class="form-input" value="<?= e($editing['title_bn'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Message Body (English) *</label><textarea name="body_en" class="form-textarea" rows="4" required><?= e($editing['body_en'] ?? '') ?></textarea></div>
      <div class="form-group"><label>Message Body (বাংলা)</label><textarea name="body_bn" class="form-textarea" rows="4"><?= e($editing['body_bn'] ?? '') ?></textarea></div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Popup Image (optional, 800×600px)</label>
        <?php if ($editing && $editing['image']): ?><div class="current-media"><img src="<?= imgUrl($editing['image'],'popup','popup') ?>" style="max-height:80px;border-radius:4px;"><small>Current image</small></div><?php endif; ?>
        <input type="file" name="image" class="form-input" accept="image/*">
      </div>
      <div class="form-group"><label>Status</label><select name="active" class="form-input"><option value="1" <?= ($editing['active'] ?? 1)==1?'selected':''?>>Active</option><option value="0" <?= ($editing['active']??1)==0?'selected':''?>>Inactive</option></select></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Start Date *</label><input type="date" name="start_date" class="form-input" required value="<?= e($editing['start_date'] ?? date('Y-m-d')) ?>"></div>
      <div class="form-group"><label>End Date *</label><input type="date" name="end_date" class="form-input" required value="<?= e($editing['end_date'] ?? date('Y-m-d', strtotime('+7 days'))) ?>"></div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $editing ? '💾 Update' : '➕ Add Popup' ?></button>
      <?php if ($editing): ?><a href="<?= SITE_URL ?>/admin/popup.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>
<div class="admin-panel">
  <h2 class="admin-section-title">All Popups</h2>
  <?php if ($popups): ?>
    <table class="admin-table">
      <thead><tr><th>Title</th><th>Date Range</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($popups as $p): $today = date('Y-m-d'); $live = $p['active'] && $p['start_date'] <= $today && $p['end_date'] >= $today; ?>
          <tr>
            <td><strong><?= e($p['title_en']) ?></strong></td>
            <td><?= date('d M y',strtotime($p['start_date'])) ?> → <?= date('d M y',strtotime($p['end_date'])) ?></td>
            <td><span class="badge <?= $live ? 'badge-green' : 'badge-gray' ?>"><?= $live ? 'Live' : ($p['active'] ? 'Scheduled/Expired' : 'Off') ?></span></td>
            <td class="table-actions">
              <a href="?action=edit&id=<?= $p['id'] ?>" class="btn-mini">Edit</a>
              <a href="?action=delete&id=<?= $p['id'] ?>&csrf_token=<?= csrf_token() ?>" class="btn-mini btn-danger" onclick="return confirm('Delete?')">Del</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?><p class="empty-msg">No popups yet.</p><?php endif; ?>
</div>
<?php require_once __DIR__ . '/partials/admin_footer.php'; ?>
