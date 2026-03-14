<?php
/**
 * Admin Sliders Module
 */
$admin_title = 'Homepage Sliders';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title_en   = trim($_POST['title_en'] ?? '');
    $title_bn   = trim($_POST['title_bn'] ?? '');
    $subtitle_en = trim($_POST['subtitle_en'] ?? '');
    $subtitle_bn = trim($_POST['subtitle_bn'] ?? '');
    $link       = trim($_POST['link'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $is_active  = (int)!empty($_POST['is_active']);

    $image = $_POST['current_image'] ?? '';
    if (!empty($_FILES['image']['name'])) {
        $dir = APP_ROOT . '/assets/uploads/sliders/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $fname = 'slide_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dir.$fname)) {
                // Resize to 1400x600 for banner
                if (function_exists('resize_image')) {
                    resize_image($dir.$fname, $dir.'large_'.$fname, 1400, 600, false);
                }
                $image = 'sliders/' . $fname;
            }
        }
    }

    $edit_id = (int)($_POST['edit_id'] ?? 0);
    if ($edit_id) {
        db()->prepare("UPDATE sliders SET title_en=?,title_bn=?,subtitle_en=?,subtitle_bn=?,image=?,link=?,sort_order=?,is_active=? WHERE id=?")
           ->execute([$title_en,$title_bn,$subtitle_en,$subtitle_bn,$image,$link,$sort_order,$is_active,$edit_id]);
    } else {
        db()->prepare("INSERT INTO sliders (title_en,title_bn,subtitle_en,subtitle_bn,image,link,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$title_en,$title_bn,$subtitle_en,$subtitle_bn,$image,$link,$sort_order,$is_active]);
    }
    $msg = 'Slider saved.';
}

if (isset($_GET['delete'])) {
    $s = db()->prepare("SELECT image FROM sliders WHERE id=?"); $s->execute([(int)$_GET['delete']]); $s=$s->fetch();
    if ($s && $s['image']) @unlink(APP_ROOT.'/assets/uploads/'.$s['image']);
    db()->prepare("DELETE FROM sliders WHERE id=?")->execute([(int)$_GET['delete']]);
    header('Location: /admin/?action=sliders&msg=deleted'); exit;
}

$edit_row = null;
if (isset($_GET['edit'])) {
    $s = db()->prepare("SELECT * FROM sliders WHERE id=?"); $s->execute([(int)$_GET['edit']]); $edit_row = $s->fetch();
}
$sliders_list = db()->query("SELECT * FROM sliders ORDER BY sort_order")->fetchAll();
?>

<?php if ($msg || isset($_GET['msg'])): ?><div class="alert alert-success">✅ <?= h($msg ?: 'Done') ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:3fr 2fr;gap:24px">
  <!-- List -->
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title">🎠 Homepage Sliders (<?= count($sliders_list) ?>/5)</div>
    </div>
    <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>Preview</th><th>Title</th><th>Status</th><th>Sort</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($sliders_list as $s): ?>
          <tr>
            <td>
              <?php if ($s['image']): ?>
              <img src="<?= upload_url($s['image']) ?>" style="width:80px;height:45px;object-fit:cover;border-radius:4px;border:1px solid var(--border)">
              <?php else: ?>
              <div style="width:80px;height:45px;background:var(--primary);border-radius:4px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.7rem">No image</div>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-weight:600"><?= h($s['title_en']?:'(no title)') ?></div>
              <?php if ($s['subtitle_en']): ?><div style="font-size:.75rem;color:var(--muted)"><?= h(mb_substr($s['subtitle_en'],0,40)) ?></div><?php endif; ?>
            </td>
            <td><span class="status-badge <?= $s['is_active']?'status-published':'status-inactive' ?>"><?= $s['is_active']?'Active':'Hidden' ?></span></td>
            <td><?= (int)$s['sort_order'] ?></td>
            <td>
              <div class="actions">
                <a href="/admin/?action=sliders&edit=<?= $s['id'] ?>" class="btn btn-xs btn-secondary">✏️</a>
                <a href="/admin/?action=sliders&delete=<?= $s['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete?')">🗑️</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($sliders_list)): ?>
          <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--muted)">No sliders yet. Add your first slider!</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Form -->
  <div class="panel">
    <div class="panel-header"><div class="panel-title"><?= $edit_row ? '✏️ Edit Slider' : '➕ Add Slider' ?></div></div>
    <div class="panel-body">
      <form method="POST" enctype="multipart/form-data">
        <?php if ($edit_row): ?>
        <input type="hidden" name="edit_id" value="<?= $edit_row['id'] ?>">
        <input type="hidden" name="current_image" value="<?= h($edit_row['image']) ?>">
        <?php endif; ?>
        <div class="form-group">
          <label class="form-label">Slide Image</label>
          <?php if (!empty($edit_row['image'])): ?>
          <img src="<?= upload_url($edit_row['image']) ?>" style="width:100%;height:100px;object-fit:cover;border-radius:8px;margin-bottom:8px;border:1px solid var(--border)">
          <?php endif; ?>
          <input type="file" name="image" class="form-control" accept="image/*">
          <div class="form-hint">Recommended: 1400×600px or wider (16:5 ratio)</div>
        </div>
        <div class="form-group">
          <label class="form-label">Title (English)</label>
          <input type="text" name="title_en" class="form-control" value="<?= h($edit_row['title_en']??'') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Title (Bangla)</label>
          <input type="text" name="title_bn" class="form-control" value="<?= h($edit_row['title_bn']??'') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Subtitle (English)</label>
          <input type="text" name="subtitle_en" class="form-control" value="<?= h($edit_row['subtitle_en']??'') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Button Link</label>
          <input type="text" name="link" class="form-control" value="<?= h($edit_row['link']??'') ?>" placeholder="/?page=about">
        </div>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" value="<?= (int)($edit_row['sort_order']??count($sliders_list)+1) ?>">
          </div>
          <div class="form-group" style="padding-top:28px">
            <label class="form-check">
              <input type="checkbox" name="is_active" value="1" <?= ($edit_row['is_active']??1)?'checked':'' ?>>
              Active
            </label>
          </div>
        </div>
        <div style="display:flex;gap:10px">
          <button type="submit" class="btn btn-primary">💾 Save</button>
          <?php if ($edit_row): ?><a href="/admin/?action=sliders" class="btn btn-secondary">Cancel</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>
