<?php
/**
 * Admin Departments Module
 */
$admin_title = 'Departments';
$msg = '';
$mode = $_GET['mode'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_en = trim($_POST['name_en']??'');
    $name_bn = trim($_POST['name_bn']??'');
    $desc_en = trim($_POST['description_en']??'');
    $desc_bn = trim($_POST['description_bn']??'');
    $head_id = (int)($_POST['head_teacher_id']??0);
    $sort    = (int)($_POST['sort_order']??0);
    $active  = (int)!empty($_POST['is_active']);
    $edit_id = (int)($_POST['edit_id']??0);
    if ($name_en) {
        if ($edit_id) {
            db()->prepare("UPDATE departments SET name_en=?,name_bn=?,description_en=?,description_bn=?,head_teacher_id=?,sort_order=?,is_active=? WHERE id=?")
               ->execute([$name_en,$name_bn,$desc_en,$desc_bn,$head_id?:null,$sort,$active,$edit_id]);
        } else {
            db()->prepare("INSERT INTO departments (name_en,name_bn,description_en,description_bn,head_teacher_id,sort_order,is_active) VALUES (?,?,?,?,?,?,?)")
               ->execute([$name_en,$name_bn,$desc_en,$desc_bn,$head_id?:null,$sort,$active]);
        }
        $msg = 'Department saved.'; $mode = 'list';
    }
}
if (isset($_GET['delete'])) { db()->prepare("DELETE FROM departments WHERE id=?")->execute([(int)$_GET['delete']]); header('Location: /admin/?action=departments'); exit; }
$edit_row = null;
if ($mode === 'edit') { $s=db()->prepare("SELECT * FROM departments WHERE id=?"); $s->execute([(int)($_GET['id']??0)]); $edit_row=$s->fetch(); }
$depts = db()->query("SELECT d.*, t.name_en as head FROM departments d LEFT JOIN teachers t ON d.head_teacher_id=t.id ORDER BY d.sort_order")->fetchAll();
$teachers_for_select = db()->query("SELECT id, name_en FROM teachers WHERE is_active=1 ORDER BY name_en")->fetchAll();
?>
<?php if ($msg): ?><div class="alert alert-success">✅ <?= h($msg) ?></div><?php endif; ?>
<div style="display:grid;grid-template-columns:3fr 2fr;gap:24px">
  <div class="panel">
    <div class="panel-header"><div class="panel-title">🏛️ Departments</div><a href="/admin/?action=departments&mode=add" class="btn btn-primary btn-sm">➕ Add</a></div>
    <table class="admin-table">
      <thead><tr><th>Name</th><th>Head Teacher</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($depts as $d): ?>
        <tr>
          <td><strong><?= h($d['name_en']) ?></strong><?php if($d['name_bn']): ?><br><small><?= h($d['name_bn']) ?></small><?php endif; ?></td>
          <td style="font-size:.85rem"><?= h($d['head']?:'—') ?></td>
          <td><span class="status-badge <?= $d['is_active']?'status-published':'status-inactive' ?>"><?= $d['is_active']?'Active':'Inactive' ?></span></td>
          <td><div class="actions"><a href="/admin/?action=departments&mode=edit&id=<?= $d['id'] ?>" class="btn btn-xs btn-secondary">✏️</a><a href="/admin/?action=departments&delete=<?= $d['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete?')">🗑️</a></div></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="panel">
    <div class="panel-header"><div class="panel-title"><?= $edit_row?'✏️ Edit':'➕ Add' ?> Department</div></div>
    <div class="panel-body">
      <form method="POST">
        <?php if ($edit_row): ?><input type="hidden" name="edit_id" value="<?= $edit_row['id'] ?>"><?php endif; ?>
        <div class="form-group"><label class="form-label">Name (English) *</label><input type="text" name="name_en" class="form-control" required value="<?= h($edit_row['name_en']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Name (Bangla)</label><input type="text" name="name_bn" class="form-control" value="<?= h($edit_row['name_bn']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Description (English)</label><textarea name="description_en" class="form-control" rows="3"><?= h($edit_row['description_en']??'') ?></textarea></div>
        <div class="form-group"><label class="form-label">Head Teacher</label>
          <select name="head_teacher_id" class="form-control form-select">
            <option value="0">— None —</option>
            <?php foreach ($teachers_for_select as $t): ?>
            <option value="<?= $t['id'] ?>" <?= ($edit_row['head_teacher_id']??0)==$t['id']?'selected':'' ?>><?= h($t['name_en']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row col-2">
          <div class="form-group"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="<?= (int)($edit_row['sort_order']??count($depts)+1) ?>"></div>
          <div class="form-group" style="padding-top:28px"><label class="form-check"><input type="checkbox" name="is_active" value="1" <?= ($edit_row['is_active']??1)?'checked':'' ?>> Active</label></div>
        </div>
        <div style="display:flex;gap:10px"><button type="submit" class="btn btn-primary">💾 Save</button><a href="/admin/?action=departments" class="btn btn-secondary">Cancel</a></div>
      </form>
    </div>
  </div>
</div>
