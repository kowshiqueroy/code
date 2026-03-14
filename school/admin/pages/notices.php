<?php
// admin/pages/notices.php
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$db     = getDB();

// ── Handle POST saves ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = [
        'title_en'    => sanitize($_POST['title_en'] ?? ''),
        'title_bn'    => sanitize($_POST['title_bn'] ?? ''),
        'content_en'  => $_POST['content_en'] ?? '',
        'content_bn'  => $_POST['content_bn'] ?? '',
        'type'        => $_POST['type'] ?? 'notice',
        'is_pinned'   => isset($_POST['is_pinned']) ? 1 : 0,
        'is_urgent'   => isset($_POST['is_urgent']) ? 1 : 0,
        'is_active'   => isset($_POST['is_active']) ? 1 : 0,
        'publish_date'=> $_POST['publish_date'] ?? date('Y-m-d'),
        'expire_date' => $_POST['expire_date'] ?: null,
        'file_url'    => sanitize($_POST['file_url'] ?? ''),
    ];

    // Handle file upload
    if (!empty($_FILES['notice_file']['name'])) {
        $up = handleUpload('notice_file', 'notices');
        if (isset($up['filename'])) {
            $d['file_url'] = UPLOAD_URL . 'documents/' . $up['filename'];
        }
    }

    if (empty($d['title_en'])) { flash('Title (English) is required.', 'error'); }
    else {
        if ($id) {
            $stmt = $db->prepare("UPDATE notices SET title_en=?,title_bn=?,content_en=?,content_bn=?,type=?,is_pinned=?,is_urgent=?,is_active=?,publish_date=?,expire_date=?,file_url=? WHERE id=?");
            $stmt->execute([...(array_values($d)), $id]);
            flash('Notice updated successfully.', 'success');
        } else {
            $stmt = $db->prepare("INSERT INTO notices (title_en,title_bn,content_en,content_bn,type,is_pinned,is_urgent,is_active,publish_date,expire_date,file_url,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([...(array_values($d)), $_SESSION['user_id']]);
            flash('Notice added successfully.', 'success');
        }
        redirect(ADMIN_PATH . '?section=notices');
    }
}

// ── Delete ─────────────────────────────────────────────────
if ($action === 'delete' && $id) {
    $db->prepare("DELETE FROM notices WHERE id=?")->execute([$id]);
    flash('Notice deleted.', 'success');
    redirect(ADMIN_PATH . '?section=notices');
}

// ── Toggle active ──────────────────────────────────────────
if ($action === 'toggle' && $id) {
    $db->prepare("UPDATE notices SET is_active = NOT is_active WHERE id=?")->execute([$id]);
    redirect(ADMIN_PATH . '?section=notices');
}

// ── Edit data ──────────────────────────────────────────────
$row = null;
if (($action === 'edit' || $action === 'add') && $id) {
    $stmt = $db->prepare("SELECT * FROM notices WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
}

// ── List ───────────────────────────────────────────────────
if ($action === 'list') {
    $typeFilter = $_GET['type'] ?? '';
    $where = '1=1'; $params = [];
    if ($typeFilter) { $where .= " AND type=?"; $params[] = $typeFilter; }
    $paged  = max(1, (int)($_GET['paged'] ?? 1));
    $limit  = 20; $offset = ($paged-1)*$limit;
    $total  = $db->prepare("SELECT COUNT(*) FROM notices WHERE $where"); $total->execute($params);
    $count  = (int)$total->fetchColumn();
    $pages  = ceil($count/$limit);
    $stmt   = $db->prepare("SELECT * FROM notices WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $notices = $stmt->fetchAll();
}

$types = ['notice','news','event','job','result','exam','admission','circular'];
?>

<?php if ($action === 'list'): ?>
<div class="acard">
  <div class="acard-header">
    <div class="acard-title">📌 Notices (<?= $count ?>)</div>
    <a href="?section=notices&action=add" class="btn btn-primary">+ Add Notice</a>
  </div>
  <!-- Type filter -->
  <div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
    <a href="?section=notices" class="btn btn-sm <?= !$typeFilter?'btn-primary':'btn-light' ?>">All</a>
    <?php foreach ($types as $t): ?>
    <a href="?section=notices&type=<?= h($t) ?>" class="btn btn-sm <?= $typeFilter===$t?'btn-primary':'btn-light' ?>"><?= ucfirst($t) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="atable-wrap">
    <table class="atable">
      <thead><tr><th>Title (EN)</th><th>বাংলা</th><th>Type</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($notices as $n): ?>
        <tr>
          <td style="max-width:200px"><a href="?section=notices&action=edit&id=<?= $n['id'] ?>"><?= h(mb_substr($n['title_en'],0,50)) ?></a>
            <?php if ($n['is_pinned']): ?> 📌<?php endif; ?>
            <?php if ($n['is_urgent']): ?> 🔴<?php endif; ?>
          </td>
          <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h(mb_substr($n['title_bn'],0,40)) ?></td>
          <td><span class="badge badge-info"><?= h($n['type']) ?></span></td>
          <td><?= date('d M Y', strtotime($n['publish_date'] ?? $n['created_at'])) ?></td>
          <td>
            <a href="?section=notices&action=toggle&id=<?= $n['id'] ?>">
              <?= $n['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-gray">Hidden</span>' ?>
            </a>
          </td>
          <td>
            <a href="?section=notices&action=edit&id=<?= $n['id'] ?>" class="btn btn-sm btn-light">✏️</a>
            <a href="?section=notices&action=delete&id=<?= $n['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Delete this notice?">🗑️</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$notices): ?><tr><td colspan="6" style="text-align:center;padding:30px;color:#999">No notices found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="acard-footer">
    <div class="apagination">
      <?php for ($i=1;$i<=$pages;$i++): ?>
      <a href="?section=notices<?= $typeFilter?"&type=$typeFilter":'' ?>&paged=<?= $i ?>" class="apage-link <?= $paged===$i?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php else: // Add / Edit form ?>
<div class="acard">
  <div class="acard-header">
    <div class="acard-title"><?= $row ? '✏️ Edit Notice' : '+ Add Notice' ?></div>
    <a href="?section=notices" class="btn btn-light btn-sm">← Back</a>
  </div>
  <div class="acard-body">
    <form method="POST" enctype="multipart/form-data" class="aform">
      <div class="atabs"><button class="atab-btn active" data-tab="en" type="button">🇬🇧 English</button><button class="atab-btn" data-tab="bn" type="button">🇧🇩 বাংলা</button><button class="atab-btn" data-tab="settings" type="button">⚙️ Settings</button></div>

      <div class="atab-content active" id="atab-en">
        <div class="form-group"><label>Title (English) <span class="req">*</span></label><input type="text" name="title_en" value="<?= h($row['title_en'] ?? '') ?>" required></div>
        <div class="form-group">
          <label>Content (English)</label>
          <div class="richtext-toolbar">
            <button data-cmd="bold" type="button"><b>B</b></button><button data-cmd="italic" type="button"><i>I</i></button>
            <button data-cmd="underline" type="button"><u>U</u></button><button data-cmd="insertUnorderedList" type="button">• List</button>
            <button data-cmd="createLink" data-val="https://" type="button">🔗</button>
          </div>
          <div class="richtext-area" id="content_en" contenteditable="true" data-richtext><?= $row['content_en'] ?? '' ?></div>
          <input type="hidden" name="content_en" id="content_en_hidden" value="<?= h($row['content_en'] ?? '') ?>">
        </div>
      </div>

      <div class="atab-content" id="atab-bn">
        <div class="form-group"><label>শিরোনাম (বাংলা)</label><input type="text" name="title_bn" value="<?= h($row['title_bn'] ?? '') ?>"></div>
        <div class="form-group">
          <label>বিষয়বস্তু (বাংলা)</label>
          <div class="richtext-toolbar">
            <button data-cmd="bold" type="button"><b>B</b></button><button data-cmd="italic" type="button"><i>I</i></button>
            <button data-cmd="insertUnorderedList" type="button">• তালিকা</button>
          </div>
          <div class="richtext-area" id="content_bn" contenteditable="true" data-richtext><?= $row['content_bn'] ?? '' ?></div>
          <input type="hidden" name="content_bn" id="content_bn_hidden" value="<?= h($row['content_bn'] ?? '') ?>">
        </div>
      </div>

      <div class="atab-content" id="atab-settings">
        <div class="form-row">
          <div class="form-group">
            <label>Type</label>
            <select name="type">
              <?php foreach ($types as $t): ?><option value="<?= $t ?>" <?= ($row['type'] ?? 'notice')===$t?'selected':'' ?>><?= ucfirst($t) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Publish Date</label>
            <input type="date" name="publish_date" value="<?= h($row['publish_date'] ?? date('Y-m-d')) ?>">
          </div>
          <div class="form-group">
            <label>Expire Date (optional)</label>
            <input type="date" name="expire_date" value="<?= h($row['expire_date'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>File URL (or paste link)</label>
          <input type="url" name="file_url" value="<?= h($row['file_url'] ?? '') ?>" placeholder="https://... or leave blank to upload">
        </div>
        <div class="form-group">
          <label>Upload File (PDF/Doc)</label>
          <input type="file" name="notice_file" accept=".pdf,.doc,.docx">
          <?php if (!empty($row['file_url'])): ?><p style="margin-top:6px;font-size:.8rem">Current: <a href="<?= h($row['file_url']) ?>" target="_blank">View File</a></p><?php endif; ?>
        </div>
        <div style="display:flex;gap:20px;flex-wrap:wrap">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_active" <?= ($row['is_active'] ?? 1) ? 'checked' : '' ?>> Active</label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_pinned" <?= ($row['is_pinned'] ?? 0) ? 'checked' : '' ?>> 📌 Pinned</label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_urgent" <?= ($row['is_urgent'] ?? 0) ? 'checked' : '' ?>> 🔴 Urgent</label>
        </div>
      </div>

      <div style="margin-top:20px;display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">💾 <?= $row ? 'Update Notice' : 'Save Notice' ?></button>
        <a href="?section=notices" class="btn btn-light">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
// sync richtext to hidden inputs on form submit
document.querySelector('form').addEventListener('submit', function(){
  const en = document.getElementById('content_en');
  const bn = document.getElementById('content_bn');
  if(en) document.getElementById('content_en_hidden').value = en.innerHTML;
  if(bn) document.getElementById('content_bn_hidden').value = bn.innerHTML;
});
</script>
<?php endif; ?>
