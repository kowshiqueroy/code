<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Sales Network';
$active_page = 'sales_network';

// Load levels and build hierarchy
$levels = db_rows("SELECT * FROM sales_levels WHERE is_active=1 ORDER BY rank_order ASC");

// Build group tree
function build_group_tree($parent_id = null) {
    $groups = db_rows(
        "SELECT sg.*, sl.name AS level_name, sl.color AS level_color,
                (SELECT COUNT(*) FROM sales_group_members sgm WHERE sgm.group_id=sg.id AND sgm.is_active=1) AS member_count,
                (SELECT COUNT(*) FROM executive_group_assignments ega WHERE ega.group_id=sg.id AND ega.is_active=1) AS exec_count
         FROM sales_groups sg
         LEFT JOIN sales_levels sl ON sl.id = sg.level_id
         WHERE sg.is_active=1 AND " . ($parent_id === null ? "sg.parent_group_id IS NULL" : "sg.parent_group_id=?") . "
         ORDER BY sl.rank_order ASC, sg.name ASC",
        $parent_id !== null ? [$parent_id] : []
    );
    return $groups;
}

// Flattened tree for display
function render_group_tree($parent_id = null, $depth = 0) {
    global $output_rows;
    $groups = build_group_tree($parent_id);
    foreach ($groups as $g) {
        $output_rows[] = array_merge($g, ['_depth' => $depth]);
        render_group_tree($g['id'], $depth + 1);
    }
}

$output_rows = [];
render_group_tree(null, 0);

$total_groups  = (int) db_val("SELECT COUNT(*) FROM sales_groups WHERE is_active=1");
$total_members = (int) db_val("SELECT COUNT(*) FROM sales_group_members WHERE is_active=1");

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-diagram-3-fill text-success"></i>
  <h5>Sales Network</h5>
  <div class="ms-auto d-flex gap-2 no-print">
    <button data-print class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-printer"></i>
    </button>
    <?php if (can('create', 'sales_network')): ?>
    <a href="<?= BASE_URL ?>/modules/sales_network/groups.php" class="btn btn-sm btn-outline-success">
      <i class="bi bi-diagram-3 me-1"></i>Manage Groups
    </a>
    <?php endif ?>
    <?php if (in_array(current_role(), ['super_admin','senior_executive'])): ?>
    <a href="<?= BASE_URL ?>/modules/sales_network/members.php" class="btn btn-sm btn-success">
      <i class="bi bi-people me-1"></i>Members
    </a>
    <?php endif ?>
  </div>
</div>

<div class="page-body">

  <!-- Stats -->
  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
      <div class="card text-center py-2">
        <div class="fs-5 fw-bold text-success"><?= number_format($total_groups) ?></div>
        <div class="text-muted small">Active Groups</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center py-2">
        <div class="fs-5 fw-bold text-primary"><?= number_format($total_members) ?></div>
        <div class="text-muted small">Total Members</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center py-2">
        <div class="fs-5 fw-bold text-info"><?= count($levels) ?></div>
        <div class="text-muted small">Network Levels</div>
      </div>
    </div>
  </div>

  <!-- Level legend -->
  <div class="d-flex gap-2 mb-3 flex-wrap no-print">
    <?php foreach ($levels as $lvl): ?>
    <span class="badge" style="background:<?= h($lvl['color']) ?>;font-size:.75rem">
      <?= h($lvl['name']) ?>
    </span>
    <?php endforeach ?>
  </div>

  <!-- Hierarchy tree -->
  <div class="card">
    <div class="card-header d-flex align-items-center gap-2">
      <i class="bi bi-diagram-3 me-1"></i>Group Hierarchy
      <input type="text" class="form-control form-control-sm ms-auto no-print" style="max-width:200px"
             placeholder="Quick filter…" data-filter-table="groupTree">
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" id="groupTree">
        <thead class="table-light">
          <tr>
            <th>Group</th>
            <th>Level</th>
            <th>Region</th>
            <th>Members</th>
            <th>Executives</th>
            <th class="no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($output_rows as $g): ?>
          <tr>
            <td>
              <div style="padding-left:<?= $g['_depth'] * 20 ?>px">
                <?php if ($g['_depth'] > 0): ?>
                <span class="text-muted me-1">└</span>
                <?php endif ?>
                <a href="<?= BASE_URL ?>/modules/sales_network/members.php?group_id=<?= $g['id'] ?>"
                   class="fw-semibold text-decoration-none">
                  <?= h($g['name']) ?>
                </a>
                <?php if ($g['description']): ?>
                <div class="text-muted small text-truncate" style="max-width:200px;padding-left:<?= $g['_depth'] > 0 ? '16px' : '' ?>">
                  <?= h($g['description']) ?>
                </div>
                <?php endif ?>
              </div>
            </td>
            <td>
              <span class="badge" style="background:<?= h($g['level_color'] ?? '#6c757d') ?>">
                <?= h($g['level_name'] ?? '—') ?>
              </span>
            </td>
            <td class="text-muted small"><?= h($g['region'] ?? '—') ?></td>
            <td>
              <?php if ($g['member_count'] > 0): ?>
              <a href="<?= BASE_URL ?>/modules/sales_network/members.php?group_id=<?= $g['id'] ?>">
                <?= number_format($g['member_count']) ?>
              </a>
              <?php else: ?>
              <span class="text-muted">0</span>
              <?php endif ?>
            </td>
            <td class="text-muted small"><?= $g['exec_count'] ?></td>
            <td class="no-print table-action-btns">
              <a href="<?= BASE_URL ?>/modules/sales_network/members.php?group_id=<?= $g['id'] ?>"
                 class="btn btn-sm btn-outline-primary" title="View members">
                <i class="bi bi-people"></i>
              </a>
              <?php if (can('edit', 'sales_network')): ?>
              <a href="<?= BASE_URL ?>/modules/sales_network/groups.php?edit=<?= $g['id'] ?>"
                 class="btn btn-sm btn-outline-warning" title="Edit group">
                <i class="bi bi-pencil"></i>
              </a>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($output_rows)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">
            No groups yet.
            <?php if (can('create', 'sales_network')): ?>
            <a href="<?= BASE_URL ?>/modules/sales_network/groups.php">Create the first group</a>
            <?php endif ?>
          </td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require ROOT . '/partials/footer.php'; ?>
