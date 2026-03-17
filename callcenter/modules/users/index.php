<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_role('super_admin');

$page_title  = 'User Management';
$active_page = 'users';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_csrf();
    $del_id = (int)($_POST['user_id'] ?? 0);
    if ($del_id === current_user_id()) {
        flash_error('You cannot delete your own account.');
    } else {
        db_exec("UPDATE users SET status='inactive' WHERE id=?", [$del_id]);
        audit_log('deactivate_user', 'users', $del_id, 'User deactivated');
        flash_success('User deactivated.');
    }
    redirect(BASE_URL . '/modules/users/index.php');
}

// Handle reactivate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reactivate') {
    require_csrf();
    $uid2 = (int)($_POST['user_id'] ?? 0);
    db_exec("UPDATE users SET status='active' WHERE id=?", [$uid2]);
    audit_log('reactivate_user', 'users', $uid2, 'User reactivated');
    flash_success('User reactivated.');
    redirect(BASE_URL . '/modules/users/index.php');
}

// Filters
$f_q      = clean($_GET['q']      ?? '');
$f_role   = clean($_GET['role']   ?? '');
$f_active = clean($_GET['active'] ?? '1');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;

$where  = [];
$params = [];
if ($f_q)    { $where[] = "(name LIKE ? OR email LIKE ?)"; $params[] = "%$f_q%"; $params[] = "%$f_q%"; }
if ($f_role) { $where[] = "role = ?"; $params[] = $f_role; }
if ($f_active !== '') { $where[] = "status = ?"; $params[] = ($f_active === '0' ? 'inactive' : 'active'); }

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total    = (int) db_val("SELECT COUNT(*) FROM users $whereStr", $params);
$p        = paginate($total, $page, $per_page);

$users = db_rows(
    "SELECT u.*,
            (SELECT COUNT(*) FROM calls cl WHERE cl.agent_id=u.id AND DATE(cl.created_at)=CURDATE()) AS calls_today,
            (SELECT MAX(cl2.created_at) FROM calls cl2 WHERE cl2.agent_id=u.id) AS last_call
     FROM users u
     $whereStr
     ORDER BY u.role DESC, u.name ASC
     LIMIT ? OFFSET ?",
    array_merge($params, [$p['per_page'], $p['offset']])
);

$role_counts = db_rows("SELECT role, COUNT(*) AS cnt, SUM(status='active') AS active FROM users GROUP BY role ORDER BY FIELD(role,'super_admin','senior_executive','executive','viewer')");

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-people-fill text-primary"></i>
  <h5>Users</h5>
  <div class="ms-auto d-flex gap-2">
    <a href="<?= BASE_URL ?>/modules/users/form.php" class="btn btn-sm btn-primary">
      <i class="bi bi-person-plus me-1"></i>Add User
    </a>
  </div>
</div>

<div class="page-body">

  <?php foreach (get_flashes() as $f): ?>
  <div class="alert alert-<?= $f['type']==='error'?'danger':'success' ?> alert-dismissible fade show">
    <?= h($f['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endforeach ?>

  <!-- Role summary cards -->
  <div class="row g-2 mb-3">
    <?php
    $roleColors = ['super_admin'=>'danger','senior_executive'=>'warning','executive'=>'primary','viewer'=>'secondary'];
    $roleLabels = ['super_admin'=>'Super Admin','senior_executive'=>'Senior Exec','executive'=>'Executive','viewer'=>'Viewer'];
    foreach ($role_counts as $rc):
      $color = $roleColors[$rc['role']] ?? 'secondary';
    ?>
    <div class="col-6 col-md-3">
      <div class="card border-<?= $color ?>"><div class="card-body py-2 d-flex align-items-center gap-2">
        <div>
          <div class="fw-bold"><?= $rc['cnt'] ?> <small class="text-muted">/ <?= $rc['active'] ?> active</small></div>
          <div class="text-muted small"><?= $roleLabels[$rc['role']] ?? ucfirst($rc['role']) ?></div>
        </div>
      </div></div>
    </div>
    <?php endforeach ?>
  </div>

  <!-- Filters -->
  <form method="get" class="card mb-3 no-print">
    <div class="card-body py-2 px-3">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <input type="text" name="q" class="form-control form-control-sm"
                 value="<?= h($f_q) ?>" placeholder="Search name or email…">
        </div>
        <div class="col-md-2">
          <select name="role" class="form-select form-select-sm">
            <option value="">All Roles</option>
            <option value="super_admin"      <?= $f_role==='super_admin'      ?'selected':'' ?>>Super Admin</option>
            <option value="senior_executive" <?= $f_role==='senior_executive' ?'selected':'' ?>>Senior Executive</option>
            <option value="executive"        <?= $f_role==='executive'        ?'selected':'' ?>>Executive</option>
            <option value="viewer"           <?= $f_role==='viewer'           ?'selected':'' ?>>Viewer</option>
          </select>
        </div>
        <div class="col-md-2">
          <select name="active" class="form-select form-select-sm">
            <option value="1" <?= $f_active==='1'?'selected':'' ?>>Active</option>
            <option value="0" <?= $f_active==='0'?'selected':'' ?>>Inactive</option>
            <option value=""  <?= $f_active==='' ?'selected':'' ?>>All</option>
          </select>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
        <div class="col-auto ms-auto text-muted small"><?= $total ?> users</div>
      </div>
    </div>
  </form>

  <!-- Users table -->
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Name / Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Calls Today</th>
            <th>Last Call</th>
            <th>Created</th>
            <th class="no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <?php
          $roleColors2 = ['super_admin'=>'danger','senior_executive'=>'warning','executive'=>'primary','viewer'=>'secondary'];
          $rc2 = $roleColors2[$u['role']] ?? 'secondary';
          ?>
          <tr class="<?= $u['status'] !== 'active' ? 'table-secondary text-muted' : '' ?>">
            <td>
              <div class="fw-semibold"><?= h($u['name']) ?></div>
              <div class="text-muted small"><?= h($u['email']) ?></div>
            </td>
            <td><span class="badge bg-<?= $rc2 ?>"><?= str_replace('_', ' ', ucfirst($u['role'])) ?></span></td>
            <td>
              <?php if (($u['status'] === 'active')): ?>
              <span class="badge bg-success">Active</span>
              <?php else: ?>
              <span class="badge bg-secondary">Inactive</span>
              <?php endif ?>
            </td>
            <td>
              <?php if ($u['calls_today'] > 0): ?>
              <span class="badge bg-primary"><?= $u['calls_today'] ?></span>
              <?php else: ?>
              <span class="text-muted">—</span>
              <?php endif ?>
            </td>
            <td class="text-muted small">
              <?= $u['last_call'] ? time_ago($u['last_call']) : '—' ?>
            </td>
            <td class="text-muted small"><?= format_date($u['created_at']) ?></td>
            <td class="no-print table-action-btns">
              <a href="<?= BASE_URL ?>/modules/users/form.php?id=<?= $u['id'] ?>"
                 class="btn btn-sm btn-outline-warning" title="Edit">
                <i class="bi bi-pencil"></i>
              </a>
              <?php if ($u['id'] !== current_user_id()): ?>
              <?php if (($u['status'] === 'active')): ?>
              <form method="post" class="d-inline"
                    data-confirm="Deactivate <?= h($u['name']) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Deactivate">
                  <i class="bi bi-person-x"></i>
                </button>
              </form>
              <?php else: ?>
              <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reactivate">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-success" title="Reactivate">
                  <i class="bi bi-person-check"></i>
                </button>
              </form>
              <?php endif ?>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($users)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No users found.</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
    <?php if ($p['pages'] > 1): ?>
    <div class="card-footer no-print">
      <?php echo pagination_html($p, '?role=' . urlencode($f_role) . '&q=' . urlencode($f_q) . '&active=' . urlencode($f_active)); ?>
    </div>
    <?php endif ?>
  </div>

</div>

<?php require ROOT . '/partials/footer.php'; ?>
