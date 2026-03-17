<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_role('senior_executive');

$page_title  = 'Team Attendance';
$active_page = 'attendance';

$f_date  = clean($_GET['date']  ?? date('Y-m-d'));
$f_month = clean($_GET['month'] ?? date('Y-m'));

// ── POST: Override attendance ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'override') {
    require_csrf();
    $user_id   = (int)($_POST['user_id']    ?? 0);
    $date      = clean($_POST['date']       ?? '');
    $status    = clean($_POST['status']     ?? 'present');
    $check_in  = clean($_POST['check_in']   ?? '');
    $check_out = clean($_POST['check_out']  ?? '');
    $work_mode = clean($_POST['work_mode']  ?? 'office');
    $notes     = clean($_POST['notes']      ?? '');

    if ($user_id && $date) {
        $exists = db_val("SELECT id FROM attendance WHERE user_id=? AND date=?", [$user_id, $date]);
        $hours = 0;
        if ($check_in && $check_out) {
            $hours = round((strtotime($check_out) - strtotime($check_in)) / 3600, 2);
        }

        if ($exists) {
            db_exec(
                "UPDATE attendance SET status=?, check_in=?, check_out=?, work_mode=?,
                  total_hours=?, notes=?, approved_by=? WHERE user_id=? AND date=?",
                [$status, $check_in ?: null, $check_out ?: null, $work_mode,
                 $hours, $notes ?: null, current_user_id(), $user_id, $date]
            );
        } else {
            db_exec(
                "INSERT INTO attendance (user_id, date, check_in, check_out, work_mode, total_hours, status, notes, approved_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$user_id, $date, $check_in ?: null, $check_out ?: null, $work_mode,
                 $hours, $status, $notes ?: null, current_user_id()]
            );
        }
        audit_log('override_attendance', 'attendance', $user_id, "Date: $date Status: $status");
        flash_success('Attendance updated.');
    }
    redirect(BASE_URL . '/modules/attendance/manage.php?date=' . $f_date);
}

// Team daily status
$team_today = db_rows(
    "SELECT u.id, u.name, u.role,
            a.check_in, a.check_out, a.work_mode, a.total_hours, a.status AS att_status,
            a.id AS att_id
     FROM users u
     LEFT JOIN attendance a ON a.user_id=u.id AND a.date=?
     WHERE u.status='active' AND u.role != 'viewer'
     ORDER BY u.name ASC",
    [$f_date]
);

// Monthly summary per user
$f_year = substr($f_month, 0, 4);
$f_mon  = substr($f_month, 5, 2);
$monthly_summary = db_rows(
    "SELECT u.id, u.name,
            COUNT(a.id) AS total_days,
            SUM(a.status='present') AS present,
            SUM(a.status='absent') AS absent,
            ROUND(SUM(a.total_hours), 1) AS hours,
            SUM(a.work_mode='wfh') AS wfh
     FROM users u
     LEFT JOIN attendance a ON a.user_id=u.id AND YEAR(a.date)=? AND MONTH(a.date)=?
     WHERE u.status='active' AND u.role != 'viewer'
     GROUP BY u.id, u.name
     ORDER BY u.name",
    [$f_year, $f_mon]
);

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/attendance/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-people-fill text-primary ms-1"></i>
  <h5 class="ms-1">Team Attendance</h5>
  <div class="ms-auto d-flex gap-2 no-print">
    <button data-print class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
  </div>
</div>

<div class="page-body">

  <!-- Tabs: Daily / Monthly -->
  <ul class="nav nav-tabs mb-3 no-print">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#daily">Daily View</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#monthly">Monthly Summary</a>
    </li>
  </ul>

  <div class="tab-content">

    <!-- Daily tab -->
    <div class="tab-pane fade show active" id="daily">
      <div class="d-flex align-items-center gap-2 mb-3 no-print">
        <?php
        $prevDay = date('Y-m-d', strtotime($f_date . ' -1 day'));
        $nextDay = date('Y-m-d', strtotime($f_date . ' +1 day'));
        ?>
        <a href="?date=<?= $prevDay ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
        <input type="date" class="form-control form-control-sm" style="max-width:180px"
               value="<?= h($f_date) ?>" onchange="location='?date='+this.value">
        <a href="?date=<?= $nextDay ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
        <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-primary">Today</a>
        <span class="text-muted small ms-2"><?= date('l, d M Y', strtotime($f_date)) ?></span>
      </div>

      <div class="card">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Staff</th>
                <th>Mode</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Hours</th>
                <th>Status</th>
                <th class="no-print">Override</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($team_today as $t): ?>
              <?php $noRecord = !$t['att_id']; ?>
              <tr class="<?= $noRecord && strtotime($f_date) < strtotime(date('Y-m-d')) ? 'table-warning' : '' ?>">
                <td>
                  <div class="small fw-semibold"><?= h($t['name']) ?></div>
                  <?= role_badge($t['role']) ?>
                </td>
                <td class="small"><?= $t['work_mode'] ? ucfirst(str_replace('_',' ',$t['work_mode'])) : '—' ?></td>
                <td class="text-muted small"><?= $t['check_in'] ? date('g:i A', strtotime($t['check_in'])) : '—' ?></td>
                <td class="text-muted small"><?= $t['check_out'] ? date('g:i A', strtotime($t['check_out'])) : '—' ?></td>
                <td class="text-muted small"><?= $t['total_hours'] ? number_format($t['total_hours'],1).'h' : '—' ?></td>
                <td>
                  <?php if ($t['att_status']): ?>
                  <?= status_badge($t['att_status']) ?>
                  <?php else: ?>
                  <span class="badge bg-light text-muted border">—</span>
                  <?php endif ?>
                </td>
                <td class="no-print">
                  <button class="btn btn-sm btn-outline-warning py-0 override-btn"
                          data-user-id="<?= $t['id'] ?>"
                          data-user-name="<?= h($t['name']) ?>"
                          data-date="<?= $f_date ?>"
                          data-status="<?= h($t['att_status'] ?? 'present') ?>"
                          data-check-in="<?= h($t['check_in'] ?? '') ?>"
                          data-check-out="<?= h($t['check_out'] ?? '') ?>"
                          data-mode="<?= h($t['work_mode'] ?? 'office') ?>">
                    <i class="bi bi-pencil"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Monthly tab -->
    <div class="tab-pane fade" id="monthly">
      <div class="d-flex align-items-center gap-2 mb-3 no-print">
        <input type="month" class="form-control form-control-sm" style="max-width:200px"
               value="<?= h($f_month) ?>" onchange="location='?month='+this.value+'#monthly'">
      </div>
      <div class="card">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Staff</th>
                <th>Present</th>
                <th>Absent</th>
                <th>Total Hours</th>
                <th>WFH Days</th>
                <th>Logged Days</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($monthly_summary as $ms): ?>
              <tr>
                <td class="fw-semibold small"><?= h($ms['name']) ?></td>
                <td><span class="badge bg-success"><?= $ms['present'] ?></span></td>
                <td><span class="badge bg-danger"><?= $ms['absent'] ?></span></td>
                <td class="text-muted small"><?= number_format($ms['hours'], 1) ?>h</td>
                <td class="text-muted small"><?= $ms['wfh'] ?></td>
                <td class="text-muted small"><?= $ms['total_days'] ?></td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

</div>

<!-- Override Modal -->
<div class="modal fade" id="overrideModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-pencil me-2"></i>Override Attendance — <span id="overrideName"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="override">
        <input type="hidden" name="user_id"  id="overrideUserId">
        <input type="hidden" name="date"     id="overrideDate">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Status</label>
              <select class="form-select" name="status" id="overrideStatus">
                <option value="present">Present</option>
                <option value="absent">Absent</option>
                <option value="leave">Leave</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Work Mode</label>
              <select class="form-select" name="work_mode" id="overrideMode">
                <option value="office">Office</option>
                <option value="wfh">WFH</option>
                <option value="field">Field</option>
                <option value="half_day">Half Day</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Check In Time</label>
              <input type="time" class="form-control" name="check_in" id="overrideCheckIn">
            </div>
            <div class="col-6">
              <label class="form-label">Check Out Time</label>
              <input type="time" class="form-control" name="check_out" id="overrideCheckOut">
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <input type="text" class="form-control" name="notes" placeholder="Reason for override…">
            </div>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="submit" class="btn btn-warning">Save Override</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.override-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.getElementById('overrideName').textContent = this.dataset.userName;
    document.getElementById('overrideUserId').value = this.dataset.userId;
    document.getElementById('overrideDate').value = this.dataset.date;
    document.getElementById('overrideStatus').value = this.dataset.status || 'present';
    document.getElementById('overrideMode').value = this.dataset.mode || 'office';
    var ci = this.dataset.checkIn;
    var co = this.dataset.checkOut;
    document.getElementById('overrideCheckIn').value  = ci ? ci.substring(11,16) : '';
    document.getElementById('overrideCheckOut').value = co ? co.substring(11,16) : '';
    new bootstrap.Modal(document.getElementById('overrideModal')).show();
  });
});
</script>

<?php require ROOT . '/partials/footer.php'; ?>
