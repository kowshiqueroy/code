<?php
/**
 * Admin Routines Module
 */
$admin_title = 'Class & Exam Routines';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title_en  = trim($_POST['title_en']??'');
    $title_bn  = trim($_POST['title_bn']??'');
    $class_n   = trim($_POST['class_name']??'');
    $r_type    = in_array($_POST['routine_type']??'class',['class','exam'])?$_POST['routine_type']:'class';
    $acad_year = trim($_POST['academic_year']??'');
    $is_pub    = (int)!empty($_POST['is_published']);
    $file_path = '';
    if (!empty($_FILES['file_path']['name'])) {
        $dir = APP_ROOT.'/assets/uploads/documents/';
        if (!is_dir($dir)) mkdir($dir,0755,true);
        $ext = strtolower(pathinfo($_FILES['file_path']['name'],PATHINFO_EXTENSION));
        if (in_array($ext,['pdf','jpg','jpeg','png'])) {
            $fname = 'routine_'.uniqid().'.'.$ext;
            if (move_uploaded_file($_FILES['file_path']['tmp_name'],$dir.$fname)) $file_path = 'documents/'.$fname;
        }
    }
    $edit_id = (int)($_POST['edit_id']??0);
    if ($title_en) {
        if ($edit_id) {
            db()->prepare("UPDATE routines SET title_en=?,title_bn=?,class_name=?,routine_type=?,academic_year=?,file_path=COALESCE(NULLIF(?,0),file_path),is_published=? WHERE id=?")
               ->execute([$title_en,$title_bn,$class_n,$r_type,$acad_year,$file_path?:null,$is_pub,$edit_id]);
        } else {
            db()->prepare("INSERT INTO routines (title_en,title_bn,class_name,routine_type,academic_year,file_path,is_published) VALUES (?,?,?,?,?,?,?)")
               ->execute([$title_en,$title_bn,$class_n,$r_type,$acad_year,$file_path,$is_pub]);
        }
        $msg = 'Routine saved.';
    }
}
if (isset($_GET['delete'])) { db()->prepare("DELETE FROM routines WHERE id=?")->execute([(int)$_GET['delete']]); header('Location: /admin/?action=routines'); exit; }
$routines = db()->query("SELECT * FROM routines ORDER BY routine_type, created_at DESC")->fetchAll();
?>
<?php if ($msg): ?><div class="alert alert-success">✅ <?= h($msg) ?></div><?php endif; ?>
<div style="display:grid;grid-template-columns:3fr 2fr;gap:24px">
  <div class="panel">
    <div class="panel-header"><div class="panel-title">📅 Routines</div></div>
    <table class="admin-table">
      <thead><tr><th>Title</th><th>Class</th><th>Type</th><th>Year</th><th>File</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($routines as $r): ?>
        <tr>
          <td><strong><?= h($r['title_en']) ?></strong></td>
          <td><?= h($r['class_name']) ?></td>
          <td><span class="status-badge <?= $r['routine_type']==='exam'?'status-inactive':'status-published' ?>"><?= ucfirst($r['routine_type']) ?></span></td>
          <td><?= h($r['academic_year']) ?></td>
          <td><?php if($r['file_path']): ?><a href="<?= upload_url($r['file_path']) ?>" target="_blank" class="btn btn-xs btn-success">📄</a><?php else: ?>—<?php endif; ?></td>
          <td><a href="/admin/?action=routines&delete=<?= $r['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete?')">🗑️</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">➕ Add Routine</div></div>
    <div class="panel-body">
      <form method="POST" enctype="multipart/form-data">
        <div class="form-group"><label class="form-label">Title (English) *</label><input type="text" name="title_en" class="form-control" required placeholder="e.g. Class 9 Routine 2024"></div>
        <div class="form-group"><label class="form-label">Title (Bangla)</label><input type="text" name="title_bn" class="form-control"></div>
        <div class="form-row col-2">
          <div class="form-group"><label class="form-label">Class</label><input type="text" name="class_name" class="form-control" placeholder="e.g. Class 9"></div>
          <div class="form-group"><label class="form-label">Type</label>
            <select name="routine_type" class="form-control form-select">
              <option value="class">Class Routine</option>
              <option value="exam">Exam Schedule</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label">Academic Year</label><input type="text" name="academic_year" class="form-control" value="<?= date('Y') ?>"></div>
        <div class="form-group"><label class="form-label">Upload File (PDF/Image)</label><input type="file" name="file_path" class="form-control" accept=".pdf,.jpg,.png"></div>
        <label class="form-check" style="margin-bottom:14px"><input type="checkbox" name="is_published" value="1" checked> Published</label>
        <button type="submit" class="btn btn-primary">💾 Save</button>
      </form>
    </div>
  </div>
</div>
