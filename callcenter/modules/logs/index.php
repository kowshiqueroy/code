<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_role('super_admin');

$page_title  = 'Audit Logs';
$active_page = 'logs';

// Filters
$f_action  = clean($_GET['action']  ?? '');
$f_module  = clean($_GET['module']  ?? '');
$f_user    = (int)($_GET['user_id'] ?? 0);
$f_date    = clean($_GET['date']    ?? '');
$f_q       = clean($_GET['q']       ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 50;

$where  = ['1=1'];
$params = [];

if ($f_action) { $where[] = 'al.action = ?';               $params[] = $f_action; }
if ($f_module) { $where[] = 'al.module = ?';               $params[] = $f_module; }
if ($f_user)   { $where[] = 'al.user_id = ?';              $params[] = $f_user; }
if ($f_date)   { $where[] = 'DATE(al.created_at) = ?';     $params[] = $f_date; }
if ($f_q)      { $where[] = 'al.description LIKE ?';       $params[] = "%$f_q%"; }

$whereStr = implode(' AND ', $where);
$total    = (int) db_val("SELECT COUNT(*) FROM audit_logs al WHERE $whereStr", $params);
$p        = paginate($total, $page, $per_page);

$logs = db_rows(
    "SELECT al.*, u.name AS user_name, u.role AS user_role
     FROM audit_logs al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE $whereStr
     ORDER BY al.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$p['per_page'], $p['offset']])
);

// Filter options
$distinct_actions = db_rows("SELECT DISTINCT action FROM audit_logs ORDER BY action");
$distinct_modules = db_rows("SELECT DISTINCT module FROM audit_logs ORDER BY module");
$all_users        = db_rows("SELECT id, name FROM users WHERE status='active' ORDER BY name");

// Stats summary (last 24h)
$stats_24h = db_row(
    "SELECT COUNT(*) AS total,
            COUNT(DISTINCT user_id) AS unique_users,
            COUNT(DISTINCT module) AS modules
     FROM audit_logs
     WHERE created_at >= NOW() - INTERVAL 1 DAY"
);

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-journal-text text-primary me-1"></i>
  <h5 class="mb-0">Audit Logs</h5>
  <div class="ms-auto no-print">
    <button data-print class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
  </div>
</div>

<div class="page-body">

  <!-- 24h stats -->
  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
      <div class="card text-center"><div class="card-body py-2">
        <div class="fs-5 fw-bold"><?= number_format($stats_24h['total']) ?></div>
        <div class="text-muted small">Events (24h)</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center"><div class="card-body py-2">
        <div class="fs-5 fw-bold"><?= $stats_24h['unique_users'] ?></div>
        <div class="text-muted small">Users Active</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center"><div class="card-body py-2">
        <div class="fs-5 fw-bold"><?= number_format($total) ?></div>
        <div class="text-muted small">Total Records</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center"><div class="card-body py-2">
        <div class="fs-5 fw-bold"><?= $stats_24h['modules'] ?></div>
        <div class="text-muted small">Modules Used</div>
      </div></div>
    </div>
  </div>

  <!-- Filters -->
  <form method="get" class="card mb-3 no-print">
    <div class="card-body py-2 px-3">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <input type="text" name="q" class="form-control form-control-sm"
                 value="<?= h($f_q) ?>" placeholder="Search description…">
        </div>
        <div class="col-md-2">
          <select name="action" class="form-select form-select-sm">
            <option value="">All Actions</option>
            <?php foreach ($distinct_actions as $da): ?>
            <option value="<?= h($da['action']) ?>" <?= $f_action===$da['action']?'selected':'' ?>>
              <?= h($da['action']) ?>
            </option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="module" class="form-select form-select-sm">
            <option value="">All Modules</option>
            <?php foreach ($distinct_modules as $dm): ?>
            <option value="<?= h($dm['module']) ?>" <?= $f_module===$dm['module']?'selected':'' ?>>
              <?= h($dm['module']) ?>
            </option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="user_id" class="form-select form-select-sm">
            <option value="">All Users</option>
            <?php foreach ($all_users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $f_user==$u['id']?'selected':'' ?>>
              <?= h($u['name']) ?>
            </option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="col-md-2">
          <input type="date" name="date" class="form-control form-control-sm" value="<?= h($f_date) ?>">
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
        <div class="col-auto ms-auto text-muted small"><?= number_format($total) ?> records</div>
      </div>
    </div>
  </form>

  <!-- Logs table -->
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Time</th>
            <th>User</th>
            <th>Action</th>
            <th>Module</th>
            <th>Record ID</th>
            <th>Description</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log):
            $actionColors = [
              'login'  => 'success',  'logout' => 'secondary',
              'create' => 'primary',  'update' => 'warning',
              'delete' => 'danger',   'view'   => 'info',
            ];
            // match any action prefix
            $ac = 'secondary';
            foreach ($actionColors as $prefix => $color) {
                if (str_starts_with($log['action'], $prefix)) { $ac = $color; break; }
            }
          ?>
          <tr>
            <td class="text-muted small text-nowrap"><?= format_datetime($log['created_at']) ?></td>
            <td>
              <div class="small"><?= h($log['user_name'] ?? 'System') ?></div>
              <?php if ($log['user_role']): ?>
              <div style="font-size:.65rem" class="text-muted"><?= str_replace('_',' ', $log['user_role']) ?></div>
              <?php endif ?>
            </td>
            <td><span class="badge bg-<?= $ac ?>"><?= h($log['action']) ?></span></td>
            <td class="text-muted small"><?= h($log['module']) ?></td>
            <td class="text-muted small"><?= $log['record_id'] ?: '—' ?></td>
            <td class="small"><?= h(truncate($log['description'] ?? '', 80)) ?></td>
            <td class="text-muted small"><?= h($log['ip_address'] ?? '—') ?></td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($logs)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No audit logs found.</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
    <?php if ($p['pages'] > 1): ?>
    <div class="card-footer no-print">
      <?php echo pagination_html($p, '?action=' . urlencode($f_action) . '&module=' . urlencode($f_module) . '&user_id=' . $f_user . '&date=' . urlencode($f_date) . '&q=' . urlencode($f_q)); ?>
    </div>
    <?php endif ?>
  </div>

</div>

<?php require ROOT . '/partials/footer.php'; ?>
