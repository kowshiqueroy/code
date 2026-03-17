<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Call Scripts';
$active_page = 'scripts';

// ── POST: Toggle active ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    require_role('senior_executive');
    require_csrf();
    $sid = (int)($_POST['script_id'] ?? 0);
    db_exec("UPDATE scripts SET is_default = NOT is_default WHERE id=?", [$sid]);
    audit_log('toggle_script', 'scripts', $sid);
    redirect(BASE_URL . '/modules/scripts/index.php');
}

// ── POST: Delete ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_role('senior_executive');
    require_csrf();
    $sid = (int)($_POST['script_id'] ?? 0);
    db_exec("DELETE FROM scripts WHERE id=?", [$sid]);
    audit_log('delete_script', 'scripts', $sid);
    flash_success('Script deleted.');
    redirect(BASE_URL . '/modules/scripts/index.php');
}

$f_q      = clean($_GET['q']      ?? '');
$f_active = $_GET['active'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

$where  = [];
$params = [];
if ($f_q)       { $where[] = "(s.name LIKE ? OR s.content LIKE ?)"; $params = array_merge($params, ["%$f_q%", "%$f_q%"]); }
if ($f_active !== '') { $where[] = "s.is_default = ?"; $params[] = (int)$f_active; }

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total = (int) db_val("SELECT COUNT(*) FROM scripts s $whereStr", $params);
$p     = paginate($total, $page, $per_page);

$scripts = db_rows(
    "SELECT s.*, u.name AS creator_name,
            c.id AS campaign_id, c.name AS campaign_name
     FROM scripts s
     LEFT JOIN users u ON u.id = s.created_by
     LEFT JOIN campaigns c ON c.id = s.campaign_id
     $whereStr
     ORDER BY s.is_default DESC, s.name ASC
     LIMIT ? OFFSET ?",
    array_merge($params, [$p['per_page'], $p['offset']])
);

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-file-text-fill text-info"></i>
  <h5>Call Scripts</h5>
  <div class="ms-auto d-flex gap-2">
    <?php if (can('create', 'scripts')): ?>
    <a href="<?= BASE_URL ?>/modules/scripts/form.php" class="btn btn-sm btn-info text-white">
      <i class="bi bi-plus-lg me-1"></i>New Script
    </a>
    <?php endif ?>
  </div>
</div>

<div class="page-body">

  <form method="get" class="card mb-3 no-print">
    <div class="card-body py-2 px-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-5">
          <input type="text" class="form-control form-control-sm" name="q"
                 value="<?= h($f_q) ?>" placeholder="Search scripts…" autofocus>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" name="active">
            <option value="">All</option>
            <option value="1" <?= $f_active==='1'?'selected':'' ?>>Active Only</option>
            <option value="0" <?= $f_active==='0'?'selected':'' ?>>Inactive Only</option>
          </select>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
        <div class="col-auto ms-auto text-muted small"><?= number_format($total) ?> scripts</div>
      </div>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Script Name</th>
            <th>Campaign</th>
            <th>Content Preview</th>
            <th>Created By</th>
            <th>Status</th>
            <th class="no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($scripts as $s): ?>
          <tr>
            <td>
              <a href="<?= BASE_URL ?>/modules/scripts/form.php?id=<?= $s['id'] ?>&view=1"
                 class="fw-semibold text-decoration-none">
                <?= h($s['name']) ?>
              </a>
              <?php if ($s['is_default']): ?>
              <span class="badge bg-warning text-dark ms-1" style="font-size:.6rem">DEFAULT</span>
              <?php endif ?>
            </td>
            <td class="text-muted small">
              <?php if ($s['campaign_id']): ?>
              <a href="<?= BASE_URL ?>/modules/campaigns/form.php?id=<?= $s['campaign_id'] ?>&view=1">
                <?= h($s['campaign_name']) ?>
              </a>
              <?php else: ?>General<?php endif ?>
            </td>
            <td class="text-muted small text-truncate" style="max-width:250px">
              <?= h(strip_tags($s['content'])) ?>
            </td>
            <td class="text-muted small"><?= h($s['creator_name'] ?? '—') ?></td>
            <td>
              <span class="badge bg-<?= $s['is_default'] ? 'success' : 'secondary' ?>">
                <?= $s['is_default'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td class="no-print table-action-btns">
              <a href="<?= BASE_URL ?>/modules/scripts/form.php?id=<?= $s['id'] ?>&view=1"
                 class="btn btn-sm btn-outline-secondary" title="View">
                <i class="bi bi-eye"></i>
              </a>
              <?php if (can('edit', 'scripts')): ?>
              <a href="<?= BASE_URL ?>/modules/scripts/form.php?id=<?= $s['id'] ?>"
                 class="btn btn-sm btn-outline-warning" title="Edit">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="script_id" value="<?= $s['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-<?= $s['is_default'] ? 'secondary' : 'success' ?>"
                        title="<?= $s['is_default'] ? 'Deactivate' : 'Activate' ?>">
                  <i class="bi bi-<?= $s['is_default'] ? 'pause' : 'play' ?>"></i>
                </button>
              </form>
              <form method="post" class="d-inline"
                    data-confirm="Delete script '<?= h($s['name']) ?>'?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="script_id" value="<?= $s['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($scripts)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No scripts found</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
    <?php if ($p['pages'] > 1): ?>
    <div class="card-footer no-print">
      <?php echo pagination_html($p, '?q=' . urlencode($f_q) . '&active=' . urlencode($f_active)); ?>
    </div>
    <?php endif ?>
  </div>

</div>

<?php require ROOT . '/partials/footer.php'; ?>
