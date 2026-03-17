<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Dashboard';
$active_page = 'dashboard';

$uid   = current_user_id();
$role  = current_role();
$isAll = in_array($role, ['super_admin', 'senior_executive']);
$today = date('Y-m-d');

// ── KPI Data ──────────────────────────────────────────────────
$calls_today = (int) db_val(
    "SELECT COUNT(*) FROM calls WHERE DATE(started_at)=?" . ($isAll ? '' : " AND agent_id=$uid"),
    [$today]
);
$calls_week = (int) db_val(
    "SELECT COUNT(*) FROM calls WHERE started_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)" . ($isAll ? '' : " AND agent_id=$uid")
);
$calls_month = (int) db_val(
    "SELECT COUNT(*) FROM calls WHERE MONTH(started_at)=MONTH(NOW()) AND YEAR(started_at)=YEAR(NOW())" . ($isAll ? '' : " AND agent_id=$uid")
);
$pending_callbacks = (int) db_val(
    "SELECT COUNT(*) FROM callbacks WHERE status='pending' AND scheduled_at <= NOW()" . ($isAll ? '' : " AND assigned_to=$uid")
);
$overdue_callbacks = (int) db_val(
    "SELECT COUNT(*) FROM callbacks WHERE status='pending' AND scheduled_at < NOW()" . ($isAll ? '' : " AND assigned_to=$uid")
);
$active_campaigns = (int) db_val("SELECT COUNT(*) FROM campaigns WHERE status='active'");
$pending_tasks    = (int) db_val(
    "SELECT COUNT(*) FROM tasks WHERE status IN ('pending','in_progress')" . ($isAll ? '' : " AND assigned_to=$uid")
);

// Talk time today (seconds)
$talk_today = (int) db_val(
    "SELECT COALESCE(SUM(duration_seconds),0) FROM calls WHERE DATE(started_at)=?" . ($isAll ? '' : " AND agent_id=$uid"),
    [$today]
);

// ── Attendance status ─────────────────────────────────────────
$my_attendance = db_row(
    "SELECT check_in, check_out, work_mode FROM attendance WHERE user_id=? AND date=?",
    [$uid, $today]
);

// ── Recent calls ─────────────────────────────────────────────
$recent_calls = db_rows(
    "SELECT ca.id, ca.direction, ca.started_at, ca.duration_seconds, ca.phone_dialed,
            co.name AS outcome, co.color AS outcome_color,
            c.name AS contact_name, c.id AS contact_id,
            u.name AS agent_name
     FROM calls ca
     LEFT JOIN contacts c ON c.id = ca.contact_id
     LEFT JOIN call_outcomes co ON co.id = ca.outcome_id
     LEFT JOIN users u ON u.id = ca.agent_id
     " . ($isAll ? '' : " WHERE ca.agent_id=$uid") . "
     ORDER BY ca.started_at DESC LIMIT 8"
);

// ── Upcoming callbacks ────────────────────────────────────────
$upcoming_callbacks = db_rows(
    "SELECT cb.id, cb.scheduled_at, cb.notes, c.name AS contact_name, c.id AS contact_id,
            u.name AS assigned_to_name
     FROM callbacks cb
     LEFT JOIN contacts c ON c.id = cb.contact_id
     LEFT JOIN users u ON u.id = cb.assigned_to
     WHERE cb.status = 'pending'
     " . ($isAll ? '' : " AND cb.assigned_to=$uid") . "
     ORDER BY cb.scheduled_at ASC LIMIT 6"
);

// ── Today's tasks ─────────────────────────────────────────────
$todays_tasks = db_rows(
    "SELECT t.id, t.title, t.priority, t.status, t.due_date,
            tt.name AS type_name, tt.icon AS type_icon, tt.color AS type_color,
            c.name AS contact_name, c.id AS contact_id
     FROM tasks t
     LEFT JOIN task_types tt ON tt.id = t.type_id
     LEFT JOIN contacts c ON c.id = t.contact_id
     WHERE t.assigned_to=? AND t.status IN ('pending','in_progress')
     ORDER BY FIELD(t.priority,'urgent','high','medium','low'), t.due_date ASC
     LIMIT 8",
    [$uid]
);

// ── Team performance (senior+ only) ──────────────────────────
$team_stats = [];
if ($isAll) {
    $team_stats = db_rows(
        "SELECT u.id, u.name, u.role,
                COUNT(ca.id) AS calls_today,
                COALESCE(SUM(ca.duration_seconds),0) AS talk_seconds,
                a.check_in, a.work_mode
         FROM users u
         LEFT JOIN calls ca ON ca.agent_id = u.id AND DATE(ca.started_at) = ?
         LEFT JOIN attendance a ON a.user_id = u.id AND a.date = ?
         WHERE u.status = 'active' AND u.role IN ('executive','senior_executive')
         GROUP BY u.id
         ORDER BY calls_today DESC",
        [$today, $today]
    );
}

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-speedometer2 text-primary"></i>
  <h5>Dashboard</h5>
  <div class="ms-auto d-flex gap-2">
    <span class="text-muted small"><?= date('l, d F Y') ?></span>
    <?php if (can('create', 'calls')): ?>
    <a href="<?= BASE_URL ?>/modules/workspace/index.php" class="btn btn-primary btn-sm">
      <i class="bi bi-telephone-plus me-1"></i>New Call
    </a>
    <?php endif ?>
  </div>
</div>

<div class="page-body">

  <!-- ── KPI Cards ─────────────────────────────────────── -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="card kpi-card border-0 bg-primary text-white">
        <div class="card-body py-3">
          <i class="bi bi-telephone-fill kpi-icon"></i>
          <div class="kpi-value"><?= $calls_today ?></div>
          <div class="kpi-label">Calls Today</div>
          <div class="kpi-change opacity-75"><?= format_duration($talk_today) ?> talk time</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card kpi-card border-0 bg-success text-white">
        <div class="card-body py-3">
          <i class="bi bi-calendar-week kpi-icon"></i>
          <div class="kpi-value"><?= $calls_week ?></div>
          <div class="kpi-label">This Week</div>
          <div class="kpi-change opacity-75"><?= $calls_month ?> this month</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card kpi-card border-0 <?= $overdue_callbacks > 0 ? 'bg-danger' : 'bg-warning' ?> text-white">
        <div class="card-body py-3">
          <i class="bi bi-clock-history kpi-icon"></i>
          <div class="kpi-value"><?= $pending_callbacks ?></div>
          <div class="kpi-label">Pending Callbacks</div>
          <div class="kpi-change opacity-75">
            <?= $overdue_callbacks > 0 ? "<strong>$overdue_callbacks overdue!</strong>" : 'On schedule' ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card kpi-card border-0 bg-info text-white">
        <div class="card-body py-3">
          <i class="bi bi-megaphone-fill kpi-icon"></i>
          <div class="kpi-value"><?= $active_campaigns ?></div>
          <div class="kpi-label">Active Campaigns</div>
          <div class="kpi-change opacity-75"><?= $pending_tasks ?> pending tasks</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Attendance / Check-in Banner ─────────────────── -->
  <?php if (current_role() !== 'viewer'): ?>
  <div class="card mb-3 border-0 <?= $my_attendance ? ($my_attendance['check_out'] ? 'bg-light' : 'bg-success bg-opacity-10 border-success') : 'bg-warning bg-opacity-10 border-warning' ?>" style="border:1px solid !important">
    <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
      <?php if (!$my_attendance): ?>
        <i class="bi bi-person-x fs-4 text-warning"></i>
        <span class="fw-semibold">You haven't checked in today.</span>
        <a href="<?= BASE_URL ?>/modules/attendance/index.php" class="btn btn-warning btn-sm ms-auto">
          <i class="bi bi-person-check me-1"></i>Check In Now
        </a>
      <?php elseif ($my_attendance['check_in'] && !$my_attendance['check_out']): ?>
        <i class="bi bi-person-check-fill fs-4 text-success"></i>
        <span class="fw-semibold text-success">Checked in at <?= date('h:i A', strtotime($my_attendance['check_in'])) ?></span>
        <span class="badge bg-success"><?= ucfirst($my_attendance['work_mode']) ?></span>
        <a href="<?= BASE_URL ?>/modules/attendance/index.php" class="btn btn-outline-success btn-sm ms-auto">
          <i class="bi bi-box-arrow-right me-1"></i>Check Out
        </a>
      <?php else: ?>
        <i class="bi bi-check2-circle fs-4 text-secondary"></i>
        <span class="text-muted">Checked out today at <?= date('h:i A', strtotime($my_attendance['check_out'])) ?></span>
      <?php endif ?>
    </div>
  </div>
  <?php endif ?>

  <div class="row g-3">

    <!-- ── Recent Calls ────────────────────────────────── -->
    <div class="col-12 col-lg-7">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center">
          <i class="bi bi-telephone-fill text-primary me-2"></i>Recent Calls
          <a href="<?= BASE_URL ?>/modules/calls/index.php" class="btn btn-sm btn-outline-primary ms-auto no-print">View All</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Contact</th>
                <th>Direction</th>
                <th>Outcome</th>
                <th>Duration</th>
                <th>Time</th>
                <?php if ($isAll): ?><th>Agent</th><?php endif ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_calls as $c): ?>
              <tr>
                <td>
                  <?php if ($c['contact_id']): ?>
                  <a href="<?= BASE_URL ?>/modules/contacts/view.php?id=<?= $c['contact_id'] ?>">
                    <?= h($c['contact_name']) ?>
                  </a>
                  <?php else: ?>
                    <span class="text-muted"><?= h($c['phone_dialed'] ?? '—') ?></span>
                  <?php endif ?>
                </td>
                <td>
                  <?php if ($c['direction'] === 'inbound'): ?>
                    <span class="text-success"><i class="bi bi-telephone-inbound-fill"></i> In</span>
                  <?php else: ?>
                    <span class="text-primary"><i class="bi bi-telephone-outbound-fill"></i> Out</span>
                  <?php endif ?>
                </td>
                <td>
                  <?php if ($c['outcome']): ?>
                  <span class="badge" style="background:<?= h($c['outcome_color']) ?>"><?= h($c['outcome']) ?></span>
                  <?php else: ?>—<?php endif ?>
                </td>
                <td><?= $c['duration_seconds'] ? format_duration((int)$c['duration_seconds']) : '—' ?></td>
                <td class="text-muted" style="font-size:.75rem"><?= time_ago($c['started_at']) ?></td>
                <?php if ($isAll): ?><td class="text-muted"><?= h($c['agent_name']) ?></td><?php endif ?>
              </tr>
              <?php endforeach ?>
              <?php if (empty($recent_calls)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No calls yet today</td></tr>
              <?php endif ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ── Right Column ────────────────────────────────── -->
    <div class="col-12 col-lg-5 d-flex flex-column gap-3">

      <!-- Upcoming Callbacks -->
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <i class="bi bi-clock-history text-warning me-2"></i>Callbacks
          <a href="<?= BASE_URL ?>/modules/callbacks/index.php" class="btn btn-sm btn-outline-secondary ms-auto no-print">All</a>
        </div>
        <ul class="list-group list-group-flush">
          <?php foreach ($upcoming_callbacks as $cb): ?>
          <?php $overdue = strtotime($cb['scheduled_at']) < time(); ?>
          <li class="list-group-item py-2 <?= $overdue ? 'bg-danger bg-opacity-5' : '' ?>">
            <div class="d-flex align-items-start gap-2">
              <i class="bi bi-alarm <?= $overdue ? 'text-danger' : 'text-warning' ?> mt-1 flex-shrink-0"></i>
              <div class="min-w-0">
                <div class="fw-semibold text-truncate"><?= h($cb['contact_name'] ?? 'Unknown') ?></div>
                <div class="small text-muted">
                  <?= $overdue ? '<span class="text-danger fw-semibold">Overdue! </span>' : '' ?>
                  <?= date('d M, h:i A', strtotime($cb['scheduled_at'])) ?>
                  <?php if ($isAll && $cb['assigned_to_name']): ?>
                    &middot; <?= h($cb['assigned_to_name']) ?>
                  <?php endif ?>
                </div>
                <?php if ($cb['notes']): ?><div class="small text-muted text-truncate"><?= h($cb['notes']) ?></div><?php endif ?>
              </div>
            </div>
          </li>
          <?php endforeach ?>
          <?php if (empty($upcoming_callbacks)): ?>
          <li class="list-group-item text-center text-muted py-3 small">No pending callbacks</li>
          <?php endif ?>
        </ul>
      </div>

      <!-- Today's Tasks -->
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <i class="bi bi-check2-square text-success me-2"></i>My Tasks
          <a href="<?= BASE_URL ?>/modules/tasks/index.php" class="btn btn-sm btn-outline-secondary ms-auto no-print">All</a>
        </div>
        <ul class="list-group list-group-flush">
          <?php foreach ($todays_tasks as $t): ?>
          <li class="list-group-item py-2">
            <div class="d-flex align-items-center gap-2">
              <i class="bi <?= h($t['type_icon'] ?? 'bi-check') ?>"
                 style="color:<?= h($t['type_color'] ?? '#999') ?>"></i>
              <div class="min-w-0 flex-grow-1">
                <div class="fw-semibold text-truncate"><?= h($t['title']) ?></div>
                <?php if ($t['contact_name']): ?>
                <div class="small text-muted text-truncate"><?= h($t['contact_name']) ?></div>
                <?php endif ?>
              </div>
              <?= status_badge($t['status']) ?>
              <?php if ($t['priority'] === 'urgent' || $t['priority'] === 'high'): ?>
              <span class="badge bg-danger"><?= ucfirst($t['priority']) ?></span>
              <?php endif ?>
            </div>
          </li>
          <?php endforeach ?>
          <?php if (empty($todays_tasks)): ?>
          <li class="list-group-item text-center text-muted py-3 small">No pending tasks</li>
          <?php endif ?>
        </ul>
      </div>

    </div>
  </div>

  <!-- ── Team Performance (senior+ only) ──────────────── -->
  <?php if ($isAll && !empty($team_stats)): ?>
  <div class="card mt-3">
    <div class="card-header d-flex align-items-center">
      <i class="bi bi-people-fill text-info me-2"></i>Team Today
      <a href="<?= BASE_URL ?>/modules/reports/executive.php" class="btn btn-sm btn-outline-info ms-auto no-print">Full Report</a>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr><th>Agent</th><th>Calls</th><th>Talk Time</th><th>Status</th><th>Mode</th></tr>
        </thead>
        <tbody>
          <?php foreach ($team_stats as $s): ?>
          <tr>
            <td class="fw-semibold"><?= h($s['name']) ?></td>
            <td><?= $s['calls_today'] ?></td>
            <td><?= format_duration((int)$s['talk_seconds']) ?></td>
            <td>
              <?php if ($s['check_in']): ?>
              <span class="badge bg-success">Active</span>
              <?php else: ?>
              <span class="badge bg-secondary">Not checked in</span>
              <?php endif ?>
            </td>
            <td><?= $s['work_mode'] ? ucfirst($s['work_mode']) : '—' ?></td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif ?>

</div><!-- /.page-body -->

<?php require ROOT . '/partials/footer.php'; ?>
