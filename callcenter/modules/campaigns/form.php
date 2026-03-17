<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_role('executive');

$id       = (int)($_GET['id'] ?? 0);
$view_only= isset($_GET['view']);
$campaign = $id ? db_row("SELECT * FROM campaigns WHERE id=?", [$id]) : null;
$is_edit  = (bool)$campaign;

if ($id && !$campaign) redirect(BASE_URL . '/modules/campaigns/index.php');

$page_title  = $is_edit ? ($view_only ? 'Campaign Detail' : 'Edit Campaign') : 'New Campaign';
$active_page = 'campaigns';

// ── POST: Save ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$view_only) {
    require_csrf();

    $name        = clean($_POST['name']        ?? '');
    $description = clean($_POST['description'] ?? '');
    $type        = clean($_POST['type']        ?? 'outbound');
    $status      = clean($_POST['status']      ?? 'draft');
    $start_date  = clean($_POST['start_date']  ?? '');
    $end_date    = clean($_POST['end_date']    ?? '');
    $script_id   = (int)($_POST['script_id']   ?? 0);

    $errors = [];
    if (!$name) $errors[] = 'Campaign name is required.';

    if (empty($errors)) {
        if ($is_edit) {
            db_exec(
                "UPDATE campaigns SET name=?, description=?, type=?, status=?,
                  start_date=?, end_date=?, script_id=?, updated_at=NOW()
                 WHERE id=?",
                [$name, $description ?: null, $type, $status,
                 $start_date ?: null, $end_date ?: null, $script_id ?: null, $id]
            );
            audit_log('edit_campaign', 'campaigns', $id, "Updated: $name");
            flash_success("Campaign <strong>" . h($name) . "</strong> updated.");
            redirect(BASE_URL . '/modules/campaigns/form.php?id=' . $id . '&view=1');
        } else {
            $cid = db_exec(
                "INSERT INTO campaigns (name, description, type, status, start_date, end_date, script_id, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$name, $description ?: null, $type, $status,
                 $start_date ?: null, $end_date ?: null, $script_id ?: null, current_user_id()]
            );
            audit_log('create_campaign', 'campaigns', $cid, "Created: $name");
            flash_success("Campaign <strong>" . h($name) . "</strong> created.");
            redirect(BASE_URL . '/modules/campaigns/form.php?id=' . $cid . '&view=1');
        }
    }
    $campaign = array_merge($campaign ?? [], $_POST);
}

// Load campaign contacts if viewing
$campaign_contacts = [];
$contact_stats = [];
if ($is_edit && $view_only) {
    $campaign_contacts = db_rows(
        "SELECT cc.*, c.name AS contact_name, c.phone AS contact_phone,
                u.name AS assigned_name,
                (SELECT MAX(ca.started_at) FROM calls ca WHERE ca.contact_id=c.id AND ca.campaign_id=?) AS last_called
         FROM campaign_contacts cc
         LEFT JOIN contacts c ON c.id = cc.contact_id
         LEFT JOIN users u ON u.id = cc.assigned_to
         WHERE cc.campaign_id=?
         ORDER BY cc.status ASC, c.name ASC
         LIMIT 100",
        [$id, $id]
    );
    $contact_stats = db_row(
        "SELECT
           COUNT(*) AS total,
           SUM(status='pending') AS pending,
           SUM(status='in_progress') AS in_progress,
           SUM(status='completed') AS completed,
           SUM(status='cancelled') AS cancelled
         FROM campaign_contacts WHERE campaign_id=?",
        [$id]
    );
}

$scripts = db_rows("SELECT id, name FROM scripts ORDER BY is_default DESC, name ASC");

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/campaigns/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-megaphone-fill text-primary ms-1"></i>
  <h5 class="ms-1"><?= $page_title ?></h5>
  <?php if ($is_edit && $view_only && can('edit', 'campaigns')): ?>
  <div class="ms-auto d-flex gap-2">
    <button data-print class="btn btn-sm btn-outline-secondary no-print">
      <i class="bi bi-printer"></i>
    </button>
    <a href="?id=<?= $id ?>" class="btn btn-sm btn-outline-warning">
      <i class="bi bi-pencil me-1"></i>Edit
    </a>
  </div>
  <?php endif ?>
</div>

<div class="page-body">
  <div class="row g-3">

    <div class="col-12 <?= ($is_edit && $view_only) ? 'col-md-5' : 'col-md-7 mx-auto' ?>">

      <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul>
      </div>
      <?php endif ?>

      <div class="card">
        <div class="card-header"><?= $is_edit ? ($view_only ? 'Campaign Info' : 'Edit Campaign') : 'New Campaign' ?></div>
        <div class="card-body">
          <?php if ($view_only): ?>
          <!-- View mode -->
          <dl class="row mb-0">
            <dt class="col-sm-4 text-muted fw-normal small">Name</dt>
            <dd class="col-sm-8 fw-semibold"><?= h($campaign['name']) ?></dd>

            <dt class="col-sm-4 text-muted fw-normal small">Type</dt>
            <dd class="col-sm-8">
              <?php $tc=['outbound'=>'primary','inbound'=>'success','sms'=>'warning','mixed'=>'info']; ?>
              <span class="badge bg-<?= $tc[$campaign['type']] ?? 'secondary' ?>"><?= ucfirst($campaign['type']) ?></span>
            </dd>

            <dt class="col-sm-4 text-muted fw-normal small">Status</dt>
            <dd class="col-sm-8">
              <?php $sc=['active'=>'success','paused'=>'warning','draft'=>'secondary','completed'=>'primary','archived'=>'light text-dark']; ?>
              <span class="badge bg-<?= $sc[$campaign['status']] ?? 'secondary' ?>"><?= ucfirst($campaign['status']) ?></span>
            </dd>

            <?php if ($campaign['start_date'] || $campaign['end_date']): ?>
            <dt class="col-sm-4 text-muted fw-normal small">Period</dt>
            <dd class="col-sm-8 small">
              <?= $campaign['start_date'] ? format_date($campaign['start_date']) : '—' ?>
              <?= $campaign['end_date'] ? ' – ' . format_date($campaign['end_date']) : '' ?>
            </dd>
            <?php endif ?>

            <?php if ($campaign['description']): ?>
            <dt class="col-sm-4 text-muted fw-normal small">Description</dt>
            <dd class="col-sm-8 small"><?= nl2br(h($campaign['description'])) ?></dd>
            <?php endif ?>

            <?php if ($campaign['script_id']): ?>
            <?php $sc2 = db_row("SELECT id, name FROM scripts WHERE id=?", [$campaign['script_id']]); ?>
            <?php if ($sc2): ?>
            <dt class="col-sm-4 text-muted fw-normal small">Script</dt>
            <dd class="col-sm-8">
              <a href="<?= BASE_URL ?>/modules/scripts/form.php?id=<?= $sc2['id'] ?>&view=1">
                <?= h($sc2['name']) ?>
              </a>
            </dd>
            <?php endif ?>
            <?php endif ?>

            <dt class="col-sm-4 text-muted fw-normal small">Created</dt>
            <dd class="col-sm-8 small"><?= format_datetime($campaign['created_at']) ?></dd>
          </dl>

          <?php else: ?>
          <!-- Edit/Create form -->
          <form method="post">
            <?= csrf_field() ?>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Campaign Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" required
                       value="<?= h($campaign['name'] ?? '') ?>" placeholder="e.g. Ramadan Outreach 2025">
              </div>
              <div class="col-6">
                <label class="form-label">Type</label>
                <select class="form-select" name="type">
                  <?php foreach (['outbound','inbound','sms','mixed'] as $t): ?>
                  <option value="<?= $t ?>" <?= ($campaign['type']??'outbound')===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                  <?php foreach (['draft','active','paused','completed','archived'] as $s): ?>
                  <option value="<?= $s ?>" <?= ($campaign['status']??'draft')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date"
                       value="<?= h($campaign['start_date'] ?? '') ?>">
              </div>
              <div class="col-6">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date"
                       value="<?= h($campaign['end_date'] ?? '') ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Default Script</label>
                <select class="form-select" name="script_id">
                  <option value="">No script</option>
                  <?php foreach ($scripts as $sc): ?>
                  <option value="<?= $sc['id'] ?>" <?= ($campaign['script_id']??0)==$sc['id']?'selected':'' ?>>
                    <?= h($sc['name']) ?>
                  </option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3"
                          placeholder="Campaign objective and notes…"><?= h($campaign['description'] ?? '') ?></textarea>
              </div>
            </div>

            <div class="d-flex gap-2 mt-4">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-2"></i><?= $is_edit ? 'Save Changes' : 'Create Campaign' ?>
              </button>
              <a href="<?= BASE_URL ?>/modules/campaigns/index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </form>
          <?php endif ?>
        </div>
      </div>

      <?php if ($is_edit && $view_only && $contact_stats): ?>
      <!-- Stats -->
      <div class="row g-2 mt-0">
        <?php
        $stats = [
          ['label'=>'Total Contacts','value'=>$contact_stats['total'],'color'=>'primary'],
          ['label'=>'Pending',       'value'=>$contact_stats['pending'],'color'=>'warning'],
          ['label'=>'Completed',     'value'=>$contact_stats['completed'],'color'=>'success'],
          ['label'=>'Cancelled',     'value'=>$contact_stats['cancelled'],'color'=>'secondary'],
        ];
        foreach ($stats as $st): ?>
        <div class="col-6 col-md-3">
          <div class="card text-center py-2">
            <div class="fs-5 fw-bold text-<?= $st['color'] ?>"><?= number_format($st['value']) ?></div>
            <div class="text-muted small"><?= $st['label'] ?></div>
          </div>
        </div>
        <?php endforeach ?>
      </div>
      <?php endif ?>

    </div>

    <?php if ($is_edit && $view_only): ?>
    <!-- Contact list -->
    <div class="col-12 col-md-7">
      <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="bi bi-people me-1"></i>Campaign Contacts
          <?php if (can('edit', 'campaigns')): ?>
          <button class="btn btn-sm btn-outline-primary ms-auto" data-bs-toggle="modal" data-bs-target="#addContactModal">
            <i class="bi bi-person-plus me-1"></i>Add Contact
          </button>
          <?php endif ?>
        </div>
        <div class="table-responsive" style="max-height:500px;overflow-y:auto">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light sticky-top">
              <tr>
                <th>Contact</th>
                <th>Assigned To</th>
                <th>Status</th>
                <th>Last Called</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($campaign_contacts as $cc): ?>
              <tr>
                <td>
                  <a href="<?= BASE_URL ?>/modules/contacts/view.php?id=<?= $cc['contact_id'] ?>">
                    <?= h($cc['contact_name']) ?>
                  </a>
                  <div class="text-muted small"><?= h($cc['contact_phone']) ?></div>
                </td>
                <td class="text-muted small"><?= h($cc['assigned_name'] ?? '—') ?></td>
                <td>
                  <?php
                  $cs = ['pending'=>'warning','in_progress'=>'primary','completed'=>'success','cancelled'=>'secondary'];
                  ?>
                  <span class="badge bg-<?= $cs[$cc['status']] ?? 'secondary' ?>"><?= ucfirst(str_replace('_',' ',$cc['status'])) ?></span>
                </td>
                <td class="text-muted small"><?= $cc['last_called'] ? time_ago($cc['last_called']) : '—' ?></td>
              </tr>
              <?php endforeach ?>
              <?php if (empty($campaign_contacts)): ?>
              <tr><td colspan="4" class="text-center text-muted py-3">No contacts added yet</td></tr>
              <?php endif ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif ?>

  </div>
</div>

<?php if ($is_edit && $view_only && can('edit', 'campaigns')): ?>
<!-- Add Contact Modal -->
<div class="modal fade" id="addContactModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Contact to Campaign</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="<?= BASE_URL ?>/modules/campaigns/add_contact.php">
        <?= csrf_field() ?>
        <input type="hidden" name="campaign_id" value="<?= $id ?>">
        <div class="modal-body">
          <div class="mb-3 position-relative">
            <label class="form-label">Contact <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="addCcName"
                   data-autocomplete="contacts" data-ac-id-field="contact_id"
                   placeholder="Search contact…" required>
            <input type="hidden" name="contact_id" id="addCcId">
          </div>
          <div class="mb-3">
            <label class="form-label">Assign To</label>
            <select class="form-select" name="assigned_to">
              <option value="">Unassigned</option>
              <?php
              $agents = db_rows("SELECT id, name FROM users WHERE status='active' AND role != 'viewer' ORDER BY name");
              foreach ($agents as $a): ?>
              <option value="<?= $a['id'] ?>"><?= h($a['name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="submit" class="btn btn-primary btn-sm">Add Contact</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif ?>

<?php require ROOT . '/partials/footer.php'; ?>
