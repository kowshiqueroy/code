<?php
// admin/pages/staff.php
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$db     = getDB();

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = [
        'name_en'        => sanitize($_POST['name_en'] ?? ''),
        'name_bn'        => sanitize($_POST['name_bn'] ?? ''),
        'designation_en' => sanitize($_POST['designation_en'] ?? ''),
        'designation_bn' => sanitize($_POST['designation_bn'] ?? ''),
        'department_en'  => sanitize($_POST['department_en'] ?? ''),
        'department_bn'  => sanitize($_POST['department_bn'] ?? ''),
        'category'       => $_POST['category'] ?? 'teacher',
        'qualification'  => sanitize($_POST['qualification'] ?? ''),
        'subject_en'     => sanitize($_POST['subject_en'] ?? ''),
        'subject_bn'     => sanitize($_POST['subject_bn'] ?? ''),
        'phone'          => sanitize($_POST['phone'] ?? ''),
        'email'          => sanitize($_POST['email'] ?? ''),
        'joining_date'   => $_POST['joining_date'] ?: null,
        'bio_en'         => $_POST['bio_en'] ?? '',
        'bio_bn'         => $_POST['bio_bn'] ?? '',
        'sort_order'     => (int)($_POST['sort_order'] ?? 0),
        'is_active'      => isset($_POST['is_active']) ? 1 : 0,
        'is_featured'    => isset($_POST['is_featured']) ? 1 : 0,
        'photo'          => sanitize($_POST['current_photo'] ?? ''),
    ];

    // Photo upload (portrait mode: 300x300 crop)
    if (!empty($_FILES['photo']['name'])) {
        $up = handleUpload('photo', 'staff', 'portrait');
        if (isset($up['filename'])) { $d['photo'] = $up['filename']; }
    }

    if (!$d['name_en']) { flash('Name (English) required.','error'); }
    else {
        if ($id) {
            $keys = implode('=?,', array_keys($d)).'=?';
            $stmt = $db->prepare("UPDATE staff SET $keys WHERE id=?");
            $stmt->execute([...array_values($d), $id]);
            flash('Staff updated.','success');
        } else {
            $cols = implode(',', array_keys($d));
            $vals = implode(',', array_fill(0, count($d), '?'));
            $stmt = $db->prepare("INSERT INTO staff ($cols) VALUES ($vals)");
            $stmt->execute(array_values($d));
            flash('Staff added.','success');
        }
        redirect(ADMIN_PATH . '?section=staff');
    }
}

// Delete
if ($action === 'delete' && $id) {
    $db->prepare("DELETE FROM staff WHERE id=?")->execute([$id]);
    flash('Staff deleted.','success');
    redirect(ADMIN_PATH . '?section=staff');
}

// Edit data
$row = null;
if ($id) {
    $stmt = $db->prepare("SELECT * FROM staff WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
}

// List
$catFilter = $_GET['cat'] ?? '';
$where = '1=1'; $params = [];
if ($catFilter) { $where .= " AND category=?"; $params[] = $catFilter; }
$stmt = $db->prepare("SELECT * FROM staff WHERE $where ORDER BY category, sort_order, name_en");
$stmt->execute($params);
$staffList = $stmt->fetchAll();

$categories = ['principal','vice_principal','teacher','staff','governing_body','committee'];
?>

<?php if ($action === 'list'): ?>
<div class="acard">
  <div class="acard-header">
    <div class="acard-title">👥 Staff (<?= count($staffList) ?>)</div>
    <a href="?section=staff&action=add" class="btn btn-primary">+ Add Staff</a>
  </div>
  <div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
    <a href="?section=staff" class="btn btn-sm <?= !$catFilter?'btn-primary':'btn-light' ?>">All</a>
    <?php foreach ($categories as $c): ?><a href="?section=staff&cat=<?= h($c) ?>" class="btn btn-sm <?= $catFilter===$c?'btn-primary':'btn-light' ?>"><?= ucfirst(str_replace('_',' ',$c)) ?></a><?php endforeach; ?>
  </div>
  <div class="atable-wrap">
    <table class="atable">
      <thead><tr><th>Photo</th><th>Name</th><th>Category</th><th>Designation</th><th>Sort</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($staffList as $s): ?>
        <tr>
          <td><img src="<?= h(imgUrl($s['photo'],'thumb')) ?>" style="width:44px;height:44px;border-radius:50%;object-fit:cover" onerror="this.style.display='none'"></td>
          <td><strong><?= h($s['name_en']) ?></strong><br><small style="color:#888"><?= h($s['name_bn']) ?></small></td>
          <td><span class="badge badge-info"><?= ucfirst(str_replace('_',' ',$s['category'])) ?></span></td>
          <td><?= h($s['designation_en'] ?: '—') ?></td>
          <td><?= h($s['sort_order']) ?></td>
          <td><?= $s['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-gray">Hidden</span>' ?></td>
          <td>
            <a href="?section=staff&action=edit&id=<?= $s['id'] ?>" class="btn btn-sm btn-light">✏️</a>
            <a href="?section=staff&action=delete&id=<?= $s['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Delete this staff member?">🗑️</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$staffList): ?><tr><td colspan="7" style="text-align:center;padding:30px;color:#999">No staff found. <a href="?section=staff&action=add">Add one</a>.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<div class="acard">
  <div class="acard-header">
    <div class="acard-title"><?= $row ? '✏️ Edit Staff' : '+ Add Staff' ?></div>
    <a href="?section=staff" class="btn btn-light btn-sm">← Back</a>
  </div>
  <div class="acard-body">
    <form method="POST" enctype="multipart/form-data" class="aform">
      <input type="hidden" name="current_photo" value="<?= h($row['photo'] ?? '') ?>">
      <div class="atabs">
        <button class="atab-btn active" data-tab="basic" type="button">Basic Info</button>
        <button class="atab-btn" data-tab="academic" type="button">Academic</button>
        <button class="atab-btn" data-tab="photo" type="button">Photo</button>
        <button class="atab-btn" data-tab="bio" type="button">Bio</button>
      </div>

      <div class="atab-content active" id="atab-basic">
        <div class="form-row">
          <div class="form-group"><label>Name (English) <span class="req">*</span></label><input type="text" name="name_en" value="<?= h($row['name_en'] ?? '') ?>" required></div>
          <div class="form-group"><label>নাম (বাংলা)</label><input type="text" name="name_bn" value="<?= h($row['name_bn'] ?? '') ?>"></div>
          <div class="form-group"><label>Designation (EN)</label><input type="text" name="designation_en" value="<?= h($row['designation_en'] ?? '') ?>"></div>
          <div class="form-group"><label>পদবি (বাংলা)</label><input type="text" name="designation_bn" value="<?= h($row['designation_bn'] ?? '') ?>"></div>
          <div class="form-group"><label>Category</label>
            <select name="category">
              <?php foreach ($categories as $c): ?><option value="<?= $c ?>" <?= ($row['category'] ?? 'teacher')===$c?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$c)) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" value="<?= h($row['sort_order'] ?? 0) ?>" min="0"></div>
          <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= h($row['phone'] ?? '') ?>"></div>
          <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= h($row['email'] ?? '') ?>"></div>
        </div>
        <div style="display:flex;gap:20px">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_active" <?= ($row['is_active'] ?? 1)?'checked':'' ?>> Active</label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_featured" <?= ($row['is_featured'] ?? 0)?'checked':'' ?>> ⭐ Featured</label>
        </div>
      </div>

      <div class="atab-content" id="atab-academic">
        <div class="form-row">
          <div class="form-group"><label>Department (EN)</label><input type="text" name="department_en" value="<?= h($row['department_en'] ?? '') ?>"></div>
          <div class="form-group"><label>বিভাগ (বাংলা)</label><input type="text" name="department_bn" value="<?= h($row['department_bn'] ?? '') ?>"></div>
          <div class="form-group"><label>Subject (EN)</label><input type="text" name="subject_en" value="<?= h($row['subject_en'] ?? '') ?>"></div>
          <div class="form-group"><label>বিষয় (বাংলা)</label><input type="text" name="subject_bn" value="<?= h($row['subject_bn'] ?? '') ?>"></div>
          <div class="form-group"><label>Qualification</label><input type="text" name="qualification" value="<?= h($row['qualification'] ?? '') ?>" placeholder="e.g. M.Sc, B.Ed"></div>
          <div class="form-group"><label>Joining Date</label><input type="date" name="joining_date" value="<?= h($row['joining_date'] ?? '') ?>"></div>
        </div>
      </div>

      <div class="atab-content" id="atab-photo">
        <div class="form-group">
          <label>Staff Photo <span class="hint">(Auto-cropped to 300×300px, 1:1 ratio)</span></label>
          <input type="file" name="photo" accept="image/*" data-preview="photo_preview">
          <?php if (!empty($row['photo'])): ?>
          <img id="photo_preview" src="<?= h(imgUrl($row['photo'],'medium')) ?>" class="form-group thumb-preview" style="width:120px;height:120px;border-radius:50%;object-fit:cover;margin-top:10px">
          <?php else: ?>
          <img id="photo_preview" src="" style="display:none;width:120px;height:120px;border-radius:50%;object-fit:cover;margin-top:10px;border:2px solid var(--border)">
          <?php endif; ?>
        </div>
      </div>

      <div class="atab-content" id="atab-bio">
        <div class="form-group"><label>Bio (English)</label><textarea name="bio_en" rows="5" placeholder="About this person..."><?= h($row['bio_en'] ?? '') ?></textarea></div>
        <div class="form-group"><label>পরিচিতি (বাংলা)</label><textarea name="bio_bn" rows="5"><?= h($row['bio_bn'] ?? '') ?></textarea></div>
      </div>

      <div style="margin-top:20px;display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">💾 <?= $row ? 'Update' : 'Save' ?></button>
        <a href="?section=staff" class="btn btn-light">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
