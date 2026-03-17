<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Leave Management';
$active_page = 'attendance';

$uid   = current_user_id();
$role  = current_role();
$isAll = in_array($role, ['super_admin','senior_executive']);

// ── POST: Apply leave ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    require_csrf();
    $leave_type_id = (int)($_POST['leave_type_id'] ?? 0);
    $start_date    = clean($_POST['start_date'] ?? '');
    $end_date      = clean($_POST['end_date']   ?? '');
    $reason        = clean($_POST['reason']     ?? '');

    $errors = [];
    if (!$leave_type_id) $errors[] = 'Leave type is required.';
    if (!$start_date)    $errors[] = 'Start date is required.';
    if (!$end_date)      $errors[] = 'End date is required.';
    if (strtotime($end_date) < strtotime($start_date)) $errors[] = 'End date must be after start date.';

    if (empty($errors)) {
        // Calculate business days
        $days = 0;
        $current = strtotime($start_date);
        $end = strtotime($end_date);
        while ($current <= $end) {
            $dow = date('w', $current);
            if ($dow != 5 && $dow != 6) $days++; // Exclude Fri & Sat (BD weekends)
            $current = strtotime('+1 day', $current);
        }
        db_exec(
            "INSERT INTO leaves (user_id, leave_type_id, start_date, end_date, days, reason, status)
             VALUES (?, ?, ?, ?, ?, ?, 'pending')",
            [$uid, $leave_type_id, $start_date, $end_date, $days, $reason ?: null]
        );
        audit_log('apply_leave', 'leaves', $uid, "From $start_date to $end_date ($days days)");
        flash_success("Leave application submitted ($days day" . ($days > 1 ? 's' : '') . ").");
    }
    redirect(BASE_URL . '/modules/attendance/leave.php');
}

// ── POST: Refer leave (senior exec) ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'refer') {
    require_role('senior_executive');
    require_csrf();
    $leave_id = (int)($_POST['leave_id'] ?? 0);
    db_exec(
        "UPDATE leaves SET status='referred', referred_by=?, referred_at=NOW() WHERE id=? AND status='pending'",
        [$uid, $leave_id]
    );
    audit_log('refer_leave', 'leaves', $leave_id);
    flash_success('Leave referred to admin.');
    redirect(BASE_URL . '/modules/attendance/leave.php');
}

// ── POST: Approve/Reject (admin) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['approve','reject'])) {
    require_role('super_admin');
    require_csrf();
    $leave_id = (int)($_POST['leave_id'] ?? 0);
    $action   = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
    db_exec(
        "UPDATE leaves SET status=?, approved_by=?, approved_at=NOW() WHERE id=?",
        [$action, $uid, $leave_id]
    );
    audit_log($action . '_leave', 'leaves', $leave_id);
    flash_success('Leave ' . $action . '.');
    redirect(BASE_URL . '/modules/attendance/leave.php');
}

$leave_types = db_rows("SELECT * FROM leave_types WHERE is_active=1 ORDER BY name");

// My leaves
$my_leaves = db_rows(
    "SELECT l.*, lt.name AS type_name, lt.color AS type_color,
            r.name AS referred_by_name, a.name AS approved_by_name
     FROM leaves l
     LEFT JOIN leave_types lt ON lt.id = l.leave_type_id
     LEFT JOIN users r ON r.id = l.referred_by
     LEFT JOIN users a ON a.id = l.approved_by
     WHERE l.user_id=?
     ORDER BY l.created_at DESC LIMIT 20",
    [$uid]
);

// Pending for action (senior sees pending→referred; admin sees referred)
$pending_action = [];
if ($role === 'senior_executive' || $role === 'super_admin') {
    $statusFilter = $role === 'super_admin' ? "l.status='referred'" : "l.status='pending'";
    $pending_action = db_rows(
        "SELECT l.*, lt.name AS type_name, u.name AS staff_name
         FROM leaves l
         LEFT JOIN leave_types lt ON lt.id = l.leave_type_id
         LEFT JOIN users u ON u.id = l.user_id
         WHERE $statusFilter
         ORDER BY l.created_at ASC"
    );
}

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/attendance/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-calendar-x text-info ms-1"></i>
  <h5 class="ms-1">Leave Management</h5>
  <div class="ms-auto no-print">
    <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
      <i class="bi bi-plus-lg me-1"></i>Apply Leave
    </button>
  </div>
</div>

<div class="page-body">

  <!-- Pending actions (for senior/admin) -->
  <?php if (!empty($pending_action)): ?>
  <div class="card mb-3 border-warning">
    <div class="card-header bg-warning text-dark">
      <i class="bi bi-exclamation-triangle me-2"></i>
      <?= $role === 'super_admin' ? 'Leave Requests Awaiting Approval' : 'Pending Leave Requests (Need Referral)' ?>
      <span class="badge bg-dark ms-1"><?= count($pending_action) ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Staff</th>
            <th>Type</th>
            <th>Dates</th>
            <th>Days</th>
            <th>Reason</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending_action as $pa): ?>
          <tr>
            <td class="fw-semibold small"><?= h($pa['staff_name']) ?></td>
            <td class="small"><?= h($pa['type_name']) ?></td>
            <td class="text-muted small"><?= format_date($pa['start_date']) ?> – <?= format_date($pa['end_date']) ?></td>
            <td class="text-muted small"><?= $pa['days'] ?></td>
            <td class="text-muted small text-truncate" style="max-width:150px"><?= h($pa['reason'] ?? '—') ?></td>
            <td>
              <?php if ($role === 'senior_executive'): ?>
              <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="refer">
                <input type="hidden" name="leave_id" value="<?= $pa['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-warning">Refer →</button>
              </form>
              <?php else: ?>
              <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="leave_id" value="<?= $pa['id'] ?>">
                <button type="submit" class="btn btn-sm btn-success">Approve</button>
              </form>
              <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="leave_id" value="<?= $pa['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
              </form>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif ?>

  <!-- My leaves -->
  <div class="card">
    <div class="card-header">My Leave History</div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Type</th>
            <th>Dates</th>
            <th>Days</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Applied</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($my_leaves as $lv): ?>
          <?php
          $sBadge = ['pending'=>'warning','referred'=>'info','approved'=>'success','rejected'=>'danger'];
          $sc = $sBadge[$lv['status']] ?? 'secondary';
          ?>
          <tr>
            <td class="small fw-semibold"><?= h($lv['type_name']) ?></td>
            <td class="text-muted small"><?= format_date($lv['start_date']) ?> – <?= format_date($lv['end_date']) ?></td>
            <td class="text-muted small"><?= $lv['days'] ?></td>
            <td class="text-muted small text-truncate" style="max-width:150px"><?= h($lv['reason'] ?? '—') ?></td>
            <td>
              <span class="badge bg-<?= $sc ?>"><?= ucfirst($lv['status']) ?></span>
              <?php if ($lv['status'] === 'referred' && $lv['referred_by_name']): ?>
              <div style="font-size:.65rem" class="text-muted">by <?= h($lv['referred_by_name']) ?></div>
              <?php endif ?>
            </td>
            <td class="text-muted small"><?= time_ago($lv['created_at']) ?></td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($my_leaves)): ?>
          <tr><td colspan="6" class="text-center text-muted py-3">No leave applications yet</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Apply Leave Modal -->
<div class="modal fade" id="applyLeaveModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-calendar-x me-2"></i>Apply for Leave</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <?php if (!empty($errors ?? [])): ?>
      <div class="alert alert-danger m-3 mb-0">
        <ul class="mb-0 ps-3"><?php foreach ($errors ?? [] as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul>
      </div>
      <?php endif ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="apply">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Leave Type <span class="text-danger">*</span></label>
            <select class="form-select" name="leave_type_id" required>
              <option value="">Select…</option>
              <?php foreach ($leave_types as $lt): ?>
              <option value="<?= $lt['id'] ?>"><?= h($lt['name']) ?> (<?= $lt['days_per_year'] ?> days/year)</option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label">Start Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="start_date" required
                     min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6">
              <label class="form-label">End Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="end_date" required
                     min="<?= date('Y-m-d') ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason</label>
            <textarea class="form-control" name="reason" rows="2" placeholder="Optional reason…"></textarea>
          </div>
          <div class="alert alert-info py-2 small">
            <i class="bi bi-info-circle me-1"></i>
            Leave flow: Apply → Senior Executive refers → Admin approves/rejects
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="submit" class="btn btn-info text-white">Submit Application</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require ROOT . '/partials/footer.php'; ?>
