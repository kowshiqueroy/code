<?php
/**
 * Admin Admissions Module
 */
$admin_title = 'Admissions';
$msg  = '';
$mode = $_GET['mode'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title_en   = trim($_POST['title_en']??'');
    $title_bn   = trim($_POST['title_bn']??'');
    $content_en = $_POST['content_en']??'';
    $content_bn = $_POST['content_bn']??'';
    $class_name = trim($_POST['class_name']??'');
    $acad_year  = trim($_POST['academic_year']??'');
    $last_date  = $_POST['last_date']??null;
    $is_active  = (int)!empty($_POST['is_active']);
    $form_file  = $_POST['current_form']??'';

    if (!empty($_FILES['form_file']['name'])) {
        $dir = APP_ROOT.'/assets/uploads/documents/';
        if (!is_dir($dir)) mkdir($dir,0755,true);
        $ext = strtolower(pathinfo($_FILES['form_file']['name'],PATHINFO_EXTENSION));
        if (in_array($ext,['pdf','doc','docx','jpg','png'])) {
            $fname = 'admission_form_'.uniqid().'.'.$ext;
            if (move_uploaded_file($_FILES['form_file']['tmp_name'],$dir.$fname)) {
                $form_file = 'documents/'.$fname;
            }
        }
    }

    $edit_id = (int)($_POST['edit_id']??0);
    if ($title_en) {
        if ($edit_id) {
            db()->prepare("UPDATE admission_info SET title_en=?,title_bn=?,content_en=?,content_bn=?,class_name=?,academic_year=?,last_date=?,form_file=?,is_active=? WHERE id=?")
               ->execute([$title_en,$title_bn,$content_en,$content_bn,$class_name,$acad_year,$last_date?:null,$form_file,$is_active,$edit_id]);
        } else {
            db()->prepare("INSERT INTO admission_info (title_en,title_bn,content_en,content_bn,class_name,academic_year,last_date,form_file,is_active) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$title_en,$title_bn,$content_en,$content_bn,$class_name,$acad_year,$last_date?:null,$form_file,$is_active]);
        }
        $msg = 'Admission info saved.'; $mode = 'list';
    }
}
if (isset($_GET['delete'])) { db()->prepare("DELETE FROM admission_info WHERE id=?")->execute([(int)$_GET['delete']]); header('Location: /admin/?action=admissions_admin'); exit; }
$edit_row = null;
if ($mode === 'edit') { $s=db()->prepare("SELECT * FROM admission_info WHERE id=?"); $s->execute([(int)($_GET['id']??0)]); $edit_row=$s->fetch(); }
$list = db()->query("SELECT * FROM admission_info ORDER BY created_at DESC")->fetchAll();
?>
<?php if ($msg): ?><div class="alert alert-success">✅ <?= h($msg) ?></div><?php endif; ?>
<?php if ($mode === 'list'): ?>
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">📝 Admission Info</div>
    <a href="/admin/?action=admissions_admin&mode=add" class="btn btn-primary btn-sm">➕ Add</a>
  </div>
  <table class="admin-table">
    <thead><tr><th>Title</th><th>Class</th><th>Year</th><th>Last Date</th><th>Form</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($list as $a): ?>
      <tr>
        <td><strong><?= h($a['title_en']) ?></strong></td>
        <td><?= h($a['class_name']) ?></td>
        <td><?= h($a['academic_year']) ?></td>
        <td><?= $a['last_date'] ? date('d M Y',strtotime($a['last_date'])) : '—' ?></td>
        <td><?php if($a['form_file']): ?><a href="<?= upload_url($a['form_file']) ?>" target="_blank" class="btn btn-xs btn-success">📄</a><?php else: ?>—<?php endif; ?></td>
        <td><span class="status-badge <?= $a['is_active']?'status-published':'status-inactive' ?>"><?= $a['is_active']?'Active':'Inactive' ?></span></td>
        <td><div class="actions"><a href="/admin/?action=admissions_admin&mode=edit&id=<?= $a['id'] ?>" class="btn btn-xs btn-secondary">✏️</a><a href="/admin/?action=admissions_admin&delete=<?= $a['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete?')">🗑️</a></div></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="panel">
  <div class="panel-header"><div class="panel-title"><?= $edit_row?'✏️ Edit':'➕ Add' ?> Admission Info</div><a href="/admin/?action=admissions_admin" class="btn btn-secondary btn-sm">← Back</a></div>
  <div class="panel-body">
    <form method="POST" enctype="multipart/form-data">
      <?php if ($edit_row): ?><input type="hidden" name="edit_id" value="<?= $edit_row['id'] ?>"><input type="hidden" name="current_form" value="<?= h($edit_row['form_file']??'') ?>"><?php endif; ?>
      <div class="form-row col-2">
        <div class="form-group"><label class="form-label">Title (English) *</label><input type="text" name="title_en" class="form-control" required value="<?= h($edit_row['title_en']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Title (Bangla)</label><input type="text" name="title_bn" class="form-control" value="<?= h($edit_row['title_bn']??'') ?>"></div>
      </div>
      <div class="form-row col-3">
        <div class="form-group"><label class="form-label">Class / Grade</label><input type="text" name="class_name" class="form-control" value="<?= h($edit_row['class_name']??'') ?>" placeholder="e.g. Class VI"></div>
        <div class="form-group"><label class="form-label">Academic Year</label><input type="text" name="academic_year" class="form-control" value="<?= h($edit_row['academic_year']??date('Y')) ?>"></div>
        <div class="form-group"><label class="form-label">Last Date</label><input type="date" name="last_date" class="form-control" value="<?= h($edit_row['last_date']??'') ?>"></div>
      </div>
      <div class="form-row col-2">
        <div class="form-group"><label class="form-label">Content (English)</label><textarea name="content_en" class="form-control" rows="6"><?= h($edit_row['content_en']??'') ?></textarea></div>
        <div class="form-group"><label class="form-label">Content (Bangla)</label><textarea name="content_bn" class="form-control" rows="6" style="font-family:'Hind Siliguri',sans-serif"><?= h($edit_row['content_bn']??'') ?></textarea></div>
      </div>
      <div class="form-group"><label class="form-label">Admission Form File (PDF/DOC)</label><?php if(!empty($edit_row['form_file'])): ?><a href="<?= upload_url($edit_row['form_file']) ?>" target="_blank" class="btn btn-xs btn-success" style="margin-bottom:6px;display:inline-block">📄 Current Form</a><br><?php endif; ?><input type="file" name="form_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.png"></div>
      <label class="form-check" style="margin-bottom:16px"><input type="checkbox" name="is_active" value="1" <?= ($edit_row['is_active']??1)?'checked':'' ?>> Active</label>
      <div style="display:flex;gap:12px"><button type="submit" class="btn btn-primary">💾 Save</button><a href="/admin/?action=admissions_admin" class="btn btn-secondary">Cancel</a></div>
    </form>
  </div>
</div>
<?php endif; ?>
