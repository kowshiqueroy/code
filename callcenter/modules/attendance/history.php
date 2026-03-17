<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Attendance History';
$active_page = 'attendance';

$uid     = current_user_id();
$f_month = clean($_GET['month'] ?? date('Y-m'));
$f_year  = substr($f_month, 0, 4);
$f_mon   = substr($f_month, 5, 2);

$history = db_rows(
    "SELECT * FROM attendance WHERE user_id=? AND YEAR(date)=? AND MONTH(date)=? ORDER BY date ASC",
    [$uid, $f_year, $f_mon]
);

// Days in month
$days_in_month = cal_days_in_month(CAL_GREGORIAN, (int)$f_mon, (int)$f_year);
$first_day     = mktime(0,0,0,(int)$f_mon,1,(int)$f_year);

// Build indexed array by date
$by_date = [];
foreach ($history as $h) $by_date[$h['date']] = $h;

// Summary
$summary = [
    'present' => 0, 'absent' => 0, 'leave' => 0,
    'total_hours' => 0, 'wfh' => 0, 'field' => 0,
];
foreach ($history as $h) {
    if ($h['status'] === 'present') { $summary['present']++; $summary['total_hours'] += $h['total_hours']; }
    if ($h['status'] === 'absent')  $summary['absent']++;
    if ($h['status'] === 'leave')   $summary['leave']++;
    if ($h['work_mode'] === 'wfh')   $summary['wfh']++;
    if ($h['work_mode'] === 'field') $summary['field']++;
}

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/attendance/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-calendar3 text-primary ms-1"></i>
  <h5 class="ms-1">Attendance History</h5>
  <div class="ms-auto d-flex gap-2 no-print">
    <button data-print class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
  </div>
</div>

<div class="page-body">

  <!-- Month picker -->
  <div class="d-flex gap-2 align-items-center mb-3 no-print">
    <?php
    $prevMonth = date('Y-m', mktime(0,0,0,(int)$f_mon-1,1,(int)$f_year));
    $nextMonth = date('Y-m', mktime(0,0,0,(int)$f_mon+1,1,(int)$f_year));
    ?>
    <a href="?month=<?= $prevMonth ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
    <input type="month" class="form-control form-control-sm" style="max-width:180px"
           value="<?= h($f_month) ?>" onchange="location='?month='+this.value">
    <a href="?month=<?= $nextMonth ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
    <a href="?month=<?= date('Y-m') ?>" class="btn btn-sm btn-outline-primary">Today</a>
  </div>

  <!-- Summary stats -->
  <div class="row g-2 mb-3">
    <?php foreach ([
      ['Present', $summary['present'], 'success'],
      ['Absent', $summary['absent'], 'danger'],
      ['Leave', $summary['leave'], 'warning'],
      ['Total Hours', number_format($summary['total_hours'], 1) . 'h', 'primary'],
      ['WFH Days', $summary['wfh'], 'info'],
      ['Field Days', $summary['field'], 'secondary'],
    ] as [$label, $val, $cls]): ?>
    <div class="col-4 col-md-2">
      <div class="card text-center py-2">
        <div class="fs-5 fw-bold text-<?= $cls ?>"><?= $val ?></div>
        <div class="text-muted small"><?= $label ?></div>
      </div>
    </div>
    <?php endforeach ?>
  </div>

  <!-- Calendar view -->
  <div class="card">
    <div class="card-header fw-semibold"><?= date('F Y', $first_day) ?></div>
    <div class="card-body p-2">
      <div class="row g-1 mb-1 text-center">
        <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day): ?>
        <div class="col text-muted small fw-semibold"><?= $day ?></div>
        <?php endforeach ?>
      </div>

      <?php
      $startDow = date('w', $first_day);
      $week_cols = array_fill(0, $startDow, null);
      for ($d = 1; $d <= $days_in_month; $d++) $week_cols[] = $d;
      // Pad to multiple of 7
      while (count($week_cols) % 7 !== 0) $week_cols[] = null;
      $weeks = array_chunk($week_cols, 7);
      foreach ($weeks as $week): ?>
      <div class="row g-1 mb-1 text-center">
        <?php foreach ($week as $day): ?>
        <div class="col">
          <?php if ($day === null): ?>
          <div class="p-1"></div>
          <?php else:
            $dateStr = sprintf('%s-%02d-%02d', $f_year, $f_mon, $day);
            $att = $by_date[$dateStr] ?? null;
            $isToday = $dateStr === date('Y-m-d');
            $dow = date('w', mktime(0,0,0,(int)$f_mon,$day,(int)$f_year));
            $isWeekend = in_array($dow, [5, 6]); // Fri, Sat for BD

            $cellClass = 'rounded p-1 small ';
            if ($isToday) $cellClass .= 'border border-primary ';
            if ($att) {
              $statusColors = ['present'=>'bg-success text-white', 'absent'=>'bg-danger text-white', 'leave'=>'bg-warning text-dark'];
              $cellClass .= $statusColors[$att['status']] ?? '';
            } elseif ($isWeekend) {
              $cellClass .= 'bg-light text-muted ';
            } elseif (strtotime($dateStr) < time()) {
              $cellClass .= 'bg-light text-danger '; // past and no record
            }
          ?>
          <div class="<?= $cellClass ?>" style="min-height:40px">
            <div><?= $day ?></div>
            <?php if ($att): ?>
            <div style="font-size:.55rem"><?= $att['total_hours'] ? number_format($att['total_hours'],1).'h' : '' ?></div>
            <?php elseif ($isWeekend): ?>
            <div style="font-size:.55rem">OFF</div>
            <?php endif ?>
          </div>
          <?php endif ?>
        </div>
        <?php endforeach ?>
      </div>
      <?php endforeach ?>
    </div>
    <!-- Legend -->
    <div class="card-footer d-flex gap-3 flex-wrap no-print" style="font-size:.75rem">
      <span><span class="badge bg-success">Present</span></span>
      <span><span class="badge bg-danger">Absent</span></span>
      <span><span class="badge bg-warning text-dark">Leave</span></span>
      <span><span class="badge bg-light text-muted border">Weekend/Holiday</span></span>
    </div>
  </div>

  <!-- Detail table -->
  <div class="card mt-3">
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
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h): ?>
          <tr>
            <td class="small"><?= format_date($h['date']) ?></td>
            <td class="small"><?= ucfirst(str_replace('_',' ',$h['work_mode'])) ?></td>
            <td class="text-muted small"><?= $h['check_in'] ? date('g:i A', strtotime($h['check_in'])) : '—' ?></td>
            <td class="text-muted small"><?= $h['check_out'] ? date('g:i A', strtotime($h['check_out'])) : '—' ?></td>
            <td class="text-muted small"><?= $h['total_hours'] ? number_format($h['total_hours'],1).'h' : '—' ?></td>
            <td><?= status_badge($h['status']) ?></td>
            <td class="text-muted small"><?= h($h['notes'] ?? '—') ?></td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($history)): ?>
          <tr><td colspan="7" class="text-center text-muted py-3">No records for this month</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require ROOT . '/partials/footer.php'; ?>
