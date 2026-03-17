<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Agent Performance Report';
$active_page = 'reports';

$uid  = current_user_id();
$role = current_role();

// Viewers cannot access agent-level report
if ($role === 'viewer') {
    flash_error('Access denied.');
    redirect(BASE_URL . '/modules/reports/index.php');
}

// Filters
$date_from = clean($_GET['date_from'] ?? date('Y-m-01'));
$date_to   = clean($_GET['date_to']   ?? date('Y-m-d'));
$agent_id  = (int)($_GET['agent_id'] ?? 0);

// For non-senior, only own data
if (role_level($role) < role_level('senior_executive')) {
    $agent_id = $uid;
}

$agents = db_rows("SELECT id, name FROM users WHERE status='active' ORDER BY name");

// Build WHERE
$agent_where  = $agent_id ? 'AND cl.agent_id = ?' : '';
$agent_params = $agent_id ? [$agent_id] : [];

// Per-agent summary
$agent_stats = db_rows(
    "SELECT u.id, u.name,
            COUNT(cl.id) AS total_calls,
            SUM(CASE WHEN cl.direction='inbound' THEN 1 ELSE 0 END) AS inbound,
            SUM(CASE WHEN cl.direction='outbound' THEN 1 ELSE 0 END) AS outbound,
            COALESCE(SUM(cl.duration_seconds),0) AS total_duration,
            COALESCE(AVG(cl.duration_seconds),0) AS avg_duration,
            COUNT(DISTINCT cl.contact_id) AS unique_contacts,
            SUM(CASE WHEN out.requires_callback=1 THEN 1 ELSE 0 END) AS callbacks_scheduled
     FROM users u
     LEFT JOIN calls cl ON cl.agent_id=u.id
       AND DATE(cl.created_at) BETWEEN ? AND ?
       $agent_where
     LEFT JOIN call_outcomes out ON out.id=cl.outcome_id
     WHERE u.status='active'
       AND u.role IN ('executive','senior_executive','super_admin')
       " . ($agent_id ? "AND u.id=?" : "") . "
     GROUP BY u.id, u.name
     ORDER BY total_calls DESC",
    array_merge([$date_from, $date_to], $agent_params, $agent_id ? [$agent_id] : [])
);

// Daily breakdown for selected agent (or all)
$daily_stats = db_rows(
    "SELECT DATE(cl.created_at) AS call_date,
            COUNT(cl.id) AS total_calls,
            COALESCE(SUM(cl.duration_seconds),0) AS total_duration,
            COUNT(DISTINCT cl.contact_id) AS contacts
     FROM calls cl
     WHERE DATE(cl.created_at) BETWEEN ? AND ?
       $agent_where
     GROUP BY DATE(cl.created_at)
     ORDER BY call_date ASC",
    array_merge([$date_from, $date_to], $agent_params)
);

// Outcome breakdown
$outcome_stats = db_rows(
    "SELECT out.name, out.color, COUNT(cl.id) AS cnt,
            ROUND(COUNT(cl.id)*100.0/NULLIF((SELECT COUNT(*) FROM calls cl2 WHERE DATE(cl2.created_at) BETWEEN ? AND ? $agent_where),0),1) AS pct
     FROM calls cl
     LEFT JOIN call_outcomes out ON out.id=cl.outcome_id
     WHERE DATE(cl.created_at) BETWEEN ? AND ?
       $agent_where
     GROUP BY cl.outcome_id, out.name, out.color
     ORDER BY cnt DESC",
    array_merge([$date_from, $date_to], $agent_params, [$date_from, $date_to], $agent_params)
);

// Total KPIs
$kpis = db_row(
    "SELECT COUNT(id) AS total_calls,
            COALESCE(SUM(duration),0) AS total_duration,
            COALESCE(AVG(duration),0) AS avg_duration,
            COUNT(DISTINCT contact_id) AS unique_contacts,
            SUM(CASE WHEN direction='inbound' THEN 1 ELSE 0 END) AS inbound,
            SUM(CASE WHEN direction='outbound' THEN 1 ELSE 0 END) AS outbound
     FROM calls
     WHERE DATE(created_at) BETWEEN ? AND ?
       $agent_where",
    array_merge([$date_from, $date_to], $agent_params)
);

// Attendance summary for period
$att_stats = db_rows(
    "SELECT u.name, COUNT(DISTINCT DATE(a.check_in)) AS days_present,
            AVG(TIMESTAMPDIFF(MINUTE, a.check_in, a.check_out)) AS avg_minutes
     FROM attendance a
     JOIN users u ON u.id=a.user_id
     WHERE DATE(a.check_in) BETWEEN ? AND ?
       " . ($agent_id ? "AND a.user_id=?" : "") . "
       AND u.status='active'
     GROUP BY a.user_id, u.name
     ORDER BY days_present DESC",
    array_merge([$date_from, $date_to], $agent_id ? [$agent_id] : [])
);

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-person-lines-fill text-primary me-1"></i>
  <h5 class="mb-0">Agent Performance</h5>
  <div class="ms-auto d-flex gap-2 no-print">
    <button id="copyWaBtn" class="btn btn-sm btn-success"><i class="bi bi-whatsapp me-1"></i>Copy WA</button>
    <button data-print class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
  </div>
</div>

<div class="page-body">

  <!-- Filters -->
  <form method="get" class="card mb-3 no-print">
    <div class="card-body py-2 px-3">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label small mb-1">Date From</label>
          <input type="date" name="date_from" class="form-control form-control-sm" value="<?= h($date_from) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Date To</label>
          <input type="date" name="date_to" class="form-control form-control-sm" value="<?= h($date_to) ?>">
        </div>
        <?php if (role_level($role) >= role_level('senior_executive')): ?>
        <div class="col-md-3">
          <label class="form-label small mb-1">Agent</label>
          <select name="agent_id" class="form-select form-select-sm">
            <option value="">All Agents</option>
            <?php foreach ($agents as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $agent_id==$a['id']?'selected':'' ?>><?= h($a['name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <?php endif ?>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm">Apply</button>
        </div>
      </div>
    </div>
  </form>

  <!-- KPI Cards -->
  <div class="row g-2 mb-3">
    <div class="col-6 col-md-2">
      <div class="card text-center"><div class="card-body py-2">
        <div class="fs-5 fw-bold"><?= number_format($kpis['total_calls']) ?></div>
        <div class="text-muted small">Total Calls</div>
      </div></div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card text-center border-success"><div class="card-body py-2">
        <div class="fs-5 fw-bold text-success"><?= number_format($kpis['inbound']) ?></div>
        <div class="text-muted small">Inbound</div>
      </div></div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card text-center border-primary"><div class="card-body py-2">
        <div class="fs-5 fw-bold text-primary"><?= number_format($kpis['outbound']) ?></div>
        <div class="text-muted small">Outbound</div>
      </div></div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card text-center border-info"><div class="card-body py-2">
        <div class="fs-5 fw-bold text-info"><?= format_duration($kpis['total_duration']) ?></div>
        <div class="text-muted small">Talk Time</div>
      </div></div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card text-center"><div class="card-body py-2">
        <div class="fs-5 fw-bold"><?= format_duration((int)$kpis['avg_duration']) ?></div>
        <div class="text-muted small">Avg Duration</div>
      </div></div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card text-center border-warning"><div class="card-body py-2">
        <div class="fs-5 fw-bold text-warning"><?= number_format($kpis['unique_contacts']) ?></div>
        <div class="text-muted small">Unique Contacts</div>
      </div></div>
    </div>
  </div>

  <div class="row g-3">
    <!-- Agent Table -->
    <div class="col-md-8">
      <div class="card mb-3">
        <div class="card-header py-2 fw-semibold">Agent Breakdown</div>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0" id="agentTable">
            <thead class="table-light">
              <tr>
                <th>Agent</th><th>Total</th><th>In</th><th>Out</th>
                <th>Talk Time</th><th>Avg Dur</th><th>Contacts</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($agent_stats as $a):
                $maxCalls = max(array_column($agent_stats, 'total_calls') ?: [1]);
                $pct = $maxCalls > 0 ? round(($a['total_calls']/$maxCalls)*100) : 0;
              ?>
              <tr>
                <td class="fw-semibold"><?= h($a['name']) ?></td>
                <td>
                  <div class="d-flex align-items-center gap-1">
                    <span><?= number_format($a['total_calls']) ?></span>
                    <div class="progress flex-grow-1" style="height:5px">
                      <div class="progress-bar" style="width:<?= $pct ?>%"></div>
                    </div>
                  </div>
                </td>
                <td class="text-success"><?= $a['inbound'] ?></td>
                <td class="text-primary"><?= $a['outbound'] ?></td>
                <td><?= format_duration($a['total_duration']) ?></td>
                <td class="text-muted small"><?= format_duration((int)$a['avg_duration']) ?></td>
                <td><?= $a['unique_contacts'] ?></td>
              </tr>
              <?php endforeach ?>
              <?php if (empty($agent_stats)): ?>
              <tr><td colspan="7" class="text-center text-muted py-3">No data</td></tr>
              <?php endif ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Daily breakdown -->
      <div class="card">
        <div class="card-header py-2 fw-semibold">Daily Breakdown</div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr><th>Date</th><th>Calls</th><th>Talk Time</th><th>Contacts</th></tr>
            </thead>
            <tbody>
              <?php foreach ($daily_stats as $d): ?>
              <tr>
                <td><?= format_date($d['call_date']) ?></td>
                <td class="fw-semibold"><?= $d['total_calls'] ?></td>
                <td><?= format_duration($d['total_duration']) ?></td>
                <td><?= $d['contacts'] ?></td>
              </tr>
              <?php endforeach ?>
              <?php if (empty($daily_stats)): ?>
              <tr><td colspan="4" class="text-center text-muted py-3">No data</td></tr>
              <?php endif ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Outcome + Attendance -->
    <div class="col-md-4">
      <div class="card mb-3">
        <div class="card-header py-2 fw-semibold">Call Outcomes</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <?php foreach ($outcome_stats as $os): ?>
            <tr>
              <td><?= h($os['name'] ?? 'Unknown') ?></td>
              <td class="text-end fw-semibold"><?= $os['cnt'] ?></td>
              <td class="text-muted small">
                <div class="progress" style="height:5px"><div class="progress-bar" style="width:<?= $os['pct'] ?>%"></div></div>
                <?= $os['pct'] ?>%
              </td>
            </tr>
            <?php endforeach ?>
            <?php if (empty($outcome_stats)): ?>
            <tr><td colspan="3" class="text-center text-muted py-2">No data</td></tr>
            <?php endif ?>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header py-2 fw-semibold">Attendance Summary</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Agent</th><th>Days</th><th>Avg Hrs</th></tr></thead>
            <tbody>
              <?php foreach ($att_stats as $a): ?>
              <tr>
                <td><?= h($a['name']) ?></td>
                <td class="fw-semibold"><?= $a['days_present'] ?></td>
                <td class="text-muted small"><?= $a['avg_minutes'] ? round($a['avg_minutes']/60, 1).'h' : '—' ?></td>
              </tr>
              <?php endforeach ?>
              <?php if (empty($att_stats)): ?>
              <tr><td colspan="3" class="text-center text-muted py-2">No data</td></tr>
              <?php endif ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Hidden WA text for copy -->
<textarea id="waReportText" class="d-none"><?php
$wa = "*Agent Performance Report*\n";
$wa .= "Period: $date_from to $date_to\n";
$wa .= "Total Calls: {$kpis['total_calls']} | Talk Time: " . format_duration($kpis['total_duration']) . "\n\n";
$wa .= "*By Agent:*\n";
foreach ($agent_stats as $a) {
    $wa .= "• {$a['name']}: {$a['total_calls']} calls | " . format_duration($a['total_duration']) . "\n";
}
echo h($wa);
?></textarea>

<script>
document.getElementById('copyWaBtn')?.addEventListener('click', function() {
    const txt = document.getElementById('waReportText').value;
    navigator.clipboard.writeText(txt).then(() => showToast('Copied for WhatsApp!', 'success'));
});
</script>

<?php require ROOT . '/partials/footer.php'; ?>
