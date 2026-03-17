<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Monthly Summary Report';
$active_page = 'reports';

$uid  = current_user_id();
$role = current_role();

// Filters
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
$year  = max(2020, min(date('Y') + 1, $year));
$month = max(1, min(12, $month));

$date_from = sprintf('%04d-%02d-01', $year, $month);
$date_to   = date('Y-m-t', strtotime($date_from));

$month_label = date('F Y', strtotime($date_from));

// Call stats
$call_kpis = db_row(
    "SELECT COUNT(id) AS total,
            SUM(CASE WHEN direction='inbound' THEN 1 ELSE 0 END) AS inbound,
            SUM(CASE WHEN direction='outbound' THEN 1 ELSE 0 END) AS outbound,
            COALESCE(SUM(duration_seconds),0) AS total_duration,
            COALESCE(AVG(duration_seconds),0) AS avg_duration,
            COUNT(DISTINCT contact_id) AS unique_contacts,
            COUNT(DISTINCT agent_id) AS active_agents
     FROM calls
     WHERE DATE(created_at) BETWEEN ? AND ?",
    [$date_from, $date_to]
);

// SMS stats
$sms_count = (int) db_val("SELECT COUNT(*) FROM sms_log WHERE DATE(sent_at) BETWEEN ? AND ?", [$date_from, $date_to]);

// Feedback/threads
$threads_opened = (int) db_val("SELECT COUNT(*) FROM feedback_threads WHERE DATE(created_at) BETWEEN ? AND ?", [$date_from, $date_to]);
$threads_closed = (int) db_val("SELECT COUNT(*) FROM feedback_threads WHERE DATE(closed_at) BETWEEN ? AND ? AND status='closed'", [$date_from, $date_to]);

// Tasks
$tasks_created   = (int) db_val("SELECT COUNT(*) FROM tasks WHERE DATE(created_at) BETWEEN ? AND ?", [$date_from, $date_to]);
$tasks_completed = (int) db_val("SELECT COUNT(*) FROM tasks WHERE DATE(completed_at) BETWEEN ? AND ? AND status='completed'", [$date_from, $date_to]);

// Callbacks
$callbacks_total  = (int) db_val("SELECT COUNT(*) FROM callbacks WHERE DATE(created_at) BETWEEN ? AND ?", [$date_from, $date_to]);
$callbacks_done   = (int) db_val("SELECT COUNT(*) FROM callbacks WHERE DATE(completed_at) BETWEEN ? AND ? AND status='completed'", [$date_from, $date_to]);
$callbacks_missed = (int) db_val("SELECT COUNT(*) FROM callbacks WHERE DATE(scheduled_at) BETWEEN ? AND ? AND status='missed'", [$date_from, $date_to]);

// Leave stats
$leaves_taken = (int) db_val("SELECT COUNT(*) FROM leaves WHERE status='approved' AND start_date <= ? AND end_date >= ?", [$date_to, $date_from]);

// Attendance
$att_summary = db_rows(
    "SELECT u.name,
            COUNT(DISTINCT DATE(a.check_in)) AS days_present,
            COUNT(cl.id) AS calls_made,
            COALESCE(SUM(cl.duration_seconds),0) AS talk_time
     FROM users u
     LEFT JOIN attendance a ON a.user_id=u.id AND DATE(a.check_in) BETWEEN ? AND ?
     LEFT JOIN calls cl ON cl.agent_id=u.id AND DATE(cl.created_at) BETWEEN ? AND ?
     WHERE u.status='active' AND u.role IN ('executive','senior_executive')
     GROUP BY u.id, u.name
     ORDER BY calls_made DESC",
    [$date_from, $date_to, $date_from, $date_to]
);

// New contacts this month
$new_contacts = (int) db_val("SELECT COUNT(*) FROM contacts WHERE DATE(created_at) BETWEEN ? AND ?", [$date_from, $date_to]);

// Top outcomes
$top_outcomes = db_rows(
    "SELECT out.name, COUNT(cl.id) AS cnt
     FROM calls cl
     LEFT JOIN call_outcomes out ON out.id=cl.outcome_id
     WHERE DATE(cl.created_at) BETWEEN ? AND ?
     GROUP BY cl.outcome_id, out.name
     ORDER BY cnt DESC LIMIT 5",
    [$date_from, $date_to]
);

// Top campaigns
$top_campaigns = db_rows(
    "SELECT cam.name, COUNT(cl.id) AS calls
     FROM calls cl
     JOIN campaigns cam ON cam.id=cl.campaign_id
     WHERE DATE(cl.created_at) BETWEEN ? AND ?
     GROUP BY cl.campaign_id, cam.name
     ORDER BY calls DESC LIMIT 5",
    [$date_from, $date_to]
);

// Week by week
$weekly = db_rows(
    "SELECT WEEK(created_at) AS wk, MIN(DATE(created_at)) AS week_start,
            COUNT(*) AS calls, COALESCE(SUM(duration_seconds),0) AS duration
     FROM calls
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY WEEK(created_at)
     ORDER BY wk",
    [$date_from, $date_to]
);

// Previous month comparison
$pm_from = date('Y-m-01', strtotime($date_from . ' -1 month'));
$pm_to   = date('Y-m-t',  strtotime($pm_from));
$pm_kpis = db_row(
    "SELECT COUNT(id) AS total, COALESCE(SUM(duration_seconds),0) AS total_duration
     FROM calls WHERE DATE(created_at) BETWEEN ? AND ?",
    [$pm_from, $pm_to]
);
$call_change = $pm_kpis['total'] > 0
    ? round((($call_kpis['total'] - $pm_kpis['total']) / $pm_kpis['total']) * 100, 1)
    : 0;

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-calendar-month-fill text-primary me-1"></i>
  <h5 class="mb-0">Monthly Summary — <?= $month_label ?></h5>
  <div class="ms-auto d-flex gap-2 no-print">
    <button id="copyWaBtn" class="btn btn-sm btn-success"><i class="bi bi-whatsapp me-1"></i>Copy WA</button>
    <button data-print class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
  </div>
</div>

<div class="page-body">

  <!-- Month navigator -->
  <div class="d-flex align-items-center gap-2 mb-3 no-print">
    <a href="?year=<?= $month == 1 ? $year-1 : $year ?>&month=<?= $month == 1 ? 12 : $month-1 ?>"
       class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i> Prev</a>
    <form method="get" class="d-flex gap-2">
      <select name="month" class="form-select form-select-sm" style="width:auto">
        <?php for ($m=1; $m<=12; $m++): ?>
        <option value="<?= $m ?>" <?= $m==$month?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
        <?php endfor ?>
      </select>
      <select name="year" class="form-select form-select-sm" style="width:auto">
        <?php for ($y=date('Y'); $y>=2020; $y--): ?>
        <option value="<?= $y ?>" <?= $y==$year?'selected':'' ?>><?= $y ?></option>
        <?php endfor ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Go</button>
    </form>
    <?php if (!($year == date('Y') && $month == date('n'))): ?>
    <a href="?year=<?= $month == 12 ? $year+1 : $year ?>&month=<?= $month == 12 ? 1 : $month+1 ?>"
       class="btn btn-sm btn-outline-secondary">Next <i class="bi bi-chevron-right"></i></a>
    <?php endif ?>
  </div>

  <!-- Print header -->
  <div class="print-only mb-2">
    <h5><?= $month_label ?> — Monthly Summary Report (<?= setting('company_name', 'Ovijat Group') ?>)</h5>
    <small>Generated: <?= format_datetime(date('Y-m-d H:i:s')) ?></small>
    <hr>
  </div>

  <!-- KPI Row -->
  <div class="row g-2 mb-3">
    <?php
    $kpiCards = [
      ['Total Calls', number_format($call_kpis['total']), 'telephone-fill', 'primary',
       $call_change >= 0 ? 'text-success' : 'text-danger',
       ($call_change >= 0 ? '+' : '') . $call_change . '% vs last month'],
      ['Talk Time', format_duration($call_kpis['total_duration']), 'clock-fill', 'info', '', ''],
      ['Unique Contacts', number_format($call_kpis['unique_contacts']), 'people-fill', 'warning', '', ''],
      ['SMS Sent', number_format($sms_count), 'chat-fill', 'success', '', ''],
      ['New Contacts', number_format($new_contacts), 'person-plus-fill', 'secondary', '', ''],
      ['Callbacks Done', number_format($callbacks_done) . '/' . number_format($callbacks_total), 'telephone-forward-fill', 'primary', '', ''],
    ];
    foreach ($kpiCards as [$label, $value, $icon, $color, $trendClass, $trend]): ?>
    <div class="col-6 col-md-2">
      <div class="card text-center border-<?= $color ?>"><div class="card-body py-2">
        <i class="bi bi-<?= $icon ?> text-<?= $color ?>"></i>
        <div class="fs-5 fw-bold mt-1"><?= $value ?></div>
        <div class="text-muted" style="font-size:.72rem"><?= $label ?></div>
        <?php if ($trend): ?>
        <div class="<?= $trendClass ?>" style="font-size:.7rem"><?= $trend ?></div>
        <?php endif ?>
      </div></div>
    </div>
    <?php endforeach ?>
  </div>

  <div class="row g-3">
    <!-- Agent Performance -->
    <div class="col-md-7">
      <div class="card mb-3">
        <div class="card-header py-2 fw-semibold">Agent Performance</div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr><th>Agent</th><th>Days</th><th>Calls</th><th>Talk Time</th><th>Avg/Day</th></tr>
            </thead>
            <tbody>
              <?php foreach ($att_summary as $a):
                $days = $a['days_present'] ?: 1;
                $avg  = round($a['calls_made'] / $days, 1);
              ?>
              <tr>
                <td class="fw-semibold"><?= h($a['name']) ?></td>
                <td><?= $a['days_present'] ?></td>
                <td><?= number_format($a['calls_made']) ?></td>
                <td><?= format_duration($a['talk_time']) ?></td>
                <td class="text-muted small"><?= $avg ?></td>
              </tr>
              <?php endforeach ?>
              <?php if (empty($att_summary)): ?>
              <tr><td colspan="5" class="text-center text-muted py-2">No data</td></tr>
              <?php endif ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Weekly breakdown -->
      <div class="card">
        <div class="card-header py-2 fw-semibold">Weekly Breakdown</div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr><th>Week Of</th><th>Calls</th><th>Talk Time</th></tr>
            </thead>
            <tbody>
              <?php foreach ($weekly as $w): ?>
              <tr>
                <td><?= format_date($w['week_start']) ?></td>
                <td class="fw-semibold"><?= $w['calls'] ?></td>
                <td><?= format_duration($w['duration']) ?></td>
              </tr>
              <?php endforeach ?>
              <?php if (empty($weekly)): ?>
              <tr><td colspan="3" class="text-center text-muted py-2">No data</td></tr>
              <?php endif ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="col-md-5">
      <!-- Outcomes -->
      <div class="card mb-3">
        <div class="card-header py-2 fw-semibold">Top Outcomes</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <?php foreach ($top_outcomes as $o): ?>
            <tr>
              <td><?= h($o['name'] ?? 'Unknown') ?></td>
              <td class="text-end fw-semibold"><?= $o['cnt'] ?></td>
              <td style="width:35%">
                <div class="progress" style="height:5px">
                  <div class="progress-bar" style="width:<?= $call_kpis['total']>0 ? round($o['cnt']/$call_kpis['total']*100) : 0 ?>%"></div>
                </div>
              </td>
            </tr>
            <?php endforeach ?>
          </table>
        </div>
      </div>

      <!-- Campaigns -->
      <?php if (!empty($top_campaigns)): ?>
      <div class="card mb-3">
        <div class="card-header py-2 fw-semibold">Top Campaigns</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <?php foreach ($top_campaigns as $camp): ?>
            <tr>
              <td><?= h($camp['name']) ?></td>
              <td class="text-end fw-semibold"><?= $camp['calls'] ?></td>
            </tr>
            <?php endforeach ?>
          </table>
        </div>
      </div>
      <?php endif ?>

      <!-- Other stats -->
      <div class="card">
        <div class="card-header py-2 fw-semibold">Other Activity</div>
        <div class="card-body">
          <div class="row g-1 small">
            <div class="col-6 text-muted">Inbound Calls</div><div class="col-6 fw-semibold"><?= number_format($call_kpis['inbound']) ?></div>
            <div class="col-6 text-muted">Outbound Calls</div><div class="col-6 fw-semibold"><?= number_format($call_kpis['outbound']) ?></div>
            <div class="col-6 text-muted">Avg Call Dur.</div><div class="col-6"><?= format_duration((int)$call_kpis['avg_duration']) ?></div>
            <div class="col-6 text-muted">Threads Opened</div><div class="col-6"><?= $threads_opened ?></div>
            <div class="col-6 text-muted">Threads Closed</div><div class="col-6"><?= $threads_closed ?></div>
            <div class="col-6 text-muted">Tasks Created</div><div class="col-6"><?= $tasks_created ?></div>
            <div class="col-6 text-muted">Tasks Done</div><div class="col-6"><?= $tasks_completed ?></div>
            <div class="col-6 text-muted">Callbacks Missed</div><div class="col-6 text-danger"><?= $callbacks_missed ?></div>
            <div class="col-6 text-muted">Leaves Taken</div><div class="col-6"><?= $leaves_taken ?></div>
            <div class="col-6 text-muted">Active Agents</div><div class="col-6"><?= $call_kpis['active_agents'] ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Hidden WA text -->
<textarea id="waMonthlyText" class="d-none"><?php
$wa  = "*Monthly Report — $month_label*\n";
$wa .= "📞 Calls: {$call_kpis['total']} (↑{$call_change}%) | 🕐 " . format_duration($call_kpis['total_duration']) . "\n";
$wa .= "📥 Inbound: {$call_kpis['inbound']} | 📤 Outbound: {$call_kpis['outbound']}\n";
$wa .= "👥 Unique Contacts: {$call_kpis['unique_contacts']} | 🆕 New: $new_contacts\n";
$wa .= "💬 SMS: $sms_count | 🔁 Callbacks: $callbacks_done/$callbacks_total\n\n";
$wa .= "*By Agent:*\n";
foreach ($att_summary as $a) {
    $wa .= "• {$a['name']}: {$a['calls_made']} calls | " . format_duration($a['talk_time']) . " | {$a['days_present']} days\n";
}
echo h($wa);
?></textarea>

<script>
document.getElementById('copyWaBtn')?.addEventListener('click', function() {
    const txt = document.getElementById('waMonthlyText').value;
    navigator.clipboard.writeText(txt).then(() => showToast('Copied for WhatsApp!', 'success'));
});
</script>

<?php require ROOT . '/partials/footer.php'; ?>
