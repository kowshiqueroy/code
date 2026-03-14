<?php
/**
 * Admin Notices Module — Full CRUD
 */
$admin_title = 'Notices';
$mode   = $_GET['mode'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$msg    = '';
$errors = [];

// ─── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title_en   = trim($_POST['title_en'] ?? '');
    $title_bn   = trim($_POST['title_bn'] ?? '');
    $content_en = $_POST['content_en'] ?? '';
    $content_bn = $_POST['content_bn'] ?? '';
    $category   = preg_replace('/[^a-z_]/', '', $_POST['category'] ?? 'general');
    $is_important = (int)!empty($_POST['is_important']);
    $is_published = (int)!empty($_POST['is_published']);
    $publish_date = $_POST['publish_date'] ?? null;
    $expire_date  = $_POST['expire_date'] ?? null;

    if (!$title_en) $errors[] = 'English title is required.';

    // Handle attachment upload
    $attachment = $_POST['current_attachment'] ?? '';
    if (!empty($_FILES['attachment']['name'])) {
        $upload = handle_file_upload($_FILES['attachment'], 'documents');
        if ($upload['success']) {
            $attachment = $upload['path'];
        } else {
            $errors[] = $upload['error'];
        }
    }

    if (empty($errors)) {
        try {
            if ($_POST['edit_id'] ?? false) {
                $edit_id = (int)$_POST['edit_id'];
                db()->prepare("UPDATE notices SET title_en=?,title_bn=?,content_en=?,content_bn=?,category=?,is_important=?,is_published=?,publish_date=?,expire_date=?,attachment=? WHERE id=?")
                   ->execute([$title_en,$title_bn,$content_en,$content_bn,$category,$is_important,$is_published,$publish_date?:null,$expire_date?:null,$attachment,$edit_id]);
                $msg = 'Notice updated successfully.';
            } else {
                db()->prepare("INSERT INTO notices (title_en,title_bn,content_en,content_bn,category,is_important,is_published,publish_date,expire_date,attachment,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$title_en,$title_bn,$content_en,$content_bn,$category,$is_important,$is_published,$publish_date?:null,$expire_date?:null,$attachment,$_SESSION['admin_user_id']]);
                $msg = 'Notice added successfully.';
            }
            $mode = 'list';
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// ─── Handle DELETE ────────────────────────────────────────────────────────────
if (isset($_GET['delete']) && (int)$_GET['delete']) {
    try {
        db()->prepare("DELETE FROM notices WHERE id=?")->execute([(int)$_GET['delete']]);
        $msg = 'Notice deleted.';
    } catch (Exception $e) {}
}

// ─── Toggle published ─────────────────────────────────────────────────────────
if (isset($_GET['toggle']) && (int)$_GET['toggle']) {
    try {
        db()->prepare("UPDATE notices SET is_published = 1-is_published WHERE id=?")->execute([(int)$_GET['toggle']]);
    } catch(Exception $e) {}
    header('Location: /admin/?action=notices');
    exit;
}

// ─── Load for edit ────────────────────────────────────────────────────────────
$edit_row = null;
if ($mode === 'edit' && $id) {
    try {
        $s = db()->prepare("SELECT * FROM notices WHERE id=?");
        $s->execute([$id]);
        $edit_row = $s->fetch();
    } catch(Exception $e) {}
}

// ─── Fetch list ───────────────────────────────────────────────────────────────
$search = trim($_GET['s'] ?? '');
$where  = $search ? "WHERE title_en LIKE ?" : "";
$params = $search ? ["%$search%"] : [];
try {
    $per = 15;
    $pg  = max(1,(int)($_GET['p']??1));
    $total = db()->prepare("SELECT COUNT(*) FROM notices $where");
    $total->execute($params);
    $total = $total->fetchColumn();
    $pag = paginate($total, $per, $pg);
    $s2 = db()->prepare("SELECT * FROM notices $where ORDER BY is_important DESC, created_at DESC LIMIT {$pag['per_page']} OFFSET {$pag['offset']}");
    $s2->execute($params);
    $notices_list = $s2->fetchAll();
} catch(Exception $e) { $notices_list = []; $pag=['pages'=>1,'current'=>1]; }

// ─── File upload helper ───────────────────────────────────────────────────────
function handle_file_upload(array $file, string $subdir = 'documents'): array {
    $upload_dir = APP_ROOT . '/assets/uploads/' . $subdir . '/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','gif'];
    if (!in_array($ext, $allowed)) return ['success'=>false,'error'=>"File type .$ext not allowed."];
    if ($file['size'] > 10*1024*1024) return ['success'=>false,'error'=>'File size exceeds 10MB.'];
    $filename = uniqid() . '_' . preg_replace('/[^a-z0-9._-]/i','_',basename($file['name']));
    if (!move_uploaded_file($file['tmp_name'], $upload_dir.$filename)) {
        return ['success'=>false,'error'=>'Upload failed.'];
    }
    return ['success'=>true,'path'=>$subdir.'/'.$filename];
}
?>

<?php if ($msg): ?>
<div class="alert alert-success">✅ <?= h($msg) ?></div>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
<div class="alert alert-error">⚠️ <?= h($e) ?></div>
<?php endforeach; ?>

<?php if ($mode === 'list'): ?>
<!-- ─── List View ──────────────────────────────────────────────────────────── -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">📋 All Notices</div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <form method="GET" style="display:flex;gap:8px">
        <input type="hidden" name="action" value="notices">
        <input type="text" name="s" value="<?= h($search) ?>" placeholder="Search notices…" class="form-control" style="width:200px;padding:7px 12px">
        <button type="submit" class="btn btn-secondary btn-sm">🔍</button>
      </form>
      <a href="/admin/?action=notices&mode=add" class="btn btn-primary">➕ Add Notice</a>
    </div>
  </div>
  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Category</th>
          <th>Status</th>
          <th>Important</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($notices_list as $n): ?>
        <tr>
          <td style="color:var(--muted)"><?= (int)$n['id'] ?></td>
          <td>
            <div style="font-weight:600"><?= h(mb_substr($n['title_en'],0,55)) ?><?= mb_strlen($n['title_en'])>55?'…':'' ?></div>
            <?php if ($n['title_bn']): ?><div style="font-size:.75rem;color:var(--muted)"><?= h(mb_substr($n['title_bn'],0,40)) ?></div><?php endif; ?>
            <?php if ($n['attachment']): ?><span style="font-size:.72rem;color:var(--primary)">📎 Has attachment</span><?php endif; ?>
          </td>
          <td><span class="status-badge" style="background:#f3f4f6;color:var(--text)"><?= h(ucfirst($n['category'])) ?></span></td>
          <td>
            <a href="/admin/?action=notices&toggle=<?= (int)$n['id'] ?>" title="Click to toggle">
              <span class="status-badge <?= $n['is_published']?'status-published':'status-draft' ?>"><?= $n['is_published']?'Published':'Draft' ?></span>
            </a>
          </td>
          <td><?= $n['is_important'] ? '<span class="status-badge status-inactive">⚠️ Yes</span>' : '—' ?></td>
          <td style="font-size:.8rem;color:var(--muted)"><?= date('d M Y', strtotime($n['created_at'])) ?></td>
          <td>
            <div class="actions">
              <a href="/admin/?action=notices&mode=edit&id=<?= (int)$n['id'] ?>" class="btn btn-xs btn-secondary">✏️ Edit</a>
              <a href="/admin/?action=notices&delete=<?= (int)$n['id'] ?>"
                 class="btn btn-xs btn-danger"
                 onclick="return confirm('Delete this notice?')">🗑️</a>
              <a href="/?page=notices&id=<?= (int)$n['id'] ?>" target="_blank" class="btn btn-xs btn-success">👁️</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($notices_list)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted)">No notices found. <a href="/admin/?action=notices&mode=add">Add one?</a></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pag['pages'] > 1): ?>
  <div class="panel-body" style="padding-top:0">
    <div class="pagination">
      <?php for($i=1;$i<=$pag['pages'];$i++): ?>
      <a href="/admin/?action=notices&p=<?= $i ?><?= $search?"&s=".urlencode($search):'' ?>"
         class="page-btn <?= $i===$pag['current']?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- ─── Add/Edit Form ─────────────────────────────────────────────────────── -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title"><?= $mode==='edit'?'✏️ Edit Notice':'➕ Add New Notice' ?></div>
    <a href="/admin/?action=notices" class="btn btn-secondary btn-sm">← Back to List</a>
  </div>
  <div class="panel-body">
    <form method="POST" enctype="multipart/form-data">
      <?php if ($edit_row): ?>
      <input type="hidden" name="edit_id" value="<?= (int)$edit_row['id'] ?>">
      <input type="hidden" name="current_attachment" value="<?= h($edit_row['attachment']) ?>">
      <?php endif; ?>

      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Title (English) *</label>
          <input type="text" name="title_en" class="form-control" required
                 value="<?= h($edit_row['title_en'] ?? '') ?>" placeholder="Notice title in English">
        </div>
        <div class="form-group">
          <label class="form-label">Title (Bangla)</label>
          <input type="text" name="title_bn" class="form-control"
                 value="<?= h($edit_row['title_bn'] ?? '') ?>" placeholder="বিজ্ঞপ্তির শিরোনাম">
        </div>
      </div>

      <div class="form-row col-3">
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" class="form-control form-select">
            <?php foreach(['general','academic','exam','admission','event','result'] as $cat): ?>
            <option value="<?= $cat ?>" <?= ($edit_row['category']??'general')===$cat?'selected':'' ?>><?= ucfirst($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Publish Date</label>
          <input type="date" name="publish_date" class="form-control"
                 value="<?= h($edit_row['publish_date'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Expiry Date</label>
          <input type="date" name="expire_date" class="form-control"
                 value="<?= h($edit_row['expire_date'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Content (English)</label>
        <textarea name="content_en" class="form-control" rows="6"><?= h($edit_row['content_en'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Content (Bangla)</label>
        <textarea name="content_bn" class="form-control" rows="6"><?= h($edit_row['content_bn'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Attachment (PDF, DOC, Image)</label>
        <?php if (!empty($edit_row['attachment'])): ?>
        <div style="margin-bottom:8px;padding:8px 12px;background:var(--bg);border-radius:8px;font-size:.85rem">
          📎 Current: <a href="<?= upload_url($edit_row['attachment']) ?>" target="_blank"><?= h(basename($edit_row['attachment'])) ?></a>
        </div>
        <?php endif; ?>
        <input type="file" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
        <div class="form-hint">Max 10MB. PDF, DOC, DOCX, or image.</div>
      </div>

      <div style="display:flex;gap:24px;margin-bottom:24px">
        <label class="form-check">
          <input type="checkbox" name="is_published" value="1" <?= ($edit_row['is_published']??1)?'checked':'' ?>>
          Published (visible on website)
        </label>
        <label class="form-check">
          <input type="checkbox" name="is_important" value="1" <?= ($edit_row['is_important']??0)?'checked':'' ?>>
          Mark as Important
        </label>
      </div>

      <div style="display:flex;gap:12px">
        <button type="submit" class="btn btn-primary">
          <?= $mode==='edit'?'💾 Update Notice':'➕ Add Notice' ?>
        </button>
        <a href="/admin/?action=notices" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
