<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_role('executive');

$id       = (int)($_GET['id'] ?? 0);
$template = $id ? db_row("SELECT * FROM report_templates WHERE id=?", [$id]) : null;
$is_edit  = (bool)$template;

if ($id && !$template) redirect(BASE_URL . '/modules/reports/index.php');

// Permission: only creator or super_admin can edit
$uid  = current_user_id();
$role = current_role();
if ($is_edit && $template['created_by'] != $uid && $role !== 'super_admin') {
    flash_error('You do not have permission to edit this template.');
    redirect(BASE_URL . '/modules/reports/index.php');
}

$page_title  = $is_edit ? 'Edit Report Template' : 'New Report Template';
$active_page = 'reports';

// ── POST: Save template ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name         = clean($_POST['name']        ?? '');
    $description  = clean($_POST['description'] ?? '');
    $visibility   = clean($_POST['visibility']  ?? 'private');
    $sources      = $_POST['sources']     ?? [];
    $columns      = $_POST['columns']     ?? [];
    $grouping     = clean($_POST['grouping']    ?? '');
    $sort_by      = clean($_POST['sort_by']     ?? '');
    $default_filters = clean($_POST['default_filters'] ?? '');
    $input_fields = clean($_POST['input_fields'] ?? '');
    $change_note  = clean($_POST['change_note'] ?? '');

    $errors = [];
    if (!$name)          $errors[] = 'Template name is required.';
    if (empty($sources)) $errors[] = 'Select at least one data source.';
    if (empty($columns)) $errors[] = 'Select at least one column.';

    if (empty($errors)) {
        $visibility = in_array($visibility, ['private','shared','public']) ? $visibility : 'private';

        $sources_json  = json_encode(array_values(array_filter($sources)));
        $columns_json  = json_encode(array_values(array_filter($columns)));
        $filters_json  = $default_filters ? $default_filters : '{}';
        $inputs_json   = $input_fields ? $input_fields : '[]';

        if ($is_edit) {
            $new_version = (int)$template['current_version'] + 1;
            // Save version snapshot
            db_exec(
                "INSERT INTO report_template_versions (template_id, version_number, snapshot, changed_by, change_note)
                 VALUES (?, ?, ?, ?, ?)",
                [$id, $template['current_version'], json_encode($template), $uid, $change_note ?: 'Updated']
            );
            db_exec(
                "UPDATE report_templates SET name=?, description=?, source_modules=?, columns=?,
                  filters=?, input_fields=?, grouping=?, sort_by=?, visibility=?,
                  current_version=?, updated_at=NOW()
                 WHERE id=?",
                [$name, $description ?: null, $sources_json, $columns_json,
                 $filters_json, $inputs_json, $grouping ?: null, $sort_by ?: null,
                 $visibility, $new_version, $id]
            );
            audit_log('edit_report_template', 'report_templates', $id, "v$new_version: $name");
            flash_success("Template updated (v{$new_version}).");
        } else {
            $tid = db_exec(
                "INSERT INTO report_templates (name, description, source_modules, columns, filters,
                  input_fields, grouping, sort_by, visibility, created_by, is_active, current_version)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)",
                [$name, $description ?: null, $sources_json, $columns_json,
                 $filters_json, $inputs_json, $grouping ?: null, $sort_by ?: null,
                 $visibility, $uid]
            );
            audit_log('create_report_template', 'report_templates', $tid, "Created: $name");
            flash_success("Template <strong>" . h($name) . "</strong> created.");
            redirect(BASE_URL . '/modules/reports/run.php?template_id=' . $tid);
        }
        redirect(BASE_URL . '/modules/reports/index.php');
    }
}

// Available columns per source
$available_columns = [
    'calls'    => ['id','direction','started_at','ended_at','duration_seconds','phone_dialed','outcome','agent_name','contact_name','campaign_name','notes'],
    'contacts' => ['id','name','phone','email','company','contact_type','status','created_at','last_call','tags'],
    'sms'      => ['id','phone_to','message','status','agent_name','contact_name','sent_at'],
    'tasks'    => ['id','title','type','priority','status','assigned_to','due_date','created_at','completed_at'],
    'attendance' => ['date','user_name','work_mode','check_in','check_out','total_hours','status'],
    'feedback' => ['id','title','status','priority','contact_name','assigned_to','created_at','resolved_at'],
    'sales_network' => ['group_name','level','member_name','member_phone','contact_type','joined_date'],
];

$saved_sources = $is_edit ? (json_decode($template['source_modules'] ?? '[]', true) ?: []) : [];
$saved_columns = $is_edit ? (json_decode($template['columns'] ?? '[]', true) ?: []) : [];

// Version history
$versions = $is_edit ? db_rows(
    "SELECT rtv.*, u.name AS changed_by_name FROM report_template_versions rtv
     LEFT JOIN users u ON u.id = rtv.changed_by
     WHERE rtv.template_id=? ORDER BY rtv.version_number DESC LIMIT 10",
    [$id]
) : [];

// Shared users
$shared_users = $is_edit ? db_rows(
    "SELECT rtp.*, u.name AS user_name FROM report_template_permissions rtp
     LEFT JOIN users u ON u.id = rtp.user_id
     WHERE rtp.template_id=?",
    [$id]
) : [];

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/reports/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-file-earmark-bar-graph text-primary ms-1"></i>
  <h5 class="ms-1"><?= $page_title ?></h5>
  <?php if ($is_edit): ?>
  <div class="ms-auto no-print">
    <a href="<?= BASE_URL ?>/modules/reports/run.php?template_id=<?= $id ?>"
       class="btn btn-sm btn-success">
      <i class="bi bi-play me-1"></i>Run Report
    </a>
  </div>
  <?php endif ?>
</div>

<div class="page-body">
  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul>
  </div>
  <?php endif ?>

  <form method="post">
    <?= csrf_field() ?>
    <div class="row g-3">

      <!-- Main config -->
      <div class="col-12 col-md-8">
        <div class="card mb-3">
          <div class="card-header fw-semibold">Template Details</div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-12 col-md-8">
                <label class="form-label">Template Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" required
                       value="<?= h($template['name'] ?? '') ?>"
                       placeholder="e.g. Monthly Sales Performance">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label">Visibility</label>
                <select class="form-select" name="visibility">
                  <option value="private"  <?= ($template['visibility']??'private')==='private' ?'selected':'' ?>>Private (only me)</option>
                  <option value="shared"   <?= ($template['visibility']??'')==='shared'         ?'selected':'' ?>>Shared (specific users)</option>
                  <option value="public"   <?= ($template['visibility']??'')==='public'         ?'selected':'' ?>>Public (everyone)</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Description</label>
                <input type="text" class="form-control" name="description"
                       value="<?= h($template['description'] ?? '') ?>"
                       placeholder="Brief description of what this report shows…">
              </div>
            </div>
          </div>
        </div>

        <!-- Data sources -->
        <div class="card mb-3">
          <div class="card-header fw-semibold">Data Sources <span class="text-danger">*</span></div>
          <div class="card-body">
            <div class="row g-2">
              <?php foreach (array_keys($available_columns) as $src): ?>
              <?php $checked = in_array($src, $saved_sources) ? 'checked' : ''; ?>
              <div class="col-6 col-md-4">
                <label class="d-flex align-items-center gap-2 p-2 border rounded source-cb-label <?= $checked ? 'border-primary' : '' ?>"
                       style="cursor:pointer">
                  <input type="checkbox" name="sources[]" value="<?= $src ?>" class="source-cb" <?= $checked ?>>
                  <span class="small fw-semibold"><?= ucfirst(str_replace('_',' ',$src)) ?></span>
                </label>
              </div>
              <?php endforeach ?>
            </div>
          </div>
        </div>

        <!-- Columns -->
        <div class="card mb-3">
          <div class="card-header fw-semibold">Columns to Include <span class="text-danger">*</span></div>
          <div class="card-body" id="columnsArea">
            <?php foreach ($available_columns as $src => $cols): ?>
            <div class="source-cols mb-3" id="cols-<?= $src ?>"
                 style="display:<?= in_array($src, $saved_sources) ? 'block' : 'none' ?>">
              <div class="text-muted small fw-semibold mb-1"><?= ucfirst(str_replace('_',' ',$src)) ?> columns:</div>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($cols as $col): ?>
                <?php $colKey = $src . '.' . $col; ?>
                <?php $checked = in_array($colKey, $saved_columns) ? 'checked' : ''; ?>
                <label class="badge bg-light text-dark border col-badge" style="cursor:pointer;font-size:.75rem">
                  <input type="checkbox" name="columns[]" value="<?= $colKey ?>" class="me-1" <?= $checked ?>>
                  <?= $col ?>
                </label>
                <?php endforeach ?>
              </div>
            </div>
            <?php endforeach ?>
            <div id="noSourceMsg" class="text-muted small" style="display:<?= empty($saved_sources) ? 'block' : 'none' ?>">
              Select a data source above to see available columns.
            </div>
          </div>
        </div>

        <!-- Grouping & Sort -->
        <div class="card mb-3">
          <div class="card-header fw-semibold">Grouping & Sort</div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-6">
                <label class="form-label">Group By</label>
                <select class="form-select" name="grouping">
                  <option value="">No grouping</option>
                  <option value="day"      <?= ($template['grouping']??'')==='day'      ?'selected':'' ?>>Day</option>
                  <option value="week"     <?= ($template['grouping']??'')==='week'     ?'selected':'' ?>>Week</option>
                  <option value="month"    <?= ($template['grouping']??'')==='month'    ?'selected':'' ?>>Month</option>
                  <option value="agent"    <?= ($template['grouping']??'')==='agent'    ?'selected':'' ?>>Agent</option>
                  <option value="campaign" <?= ($template['grouping']??'')==='campaign' ?'selected':'' ?>>Campaign</option>
                  <option value="outcome"  <?= ($template['grouping']??'')==='outcome'  ?'selected':'' ?>>Outcome</option>
                  <option value="group"    <?= ($template['grouping']??'')==='group'    ?'selected':'' ?>>Sales Group</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">Sort By</label>
                <input type="text" class="form-control" name="sort_by"
                       value="<?= h($template['sort_by'] ?? '') ?>"
                       placeholder="e.g. started_at DESC">
              </div>
            </div>
          </div>
        </div>

        <!-- Custom input fields -->
        <div class="card mb-3">
          <div class="card-header fw-semibold">
            Custom Input Fields
            <span class="text-muted small fw-normal ms-2">(for requisitions, visit forms, etc.)</span>
          </div>
          <div class="card-body">
            <div class="mb-2 text-muted small">Define additional fields that users fill in when running this report.</div>
            <textarea class="form-control font-monospace" name="input_fields" rows="4"
                      placeholder='[{"name":"area_visited","label":"Area Visited","type":"text","required":true},{"name":"units_sold","label":"Units Sold","type":"number"}]'
                      style="font-size:.8rem"><?= h($template['input_fields'] ?? '') ?></textarea>
            <div class="form-text">JSON array of field definitions. Types: text, number, date, textarea, select.</div>
          </div>
        </div>

        <?php if ($is_edit): ?>
        <div class="mb-3">
          <label class="form-label">Change Note</label>
          <input type="text" class="form-control" name="change_note"
                 placeholder="Brief description of what changed…">
        </div>
        <?php endif ?>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-2"></i><?= $is_edit ? 'Save Template' : 'Create Template' ?>
          </button>
          <a href="<?= BASE_URL ?>/modules/reports/index.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>

      <!-- Right: versions & sharing -->
      <div class="col-12 col-md-4">

        <?php if ($is_edit): ?>
        <!-- Version history -->
        <div class="card mb-3">
          <div class="card-header small fw-semibold">
            Version History
            <span class="badge bg-primary ms-1">v<?= $template['current_version'] ?></span>
          </div>
          <?php if (!empty($versions)): ?>
          <ul class="list-group list-group-flush" style="max-height:250px;overflow-y:auto">
            <?php foreach ($versions as $v): ?>
            <li class="list-group-item py-2">
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary">v<?= $v['version_number'] ?></span>
                <div class="flex-grow-1">
                  <div class="small"><?= h($v['change_note'] ?? 'Updated') ?></div>
                  <div class="text-muted" style="font-size:.68rem">
                    <?= h($v['changed_by_name'] ?? '—') ?> · <?= time_ago($v['created_at']) ?>
                  </div>
                </div>
              </div>
            </li>
            <?php endforeach ?>
          </ul>
          <?php else: ?>
          <div class="card-body text-muted small text-center py-2">No previous versions</div>
          <?php endif ?>
        </div>

        <!-- Sharing -->
        <div class="card">
          <div class="card-header small fw-semibold">Shared With</div>
          <ul class="list-group list-group-flush">
            <?php foreach ($shared_users as $su): ?>
            <li class="list-group-item d-flex py-1">
              <span class="small"><?= h($su['user_name']) ?></span>
              <span class="badge bg-<?= $su['permission']==='edit' ? 'warning' : 'info' ?> ms-auto">
                <?= ucfirst($su['permission']) ?>
              </span>
            </li>
            <?php endforeach ?>
            <?php if (empty($shared_users)): ?>
            <li class="list-group-item text-muted text-center py-2 small">No one yet</li>
            <?php endif ?>
          </ul>
          <div class="card-body py-2">
            <form method="post" action="<?= BASE_URL ?>/modules/reports/share.php">
              <?= csrf_field() ?>
              <input type="hidden" name="template_id" value="<?= $id ?>">
              <div class="input-group input-group-sm">
                <select class="form-select form-select-sm" name="user_id">
                  <option value="">Add user…</option>
                  <?php
                  $all_users = db_rows("SELECT id, name FROM users WHERE status='active' ORDER BY name");
                  foreach ($all_users as $u): ?>
                  <option value="<?= $u['id'] ?>"><?= h($u['name']) ?></option>
                  <?php endforeach ?>
                </select>
                <select class="form-select form-select-sm" name="permission" style="max-width:80px">
                  <option value="view">View</option>
                  <option value="edit">Edit</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary">Add</button>
              </div>
            </form>
          </div>
        </div>
        <?php endif ?>

      </div>
    </div>
  </form>
</div>

<script>
(function() {
  var sourceCbs = document.querySelectorAll('.source-cb');
  sourceCbs.forEach(function(cb) {
    cb.addEventListener('change', function() {
      var cols = document.getElementById('cols-' + this.value);
      if (cols) cols.style.display = this.checked ? 'block' : 'none';
      var noMsg = document.getElementById('noSourceMsg');
      var anyChecked = Array.from(sourceCbs).some(c => c.checked);
      if (noMsg) noMsg.style.display = anyChecked ? 'none' : 'block';
    });
  });

  // Highlight label on source checkbox change
  sourceCbs.forEach(function(cb) {
    cb.addEventListener('change', function() {
      this.closest('.source-cb-label').classList.toggle('border-primary', this.checked);
    });
  });
})();
</script>

<?php require ROOT . '/partials/footer.php'; ?>
