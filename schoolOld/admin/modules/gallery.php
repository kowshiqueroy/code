<?php
/**
 * Admin Gallery Module
 */
$admin_title = 'Gallery & Media';
$mode = $_GET['mode'] ?? 'list';
$msg  = '';

// Handle album creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_album'])) {
    $title_en = trim($_POST['title_en'] ?? '');
    $title_bn = trim($_POST['title_bn'] ?? '');
    $type     = in_array($_POST['media_type']??'image',['image','video','mixed'])?$_POST['media_type']:'image';
    if ($title_en) {
        db()->prepare("INSERT INTO albums (title_en,title_bn,media_type,is_published) VALUES (?,?,?,1)")
           ->execute([$title_en,$title_bn,$type]);
        $msg = 'Album created.';
    }
}

// Handle gallery item upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_gallery'])) {
    $title_en  = trim($_POST['title_en'] ?? '');
    $album_id  = (int)($_POST['album_id'] ?? 0);
    $media_type = $_POST['media_type'] === 'video' ? 'video' : 'image';
    $video_url  = trim($_POST['video_url'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    $file_path = '';
    $thumb_path = '';

    if ($media_type === 'image' && !empty($_FILES['file']['name'])) {
        // Use media library processor
        $upload_dir = APP_ROOT . '/assets/uploads/gallery/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $base = uniqid('gal_');
            $orig = $upload_dir . $base . '.' . $ext;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $orig)) {
                // Create thumbnail
                if (function_exists('imagecreatefromjpeg')) {
                    include_once ADMIN_ROOT . '/modules/media.php'; // get resize_image if loaded
                }
                // Direct resize
                $thumb = $upload_dir . $base . '_thumb.' . $ext;
                if (function_exists('resize_image')) {
                    resize_image($orig, $thumb, 250, 200, true);
                    $thumb_path = 'gallery/' . $base . '_thumb.' . $ext;
                }
                $file_path = 'gallery/' . $base . '.' . $ext;
            }
        }
    }

    if ($file_path || $video_url) {
        db()->prepare("INSERT INTO gallery (title_en,album_id,media_type,file_path,thumbnail,video_url,sort_order,is_published) VALUES (?,?,?,?,?,?,?,1)")
           ->execute([$title_en,$album_id,$media_type,$file_path,$thumb_path,$video_url,$sort_order]);
        $msg = 'Gallery item added.';
    }
}

if (isset($_GET['delete'])) {
    $row = db()->prepare("SELECT * FROM gallery WHERE id=?"); $row->execute([(int)$_GET['delete']]); $row = $row->fetch();
    if ($row && $row['file_path']) @unlink(APP_ROOT.'/assets/uploads/'.$row['file_path']);
    db()->prepare("DELETE FROM gallery WHERE id=?")->execute([(int)$_GET['delete']]);
    header('Location: /admin/?action=gallery&msg=deleted'); exit;
}

$albums_list = db()->query("SELECT * FROM albums ORDER BY created_at DESC")->fetchAll();
$gallery_list = db()->query("SELECT g.*, a.title_en as album_title FROM gallery g LEFT JOIN albums a ON g.album_id=a.id ORDER BY g.created_at DESC LIMIT 50")->fetchAll();
?>

<?php if ($msg || isset($_GET['msg'])): ?>
<div class="alert alert-success">✅ <?= h($msg ?: 'Done') ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">
  <!-- Gallery Items -->
  <div>
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title">🖼️ Gallery Items (<?= count($gallery_list) ?>)</div>
        <a href="/?page=gallery" target="_blank" class="btn btn-sm btn-success">👁️ View Public</a>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;padding:16px">
        <?php foreach ($gallery_list as $item): ?>
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;overflow:hidden;text-align:center">
          <?php if ($item['media_type'] === 'image' && $item['file_path']): ?>
          <img src="<?= upload_url($item['thumbnail'] ?: $item['file_path']) ?>" alt="" style="width:100%;height:90px;object-fit:cover" loading="lazy">
          <?php elseif ($item['media_type'] === 'video'): ?>
          <div style="height:90px;background:#1a1a2e;display:flex;align-items:center;justify-content:center;font-size:1.8rem">🎬</div>
          <?php endif; ?>
          <div style="padding:6px 8px">
            <div style="font-size:.72rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($item['title_en']?:'Untitled') ?></div>
            <?php if ($item['album_title']): ?><div style="font-size:.65rem;color:var(--primary)"><?= h($item['album_title']) ?></div><?php endif; ?>
            <a href="/admin/?action=gallery&delete=<?= (int)$item['id'] ?>" class="btn btn-xs btn-danger" style="margin-top:4px;width:100%;justify-content:center" onclick="return confirm('Delete?')">🗑️</a>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($gallery_list)): ?>
        <p style="grid-column:1/-1;text-align:center;padding:30px;color:var(--muted)">No gallery items yet. Add some!</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Add Forms -->
  <div>
    <!-- Add Album -->
    <div class="panel" style="margin-bottom:16px">
      <div class="panel-header"><div class="panel-title">📁 Create Album</div></div>
      <div class="panel-body">
        <form method="POST">
          <input type="hidden" name="add_album" value="1">
          <div class="form-group">
            <label class="form-label">Album Name (English)</label>
            <input type="text" name="title_en" class="form-control" required placeholder="e.g. Annual Sports Day 2024">
          </div>
          <div class="form-group">
            <label class="form-label">Album Name (Bangla)</label>
            <input type="text" name="title_bn" class="form-control" placeholder="বার্ষিক ক্রীড়া দিবস ২০২৪">
          </div>
          <div class="form-group">
            <label class="form-label">Type</label>
            <select name="media_type" class="form-control form-select">
              <option value="image">Photos</option>
              <option value="video">Videos</option>
              <option value="mixed">Mixed</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-block">📁 Create Album</button>
        </form>
      </div>
    </div>

    <!-- Add Gallery Item -->
    <div class="panel">
      <div class="panel-header"><div class="panel-title">➕ Add Gallery Item</div></div>
      <div class="panel-body">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="add_gallery" value="1">
          <div class="form-group">
            <label class="form-label">Title (optional)</label>
            <input type="text" name="title_en" class="form-control" placeholder="Photo/video title">
          </div>
          <div class="form-group">
            <label class="form-label">Type</label>
            <select name="media_type" class="form-control form-select" id="galleryType" onchange="toggleVideoUrl()">
              <option value="image">Image</option>
              <option value="video">Video</option>
            </select>
          </div>
          <div id="imageUpload" class="form-group">
            <label class="form-label">Upload Image</label>
            <input type="file" name="file" class="form-control" accept="image/*">
          </div>
          <div id="videoUrlField" class="form-group" style="display:none">
            <label class="form-label">Video URL (YouTube or direct)</label>
            <input type="url" name="video_url" class="form-control" placeholder="https://youtube.com/watch?v=...">
          </div>
          <div class="form-group">
            <label class="form-label">Album</label>
            <select name="album_id" class="form-control form-select">
              <option value="0">— No Album —</option>
              <?php foreach ($albums_list as $a): ?>
              <option value="<?= $a['id'] ?>"><?= h($a['title_en']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-block">➕ Add Item</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function toggleVideoUrl() {
  const type = document.getElementById('galleryType').value;
  document.getElementById('imageUpload').style.display = type === 'image' ? 'block' : 'none';
  document.getElementById('videoUrlField').style.display = type === 'video' ? 'block' : 'none';
}
</script>
