<?php
// admin/pages/banners.php
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$db     = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = ['title_en'=>sanitize($_POST['title_en']??''),'title_bn'=>sanitize($_POST['title_bn']??''),'subtitle_en'=>sanitize($_POST['subtitle_en']??''),'subtitle_bn'=>sanitize($_POST['subtitle_bn']??''),'link'=>sanitize($_POST['link']??''),'sort_order'=>(int)($_POST['sort_order']??0),'is_active'=>isset($_POST['is_active'])?1:0,'image'=>sanitize($_POST['current_image']??'')];
    if (!empty($_FILES['image']['name'])) { $up = handleUpload('image','banners','banner'); if (isset($up['filename'])) $d['image'] = $up['filename']; }
    if ($id) { $stmt = $db->prepare("UPDATE banners SET title_en=?,title_bn=?,subtitle_en=?,subtitle_bn=?,link=?,sort_order=?,is_active=?,image=? WHERE id=?"); $stmt->execute([...array_values($d),$id]); flash('Banner updated.','success'); }
    else { $stmt = $db->prepare("INSERT INTO banners (title_en,title_bn,subtitle_en,subtitle_bn,link,sort_order,is_active,image) VALUES (?,?,?,?,?,?,?,?)"); $stmt->execute(array_values($d)); flash('Banner added.','success'); }
    redirect(ADMIN_PATH.'?section=banners');
}
if ($action === 'delete' && $id) { $db->prepare("DELETE FROM banners WHERE id=?")->execute([$id]); flash('Deleted.','success'); redirect(ADMIN_PATH.'?section=banners'); }
$row = null; if ($id) { $stmt = $db->prepare("SELECT * FROM banners WHERE id=?"); $stmt->execute([$id]); $row = $stmt->fetch(); }
$banners = $db->query("SELECT * FROM banners ORDER BY sort_order")->fetchAll();
?>
<div class="acard">
  <div class="acard-header"><div class="acard-title">🖼️ Banners / Slider</div><?php if ($action==='list'): ?><a href="?section=banners&action=add" class="btn btn-primary">+ Add Banner</a><?php else: ?><a href="?section=banners" class="btn btn-light btn-sm">← Back</a><?php endif; ?></div>
  <div class="acard-body">
    <?php if ($action === 'list'): ?>
    <table class="atable"><thead><tr><th>Image</th><th>Title</th><th>Sort</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($banners as $b): ?>
    <tr><td><?php if($b['image']): ?><img src="<?= h(imgUrl($b['image'],'thumb')) ?>" style="width:80px;height:45px;object-fit:cover;border-radius:4px"><?php else: ?><span style="font-size:2rem">🖼️</span><?php endif; ?></td><td><?= h($b['title_en']) ?></td><td><?= h($b['sort_order']) ?></td><td><?= $b['is_active']?'<span class="badge badge-success">Active</span>':'<span class="badge badge-gray">Hidden</span>' ?></td><td><a href="?section=banners&action=edit&id=<?= $b['id'] ?>" class="btn btn-sm btn-light">✏️</a> <a href="?section=banners&action=delete&id=<?= $b['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Delete?">🗑️</a></td></tr>
    <?php endforeach; ?></tbody></table>
    <?php else: ?>
    <form method="POST" enctype="multipart/form-data" class="aform">
      <input type="hidden" name="current_image" value="<?= h($row['image']??'') ?>">
      <div class="form-row">
        <div class="form-group"><label>Title (EN)</label><input type="text" name="title_en" value="<?= h($row['title_en']??'') ?>"></div>
        <div class="form-group"><label>শিরোনাম (বাংলা)</label><input type="text" name="title_bn" value="<?= h($row['title_bn']??'') ?>"></div>
        <div class="form-group"><label>Subtitle (EN)</label><input type="text" name="subtitle_en" value="<?= h($row['subtitle_en']??'') ?>"></div>
        <div class="form-group"><label>উপশিরোনাম (বাংলা)</label><input type="text" name="subtitle_bn" value="<?= h($row['subtitle_bn']??'') ?>"></div>
        <div class="form-group"><label>Link (optional)</label><input type="url" name="link" value="<?= h($row['link']??'') ?>"></div>
        <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" value="<?= h($row['sort_order']??0) ?>"></div>
      </div>
      <div class="form-group"><label>Banner Image <span class="hint">(Recommended: 1200×600px)</span></label><input type="file" name="image" accept="image/*" data-preview="img_prev"><?php if(!empty($row['image'])): ?><img id="img_prev" src="<?= h(imgUrl($row['image'],'medium')) ?>" style="margin-top:8px;max-height:120px;border-radius:6px;border:1px solid var(--border)"><?php else: ?><img id="img_prev" src="" style="display:none;max-height:120px;margin-top:8px"><?php endif; ?></div>
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:16px"><input type="checkbox" name="is_active" <?= ($row['is_active']??1)?'checked':'' ?>> Active</label>
      <button type="submit" class="btn btn-primary">💾 Save</button>
    </form>
    <?php endif; ?>
  </div>
</div>
