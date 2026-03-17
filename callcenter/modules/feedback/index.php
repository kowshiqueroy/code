<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Feedback Threads';
$active_page = 'feedback';

$uid   = current_user_id();
$isAll = in_array(current_role(), ['super_admin','senior_executive']);

// ── Filters ───────────────────────────────────────────────
$f_status   = clean($_GET['status']   ?? 'open');
$f_priority = clean($_GET['priority'] ?? '');
$f_q        = clean($_GET['q']        ?? '');
$f_agent    = (int)($_GET['agent']    ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 20;

$where  = ["1=1"];
$params = [];
if ($f_status)   { $where[] = "ft.status = ?"; $params[] = $f_status; }
if ($f_priority) { $where[] = "ft.priority = ?"; $params[] = $f_priority; }
if ($f_q) {
    $where[] = "(ft.title LIKE ? OR ft.problem_description LIKE ? OR c.name LIKE ?)";
    $params  = array_merge($params, ["%$f_q%", "%$f_q%", "%$f_q%"]);
}
if (!$isAll) {
    $where[] = "(ft.created_by = ? OR ft.assigned_to = ?)";
    $params  = array_merge($params, [$uid, $uid]);
}
elseif ($f_agent) {
    $where[] = "(ft.created_by = ? OR ft.assigned_to = ?)";
    $params  = array_merge($params, [$f_agent, $f_agent]);
}

$whereStr = 'WHERE ' . implode(' AND ', $where);
$total = (int) db_val(
    "SELECT COUNT(*) FROM feedback_threads ft LEFT JOIN contacts c ON c.id=ft.contact_id $whereStr",
    $params
);
$p = paginate($total, $page, $per_page);

$threads = db_rows(
    "SELECT ft.id, ft.title, ft.status, ft.priority, ft.created_at, ft.resolved_at,
            c.id AS contact_id, c.name AS contact_name,
            u.name AS creator_name, ua.name AS assigned_name,
            (SELECT COUNT(*) FROM feedback_entries fe WHERE fe.thread_id=ft.id) AS entry_count,
            (SELECT MAX(fe2.created_at) FROM feedback_entries fe2 WHERE fe2.thread_id=ft.id) AS last_activity
     FROM feedback_threads ft
     LEFT JOIN contacts c ON c.id = ft.contact_id
     LEFT JOIN users u ON u.id = ft.created_by
     LEFT JOIN users ua ON ua.id = ft.assigned_to
     $whereStr
     ORDER BY
       CASE ft.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
       ft.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$p['per_page'], $p['offset']])
);

$agents = $isAll ? db_rows("SELECT id, name FROM users WHERE status='active' AND role != 'viewer' ORDER BY name") : [];

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-chat-square-text-fill text-danger"></i>
  <h5>Feedback Threads</h5>
  <div class="ms-auto d-flex gap-2">
    <button data-print class="btn btn-sm btn-outline-secondary no-print">
      <i class="bi bi-printer"></i>
    </button>
    <a href="<?= BASE_URL ?>/modules/feedback/form.php" class="btn btn-sm btn-danger">
      <i class="bi bi-plus-lg me-1"></i>New Thread
    </a>
  </div>
</div>

<div class="page-body">

  <!-- Status filter tabs -->
  <div class="d-flex gap-2 mb-3 flex-wrap no-print">
    <?php foreach (['open'=>'danger','in_progress'=>'warning','resolved'=>'success','closed'=>'secondary',''=>'primary'] as $s => $cls): ?>
    <a href="?status=<?= $s ?>&priority=<?= h($f_priority) ?>&q=<?= urlencode($f_q) ?>"
       class="btn btn-sm <?= $f_status===$s ? "btn-$cls" : "btn-outline-$cls" ?>">
      <?= $s ? ucwords(str_replace('_',' ',$s)) : 'All' ?>
    </a>
    <?php endforeach ?>

    <?php if ($isAll && !empty($agents)): ?>
    <form method="get" class="d-flex gap-1 ms-auto">
      <input type="hidden" name="status" value="<?= h($f_status) ?>">
      <input type="hidden" name="priority" value="<?= h($f_priority) ?>">
      <select class="form-select form-select-sm" name="agent" onchange="this.form.submit()">
        <option value="">All Agents</option>
        <?php foreach ($agents as $a): ?>
        <option value="<?= $a['id'] ?>" <?= $f_agent==$a['id']?'selected':'' ?>><?= h($a['name']) ?></option>
        <?php endforeach ?>
      </select>
    </form>
    <?php endif ?>
  </div>

  <!-- Priority filter -->
  <div class="d-flex gap-2 mb-3 flex-wrap no-print">
    <?php foreach (['' => 'secondary', 'urgent' => 'danger', 'high' => 'warning', 'medium' => 'primary', 'low' => 'light text-dark'] as $pr => $cls): ?>
    <a href="?status=<?= h($f_status) ?>&priority=<?= $pr ?>&q=<?= urlencode($f_q) ?>"
       class="badge <?= $f_priority===$pr ? "bg-$cls border border-dark" : "bg-$cls" ?> text-decoration-none"
       style="font-size:.75rem;cursor:pointer">
      <?= $pr ? ucfirst($pr) : 'All Priority' ?>
    </a>
    <?php endforeach ?>
  </div>

  <div class="card">
    <div class="card-header d-flex align-items-center gap-2 no-print">
      <form method="get" class="d-flex gap-2 flex-grow-1">
        <input type="hidden" name="status" value="<?= h($f_status) ?>">
        <input type="hidden" name="priority" value="<?= h($f_priority) ?>">
        <input type="text" class="form-control form-control-sm" name="q"
               value="<?= h($f_q) ?>" placeholder="Search threads…">
        <button type="submit" class="btn btn-sm btn-primary">Search</button>
        <?php if ($f_q): ?><a href="?status=<?= h($f_status) ?>" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif ?>
      </form>
      <span class="text-muted small"><?= number_format($total) ?> threads</span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Thread</th>
            <th>Contact</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Assigned To</th>
            <th>Entries</th>
            <th>Last Activity</th>
            <th class="no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($threads as $ft): ?>
          <?php
          $priorityBadge = ['urgent'=>'danger','high'=>'warning','medium'=>'primary','low'=>'secondary'];
          $pc = $priorityBadge[$ft['priority']] ?? 'secondary';
          $statusBadge = ['open'=>'danger','in_progress'=>'warning','resolved'=>'success','closed'=>'secondary'];
          $sc = $statusBadge[$ft['status']] ?? 'secondary';
          ?>
          <tr>
            <td>
              <a href="<?= BASE_URL ?>/modules/feedback/thread.php?id=<?= $ft['id'] ?>"
                 class="fw-semibold text-decoration-none">
                <?= h($ft['title']) ?>
              </a>
              <div class="text-muted small">by <?= h($ft['creator_name'] ?? '—') ?></div>
            </td>
            <td>
              <?php if ($ft['contact_id']): ?>
              <a href="<?= BASE_URL ?>/modules/contacts/view.php?id=<?= $ft['contact_id'] ?>">
                <?= h($ft['contact_name']) ?>
              </a>
              <?php else: ?>—<?php endif ?>
            </td>
            <td><span class="badge bg-<?= $pc ?>"><?= ucfirst($ft['priority']) ?></span></td>
            <td><span class="badge bg-<?= $sc ?>"><?= ucwords(str_replace('_',' ',$ft['status'])) ?></span></td>
            <td class="text-muted small"><?= h($ft['assigned_name'] ?? '—') ?></td>
            <td class="text-muted small"><?= $ft['entry_count'] ?></td>
            <td class="text-muted small">
              <?= $ft['last_activity'] ? time_ago($ft['last_activity']) : time_ago($ft['created_at']) ?>
            </td>
            <td class="no-print table-action-btns">
              <a href="<?= BASE_URL ?>/modules/feedback/thread.php?id=<?= $ft['id'] ?>"
                 class="btn btn-sm btn-outline-primary" title="View thread">
                <i class="bi bi-chat-square-text"></i>
              </a>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($threads)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No feedback threads found</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
    <?php if ($p['pages'] > 1): ?>
    <div class="card-footer no-print">
      <?php echo pagination_html($p, '?status=' . urlencode($f_status) . '&priority=' . urlencode($f_priority) . '&q=' . urlencode($f_q)); ?>
    </div>
    <?php endif ?>
  </div>

</div>

<?php require ROOT . '/partials/footer.php'; ?>
