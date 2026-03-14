<?php
/**
 * Admin Governing Body
 */
$admin_title = 'Governing Body';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_en = trim($_POST['name_en']??'');
    $name_bn = trim($_POST['name_bn']??'');
    $desig_en = trim($_POST['designation_en']??'');
    $desig_bn = trim($_POST['designation_bn']??'');
    $sort = (int)($_POST['sort_order']??0);
    $active = (int)!empty($_POST['is_active']);
    $photo = '';
    if (!empty($_FILES['photo']['name'])) {
        $dir = APP_ROOT.'/assets/uploads/photos/';
        if (!is_dir($dir)) mkdir($dir,0755,true);
        $ext = strtolower(pathinfo($_FILES['photo']['name'],PATHINFO_EXTENSION));
        if (in_array($ext,['jpg','jpeg','png','gif','webp'])) {
            $fname = 'gb_'.uniqid().'.'.$ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'],$dir.$fname)) {
                if (function_exists('resize_image')) resize_image($dir.$fname,$dir.'t_'.$fname,200,200,true);
                $photo = 'photos/t_'.$fname;
            }
        }
    }
    $edit_id = (int)($_POST['edit_id']??0);
    if ($name_en) {
        if ($edit_id) {
            db()->prepare("UPDATE governing_body SET name_en=?,name_bn=?,designation_en=?,designation_bn=?".($photo?",photo='$photo'":'').",sort_order=?,is_active=? WHERE id=?")
               ->execute([$name_en,$name_bn,$desig_en,$desig_bn,$sort,$active,$edit_id]);
        } else {
            db()->prepare("INSERT INTO governing_body (name_en,name_bn,designation_en,designation_bn,photo,sort_order,is_active) VALUES (?,?,?,?,?,?,?)")
               ->execute([$name_en,$name_bn,$desig_en,$desig_bn,$photo,$sort,$active]);
        }
        $msg = 'Member saved.';
    }
}
if (isset($_GET['delete'])) { db()->prepare("DELETE FROM governing_body WHERE id=?")->execute([(int)$_GET['delete']]); header('Location: /admin/?action=governing'); exit; }
$members = db()->query("SELECT * FROM governing_body ORDER BY sort_order")->fetchAll();
?>
<?php if ($msg): ?><div class="alert alert-success">✅ <?= h($msg) ?></div><?php endif; ?>
<div style="display:grid;grid-template-columns:3fr 2fr;gap:24px">
  <div class="panel">
    <div class="panel-header"><div class="panel-title">🏛️ Governing Body</div></div>
    <table class="admin-table">
      <thead><tr><th>Photo</th><th>Name</th><th>Designation</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($members as $m): ?>
        <tr>
          <td><?php if($m['photo']): ?><img src="<?= upload_url($m['photo']) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover"><?php else: ?><div style="width:40px;height:40px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center">👤</div><?php endif; ?></td>
          <td><strong><?= h($m['name_en']) ?></strong><?php if($m['name_bn']): ?><br><small><?= h($m['name_bn']) ?></small><?php endif; ?></td>
          <td><?= h($m['designation_en']) ?></td>
          <td><span class="status-badge <?= $m['is_active']?'status-published':'status-inactive' ?>"><?= $m['is_active']?'Active':'Inactive' ?></span></td>
          <td><a href="/admin/?action=governing&delete=<?= $m['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete?')">🗑️</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">➕ Add Member</div></div>
    <div class="panel-body">
      <form method="POST" enctype="multipart/form-data">
        <div class="form-group"><label class="form-label">Name (English) *</label><input type="text" name="name_en" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Name (Bangla)</label><input type="text" name="name_bn" class="form-control"></div>
        <div class="form-group"><label class="form-label">Designation (English)</label><input type="text" name="designation_en" class="form-control" placeholder="Chairman, Member..."></div>
        <div class="form-group"><label class="form-label">Designation (Bangla)</label><input type="text" name="designation_bn" class="form-control"></div>
        <div class="form-group"><label class="form-label">Photo</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
        <div class="form-group"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="<?= count($members)+1 ?>"></div>
        <label class="form-check" style="margin-bottom:14px"><input type="checkbox" name="is_active" value="1" checked> Active</label>
        <button type="submit" class="btn btn-primary">➕ Add Member</button>
      </form>
    </div>
  </div>
</div>
