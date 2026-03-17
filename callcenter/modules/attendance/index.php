<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Attendance';
$active_page = 'attendance';

$uid  = current_user_id();
$today = date('Y-m-d');

// Today's record
$record = db_row("SELECT * FROM attendance WHERE user_id=? AND date=?", [$uid, $today]);

// ── POST: Check in ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkin') {
    require_csrf();
    if (!$record) {
        $mode = clean($_POST['work_mode'] ?? 'office');
        $mode = in_array($mode, ['office','wfh','field','half_day']) ? $mode : 'office';
        $notes = clean($_POST['notes'] ?? '');
        db_exec(
            "INSERT INTO attendance (user_id, date, check_in, work_mode, status, notes)
             VALUES (?, ?, NOW(), ?, 'present', ?)",
            [$uid, $today, $mode, $notes ?: null]
        );
        audit_log('check_in', 'attendance', $uid);
        flash_success("Checked in. Good work today!");
    }
    redirect(BASE_URL . '/modules/attendance/index.php');
}

// ── POST: Check out ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    require_csrf();
    if ($record && !$record['check_out']) {
        db_exec(
            "UPDATE attendance SET check_out=NOW(),
              total_hours = ROUND(TIMESTAMPDIFF(MINUTE, check_in, NOW()) / 60, 2)
             WHERE id=?",
            [$record['id']]
        );
        audit_log('check_out', 'attendance', $uid);
        flash_success("Checked out. See you tomorrow!");
    }
    redirect(BASE_URL . '/modules/attendance/index.php');
}

// Reload after POST
$record = db_row("SELECT * FROM attendance WHERE user_id=? AND date=?", [$uid, $today]);

// Recent history
$history = db_rows(
    "SELECT * FROM attendance WHERE user_id=? AND date < ? ORDER BY date DESC LIMIT 14",
    [$uid, $today]
);

// Stats this month
$month_start = date('Y-m-01');
$month_stats = db_row(
    "SELECT
       COUNT(*) AS total_days,
       SUM(status='present') AS present_days,
       SUM(status='absent') AS absent_days,
       ROUND(SUM(total_hours), 1) AS total_hours,
       SUM(work_mode='wfh') AS wfh_days,
       SUM(work_mode='field') AS field_days
     FROM attendance
     WHERE user_id=? AND date BETWEEN ? AND CURDATE()",
    [$uid, $month_start]
);

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-clock-fill text-primary"></i>
  <h5>Attendance</h5>
  <div class="ms-auto d-flex gap-2 no-print">
    <a href="<?= BASE_URL ?>/modules/attendance/history.php" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-calendar3 me-1"></i>Full History
    </a>
    <?php if (in_array(current_role(), ['super_admin','senior_executive'])): ?>
    <a href="<?= BASE_URL ?>/modules/attendance/manage.php" class="btn btn-sm btn-outline-warning">
      <i class="bi bi-people me-1"></i>Team
    </a>
    <?php endif ?>
    <a href="<?= BASE_URL ?>/modules/attendance/leave.php" class="btn btn-sm btn-outline-info">
      <i class="bi bi-calendar-x me-1"></i>Leave
    </a>
  </div>
</div>

<div class="page-body">

  <!-- Check in/out card -->
  <div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
      <div class="card">
        <div class="card-body text-center py-4">
          <div class="fs-3 fw-bold mb-1"><?= date('D, d M Y') ?></div>
          <div class="text-muted mb-3"><?= date('g:i A') ?></div>

          <?php if (!$record): ?>
          <!-- Not checked in -->
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="checkin">
            <div class="mb-3">
              <select class="form-select form-select-sm text-center" name="work_mode" style="max-width:200px;margin:0 auto">
                <option value="office">🏢 Office</option>
                <option value="wfh">🏠 Work from Home</option>
                <option value="field">🌍 Field Work</option>
                <option value="half_day">🌓 Half Day</option>
              </select>
            </div>
            <button type="submit" class="btn btn-success btn-lg px-4">
              <i class="bi bi-box-arrow-in-right me-2"></i>Check In
            </button>
          </form>

          <?php elseif (!$record['check_out']): ?>
          <!-- Checked in, not out -->
          <div class="alert alert-success py-2 mx-auto" style="max-width:280px">
            <i class="bi bi-check-circle me-1"></i>
            Checked in at <strong><?= date('g:i A', strtotime($record['check_in'])) ?></strong>
            <div class="text-muted small">Mode: <?= ucfirst(str_replace('_',' ',$record['work_mode'])) ?></div>
          </div>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="checkout">
            <button type="submit" class="btn btn-outline-danger px-4">
              <i class="bi bi-box-arrow-right me-2"></i>Check Out
            </button>
          </form>

          <?php else: ?>
          <!-- Checked out -->
          <div class="alert alert-secondary py-2 mx-auto" style="max-width:280px">
            <i class="bi bi-moon me-1"></i>
            Done for today!<br>
            <small>
              In: <?= date('g:i A', strtotime($record['check_in'])) ?> →
              Out: <?= date('g:i A', strtotime($record['check_out'])) ?>
              (<?= number_format($record['total_hours'], 1) ?>h)
            </small>
          </div>
          <?php endif ?>
        </div>
      </div>
    </div>

    <!-- Month stats -->
    <div class="col-12 col-md-6">
      <div class="card h-100">
        <div class="card-header small fw-semibold">This Month</div>
        <div class="card-body">
          <div class="row g-2 text-center">
            <div class="col-4">
              <div class="fs-4 fw-bold text-success"><?= $month_stats['present_days'] ?? 0 ?></div>
              <div class="text-muted small">Present</div>
            </div>
            <div class="col-4">
              <div class="fs-4 fw-bold text-primary"><?= number_format($month_stats['total_hours'] ?? 0, 1) ?></div>
              <div class="text-muted small">Hours</div>
            </div>
            <div class="col-4">
              <div class="fs-4 fw-bold text-warning"><?= $month_stats['wfh_days'] ?? 0 ?></div>
              <div class="text-muted small">WFH Days</div>
            </div>
            <div class="col-4">
              <div class="fs-4 fw-bold text-info"><?= $month_stats['field_days'] ?? 0 ?></div>
              <div class="text-muted small">Field Days</div>
            </div>
            <div class="col-4">
              <div class="fs-4 fw-bold text-danger"><?= $month_stats['absent_days'] ?? 0 ?></div>
              <div class="text-muted small">Absent</div>
            </div>
            <div class="col-4">
              <div class="fs-4 fw-bold text-secondary"><?= $month_stats['total_days'] ?? 0 ?></div>
              <div class="text-muted small">Total Days</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent history -->
  <div class="card">
    <div class="card-header d-flex align-items-center">
      <i class="bi bi-calendar3 me-2"></i>Recent History
      <a href="<?= BASE_URL ?>/modules/attendance/history.php" class="ms-auto btn btn-sm btn-outline-secondary no-print">
        View All
      </a>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Mode</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th>Hours</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h): ?>
          <tr>
            <td class="small"><?= format_date($h['date']) ?></td>
            <td>
              <?php
              $modeIcons = ['office'=>'🏢','wfh'=>'🏠','field'=>'🌍','half_day'=>'🌓'];
              echo ($modeIcons[$h['work_mode']] ?? '') . ' ';
              echo ucfirst(str_replace('_',' ',$h['work_mode']));
              ?>
            </td>
            <td class="text-muted small"><?= $h['check_in'] ? date('g:i A', strtotime($h['check_in'])) : '—' ?></td>
            <td class="text-muted small"><?= $h['check_out'] ? date('g:i A', strtotime($h['check_out'])) : '—' ?></td>
            <td class="text-muted small"><?= $h['total_hours'] ? number_format($h['total_hours'], 1) . 'h' : '—' ?></td>
            <td><?= status_badge($h['status']) ?></td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($history)): ?>
          <tr><td colspan="6" class="text-center text-muted py-3">No history yet</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require ROOT . '/partials/footer.php'; ?>
