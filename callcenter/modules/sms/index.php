<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'SMS Log';
$active_page = 'sms';

$uid   = current_user_id();
$isAll = in_array(current_role(), ['super_admin','senior_executive']);

// ── Filters ───────────────────────────────────────────────
$f_q      = clean($_GET['q']     ?? '');
$f_status = clean($_GET['status'] ?? '');
$f_date   = clean($_GET['date']   ?? '');
$f_agent  = (int)($_GET['agent']  ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;

$where  = ["1=1"];
$params = [];
if ($f_q)      { $where[] = "(c.name LIKE ? OR s.phone_to LIKE ? OR s.message LIKE ?)"; $params = array_merge($params, ["%$f_q%","%$f_q%","%$f_q%"]); }
if ($f_status) { $where[] = "s.status = ?"; $params[] = $f_status; }
if ($f_date)   { $where[] = "DATE(s.sent_at) = ?"; $params[] = $f_date; }
if (!$isAll)   { $where[] = "s.agent_id = ?"; $params[] = $uid; }
elseif ($f_agent) { $where[] = "s.agent_id = ?"; $params[] = $f_agent; }

$whereStr = 'WHERE ' . implode(' AND ', $where);
$total = (int) db_val("SELECT COUNT(*) FROM sms_log s LEFT JOIN contacts c ON c.id=s.contact_id $whereStr", $params);
$p     = paginate($total, $page, $per_page);

$sms_logs = db_rows(
    "SELECT s.*, c.id AS contact_id, c.name AS contact_name, u.name AS agent_name
     FROM sms_log s
     LEFT JOIN contacts c ON c.id = s.contact_id
     LEFT JOIN users u ON u.id = s.agent_id
     $whereStr
     ORDER BY s.sent_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$p['per_page'], $p['offset']])
);

$agents = $isAll ? db_rows("SELECT id, name FROM users WHERE status='active' AND role != 'viewer' ORDER BY name") : [];

// Stats
$today_count = (int) db_val(
    "SELECT COUNT(*) FROM sms_log WHERE " . (!$isAll ? "agent_id=? AND " : "") . "DATE(sent_at)=CURDATE()",
    !$isAll ? [$uid] : []
);

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-chat-dots-fill text-success"></i>
  <h5>SMS Log</h5>
  <div class="ms-auto d-flex gap-2">
    <button data-print class="btn btn-sm btn-outline-secondary no-print">
      <i class="bi bi-printer"></i>
    </button>
    <a href="<?= BASE_URL ?>/modules/sms/form.php" class="btn btn-sm btn-success">
      <i class="bi bi-send me-1"></i>Send SMS
    </a>
  </div>
</div>

<div class="page-body">

  <!-- Stats row -->
  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
      <div class="card text-center py-2">
        <div class="fs-5 fw-bold text-success"><?= number_format($today_count) ?></div>
        <div class="text-muted small">SMS Today</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center py-2">
        <div class="fs-5 fw-bold text-primary"><?= number_format($total) ?></div>
        <div class="text-muted small">Total (filtered)</div>
      </div>
    </div>
  </div>

  <!-- Filter -->
  <form method="get" class="card mb-3 no-print">
    <div class="card-body py-2 px-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
          <input type="text" class="form-control form-control-sm" name="q"
                 value="<?= h($f_q) ?>" placeholder="Contact, phone, message…">
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" name="status">
            <option value="">All Status</option>
            <option value="sent"    <?= $f_status==='sent'   ?'selected':'' ?>>Sent</option>
            <option value="failed"  <?= $f_status==='failed' ?'selected':'' ?>>Failed</option>
            <option value="pending" <?= $f_status==='pending'?'selected':'' ?>>Pending</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <input type="date" class="form-control form-control-sm" name="date"
                 value="<?= h($f_date) ?>">
        </div>
        <?php if ($isAll && !empty($agents)): ?>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" name="agent">
            <option value="">All Agents</option>
            <?php foreach ($agents as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $f_agent==$a['id']?'selected':'' ?>><?= h($a['name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <?php endif ?>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
      </div>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Contact</th>
            <th>Phone</th>
            <th>Message</th>
            <?php if ($isAll): ?><th>Agent</th><?php endif ?>
            <th>Status</th>
            <th>Sent At</th>
            <th class="no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sms_logs as $s): ?>
          <tr>
            <td>
              <?php if ($s['contact_id']): ?>
              <a href="<?= BASE_URL ?>/modules/contacts/view.php?id=<?= $s['contact_id'] ?>">
                <?= h($s['contact_name']) ?>
              </a>
              <?php else: ?>
              <span class="text-muted">Unknown</span>
              <?php endif ?>
            </td>
            <td class="text-muted small"><?= h($s['phone_to']) ?></td>
            <td class="text-muted small text-truncate" style="max-width:200px"
                title="<?= h($s['message']) ?>">
              <?= h($s['message']) ?>
            </td>
            <?php if ($isAll): ?>
            <td class="text-muted small"><?= h($s['agent_name'] ?? '—') ?></td>
            <?php endif ?>
            <td>
              <?php
              $sBadge = ['sent'=>'success','failed'=>'danger','pending'=>'warning'];
              $sc = $sBadge[$s['status']] ?? 'secondary';
              ?>
              <span class="badge bg-<?= $sc ?>"><?= ucfirst($s['status']) ?></span>
            </td>
            <td class="text-muted small"><?= format_datetime($s['sent_at'] ?? $s['created_at']) ?></td>
            <td class="no-print table-action-btns">
              <?php if ($s['contact_id']): ?>
              <a href="<?= BASE_URL ?>/modules/workspace/index.php?contact_id=<?= $s['contact_id'] ?>"
                 class="btn btn-sm btn-outline-primary" title="Open in workspace">
                <i class="bi bi-telephone-plus"></i>
              </a>
              <?php endif ?>
              <button class="btn btn-sm btn-outline-secondary"
                      data-copy-wa data-text="SMS to <?= h($s['phone_to']) ?>: <?= h($s['message']) ?>"
                      title="Copy message">
                <i class="bi bi-clipboard"></i>
              </button>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($sms_logs)): ?>
          <tr><td colspan="<?= $isAll ? 7 : 6 ?>" class="text-center text-muted py-4">No SMS messages found</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
    <?php if ($p['pages'] > 1): ?>
    <div class="card-footer no-print">
      <?php echo pagination_html($p, '?status=' . urlencode($f_status) . '&q=' . urlencode($f_q)); ?>
    </div>
    <?php endif ?>
  </div>

</div>

<?php require ROOT . '/partials/footer.php'; ?>
