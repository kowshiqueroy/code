<?php
// admin/pages/gallery.php
$action = $_GET['action'] ?? 'list';
$albumId = (int)($_GET['album_id'] ?? 0);
$id      = (int)($_GET['id'] ?? 0);
$db      = getDB();

// Add album
if ($action === 'add_album' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = [
        'title_en'       => sanitize($_POST['title_en'] ?? ''),
        'title_bn'       => sanitize($_POST['title_bn'] ?? ''),
        'description_en' => sanitize($_POST['description_en'] ?? ''),
        'description_bn' => sanitize($_POST['description_bn'] ?? ''),
        'album_date'     => $_POST['album_date'] ?: null,
        'is_active'      => isset($_POST['is_active']) ? 1 : 0,
        'sort_order'     => (int)($_POST['sort_order'] ?? 0),
        'cover_image'    => '',
    ];
    if ($d['title_en']) {
        $stmt = $db->prepare("INSERT INTO gallery_albums (title_en,title_bn,description_en,description_bn,album_date,is_active,sort_order) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$d['title_en'],$d['title_bn'],$d['description_en'],$d['description_bn'],$d['album_date'],$d['is_active'],$d['sort_order']]);
        flash('Album created.','success');
    }
    redirect(ADMIN_PATH . '?section=gallery');
}

// Upload images to album
if ($action === 'upload' && $albumId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploaded = 0;
    foreach ($_FILES['images']['tmp_name'] ?? [] as $idx => $tmp) {
        if ($_FILES['images']['error'][$idx] !== UPLOAD_ERR_OK) continue;
        $fakeFile = ['name'=>$_FILES['images']['name'][$idx],'tmp_name'=>$tmp,'size'=>$_FILES['images']['size'][$idx],'error'=>0,'type'=>$_FILES['images']['type'][$idx]];
        $_FILES['img_single'] = $fakeFile;
        $up = handleUpload('img_single', 'gallery');
        if (isset($up['sizes'])) {
            $stmt = $db->prepare("INSERT INTO gallery_images (album_id,filename,thumb,medium,large,title_en) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$albumId, $up['filename'], $up['sizes']['thumb'] ?? '', $up['sizes']['medium'] ?? '', $up['sizes']['large'] ?? '', '']);
            $uploaded++;
        }
    }
    flash("$uploaded image(s) uploaded.", 'success');
    redirect(ADMIN_PATH . "?section=gallery&action=album&album_id=$albumId");
}

// Delete album
if ($action === 'delete_album' && $id) {
    $db->prepare("DELETE FROM gallery_albums WHERE id=?")->execute([$id]);
    flash('Album deleted.','success');
    redirect(ADMIN_PATH . '?section=gallery');
}

// Delete image
if ($action === 'delete_img' && $id) {
    $img = $db->prepare("SELECT * FROM gallery_images WHERE id=?")->execute([$id]) ? $db->prepare("SELECT album_id FROM gallery_images WHERE id=?")->execute([$id]) : null;
    $stmt = $db->prepare("SELECT album_id FROM gallery_images WHERE id=?"); $stmt->execute([$id]); $imgRow = $stmt->fetch();
    $db->prepare("DELETE FROM gallery_images WHERE id=?")->execute([$id]);
    flash('Image deleted.','success');
    redirect(ADMIN_PATH . '?section=gallery&action=album&album_id=' . ($imgRow['album_id'] ?? 0));
}

// Albums list
if ($action === 'list') {
    $albums = $db->query("SELECT ga.*, (SELECT COUNT(*) FROM gallery_images gi WHERE gi.album_id=ga.id) AS img_count FROM gallery_albums ga ORDER BY ga.sort_order,ga.created_at DESC")->fetchAll();
}

// Album detail (upload)
if ($action === 'album' && $albumId) {
    $stmt = $db->prepare("SELECT * FROM gallery_albums WHERE id=?"); $stmt->execute([$albumId]); $album = $stmt->fetch();
    $stmt = $db->prepare("SELECT * FROM gallery_images WHERE album_id=? ORDER BY sort_order,id"); $stmt->execute([$albumId]); $images = $stmt->fetchAll();
}
?>

<?php if ($action === 'list'): ?>
<div class="acard">
  <div class="acard-header">
    <div class="acard-title">📷 Gallery Albums</div>
    <button class="btn btn-primary" data-modal-open="addAlbumModal">+ New Album</button>
  </div>
  <div class="atable-wrap">
    <table class="atable">
      <thead><tr><th>Title</th><th>Date</th><th>Images</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($albums as $a): ?>
        <tr>
          <td><a href="?section=gallery&action=album&album_id=<?= $a['id'] ?>"><?= h($a['title_en']) ?></a></td>
          <td><?= $a['album_date'] ? date('d M Y',strtotime($a['album_date'])) : '—' ?></td>
          <td><span class="badge badge-info"><?= $a['img_count'] ?> photos</span></td>
          <td><?= $a['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-gray">Hidden</span>' ?></td>
          <td>
            <a href="?section=gallery&action=album&album_id=<?= $a['id'] ?>" class="btn btn-sm btn-light">📷 Manage</a>
            <a href="?section=gallery&action=delete_album&id=<?= $a['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Delete this album and all its images?">🗑️</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$albums): ?><tr><td colspan="5" style="text-align:center;padding:30px;color:#999">No albums yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Album Modal -->
<div class="modal-overlay" id="addAlbumModal">
  <div class="modal-box">
    <div class="modal-header"><h3>+ New Album</h3><button class="modal-close">✕</button></div>
    <div class="modal-body">
      <form method="POST" action="?section=gallery&action=add_album" class="aform">
        <div class="form-row">
          <div class="form-group"><label>Album Title (EN) <span class="req">*</span></label><input type="text" name="title_en" required></div>
          <div class="form-group"><label>অ্যালবামের নাম (বাংলা)</label><input type="text" name="title_bn"></div>
          <div class="form-group"><label>Date</label><input type="date" name="album_date"></div>
          <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" value="0"></div>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
          <button type="submit" class="btn btn-primary">Create Album</button>
        </div>
        <input type="hidden" name="is_active" value="1">
      </form>
    </div>
  </div>
</div>

<?php elseif ($action === 'album' && isset($album)): ?>
<div class="acard">
  <div class="acard-header">
    <div class="acard-title">📷 <?= h($album['title_en']) ?> (<?= count($images) ?> photos)</div>
    <a href="?section=gallery" class="btn btn-light btn-sm">← Albums</a>
  </div>
  <div class="acard-body">
    <!-- Upload form -->
    <form method="POST" enctype="multipart/form-data" action="?section=gallery&action=upload&album_id=<?= $albumId ?>">
      <div class="form-group">
        <label>Upload Images (Multiple allowed) <span class="hint">— Auto-resized to multiple sizes</span></label>
        <input type="file" name="images[]" accept="image/*" multiple required>
      </div>
      <button type="submit" class="btn btn-primary">📤 Upload Images</button>
    </form>
    <hr style="margin:20px 0;border:none;border-top:1px solid var(--border)">
    <!-- Image grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px">
      <?php foreach ($images as $img): ?>
      <div style="position:relative;aspect-ratio:1;border-radius:8px;overflow:hidden;background:#f0f0f0">
        <img src="<?= h(imgUrl($img['filename'],'thumb')) ?>" style="width:100%;height:100%;object-fit:cover">
        <a href="?section=gallery&action=delete_img&id=<?= $img['id'] ?>&album_id=<?= $albumId ?>" class="btn btn-xs btn-danger" data-confirm="Delete this image?" style="position:absolute;top:6px;right:6px">🗑️</a>
      </div>
      <?php endforeach; ?>
      <?php if (!$images): ?><p style="grid-column:1/-1;text-align:center;color:#999;padding:30px">No images yet. Upload above.</p><?php endif; ?>
    </div>
  </div>
</div>

<?php else: ?>
<div class="alert alert-info">Album not found.</div>
<?php endif; ?>
