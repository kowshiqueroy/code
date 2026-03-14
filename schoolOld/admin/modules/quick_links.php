<?php
/**
 * Quick Links Admin
 */
$admin_title = 'Quick Links';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title_en = trim($_POST['title_en']??'');
    $title_bn = trim($_POST['title_bn']??'');
    $url      = trim($_POST['url']??'');
    $icon     = trim($_POST['icon']??'');
    $sort     = (int)($_POST['sort_order']??0);
    $active   = (int)!empty($_POST['is_active']);
    $edit_id  = (int)($_POST['edit_id']??0);
    if ($title_en && $url) {
        if ($edit_id) {
            db()->prepare("UPDATE quick_links SET title_en=?,title_bn=?,url=?,icon=?,sort_order=?,is_active=? WHERE id=?")
               ->execute([$title_en,$title_bn,$url,$icon,$sort,$active,$edit_id]);
        } else {
            db()->prepare("INSERT INTO quick_links (title_en,title_bn,url,icon,sort_order,is_active) VALUES (?,?,?,?,?,?)")
               ->execute([$title_en,$title_bn,$url,$icon,$sort,$active]);
        }
        $msg = 'Quick link saved.';
    }
}
if (isset($_GET['delete'])) { db()->prepare("DELETE FROM quick_links WHERE id=?")->execute([(int)$_GET['delete']]); header('Location: /admin/?action=quick_links'); exit; }
$links = db()->query("SELECT * FROM quick_links ORDER BY sort_order")->fetchAll();
?>
<?php if ($msg): ?><div class="alert alert-success">✅ <?= h($msg) ?></div><?php endif; ?>
<div style="display:grid;grid-template-columns:3fr 2fr;gap:24px">
  <div class="panel">
    <div class="panel-header"><div class="panel-title">🔗 Quick Links</div><small style="color:var(--muted)">Shown as quick access buttons on homepage</small></div>
    <table class="admin-table">
      <thead><tr><th>Icon</th><th>Title</th><th>URL</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($links as $l): ?>
        <tr>
          <td style="font-size:1.3rem"><?= h($l['icon']) ?></td>
          <td><strong><?= h($l['title_en']) ?></strong><?php if($l['title_bn']): ?><br><span style="font-size:.75rem;color:var(--muted)"><?= h($l['title_bn']) ?></span><?php endif; ?></td>
          <td style="font-size:.8rem"><a href="<?= h($l['url']) ?>" target="_blank"><?= h(mb_substr($l['url'],0,40)) ?></a></td>
          <td><span class="status-badge <?= $l['is_active']?'status-published':'status-inactive' ?>"><?= $l['is_active']?'Active':'Hidden' ?></span></td>
          <td><div class="actions"><a href="/admin/?action=quick_links&delete=<?= $l['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete?')">🗑️</a></div></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">➕ Add Quick Link</div></div>
    <div class="panel-body">
      <form method="POST">
        <div class="form-group"><label class="form-label">Title (English) *</label><input type="text" name="title_en" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Title (Bangla)</label><input type="text" name="title_bn" class="form-control"></div>
        <div class="form-group"><label class="form-label">URL *</label><input type="text" name="url" class="form-control" required placeholder="/?page=admissions or https://..."></div>
        <div class="form-group"><label class="form-label">Icon (Emoji)</label><input type="text" name="icon" class="form-control" placeholder="📝" maxlength="4"></div>
        <div class="form-group"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="<?= count($links)+1 ?>"></div>
        <label class="form-check" style="margin-bottom:14px"><input type="checkbox" name="is_active" value="1" checked> Active</label>
        <button type="submit" class="btn btn-primary">➕ Add</button>
      </form>
    </div>
  </div>
</div>
