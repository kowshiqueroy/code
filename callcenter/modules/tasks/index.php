<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Tasks';
$active_page = 'tasks';

$uid   = current_user_id();
$isAll = in_array(current_role(), ['super_admin','senior_executive']);

// ── POST: Update status ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    require_csrf();
    $task_id   = (int)($_POST['task_id'] ?? 0);
    $new_status = clean($_POST['new_status'] ?? '');
    $valid = ['pending','in_progress','completed','cancelled'];

    if ($task_id && in_array($new_status, $valid)) {
        $completed_at = $new_status === 'completed' ? 'NOW()' : 'NULL';
        db_exec(
            "UPDATE tasks SET status=?, completed_at=$completed_at WHERE id=?",
            [$new_status, $task_id]
        );
        audit_log('update_task', 'tasks', $task_id, "Status: $new_status");
    }
    redirect(BASE_URL . '/modules/tasks/index.php?' . http_build_query(array_filter([
        'status' => $_GET['status'] ?? '', 'priority' => $_GET['priority'] ?? ''
    ])));
}

// ── Filters ───────────────────────────────────────────────
$f_status   = clean($_GET['status']   ?? 'pending');
$f_priority = clean($_GET['priority'] ?? '');
$f_type     = (int)($_GET['type']     ?? 0);
$f_agent    = (int)($_GET['agent']    ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 25;

$where  = ["1=1"];
$params = [];
if ($f_status)   { $where[] = "t.status = ?"; $params[] = $f_status; }
if ($f_priority) { $where[] = "t.priority = ?"; $params[] = $f_priority; }
if ($f_type)     { $where[] = "t.type_id = ?"; $params[] = $f_type; }
if (!$isAll)     {
    $where[] = "(t.assigned_to = ? OR t.assigned_by = ?)";
    $params = array_merge($params, [$uid, $uid]);
} elseif ($f_agent) {
    $where[] = "t.assigned_to = ?"; $params[] = $f_agent;
}

$whereStr = 'WHERE ' . implode(' AND ', $where);
$total = (int) db_val("SELECT COUNT(*) FROM tasks t $whereStr", $params);
$p     = paginate($total, $page, $per_page);

$tasks = db_rows(
    "SELECT t.*, tt.name AS type_name, tt.icon AS type_icon, tt.color AS type_color,
            c.id AS contact_id, c.name AS contact_name,
            ua.name AS assigned_name, ub.name AS assigned_by_name
     FROM tasks t
     LEFT JOIN task_types tt ON tt.id = t.type_id
     LEFT JOIN contacts c ON c.id = t.contact_id
     LEFT JOIN users ua ON ua.id = t.assigned_to
     LEFT JOIN users ub ON ub.id = t.assigned_by
     $whereStr
     ORDER BY
       CASE t.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
       t.due_date ASC,
       t.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$p['per_page'], $p['offset']])
);

$task_types = db_rows("SELECT * FROM task_types WHERE is_active=1 ORDER BY sort_order");
$agents = $isAll ? db_rows("SELECT id, name FROM users WHERE status='active' AND role != 'viewer' ORDER BY name") : [];

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-check2-square text-primary"></i>
  <h5>Tasks</h5>
  <div class="ms-auto d-flex gap-2">
    <?php if (can('create', 'tasks')): ?>
    <a href="<?= BASE_URL ?>/modules/tasks/form.php" class="btn btn-sm btn-primary">
      <i class="bi bi-plus-lg me-1"></i>New Task
    </a>
    <?php endif ?>
    <a href="<?= BASE_URL ?>/modules/tasks/self_assign.php" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-person-check me-1"></i>Self Assign
    </a>
  </div>
</div>

<div class="page-body">

  <!-- Status tabs -->
  <div class="d-flex gap-2 mb-3 flex-wrap no-print">
    <?php foreach (['' => 'secondary', 'pending' => 'warning', 'in_progress' => 'primary', 'completed' => 'success', 'cancelled' => 'secondary'] as $s => $cls): ?>
    <a href="?status=<?= $s ?>&priority=<?= h($f_priority) ?>"
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

  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Task</th>
            <th>Type</th>
            <th>Contact</th>
            <th>Priority</th>
            <th>Due Date</th>
            <th>Assigned To</th>
            <th>Status</th>
            <th class="no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tasks as $t): ?>
          <?php
          $isOverdue = $t['status'] === 'pending' && $t['due_date'] && strtotime($t['due_date']) < time();
          $priorityBadge = ['urgent'=>'danger','high'=>'warning','medium'=>'primary','low'=>'secondary'];
          $pc = $priorityBadge[$t['priority']] ?? 'secondary';
          ?>
          <tr class="<?= $isOverdue ? 'table-danger' : '' ?>">
            <td>
              <div class="fw-semibold small">
                <?php if ($isOverdue): ?><span class="text-danger me-1">OVERDUE</span><?php endif ?>
                <?= h($t['title']) ?>
              </div>
              <?php if ($t['description']): ?>
              <div class="text-muted small text-truncate" style="max-width:200px"><?= h($t['description']) ?></div>
              <?php endif ?>
            </td>
            <td>
              <?php if ($t['type_name']): ?>
              <span class="badge" style="background:<?= h($t['type_color'] ?? '#6c757d') ?>;font-size:.65rem">
                <i class="bi bi-<?= h($t['type_icon'] ?? 'check2') ?> me-1"></i><?= h($t['type_name']) ?>
              </span>
              <?php endif ?>
            </td>
            <td class="text-muted small">
              <?php if ($t['contact_id']): ?>
              <a href="<?= BASE_URL ?>/modules/contacts/view.php?id=<?= $t['contact_id'] ?>">
                <?= h($t['contact_name']) ?>
              </a>
              <?php else: ?>—<?php endif ?>
            </td>
            <td><span class="badge bg-<?= $pc ?>"><?= ucfirst($t['priority'] ?? 'medium') ?></span></td>
            <td class="text-muted small"><?= $t['due_date'] ? format_date($t['due_date']) : '—' ?></td>
            <td class="text-muted small"><?= h($t['assigned_name'] ?? '—') ?></td>
            <td><?= status_badge($t['status']) ?></td>
            <td class="no-print table-action-btns">
              <?php if ($t['status'] === 'pending' || $t['status'] === 'in_progress'): ?>
              <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                <input type="hidden" name="new_status" value="<?= $t['status'] === 'pending' ? 'in_progress' : 'completed' ?>">
                <button type="submit" class="btn btn-sm btn-outline-success" title="<?= $t['status'] === 'pending' ? 'Start' : 'Complete' ?>">
                  <i class="bi bi-<?= $t['status'] === 'pending' ? 'play' : 'check2' ?>"></i>
                </button>
              </form>
              <?php endif ?>
              <?php if (can('edit', 'tasks')): ?>
              <a href="<?= BASE_URL ?>/modules/tasks/form.php?id=<?= $t['id'] ?>"
                 class="btn btn-sm btn-outline-warning" title="Edit">
                <i class="bi bi-pencil"></i>
              </a>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($tasks)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No tasks found</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
    <?php if ($p['pages'] > 1): ?>
    <div class="card-footer no-print">
      <?php echo pagination_html($p, '?status=' . urlencode($f_status) . '&priority=' . urlencode($f_priority)); ?>
    </div>
    <?php endif ?>
  </div>

</div>

<?php require ROOT . '/partials/footer.php'; ?>
