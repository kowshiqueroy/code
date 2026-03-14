<?php
/**
 * Admin Teachers Module — with PHP image auto-resize
 */
$admin_title = 'Teachers';
$mode   = $_GET['mode'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$msg    = '';
$errors = [];

// ─── Image resize/crop helper ─────────────────────────────────────────────────
function resize_image(string $src_path, string $dst_path, int $w, int $h, bool $crop = true): bool {
    if (!function_exists('imagecreatefromjpeg')) return false;
    $info = @getimagesize($src_path);
    if (!$info) return false;
    [$sw, $sh, $type] = [$info[0], $info[1], $info[2]];

    $src_img = match($type) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($src_path),
        IMAGETYPE_PNG  => imagecreatefrompng($src_path),
        IMAGETYPE_GIF  => imagecreatefromgif($src_path),
        IMAGETYPE_WEBP => imagecreatefromwebp($src_path),
        default => false
    };
    if (!$src_img) return false;

    $dst = imagecreatetruecolor($w, $h);

    // Preserve transparency
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $w, $h, $transparent);
    }

    if ($crop) {
        // Smart crop: calculate source area to maintain aspect ratio
        $src_ratio = $sw / $sh;
        $dst_ratio = $w / $h;
        if ($src_ratio > $dst_ratio) {
            $copy_h = $sh;
            $copy_w = (int)($sh * $dst_ratio);
            $src_x  = (int)(($sw - $copy_w) / 2);
            $src_y  = 0;
        } else {
            $copy_w = $sw;
            $copy_h = (int)($sw / $dst_ratio);
            $src_x  = 0;
            $src_y  = (int)(($sh - $copy_h) / 2);
        }
        imagecopyresampled($dst, $src_img, 0, 0, $src_x, $src_y, $w, $h, $copy_w, $copy_h);
    } else {
        // Fit (no crop, add padding)
        $src_ratio = $sw / $sh;
        $dst_ratio = $w / $h;
        if ($src_ratio > $dst_ratio) {
            $new_w = $w;
            $new_h = (int)($w / $src_ratio);
            $off_x = 0;
            $off_y = (int)(($h - $new_h) / 2);
        } else {
            $new_h = $h;
            $new_w = (int)($h * $src_ratio);
            $off_x = (int)(($w - $new_w) / 2);
            $off_y = 0;
        }
        $bg = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $bg);
        imagecopyresampled($dst, $src_img, $off_x, $off_y, 0, 0, $new_w, $new_h, $sw, $sh);
    }

    $result = match($type) {
        IMAGETYPE_PNG  => imagepng($dst, $dst_path, 8),
        IMAGETYPE_GIF  => imagegif($dst, $dst_path),
        default        => imagejpeg($dst, $dst_path, 88)
    };

    imagedestroy($src_img);
    imagedestroy($dst);
    return $result;
}

// ─── Upload & auto-resize teacher photo ──────────────────────────────────────
function upload_teacher_photo(array $file): array {
    $upload_dir = APP_ROOT . '/assets/uploads/photos/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
        return ['success'=>false,'error'=>'Only image files allowed (JPG, PNG, GIF, WEBP).'];
    }
    if ($file['size'] > 5*1024*1024) return ['success'=>false,'error'=>'Image exceeds 5MB.'];

    $base = uniqid('teacher_');
    $orig  = $upload_dir . $base . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $orig)) {
        return ['success'=>false,'error'=>'Upload failed.'];
    }

    // Teacher photo: 300×300 square crop (1:1)
    $thumb = $upload_dir . $base . '_300x300.' . $ext;
    resize_image($orig, $thumb, 300, 300, true);

    // Also create medium 150×150 for cards
    $med = $upload_dir . $base . '_150x150.' . $ext;
    resize_image($orig, $med, 150, 150, true);

    // Clean up original (save space)
    // Keep original for quality re-processing
    return ['success'=>true,'path'=>'photos/' . $base . '_300x300.' . $ext];
}

// ─── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_en     = trim($_POST['name_en'] ?? '');
    $name_bn     = trim($_POST['name_bn'] ?? '');
    $desig_en    = trim($_POST['designation_en'] ?? '');
    $desig_bn    = trim($_POST['designation_bn'] ?? '');
    $dept_en     = trim($_POST['department_en'] ?? '');
    $dept_bn     = trim($_POST['department_bn'] ?? '');
    $qual        = trim($_POST['qualification'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $bio_en      = $_POST['bio_en'] ?? '';
    $bio_bn      = $_POST['bio_bn'] ?? '';
    $sort_order  = (int)($_POST['sort_order'] ?? 0);
    $is_principal = (int)!empty($_POST['is_principal']);
    $is_active   = (int)!empty($_POST['is_active']);
    $joined_date = $_POST['joined_date'] ?? null;

    if (!$name_en) $errors[] = 'English name is required.';

    $photo = $_POST['current_photo'] ?? '';
    if (!empty($_FILES['photo']['name'])) {
        $up = upload_teacher_photo($_FILES['photo']);
        if ($up['success']) $photo = $up['path'];
        else $errors[] = $up['error'];
    }

    if (empty($errors)) {
        $edit_id = (int)($_POST['edit_id'] ?? 0);
        if ($is_principal) {
            db()->query("UPDATE teachers SET is_principal=0"); // Only one principal
        }
        try {
            if ($edit_id) {
                db()->prepare("UPDATE teachers SET name_en=?,name_bn=?,designation_en=?,designation_bn=?,department_en=?,department_bn=?,qualification=?,email=?,phone=?,bio_en=?,bio_bn=?,photo=?,sort_order=?,is_principal=?,is_active=?,joined_date=? WHERE id=?")
                   ->execute([$name_en,$name_bn,$desig_en,$desig_bn,$dept_en,$dept_bn,$qual,$email,$phone,$bio_en,$bio_bn,$photo,$sort_order,$is_principal,$is_active,$joined_date?:null,$edit_id]);
                $msg = 'Teacher updated.';
            } else {
                db()->prepare("INSERT INTO teachers (name_en,name_bn,designation_en,designation_bn,department_en,department_bn,qualification,email,phone,bio_en,bio_bn,photo,sort_order,is_principal,is_active,joined_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$name_en,$name_bn,$desig_en,$desig_bn,$dept_en,$dept_bn,$qual,$email,$phone,$bio_en,$bio_bn,$photo,$sort_order,$is_principal,$is_active,$joined_date?:null]);
                $msg = 'Teacher added.';
            }
            $mode = 'list';
        } catch(Exception $e) { $errors[] = $e->getMessage(); }
    }
}

if (isset($_GET['delete'])) {
    db()->prepare("DELETE FROM teachers WHERE id=?")->execute([(int)$_GET['delete']]);
    header('Location: /admin/?action=teachers&msg=deleted'); exit;
}

$edit_row = null;
if ($mode === 'edit' && $id) {
    $s = db()->prepare("SELECT * FROM teachers WHERE id=?"); $s->execute([$id]); $edit_row = $s->fetch();
}

$teachers_list = db()->query("SELECT * FROM teachers ORDER BY is_principal DESC, sort_order, name_en")->fetchAll();
?>

<?php if ($msg || isset($_GET['msg'])): ?>
<div class="alert alert-success">✅ <?= h($msg ?: 'Done.') ?></div>
<?php endif; ?>
<?php foreach ($errors as $e): ?><div class="alert alert-error">⚠️ <?= h($e) ?></div><?php endforeach; ?>

<?php if ($mode === 'list'): ?>
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">👩‍🏫 Teachers & Staff</div>
    <a href="/admin/?action=teachers&mode=add" class="btn btn-primary">➕ Add Teacher</a>
  </div>
  <div class="table-wrap">
    <table class="admin-table">
      <thead><tr><th>Photo</th><th>Name</th><th>Designation</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($teachers_list as $t): ?>
        <tr>
          <td>
            <?php if ($t['photo']): ?>
            <img src="<?= upload_url($t['photo']) ?>" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">
            <?php else: ?>
            <div style="width:44px;height:44px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:1.3rem">👤</div>
            <?php endif; ?>
          </td>
          <td>
            <div style="font-weight:600"><?= h($t['name_en']) ?></div>
            <?php if ($t['name_bn']): ?><div style="font-size:.75rem;color:var(--muted)"><?= h($t['name_bn']) ?></div><?php endif; ?>
            <?php if ($t['is_principal']): ?><span class="status-badge status-published" style="margin-top:2px;display:inline-block">Principal</span><?php endif; ?>
          </td>
          <td style="font-size:.88rem"><?= h($t['designation_en']) ?></td>
          <td style="font-size:.88rem;color:var(--muted)"><?= h($t['department_en']) ?></td>
          <td><span class="status-badge <?= $t['is_active']?'status-published':'status-inactive' ?>"><?= $t['is_active']?'Active':'Inactive' ?></span></td>
          <td>
            <div class="actions">
              <a href="/admin/?action=teachers&mode=edit&id=<?= (int)$t['id'] ?>" class="btn btn-xs btn-secondary">✏️ Edit</a>
              <a href="/admin/?action=teachers&delete=<?= (int)$t['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete?')">🗑️</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($teachers_list)): ?>
        <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted)">No teachers added yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<div class="panel">
  <div class="panel-header">
    <div class="panel-title"><?= $mode==='edit'?'✏️ Edit Teacher':'➕ Add Teacher' ?></div>
    <a href="/admin/?action=teachers" class="btn btn-secondary btn-sm">← Back</a>
  </div>
  <div class="panel-body">
    <form method="POST" enctype="multipart/form-data">
      <?php if ($edit_row): ?><input type="hidden" name="edit_id" value="<?= (int)$edit_row['id'] ?>"><?php endif; ?>
      <input type="hidden" name="current_photo" value="<?= h($edit_row['photo'] ?? '') ?>">

      <div style="display:grid;grid-template-columns:160px 1fr;gap:24px;align-items:start;margin-bottom:24px">
        <!-- Photo upload -->
        <div style="text-align:center">
          <div id="photoPreview" style="width:120px;height:120px;border-radius:50%;margin:0 auto 12px;border:3px solid var(--border);overflow:hidden;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:2.5rem">
            <?php if (!empty($edit_row['photo'])): ?>
            <img src="<?= upload_url($edit_row['photo']) ?>" style="width:100%;height:100%;object-fit:cover" id="photoImg">
            <?php else: ?>
            <span id="photoIcon">👤</span>
            <?php endif; ?>
          </div>
          <label for="photo" style="cursor:pointer" class="btn btn-secondary btn-sm">📷 Upload Photo</label>
          <input type="file" name="photo" id="photo" accept="image/*" style="display:none" onchange="previewPhoto(this)">
          <div style="font-size:.72rem;color:var(--muted);margin-top:6px">Auto-resized to 300×300px square</div>
        </div>
        <div>
          <div class="form-row col-2">
            <div class="form-group">
              <label class="form-label">Full Name (English) *</label>
              <input type="text" name="name_en" class="form-control" required value="<?= h($edit_row['name_en']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Full Name (Bangla)</label>
              <input type="text" name="name_bn" class="form-control" value="<?= h($edit_row['name_bn']??'') ?>">
            </div>
          </div>
          <div class="form-row col-2">
            <div class="form-group">
              <label class="form-label">Designation (English)</label>
              <input type="text" name="designation_en" class="form-control" value="<?= h($edit_row['designation_en']??'') ?>" placeholder="e.g. Assistant Teacher">
            </div>
            <div class="form-group">
              <label class="form-label">Designation (Bangla)</label>
              <input type="text" name="designation_bn" class="form-control" value="<?= h($edit_row['designation_bn']??'') ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Department (English)</label>
          <input type="text" name="department_en" class="form-control" value="<?= h($edit_row['department_en']??'') ?>" placeholder="e.g. Science, Mathematics">
        </div>
        <div class="form-group">
          <label class="form-label">Department (Bangla)</label>
          <input type="text" name="department_bn" class="form-control" value="<?= h($edit_row['department_bn']??'') ?>">
        </div>
      </div>

      <div class="form-row col-3">
        <div class="form-group">
          <label class="form-label">Qualification</label>
          <input type="text" name="qualification" class="form-control" value="<?= h($edit_row['qualification']??'') ?>" placeholder="e.g. M.Sc., B.Ed.">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= h($edit_row['email']??'') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="tel" name="phone" class="form-control" value="<?= h($edit_row['phone']??'') ?>">
        </div>
      </div>

      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Bio / Message (English)</label>
          <textarea name="bio_en" class="form-control" rows="5"><?= h($edit_row['bio_en']??'') ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Bio / Message (Bangla)</label>
          <textarea name="bio_bn" class="form-control" rows="5" style="font-family:'Hind Siliguri',sans-serif"><?= h($edit_row['bio_bn']??'') ?></textarea>
        </div>
      </div>

      <div class="form-row col-3">
        <div class="form-group">
          <label class="form-label">Joining Date</label>
          <input type="date" name="joined_date" class="form-control" value="<?= h($edit_row['joined_date']??'') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Sort Order</label>
          <input type="number" name="sort_order" class="form-control" value="<?= (int)($edit_row['sort_order']??0) ?>">
        </div>
      </div>

      <div style="display:flex;gap:20px;margin-bottom:20px">
        <label class="form-check">
          <input type="checkbox" name="is_active" value="1" <?= ($edit_row['is_active']??1)?'checked':'' ?>>
          Active
        </label>
        <label class="form-check">
          <input type="checkbox" name="is_principal" value="1" <?= ($edit_row['is_principal']??0)?'checked':'' ?>>
          Is Principal/Head Teacher
        </label>
      </div>

      <div style="display:flex;gap:12px">
        <button type="submit" class="btn btn-primary">💾 Save</button>
        <a href="/admin/?action=teachers" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
function previewPhoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const preview = document.getElementById('photoPreview');
      preview.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover">';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
<?php endif; ?>
