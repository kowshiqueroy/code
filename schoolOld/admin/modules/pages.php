<?php
/**
 * Admin Pages Module — Full CRUD with rich editor
 */
$admin_title = 'Pages';
$mode   = $_GET['mode'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$msg    = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug       = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_POST['slug'] ?? '')));
    $title_en   = trim($_POST['title_en'] ?? '');
    $title_bn   = trim($_POST['title_bn'] ?? '');
    $content_en = $_POST['content_en'] ?? '';
    $content_bn = $_POST['content_bn'] ?? '';
    $template   = preg_replace('/[^a-z0-9_]/', '', $_POST['template'] ?? 'default');
    $is_published = (int)!empty($_POST['is_published']);
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_desc  = trim($_POST['meta_description'] ?? '');
    $custom_css = $_POST['custom_css'] ?? '';
    $custom_js  = $_POST['custom_js'] ?? '';
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if (!$title_en) $errors[] = 'English title is required.';
    if (!$slug)     $errors[] = 'Slug is required (lowercase letters, numbers, hyphens).';

    if (empty($errors)) {
        try {
            $edit_id = (int)($_POST['edit_id'] ?? 0);
            if ($edit_id) {
                db()->prepare("UPDATE pages SET slug=?,title_en=?,title_bn=?,content_en=?,content_bn=?,template=?,is_published=?,meta_title=?,meta_description=?,custom_css=?,custom_js=?,sort_order=?,updated_at=NOW() WHERE id=?")
                   ->execute([$slug,$title_en,$title_bn,$content_en,$content_bn,$template,$is_published,$meta_title,$meta_desc,$custom_css,$custom_js,$sort_order,$edit_id]);
                $msg = 'Page updated successfully.';
            } else {
                db()->prepare("INSERT INTO pages (slug,title_en,title_bn,content_en,content_bn,template,is_published,meta_title,meta_description,custom_css,custom_js,sort_order,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$slug,$title_en,$title_bn,$content_en,$content_bn,$template,$is_published,$meta_title,$meta_desc,$custom_css,$custom_js,$sort_order,$_SESSION['admin_user_id']]);
                $msg = 'Page created successfully.';
            }
            $mode = 'list';
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['delete']) && (int)$_GET['delete']) {
    $core = ['index','about','contact','notices','gallery','administration','admissions','students','academic','results'];
    $del_row = db()->prepare("SELECT slug FROM pages WHERE id=?");
    $del_row->execute([(int)$_GET['delete']]);
    $del_row = $del_row->fetch();
    if ($del_row && in_array($del_row['slug'], $core)) {
        $msg = 'Cannot delete core system pages.';
    } else {
        db()->prepare("DELETE FROM pages WHERE id=?")->execute([(int)$_GET['delete']]);
        $msg = 'Page deleted.';
    }
}

$edit_row = null;
if ($mode === 'edit' && $id) {
    $s = db()->prepare("SELECT * FROM pages WHERE id=?");
    $s->execute([$id]);
    $edit_row = $s->fetch();
}

// Auto-generate slug from title
$pages_list = db()->query("SELECT * FROM pages ORDER BY sort_order, title_en")->fetchAll();
$templates  = ['default'=>'Default','home'=>'Home','gallery'=>'Gallery','notices'=>'Notices','contact'=>'Contact','teachers'=>'Teachers','results'=>'Results'];
?>

<?php if ($msg): ?><div class="alert alert-success">✅ <?= h($msg) ?></div><?php endif; ?>
<?php foreach ($errors as $e): ?><div class="alert alert-error">⚠️ <?= h($e) ?></div><?php endforeach; ?>

<?php if ($mode === 'list'): ?>
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">📄 All Pages</div>
    <a href="/admin/?action=pages&mode=add" class="btn btn-primary">➕ New Page</a>
  </div>
  <div class="table-wrap">
    <table class="admin-table">
      <thead><tr><th>Title</th><th>Slug / URL</th><th>Template</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($pages_list as $p): ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= h($p['title_en']) ?></div>
            <?php if ($p['title_bn']): ?><div style="font-size:.75rem;color:var(--muted)"><?= h($p['title_bn']) ?></div><?php endif; ?>
          </td>
          <td><code style="background:var(--bg);padding:2px 6px;border-radius:4px;font-size:.8rem">/?page=<?= h($p['slug']) ?></code></td>
          <td><span class="status-badge" style="background:#f3f4f6;color:var(--text)"><?= h(ucfirst($p['template'])) ?></span></td>
          <td>
            <span class="status-badge <?= $p['is_published']?'status-published':'status-draft' ?>">
              <?= $p['is_published']?'Published':'Draft' ?>
            </span>
          </td>
          <td style="font-size:.78rem;color:var(--muted)"><?= date('d M Y', strtotime($p['updated_at'])) ?></td>
          <td>
            <div class="actions">
              <a href="/admin/?action=pages&mode=edit&id=<?= (int)$p['id'] ?>" class="btn btn-xs btn-secondary">✏️ Edit</a>
              <a href="/?page=<?= h($p['slug']) ?>" target="_blank" class="btn btn-xs btn-success">👁️</a>
              <a href="/admin/?action=pages&delete=<?= (int)$p['id'] ?>"
                 class="btn btn-xs btn-danger"
                 onclick="return confirm('Delete page &quot;<?= h($p['title_en']) ?>&quot;?')">🗑️</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<!-- Edit/Add form -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title"><?= $mode==='edit'?'✏️ Edit Page':'➕ New Page' ?></div>
    <a href="/admin/?action=pages" class="btn btn-secondary btn-sm">← Back</a>
  </div>
  <div class="panel-body">
    <form method="POST" id="pageForm">
      <?php if ($edit_row): ?><input type="hidden" name="edit_id" value="<?= (int)$edit_row['id'] ?>"><?php endif; ?>

      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Title (English) *</label>
          <input type="text" name="title_en" id="titleInput" class="form-control" required
                 value="<?= h($edit_row['title_en'] ?? '') ?>"
                 oninput="autoSlug(this.value)">
        </div>
        <div class="form-group">
          <label class="form-label">Title (Bangla)</label>
          <input type="text" name="title_bn" class="form-control"
                 value="<?= h($edit_row['title_bn'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row col-3">
        <div class="form-group">
          <label class="form-label">URL Slug *</label>
          <input type="text" name="slug" id="slugInput" class="form-control" required
                 value="<?= h($edit_row['slug'] ?? '') ?>"
                 pattern="[a-z0-9-]+" placeholder="e.g. about-us">
          <div class="form-hint">Only lowercase letters, numbers, hyphens. Access: /?page=<span id="slugPreview"><?= h($edit_row['slug'] ?? '') ?></span></div>
        </div>
        <div class="form-group">
          <label class="form-label">Template</label>
          <select name="template" class="form-control form-select">
            <?php foreach($templates as $tval => $tlabel): ?>
            <option value="<?= $tval ?>" <?= ($edit_row['template']??'default')===$tval?'selected':'' ?>><?= $tlabel ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Sort Order</label>
          <input type="number" name="sort_order" class="form-control" value="<?= (int)($edit_row['sort_order']??0) ?>">
        </div>
      </div>

      <!-- Content Tabs -->
      <div style="margin-bottom:8px;display:flex;gap:4px;border-bottom:2px solid var(--border)">
        <button type="button" class="tb-btn" onclick="showTab('en')" id="tabEn" style="padding:8px 16px;border:none;background:var(--primary);color:#fff;border-radius:8px 8px 0 0;cursor:pointer;font-weight:600">English Content</button>
        <button type="button" class="tb-btn" onclick="showTab('bn')" id="tabBn" style="padding:8px 16px;border:none;background:var(--bg);border-radius:8px 8px 0 0;cursor:pointer;font-weight:600">Bangla Content</button>
      </div>

      <!-- Editor toolbar -->
      <div class="editor-toolbar" id="editorToolbar">
        <?php foreach([
          ['B','bold','<b>Bold</b>'],['I','italic','<i>Italic</i>'],['U','underline','<u>Underline</u>'],
          ['H2','formatBlock','H2'],['H3','formatBlock','H3'],
          ['UL','insertUnorderedList','List'],['OL','insertOrderedList','Numbered'],
          ['Link','createLink','Insert Link'],['IMG','insertImage','Insert Image URL'],
          ['Table','','Insert Table'],['HR','insertHorizontalRule','—'],
          ['Clear','removeFormat','Clear Format'],
        ] as $btn): ?>
        <button type="button" class="tb-btn"
          onclick="editorCmd('<?= $btn[1] ?>','<?= addslashes($btn[0]) ?>')"
          title="<?= $btn[2] ?>"><?= $btn[0] ?></button>
        <?php endforeach; ?>
        <button type="button" class="tb-btn" onclick="insertTable()" title="Insert Table">⊞ Table</button>
        <button type="button" class="tb-btn" onclick="toggleSource()" title="Toggle HTML Source" id="sourceBtn">
          &lt;/&gt; HTML
        </button>
      </div>

      <div id="tabEnContent">
        <div id="editor_en" contenteditable="true" class="form-control" id="contentEditor"
             style="min-height:320px;border-radius:0 0 8px 8px;border-top:none"
             oninput="syncContent('en')"><?= $edit_row['content_en'] ?? '' ?></div>
        <textarea name="content_en" id="content_en_raw" style="display:none"><?= h($edit_row['content_en'] ?? '') ?></textarea>
      </div>
      <div id="tabBnContent" style="display:none">
        <div contenteditable="true" class="form-control"
             style="min-height:320px;border-radius:0 0 8px 8px;border-top:none;font-family:'Hind Siliguri',sans-serif"
             oninput="syncContent('bn')"
             id="editor_bn"><?= $edit_row['content_bn'] ?? '' ?></div>
        <textarea name="content_bn" id="content_bn_raw" style="display:none"><?= h($edit_row['content_bn'] ?? '') ?></textarea>
      </div>

      <!-- SEO -->
      <div style="margin-top:28px;padding-top:20px;border-top:1px solid var(--border)">
        <h3 style="font-size:.95rem;margin-bottom:16px;color:var(--muted)">SEO Settings</h3>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Meta Title</label>
            <input type="text" name="meta_title" class="form-control"
                   value="<?= h($edit_row['meta_title'] ?? '') ?>" placeholder="Page title for search engines">
          </div>
          <div class="form-group">
            <label class="form-label">Meta Description</label>
            <input type="text" name="meta_description" class="form-control"
                   value="<?= h($edit_row['meta_description'] ?? '') ?>" placeholder="Brief description for search results">
          </div>
        </div>
      </div>

      <!-- Custom Code (admin/superadmin only) -->
      <?php if (in_array($_SESSION['admin_role']??'editor',['admin','superadmin'])): ?>
      <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border)">
        <h3 style="font-size:.95rem;margin-bottom:4px;color:var(--muted)">Custom Code Injection</h3>
        <div class="form-hint" style="margin-bottom:12px">For advanced users. Code is injected only on this page.</div>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Custom CSS</label>
            <textarea name="custom_css" class="form-control" rows="4" style="font-family:monospace;font-size:.82rem" placeholder="/* page-specific CSS */"><?= h($edit_row['custom_css'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Custom JavaScript</label>
            <textarea name="custom_js" class="form-control" rows="4" style="font-family:monospace;font-size:.82rem" placeholder="// page-specific JS"><?= h($edit_row['custom_js'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div style="display:flex;align-items:center;gap:20px;margin-top:20px;padding-top:20px;border-top:1px solid var(--border)">
        <label class="form-check">
          <input type="checkbox" name="is_published" value="1" <?= ($edit_row['is_published']??1)?'checked':'' ?>>
          Published
        </label>
        <button type="submit" class="btn btn-primary">💾 Save Page</button>
        <a href="/admin/?action=pages" class="btn btn-secondary">Cancel</a>
        <?php if ($edit_row): ?>
        <a href="/?page=<?= h($edit_row['slug']) ?>" target="_blank" class="btn btn-success">👁️ Preview</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<script>
function autoSlug(val) {
  const slug = val.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
  const si = document.getElementById('slugInput');
  const sp = document.getElementById('slugPreview');
  if (si && !si.dataset.manual) { si.value = slug; }
  if (sp) sp.textContent = slug;
}
document.getElementById('slugInput')?.addEventListener('input', function(){
  this.dataset.manual = '1';
  document.getElementById('slugPreview').textContent = this.value;
});

let activeTab = 'en';
function showTab(lang) {
  activeTab = lang;
  document.getElementById('tabEnContent').style.display = lang==='en'?'block':'none';
  document.getElementById('tabBnContent').style.display = lang==='bn'?'block':'none';
  document.getElementById('tabEn').style.background = lang==='en'?'var(--primary)':'var(--bg)';
  document.getElementById('tabEn').style.color = lang==='en'?'#fff':'var(--text)';
  document.getElementById('tabBn').style.background = lang==='bn'?'var(--primary)':'var(--bg)';
  document.getElementById('tabBn').style.color = lang==='bn'?'#fff':'var(--text)';
}

function syncContent(lang) {
  const editor = document.getElementById('editor_' + lang);
  const raw = document.getElementById('content_' + lang + '_raw');
  if (editor && raw) raw.value = editor.innerHTML;
}

function editorCmd(cmd, val) {
  const editor = document.getElementById('editor_' + activeTab);
  if (editor) {
    editor.focus();
    if (cmd === 'formatBlock') {
      document.execCommand(cmd, false, val);
    } else if (cmd === 'createLink') {
      const url = prompt('Enter URL:');
      if (url) document.execCommand(cmd, false, url);
    } else if (cmd === 'insertImage') {
      const url = prompt('Enter image URL:');
      if (url) document.execCommand(cmd, false, url);
    } else {
      document.execCommand(cmd, false, null);
    }
    syncContent(activeTab);
  }
}

function insertTable() {
  const rows = parseInt(prompt('Number of rows:', '3')) || 3;
  const cols = parseInt(prompt('Number of columns:', '3')) || 3;
  let html = '<table style="width:100%;border-collapse:collapse"><thead><tr>';
  for (let c=0; c<cols; c++) html += '<th style="border:1px solid #ccc;padding:8px;background:#006B3F;color:#fff">Header ' + (c+1) + '</th>';
  html += '</tr></thead><tbody>';
  for (let r=0; r<rows; r++) {
    html += '<tr>';
    for (let c=0; c<cols; c++) html += '<td style="border:1px solid #ccc;padding:8px">Cell</td>';
    html += '</tr>';
  }
  html += '</tbody></table><p></p>';
  document.execCommand('insertHTML', false, html);
  syncContent(activeTab);
}

let sourceMode = false;
function toggleSource() {
  const editor = document.getElementById('editor_' + activeTab);
  if (!editor) return;
  sourceMode = !sourceMode;
  if (sourceMode) {
    const pre = document.createElement('pre');
    pre.contentEditable = true;
    pre.id = 'source_' + activeTab;
    pre.style.cssText = 'min-height:320px;border:2px solid var(--primary);border-radius:0 0 8px 8px;padding:14px;font-family:monospace;font-size:.8rem;background:#1a1a2e;color:#a8ff78;overflow:auto';
    pre.textContent = editor.innerHTML;
    pre.oninput = () => { editor.innerHTML = pre.textContent; syncContent(activeTab); };
    editor.style.display = 'none';
    editor.parentNode.insertBefore(pre, editor.nextSibling);
    document.getElementById('sourceBtn').style.background = 'var(--primary)';
    document.getElementById('sourceBtn').style.color = '#fff';
  } else {
    const pre = document.getElementById('source_' + activeTab);
    if (pre) { editor.innerHTML = pre.textContent; pre.remove(); }
    editor.style.display = '';
    document.getElementById('sourceBtn').style.background = '';
    document.getElementById('sourceBtn').style.color = '';
  }
}

// Sync before submit
document.getElementById('pageForm').addEventListener('submit', () => {
  syncContent('en'); syncContent('bn');
  document.getElementById('editor_en').contentEditable = false;
  document.getElementById('editor_bn').contentEditable = false;
});
</script>
<?php endif; ?>
