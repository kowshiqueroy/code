<?php
/**
 * Results Admin Module
 */
$admin_title = 'Exam Results';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title_en   = trim($_POST['title_en'] ?? '');
    $title_bn   = trim($_POST['title_bn'] ?? '');
    $exam_year  = trim($_POST['exam_year'] ?? '');
    $exam_type  = trim($_POST['exam_type'] ?? '');
    $ext_link   = trim($_POST['external_link'] ?? '');
    $is_pub     = (int)!empty($_POST['is_published']);
    $file_path  = $_POST['current_file'] ?? '';

    if (!empty($_FILES['file_path']['name'])) {
        $dir = APP_ROOT . '/assets/uploads/documents/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['file_path']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf','jpg','jpeg','png'])) {
            $fname = 'result_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['file_path']['tmp_name'], $dir.$fname)) {
                $file_path = 'documents/' . $fname;
            }
        }
    }

    $edit_id = (int)($_POST['edit_id'] ?? 0);
    if ($title_en) {
        if ($edit_id) {
            db()->prepare("UPDATE results SET title_en=?,title_bn=?,exam_year=?,exam_type=?,file_path=?,external_link=?,is_published=? WHERE id=?")
               ->execute([$title_en,$title_bn,$exam_year,$exam_type,$file_path,$ext_link,$is_pub,$edit_id]);
        } else {
            db()->prepare("INSERT INTO results (title_en,title_bn,exam_year,exam_type,file_path,external_link,is_published) VALUES (?,?,?,?,?,?,?)")
               ->execute([$title_en,$title_bn,$exam_year,$exam_type,$file_path,$ext_link,$is_pub]);
        }
        $msg = 'Result saved.';
    }
}
if (isset($_GET['delete'])) { db()->prepare("DELETE FROM results WHERE id=?")->execute([(int)$_GET['delete']]); header('Location: /admin/?action=results_admin'); exit; }

$mode = $_GET['mode'] ?? 'list';
$edit_row = null;
if ($mode === 'edit') { $s=db()->prepare("SELECT * FROM results WHERE id=?"); $s->execute([(int)($_GET['id']??0)]); $edit_row=$s->fetch(); $mode='edit'; }
$results_list = db()->query("SELECT * FROM results ORDER BY created_at DESC")->fetchAll();
?>
<?php if ($msg): ?><div class="alert alert-success">✅ <?= h($msg) ?></div><?php endif; ?>
<div style="display:grid;grid-template-columns:3fr 2fr;gap:24px">
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title">📊 Exam Results</div>
      <a href="/admin/?action=results_admin&mode=add" class="btn btn-primary btn-sm">➕ Add Result</a>
    </div>
    <table class="admin-table">
      <thead><tr><th>Title</th><th>Year</th><th>Type</th><th>File/Link</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($results_list as $r): ?>
        <tr>
          <td><strong><?= h($r['title_en']) ?></strong></td>
          <td><?= h($r['exam_year']) ?></td>
          <td style="font-size:.82rem"><?= h($r['exam_type']) ?></td>
          <td>
            <?php if ($r['file_path']): ?><a href="<?= upload_url($r['file_path']) ?>" target="_blank" class="btn btn-xs btn-success">📄 PDF</a><?php endif; ?>
            <?php if ($r['external_link']): ?><a href="<?= h($r['external_link']) ?>" target="_blank" class="btn btn-xs btn-secondary">🔗 Link</a><?php endif; ?>
          </td>
          <td><span class="status-badge <?= $r['is_published']?'status-published':'status-draft' ?>"><?= $r['is_published']?'Live':'Draft' ?></span></td>
          <td>
            <div class="actions">
              <a href="/admin/?action=results_admin&mode=edit&id=<?= $r['id'] ?>" class="btn btn-xs btn-secondary">✏️</a>
              <a href="/admin/?action=results_admin&delete=<?= $r['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete?')">🗑️</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="panel">
    <div class="panel-header"><div class="panel-title"><?= $edit_row?'✏️ Edit':'➕ Add' ?> Result</div></div>
    <div class="panel-body">
      <form method="POST" enctype="multipart/form-data">
        <?php if ($edit_row): ?><input type="hidden" name="edit_id" value="<?= $edit_row['id'] ?>"><input type="hidden" name="current_file" value="<?= h($edit_row['file_path']) ?>"><?php endif; ?>
        <div class="form-group"><label class="form-label">Title (English) *</label><input type="text" name="title_en" class="form-control" required value="<?= h($edit_row['title_en']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Title (Bangla)</label><input type="text" name="title_bn" class="form-control" value="<?= h($edit_row['title_bn']??'') ?>"></div>
        <div class="form-row col-2">
          <div class="form-group"><label class="form-label">Year</label><input type="text" name="exam_year" class="form-control" value="<?= h($edit_row['exam_year']??date('Y')) ?>" placeholder="2024"></div>
          <div class="form-group"><label class="form-label">Exam Type</label>
            <select name="exam_type" class="form-control form-select">
              <?php foreach(['SSC','HSC','JSC','PSC','Annual','Half-Yearly','Test','Other'] as $t): ?>
              <option value="<?= $t ?>" <?= ($edit_row['exam_type']??'')===$t?'selected':'' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label">Upload Result File (PDF/Image)</label><input type="file" name="file_path" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
        <div class="form-group"><label class="form-label">Or External Link (Education Board etc.)</label><input type="url" name="external_link" class="form-control" value="<?= h($edit_row['external_link']??'') ?>" placeholder="https://educationboard.gov.bd/..."></div>
        <label class="form-check" style="margin-bottom:16px"><input type="checkbox" name="is_published" value="1" <?= ($edit_row['is_published']??1)?'checked':'' ?>> Published</label>
        <button type="submit" class="btn btn-primary">💾 Save</button>
      </form>
    </div>
  </div>
</div>
