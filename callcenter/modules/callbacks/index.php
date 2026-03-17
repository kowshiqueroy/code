<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Callbacks';
$active_page = 'callbacks';

$uid   = current_user_id();
$isAll = in_array(current_role(), ['super_admin','senior_executive']);

// ── POST: Save new callback ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    require_csrf();
    $contact_id  = (int)($_POST['contact_id'] ?? 0);
    $assigned_to = (int)($_POST['assigned_to'] ?? $uid);
    $scheduled   = clean($_POST['scheduled_at'] ?? '');
    $notes       = clean($_POST['notes'] ?? '');
    $call_id     = (int)($_POST['call_id'] ?? 0);

    if ($contact_id && $scheduled) {
        $cb_id = db_exec(
            "INSERT INTO callbacks (call_id, contact_id, assigned_to, scheduled_at, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$call_id ?: null, $contact_id, $assigned_to, $scheduled, $notes ?: null, $uid]
        );
        audit_log('create_callback', 'callbacks', $cb_id);
        flash_success('Callback scheduled.');
    }
    redirect(BASE_URL . '/modules/callbacks/index.php');
}

// ── POST: Update status ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    require_csrf();
    $cb_id  = (int)($_POST['cb_id'] ?? 0);
    $status = in_array($_POST['status']??'', ['completed','missed','cancelled']) ? $_POST['status'] : 'completed';
    db_exec("UPDATE callbacks SET status=? WHERE id=?", [$status, $cb_id]);
    audit_log('update_callback', 'callbacks', $cb_id, "Status: $status");
    redirect(BASE_URL . '/modules/callbacks/index.php');
}

// ── Filters ───────────────────────────────────────────────────
$f_status = clean($_GET['status'] ?? 'pending');
$f_agent  = (int)($_GET['agent'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

$where  = ["1=1"];
$params = [];
if ($f_status) { $where[] = "cb.status = ?"; $params[] = $f_status; }
if (!$isAll)   { $where[] = "cb.assigned_to = ?"; $params[] = $uid; }
elseif ($f_agent) { $where[] = "cb.assigned_to = ?"; $params[] = $f_agent; }

// Overdue first, then by scheduled_at
$whereStr = 'WHERE ' . implode(' AND ', $where);
$total = (int) db_val("SELECT COUNT(*) FROM callbacks cb $whereStr", $params);
$p     = paginate($total, $page, $per_page);

$callbacks = db_rows(
    "SELECT cb.id, cb.scheduled_at, cb.notes, cb.status,
            c.id AS contact_id, c.name AS contact_name, c.phone AS contact_phone,
            u.name AS assigned_name,
            ca.id AS call_id
     FROM callbacks cb
     LEFT JOIN contacts c ON c.id = cb.contact_id
     LEFT JOIN users u ON u.id = cb.assigned_to
     LEFT JOIN calls ca ON ca.id = cb.call_id
     $whereStr
     ORDER BY CASE WHEN cb.scheduled_at < NOW() THEN 0 ELSE 1 END, cb.scheduled_at ASC
     LIMIT ? OFFSET ?",
    array_merge($params, [$p['per_page'], $p['offset']])
);

$agents = $isAll ? db_rows("SELECT id, name FROM users WHERE status='active' AND role != 'viewer' ORDER BY name") : [];

// Pre-fill for new callback from URL params
$new_contact_id = (int)($_GET['contact_id'] ?? 0);
$new_call_id    = (int)($_GET['call_id'] ?? 0);
$show_form      = isset($_GET['new']);
$new_contact    = $new_contact_id ? db_row("SELECT id, name, phone FROM contacts WHERE id=?", [$new_contact_id]) : null;

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-clock-history text-warning"></i>
  <h5>Callbacks</h5>
  <div class="ms-auto">
    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#newCbModal">
      <i class="bi bi-alarm-plus me-1"></i>Schedule
    </button>
  </div>
</div>

<div class="page-body">

  <!-- Filter tabs -->
  <div class="d-flex gap-2 mb-3 flex-wrap no-print">
    <?php foreach (['pending'=>'warning','completed'=>'success','missed'=>'danger','cancelled'=>'secondary'] as $s => $cls): ?>
    <a href="?status=<?= $s ?>" class="btn btn-sm <?= $f_status===$s ? "btn-$cls" : "btn-outline-$cls" ?>">
      <?= ucfirst($s) ?>
    </a>
    <?php endforeach ?>
    <?php if ($isAll && !empty($agents)): ?>
    <form method="get" class="d-flex gap-1 ms-auto">
      <input type="hidden" name="status" value="<?= h($f_status) ?>">
      <select class="form-select form-select-sm" name="agent" onchange="this.form.submit()">
        <option value="">All Agents</option>
        <?php foreach ($agents as $a): ?>
        <option value="<?= $a['id'] ?>" <?= $f_agent==$a['id']?'selected':'' ?>><?= h($a['name']) ?></option>
        <?php endforeach ?>
      </select>
    </form>
    <?php endif ?>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Contact</th>
            <th>Scheduled</th>
            <th>Notes</th>
            <?php if ($isAll): ?><th>Assigned To</th><?php endif ?>
            <th>Status</th>
            <th class="no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($callbacks as $cb): ?>
          <?php $overdue = $cb['status']==='pending' && strtotime($cb['scheduled_at']) < time(); ?>
          <tr class="<?= $overdue ? 'table-danger' : '' ?>">
            <td>
              <?php if ($cb['contact_id']): ?>
              <a href="<?= BASE_URL ?>/modules/contacts/view.php?id=<?= $cb['contact_id'] ?>">
                <?= h($cb['contact_name']) ?>
              </a>
              <div class="text-muted small"><?= h($cb['contact_phone']) ?></div>
              <?php else: ?>—<?php endif ?>
            </td>
            <td>
              <?= $overdue ? '<span class="text-danger fw-bold small">OVERDUE</span><br>' : '' ?>
              <?= format_datetime($cb['scheduled_at']) ?>
            </td>
            <td class="text-muted small text-truncate" style="max-width:150px">
              <?= h($cb['notes'] ?? '—') ?>
            </td>
            <?php if ($isAll): ?>
            <td class="text-muted small"><?= h($cb['assigned_name']) ?></td>
            <?php endif ?>
            <td><?= status_badge($cb['status']) ?></td>
            <td class="no-print table-action-btns">
              <?php if ($cb['contact_id']): ?>
              <a href="<?= BASE_URL ?>/modules/workspace/index.php?contact_id=<?= $cb['contact_id'] ?>"
                 class="btn btn-sm btn-outline-primary" title="Call now">
                <i class="bi bi-telephone-plus"></i>
              </a>
              <?php endif ?>
              <?php if ($cb['status'] === 'pending'): ?>
              <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="cb_id" value="<?= $cb['id'] ?>">
                <input type="hidden" name="status" value="completed">
                <button type="submit" class="btn btn-sm btn-outline-success" title="Mark completed">
                  <i class="bi bi-check2"></i>
                </button>
              </form>
              <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="cb_id" value="<?= $cb['id'] ?>">
                <input type="hidden" name="status" value="missed">
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Mark missed">
                  <i class="bi bi-x"></i>
                </button>
              </form>
              <?php endif ?>
              <?php if ($cb['call_id']): ?>
              <a href="<?= BASE_URL ?>/modules/calls/view.php?id=<?= $cb['call_id'] ?>"
                 class="btn btn-sm btn-outline-secondary" title="View original call">
                <i class="bi bi-eye"></i>
              </a>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($callbacks)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No callbacks found</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
    <?php if ($p['pages'] > 1): ?>
    <div class="card-footer no-print">
      <?php echo pagination_html($p, '?status=' . urlencode($f_status)); ?>
    </div>
    <?php endif ?>
  </div>

</div>

<!-- New Callback Modal -->
<div class="modal fade" id="newCbModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-alarm-plus me-2"></i>Schedule Callback</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="call_id" value="<?= $new_call_id ?>">
        <div class="modal-body">
          <div class="mb-3 position-relative">
            <label class="form-label">Contact <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="cbContactName"
                   data-autocomplete="contacts" data-ac-id-field="contact_id"
                   value="<?= $new_contact ? h($new_contact['name']) : '' ?>"
                   placeholder="Search contact…" required>
            <input type="hidden" name="contact_id" id="contact_id"
                   value="<?= $new_contact_id ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Scheduled Date & Time <span class="text-danger">*</span></label>
            <input type="datetime-local" class="form-control" name="scheduled_at"
                   min="<?= date('Y-m-d\TH:i') ?>" required>
          </div>
          <?php if ($isAll): ?>
          <div class="mb-3">
            <label class="form-label">Assign To</label>
            <select class="form-select" name="assigned_to">
              <option value="<?= $uid ?>">Me</option>
              <?php foreach ($agents as $a): ?>
              <option value="<?= $a['id'] ?>"><?= h($a['name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <?php endif ?>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="2" placeholder="Reason for callback…"></textarea>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="submit" class="btn btn-warning">Schedule Callback</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($show_form): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  new bootstrap.Modal(document.getElementById('newCbModal')).show();
});
</script>
<?php endif ?>

<?php require ROOT . '/partials/footer.php'; ?>
