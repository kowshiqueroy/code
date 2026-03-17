<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Staff Directory';
$active_page = 'staff';

$f_q        = clean($_GET['q']      ?? '');
$f_dept     = clean($_GET['dept']   ?? '');
$f_active   = $_GET['active'] ?? '1';
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 25;

$where  = ["sp.id IS NOT NULL"];
$params = [];
if ($f_q) {
    $q = '%' . $f_q . '%';
    $where[] = "(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR sp.employee_id LIKE ? OR sp.department LIKE ? OR sp.position LIKE ?)";
    $params  = array_merge($params, [$q,$q,$q,$q,$q,$q]);
}
if ($f_dept)      { $where[] = "sp.department = ?"; $params[] = $f_dept; }
if ($f_active !== '') { $where[] = "sp.is_active = ?"; $params[] = (int)$f_active; }

$whereStr = 'WHERE ' . implode(' AND ', $where);
$total = (int) db_val(
    "SELECT COUNT(*) FROM contacts c
     LEFT JOIN staff_profiles sp ON sp.contact_id = c.id
     $whereStr",
    $params
);
$p = paginate($total, $page, $per_page);

$staff = db_rows(
    "SELECT c.id, c.name, c.phone, c.email, c.status AS contact_status,
            sp.employee_id, sp.department, sp.position, sp.join_date, sp.exit_date, sp.is_active,
            sp.successor_contact_id,
            sc.name AS successor_name
     FROM contacts c
     LEFT JOIN staff_profiles sp ON sp.contact_id = c.id
     LEFT JOIN contacts sc ON sc.id = sp.successor_contact_id
     $whereStr
     ORDER BY sp.is_active DESC, sp.department ASC, c.name ASC
     LIMIT ? OFFSET ?",
    array_merge($params, [$p['per_page'], $p['offset']])
);

$departments = db_rows("SELECT DISTINCT department FROM staff_profiles WHERE department IS NOT NULL ORDER BY department");

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-people-fill text-primary"></i>
  <h5>Staff Directory</h5>
  <div class="ms-auto d-flex gap-2">
    <button data-print class="btn btn-sm btn-outline-secondary no-print">
      <i class="bi bi-printer"></i>
    </button>
    <?php if (can('create', 'staff')): ?>
    <a href="<?= BASE_URL ?>/modules/staff/form.php" class="btn btn-sm btn-primary">
      <i class="bi bi-person-plus me-1"></i>New Staff
    </a>
    <?php endif ?>
  </div>
</div>

<div class="page-body">

  <form method="get" class="card mb-3 no-print">
    <div class="card-body py-2 px-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
          <input type="text" class="form-control form-control-sm" name="q"
                 value="<?= h($f_q) ?>" placeholder="Name, phone, ID, position…" autofocus>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" name="dept">
            <option value="">All Depts</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= h($d['department']) ?>" <?= $f_dept===$d['department']?'selected':'' ?>>
              <?= h($d['department']) ?>
            </option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" name="active">
            <option value="">All</option>
            <option value="1" <?= $f_active==='1'?'selected':'' ?>>Active Only</option>
            <option value="0" <?= $f_active==='0'?'selected':'' ?>>Former Only</option>
          </select>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm">Search</button>
          <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
        <div class="col-auto ms-auto text-muted small"><?= number_format($total) ?> staff</div>
      </div>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Name</th>
            <th>Employee ID</th>
            <th>Department</th>
            <th>Position</th>
            <th>Phone</th>
            <th>Joined</th>
            <th>Status</th>
            <th class="no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($staff as $s): ?>
          <tr class="<?= !$s['is_active'] ? 'table-secondary' : '' ?>">
            <td>
              <a href="<?= BASE_URL ?>/modules/staff/view.php?id=<?= $s['id'] ?>" class="fw-semibold">
                <?= h($s['name']) ?>
              </a>
              <?php if (!$s['is_active'] && $s['successor_name']): ?>
              <div class="text-muted small">
                <i class="bi bi-arrow-right-circle me-1"></i>
                <a href="<?= BASE_URL ?>/modules/staff/view.php?id=<?= $s['successor_contact_id'] ?>">
                  <?= h($s['successor_name']) ?>
                </a>
              </div>
              <?php endif ?>
            </td>
            <td class="text-muted small"><?= h($s['employee_id'] ?? '—') ?></td>
            <td class="text-muted small"><?= h($s['department'] ?? '—') ?></td>
            <td class="text-muted small"><?= h($s['position'] ?? '—') ?></td>
            <td class="text-muted small"><?= h($s['phone']) ?></td>
            <td class="text-muted small"><?= $s['join_date'] ? format_date($s['join_date']) : '—' ?></td>
            <td>
              <?php if ($s['is_active']): ?>
              <span class="badge bg-success">Active</span>
              <?php else: ?>
              <span class="badge bg-secondary">Former</span>
              <?php if ($s['exit_date']): ?>
              <div class="text-muted" style="font-size:.65rem"><?= format_date($s['exit_date']) ?></div>
              <?php endif ?>
              <?php endif ?>
            </td>
            <td class="no-print table-action-btns">
              <a href="<?= BASE_URL ?>/modules/workspace/index.php?contact_id=<?= $s['id'] ?>"
                 class="btn btn-sm btn-outline-primary" title="Call">
                <i class="bi bi-telephone-plus"></i>
              </a>
              <a href="<?= BASE_URL ?>/modules/staff/view.php?id=<?= $s['id'] ?>"
                 class="btn btn-sm btn-outline-secondary" title="View">
                <i class="bi bi-eye"></i>
              </a>
              <?php if (can('edit', 'staff')): ?>
              <a href="<?= BASE_URL ?>/modules/staff/form.php?id=<?= $s['id'] ?>"
                 class="btn btn-sm btn-outline-warning" title="Edit">
                <i class="bi bi-pencil"></i>
              </a>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($staff)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No staff found</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
    <?php if ($p['pages'] > 1): ?>
    <div class="card-footer no-print">
      <?php echo pagination_html($p, '?q=' . urlencode($f_q) . '&dept=' . urlencode($f_dept) . '&active=' . urlencode($f_active)); ?>
    </div>
    <?php endif ?>
  </div>

</div>

<?php require ROOT . '/partials/footer.php'; ?>
