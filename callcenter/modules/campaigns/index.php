<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Campaigns';
$active_page = 'campaigns';

// ── POST: Delete ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_role('senior_executive');
    require_csrf();
    $cid = (int)($_POST['campaign_id'] ?? 0);
    db_exec("DELETE FROM campaigns WHERE id=?", [$cid]);
    audit_log('delete_campaign', 'campaigns', $cid);
    flash_success('Campaign deleted.');
    redirect(BASE_URL . '/modules/campaigns/index.php');
}

// ── Filters ───────────────────────────────────────────────
$f_q      = clean($_GET['q']      ?? '');
$f_status = clean($_GET['status'] ?? '');
$f_type   = clean($_GET['type']   ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

$where  = [];
$params = [];
if ($f_q)      { $where[] = "(name LIKE ? OR description LIKE ?)"; $params = array_merge($params, ["%$f_q%", "%$f_q%"]); }
if ($f_status) { $where[] = "status = ?"; $params[] = $f_status; }
if ($f_type)   { $where[] = "type = ?";   $params[] = $f_type; }

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total = (int) db_val("SELECT COUNT(*) FROM campaigns $whereStr", $params);
$p     = paginate($total, $page, $per_page);

$campaigns = db_rows(
    "SELECT c.*, u.name AS creator_name,
            (SELECT COUNT(*) FROM campaign_contacts cc WHERE cc.campaign_id=c.id) AS contact_count,
            (SELECT COUNT(*) FROM campaign_contacts cc2 WHERE cc2.campaign_id=c.id AND cc2.status='completed') AS completed_count,
            (SELECT COUNT(*) FROM calls ca WHERE ca.campaign_id=c.id) AS call_count
     FROM campaigns c
     LEFT JOIN users u ON u.id = c.created_by
     $whereStr
     ORDER BY c.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$p['per_page'], $p['offset']])
);

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-megaphone-fill text-primary"></i>
  <h5>Campaigns</h5>
  <div class="ms-auto d-flex gap-2">
    <button data-print class="btn btn-sm btn-outline-secondary no-print">
      <i class="bi bi-printer"></i>
    </button>
    <?php if (can('create', 'campaigns')): ?>
    <a href="<?= BASE_URL ?>/modules/campaigns/form.php" class="btn btn-sm btn-primary">
      <i class="bi bi-plus-lg me-1"></i>New Campaign
    </a>
    <?php endif ?>
  </div>
</div>

<div class="page-body">

  <!-- Filter -->
  <form method="get" class="card mb-3 no-print">
    <div class="card-body py-2 px-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
          <input type="text" class="form-control form-control-sm" name="q"
                 value="<?= h($f_q) ?>" placeholder="Search campaigns…" autofocus>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" name="type">
            <option value="">All Types</option>
            <option value="outbound" <?= $f_type==='outbound'?'selected':'' ?>>Outbound</option>
            <option value="inbound"  <?= $f_type==='inbound' ?'selected':'' ?>>Inbound</option>
            <option value="sms"      <?= $f_type==='sms'     ?'selected':'' ?>>SMS</option>
            <option value="mixed"    <?= $f_type==='mixed'   ?'selected':'' ?>>Mixed</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" name="status">
            <option value="">All Status</option>
            <option value="active"   <?= $f_status==='active'   ?'selected':'' ?>>Active</option>
            <option value="paused"   <?= $f_status==='paused'   ?'selected':'' ?>>Paused</option>
            <option value="draft"    <?= $f_status==='draft'    ?'selected':'' ?>>Draft</option>
            <option value="completed"<?= $f_status==='completed'?'selected':'' ?>>Completed</option>
            <option value="archived" <?= $f_status==='archived' ?'selected':'' ?>>Archived</option>
          </select>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
        <div class="col-auto ms-auto text-muted small"><?= number_format($total) ?> campaigns</div>
      </div>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Campaign</th>
            <th>Type</th>
            <th>Status</th>
            <th>Period</th>
            <th>Contacts</th>
            <th>Calls Made</th>
            <th>Created By</th>
            <th class="no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($campaigns as $c): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= h($c['name']) ?></div>
              <?php if ($c['description']): ?>
              <div class="text-muted small text-truncate" style="max-width:200px"><?= h($c['description']) ?></div>
              <?php endif ?>
            </td>
            <td>
              <?php
              $typeColors = ['outbound'=>'primary','inbound'=>'success','sms'=>'warning','mixed'=>'info'];
              $tc = $typeColors[$c['type']] ?? 'secondary';
              ?>
              <span class="badge bg-<?= $tc ?>"><?= ucfirst($c['type']) ?></span>
            </td>
            <td>
              <?php
              $sBadge = ['active'=>'success','paused'=>'warning','draft'=>'secondary','completed'=>'primary','archived'=>'light text-dark'];
              $sc = $sBadge[$c['status']] ?? 'secondary';
              ?>
              <span class="badge bg-<?= $sc ?>"><?= ucfirst($c['status']) ?></span>
            </td>
            <td class="text-muted small">
              <?php if ($c['start_date']): ?>
              <?= format_date($c['start_date']) ?>
              <?php if ($c['end_date']): ?> – <?= format_date($c['end_date']) ?><?php endif ?>
              <?php else: ?>—<?php endif ?>
            </td>
            <td>
              <?php if ($c['contact_count'] > 0): ?>
              <span class="fw-semibold"><?= number_format($c['contact_count']) ?></span>
              <?php if ($c['completed_count'] > 0): ?>
              <span class="text-muted small"> (<?= $c['completed_count'] ?> done)</span>
              <?php endif ?>
              <?php else: ?>
              <span class="text-muted">0</span>
              <?php endif ?>
            </td>
            <td><?= number_format($c['call_count']) ?></td>
            <td class="text-muted small"><?= h($c['creator_name'] ?? '—') ?></td>
            <td class="no-print table-action-btns">
              <a href="<?= BASE_URL ?>/modules/campaigns/form.php?id=<?= $c['id'] ?>&view=1"
                 class="btn btn-sm btn-outline-secondary" title="View">
                <i class="bi bi-eye"></i>
              </a>
              <?php if (can('edit', 'campaigns')): ?>
              <a href="<?= BASE_URL ?>/modules/campaigns/form.php?id=<?= $c['id'] ?>"
                 class="btn btn-sm btn-outline-warning" title="Edit">
                <i class="bi bi-pencil"></i>
              </a>
              <?php endif ?>
              <?php if (can('delete', 'campaigns')): ?>
              <form method="post" class="d-inline"
                    data-confirm="Delete campaign '<?= h($c['name']) ?>'? This cannot be undone.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="campaign_id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($campaigns)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No campaigns found</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
    <?php if ($p['pages'] > 1): ?>
    <div class="card-footer no-print">
      <?php echo pagination_html($p, '?status=' . urlencode($f_status) . '&type=' . urlencode($f_type) . '&q=' . urlencode($f_q)); ?>
    </div>
    <?php endif ?>
  </div>

</div>

<?php require ROOT . '/partials/footer.php'; ?>
