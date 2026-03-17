<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Reports';
$active_page = 'reports';

$uid  = current_user_id();
$role = current_role();

// What templates can this user see?
$where  = ["(rt.visibility = 'public'"];
$params = [];
if ($role !== 'viewer') {
    $where[0] .= " OR rt.created_by = ?";
    $params[] = $uid;
    $where[0] .= " OR EXISTS (SELECT 1 FROM report_template_permissions rtp WHERE rtp.template_id=rt.id AND rtp.user_id=?)";
    $params[] = $uid;
}
$where[0] .= ")";
if ($role === 'viewer') {
    // Viewers can only run shared/public templates
    $where[] = "rt.visibility IN ('public','shared')";
}

$whereStr = 'WHERE ' . implode(' AND ', $where) . ' AND rt.is_active=1';

$templates = db_rows(
    "SELECT rt.*, u.name AS creator_name,
            (SELECT COUNT(*) FROM report_runs rr WHERE rr.template_id=rt.id) AS run_count,
            (SELECT MAX(rr2.created_at) FROM report_runs rr2 WHERE rr2.template_id=rt.id) AS last_run
     FROM report_templates rt
     LEFT JOIN users u ON u.id = rt.created_by
     $whereStr
     ORDER BY rt.updated_at DESC",
    $params
);

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-bar-chart-fill text-primary"></i>
  <h5>Reports</h5>
  <div class="ms-auto d-flex gap-2">
    <?php if ($role !== 'viewer'): ?>
    <a href="<?= BASE_URL ?>/modules/reports/builder.php" class="btn btn-sm btn-primary">
      <i class="bi bi-plus-lg me-1"></i>New Template
    </a>
    <?php endif ?>
    <a href="<?= BASE_URL ?>/modules/reports/executive.php" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-person-check me-1"></i>Agent Report
    </a>
    <a href="<?= BASE_URL ?>/modules/reports/monthly.php" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-calendar-month me-1"></i>Monthly
    </a>
  </div>
</div>

<div class="page-body">

  <!-- Quick report links -->
  <div class="row g-2 mb-3">
    <?php
    $quick = [
      ['Calls Today', 'bar-chart', 'primary', BASE_URL . '/modules/reports/executive.php'],
      ['Monthly Summary', 'calendar-month', 'success', BASE_URL . '/modules/reports/monthly.php'],
      ['SR Tracking', 'diagram-3', 'warning', BASE_URL . '/modules/reports/run.php?builtin=sr_tracking'],
      ['Attendance Report', 'clock', 'info', BASE_URL . '/modules/reports/run.php?builtin=attendance'],
    ];
    foreach ($quick as [$label, $icon, $color, $url]): ?>
    <div class="col-6 col-md-3">
      <a href="<?= $url ?>" class="card text-decoration-none hover-lift">
        <div class="card-body d-flex align-items-center gap-2 py-3">
          <i class="bi bi-<?= $icon ?> text-<?= $color ?> fs-4"></i>
          <span class="fw-semibold small"><?= $label ?></span>
        </div>
      </a>
    </div>
    <?php endforeach ?>
  </div>

  <!-- Template list -->
  <div class="card">
    <div class="card-header d-flex align-items-center gap-2">
      <i class="bi bi-file-earmark-bar-graph me-1"></i>Report Templates
      <input type="text" class="form-control form-control-sm ms-auto no-print" style="max-width:200px"
             placeholder="Filter…" data-filter-table="templatesTable">
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" id="templatesTable">
        <thead class="table-light">
          <tr>
            <th>Template Name</th>
            <th>Data Sources</th>
            <th>Visibility</th>
            <th>Created By</th>
            <th>Times Run</th>
            <th>Last Run</th>
            <th class="no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($templates as $t): ?>
          <?php
          $sources = json_decode($t['source_modules'] ?? '[]', true) ?: [];
          $visBadge = ['private'=>'secondary','shared'=>'primary','public'=>'success'];
          $vc = $visBadge[$t['visibility']] ?? 'secondary';
          $canEdit = ($t['created_by'] == $uid || $role === 'super_admin');
          ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= h($t['name']) ?></div>
              <?php if ($t['description']): ?>
              <div class="text-muted small text-truncate" style="max-width:200px"><?= h($t['description']) ?></div>
              <?php endif ?>
            </td>
            <td>
              <?php foreach ($sources as $src): ?>
              <span class="badge bg-light text-dark border me-1" style="font-size:.65rem"><?= h($src) ?></span>
              <?php endforeach ?>
            </td>
            <td><span class="badge bg-<?= $vc ?>"><?= ucfirst($t['visibility']) ?></span></td>
            <td class="text-muted small"><?= h($t['creator_name'] ?? '—') ?></td>
            <td class="text-muted small"><?= $t['run_count'] ?></td>
            <td class="text-muted small"><?= $t['last_run'] ? time_ago($t['last_run']) : '—' ?></td>
            <td class="no-print table-action-btns">
              <a href="<?= BASE_URL ?>/modules/reports/run.php?template_id=<?= $t['id'] ?>"
                 class="btn btn-sm btn-outline-primary" title="Run">
                <i class="bi bi-play"></i>
              </a>
              <?php if ($canEdit && $role !== 'viewer'): ?>
              <a href="<?= BASE_URL ?>/modules/reports/builder.php?id=<?= $t['id'] ?>"
                 class="btn btn-sm btn-outline-warning" title="Edit">
                <i class="bi bi-pencil"></i>
              </a>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($templates)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">
            No report templates yet.
            <?php if ($role !== 'viewer'): ?>
            <a href="<?= BASE_URL ?>/modules/reports/builder.php">Create your first template</a>
            <?php endif ?>
          </td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<style>
.hover-lift { transition: transform .15s; }
.hover-lift:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
</style>

<?php require ROOT . '/partials/footer.php'; ?>
