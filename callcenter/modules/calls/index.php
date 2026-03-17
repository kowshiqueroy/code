<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Call History';
$active_page = 'calls';

$uid   = current_user_id();
$role  = current_role();
$isAll = in_array($role, ['super_admin','senior_executive']);

// ── Filters ───────────────────────────────────────────────────
$f_agent     = (int)($_GET['agent']    ?? 0);
$f_campaign  = (int)($_GET['campaign'] ?? 0);
$f_direction = clean($_GET['direction'] ?? '');
$f_outcome   = (int)($_GET['outcome']  ?? 0);
$f_date_from = clean($_GET['date_from'] ?? date('Y-m-01'));
$f_date_to   = clean($_GET['date_to']   ?? date('Y-m-d'));
$f_search    = clean($_GET['q']         ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 25;

// ── Build WHERE ───────────────────────────────────────────────
$where = ["ca.started_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)"];
$params = [$f_date_from, $f_date_to];

if (!$isAll) {
    $where[] = "ca.agent_id = ?";
    $params[] = $uid;
} elseif ($f_agent) {
    $where[] = "ca.agent_id = ?";
    $params[] = $f_agent;
}
if ($f_campaign)  { $where[] = "ca.campaign_id = ?"; $params[] = $f_campaign; }
if ($f_direction) { $where[] = "ca.direction = ?";   $params[] = $f_direction; }
if ($f_outcome)   { $where[] = "ca.outcome_id = ?";  $params[] = $f_outcome; }
if ($f_search) {
    $where[] = "(c.name LIKE ? OR c.phone LIKE ? OR ca.phone_dialed LIKE ? OR ca.notes LIKE ?)";
    $sq = '%' . $f_search . '%';
    $params = array_merge($params, [$sq, $sq, $sq, $sq]);
}

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) db_val(
    "SELECT COUNT(*) FROM calls ca
     LEFT JOIN contacts c ON c.id = ca.contact_id
     $whereStr",
    $params
);
$p = paginate($total, $page, $per_page);

$calls = db_rows(
    "SELECT ca.id, ca.direction, ca.started_at, ca.ended_at, ca.duration_seconds,
            ca.phone_dialed, ca.notes,
            co.name AS outcome, co.color AS outcome_color,
            c.id AS contact_id, c.name AS contact_name, c.phone AS contact_phone,
            u.id AS agent_id, u.name AS agent_name,
            camp.name AS campaign_name,
            cs.sentiment, cs.follow_up_required
     FROM calls ca
     LEFT JOIN contacts c    ON c.id    = ca.contact_id
     LEFT JOIN call_outcomes co ON co.id = ca.outcome_id
     LEFT JOIN users u        ON u.id    = ca.agent_id
     LEFT JOIN campaigns camp ON camp.id = ca.campaign_id
     LEFT JOIN call_summary cs ON cs.call_id = ca.id
     $whereStr
     ORDER BY ca.started_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$p['per_page'], $p['offset']])
);

// ── Totals for the filtered period ───────────────────────────
$period_stats = db_row(
    "SELECT COUNT(*) AS total_calls,
            SUM(CASE WHEN ca.direction='inbound' THEN 1 ELSE 0 END) AS inbound,
            SUM(CASE WHEN ca.direction='outbound' THEN 1 ELSE 0 END) AS outbound,
            COALESCE(SUM(ca.duration_seconds),0) AS total_seconds
     FROM calls ca LEFT JOIN contacts c ON c.id=ca.contact_id $whereStr",
    $params
);

$agents    = $isAll ? db_rows("SELECT id, name FROM users WHERE status='active' AND role != 'viewer' ORDER BY name") : [];
$outcomes  = db_rows("SELECT id, name, color FROM call_outcomes WHERE is_active=1 ORDER BY sort_order");
$campaigns = db_rows("SELECT id, name FROM campaigns ORDER BY name");

// ── WhatsApp summary text ─────────────────────────────────────
$wa_text = sprintf(
    "📊 Call Report (%s to %s)\n" .
    "Total: %d | Inbound: %d | Outbound: %d\n" .
    "Talk time: %s",
    $f_date_from, $f_date_to,
    $period_stats['total_calls'], $period_stats['inbound'], $period_stats['outbound'],
    format_duration((int)$period_stats['total_seconds'])
);

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-telephone-fill text-primary"></i>
  <h5>Call History</h5>
  <div class="ms-auto d-flex gap-2">
    <button data-print class="btn btn-sm btn-outline-secondary no-print">
      <i class="bi bi-printer me-1"></i>Print
    </button>
    <button data-copy-wa data-text="<?= h($wa_text) ?>" class="btn btn-sm btn-outline-success no-print">
      <i class="bi bi-whatsapp me-1"></i>Copy Summary
    </button>
    <?php if (can('create')): ?>
    <a href="<?= BASE_URL ?>/modules/workspace/index.php" class="btn btn-sm btn-primary no-print">
      <i class="bi bi-plus me-1"></i>Log Call
    </a>
    <?php endif ?>
  </div>
</div>

<div class="page-body">

  <!-- ── Filter Bar ────────────────────────────────────── -->
  <form method="get" class="card mb-3 no-print">
    <div class="card-body py-2 px-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-2">
          <label class="form-label">From</label>
          <input type="date" class="form-control form-control-sm" name="date_from" value="<?= h($f_date_from) ?>">
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label">To</label>
          <input type="date" class="form-control form-control-sm" name="date_to" value="<?= h($f_date_to) ?>">
        </div>
        <?php if ($isAll): ?>
        <div class="col-12 col-md-2">
          <label class="form-label">Agent</label>
          <select class="form-select form-select-sm" name="agent">
            <option value="">All Agents</option>
            <?php foreach ($agents as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $f_agent == $a['id'] ? 'selected' : '' ?>><?= h($a['name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <?php endif ?>
        <div class="col-12 col-md-2">
          <label class="form-label">Direction</label>
          <select class="form-select form-select-sm" name="direction">
            <option value="">All</option>
            <option value="inbound"  <?= $f_direction==='inbound'  ? 'selected':'' ?>>Inbound</option>
            <option value="outbound" <?= $f_direction==='outbound' ? 'selected':'' ?>>Outbound</option>
          </select>
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label">Outcome</label>
          <select class="form-select form-select-sm" name="outcome">
            <option value="">All</option>
            <?php foreach ($outcomes as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $f_outcome == $o['id'] ? 'selected' : '' ?>><?= h($o['name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label">Search</label>
          <input type="text" class="form-control form-control-sm" name="q"
                 value="<?= h($f_search) ?>" placeholder="Name, phone, notes…">
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
      </div>
    </div>
  </form>

  <!-- ── Period Stats ──────────────────────────────────── -->
  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
      <div class="card text-center py-2">
        <div class="fw-bold fs-5"><?= number_format($period_stats['total_calls']) ?></div>
        <div class="text-muted small">Total Calls</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center py-2">
        <div class="fw-bold fs-5 text-success"><?= number_format($period_stats['inbound']) ?></div>
        <div class="text-muted small">Inbound</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center py-2">
        <div class="fw-bold fs-5 text-primary"><?= number_format($period_stats['outbound']) ?></div>
        <div class="text-muted small">Outbound</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center py-2">
        <div class="fw-bold fs-5"><?= format_duration((int)$period_stats['total_seconds']) ?></div>
        <div class="text-muted small">Total Talk Time</div>
      </div>
    </div>
  </div>

  <!-- ── Calls Table ───────────────────────────────────── -->
  <div class="card">
    <div class="card-header d-flex align-items-center gap-2 no-print">
      <span><?= number_format($total) ?> calls found</span>
      <input type="text" class="form-control form-control-sm ms-auto" style="max-width:200px"
             placeholder="Quick filter…" data-filter-table="callsTable">
      <button class="btn btn-sm btn-outline-success" id="bulkCopyWA" title="Copy selected for WhatsApp">
        <i class="bi bi-whatsapp"></i>
      </button>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" id="callsTable">
        <thead class="table-light">
          <tr>
            <th class="no-print"><input type="checkbox" id="selectAll"></th>
            <th>Contact</th>
            <th>Direction</th>
            <th>Outcome</th>
            <th>Duration</th>
            <th>Date/Time</th>
            <?php if ($isAll): ?><th>Agent</th><?php endif ?>
            <th class="no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($calls as $c): ?>
          <?php
          $waText = sprintf(
            "Call: %s | %s | %s | %s | %s",
            $c['contact_name'] ?: $c['phone_dialed'],
            ucfirst($c['direction']),
            $c['outcome'] ?: 'N/A',
            format_duration((int)$c['duration_seconds']),
            format_datetime($c['started_at'])
          );
          ?>
          <tr data-wa-text="<?= h($waText) ?>">
            <td class="no-print"><input type="checkbox" class="row-check"></td>
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
              <span class="text-success fw-semibold"><i class="bi bi-telephone-inbound-fill"></i> In</span>
              <?php else: ?>
              <span class="text-primary fw-semibold"><i class="bi bi-telephone-outbound-fill"></i> Out</span>
              <?php endif ?>
            </td>
            <td>
              <?php if ($c['outcome']): ?>
              <span class="badge" style="background:<?= h($c['outcome_color']) ?>"><?= h($c['outcome']) ?></span>
              <?php else: ?><span class="text-muted">—</span><?php endif ?>
            </td>
            <td><?= $c['duration_seconds'] ? format_duration((int)$c['duration_seconds']) : '—' ?></td>
            <td>
              <div><?= format_date($c['started_at'], 'd M Y') ?></div>
              <div class="text-muted small"><?= date('h:i A', strtotime($c['started_at'])) ?></div>
            </td>
            <?php if ($isAll): ?>
            <td class="text-muted small"><?= h($c['agent_name']) ?></td>
            <?php endif ?>
            <td class="no-print table-action-btns">
              <a href="<?= BASE_URL ?>/modules/calls/view.php?id=<?= $c['id'] ?>"
                 class="btn btn-sm btn-outline-primary" title="View">
                <i class="bi bi-eye"></i>
              </a>
              <?php if ($c['contact_id']): ?>
              <a href="<?= BASE_URL ?>/modules/workspace/index.php?contact_id=<?= $c['contact_id'] ?>"
                 class="btn btn-sm btn-outline-success" title="Call again">
                <i class="bi bi-telephone-plus"></i>
              </a>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($calls)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No calls found for this period</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
    <?php if ($p['pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center no-print">
      <small class="text-muted">Showing <?= $p['offset']+1 ?>–<?= min($p['offset']+$p['per_page'],$total) ?> of <?= $total ?></small>
      <?php
      $urlBase = '?' . http_build_query(array_filter(['date_from'=>$f_date_from,'date_to'=>$f_date_to,
        'agent'=>$f_agent,'direction'=>$f_direction,'outcome'=>$f_outcome,'q'=>$f_search]));
      echo pagination_html($p, $urlBase);
      ?>
    </div>
    <?php endif ?>
  </div>

</div>

<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
  document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
});
</script>

<?php require ROOT . '/partials/footer.php'; ?>
