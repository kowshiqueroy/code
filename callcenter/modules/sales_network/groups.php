<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_role('senior_executive');

$page_title  = 'Manage Groups';
$active_page = 'sales_network';

$edit_id = (int)($_GET['edit'] ?? 0);
$edit_group = $edit_id ? db_row("SELECT * FROM sales_groups WHERE id=?", [$edit_id]) : null;

// ── POST: Save group ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_group') {
    require_csrf();
    $gid           = (int)($_POST['group_id']      ?? 0);
    $name          = clean($_POST['name']           ?? '');
    $level_id      = (int)($_POST['level_id']       ?? 0);
    $parent_gid    = (int)($_POST['parent_group_id']?? 0);
    $region        = clean($_POST['region']         ?? '');
    $description   = clean($_POST['description']    ?? '');

    if ($name && $level_id) {
        if ($gid) {
            db_exec(
                "UPDATE sales_groups SET name=?, level_id=?, parent_group_id=?, region=?, description=? WHERE id=?",
                [$name, $level_id, $parent_gid ?: null, $region ?: null, $description ?: null, $gid]
            );
            audit_log('edit_group', 'sales_groups', $gid, "Updated: $name");
            flash_success("Group updated.");
        } else {
            $new_id = db_exec(
                "INSERT INTO sales_groups (name, level_id, parent_group_id, region, description, is_active, created_by)
                 VALUES (?, ?, ?, ?, ?, 1, ?)",
                [$name, $level_id, $parent_gid ?: null, $region ?: null, $description ?: null, current_user_id()]
            );
            audit_log('create_group', 'sales_groups', $new_id, "Created: $name");
            flash_success("Group <strong>" . h($name) . "</strong> created.");
        }
    }
    redirect(BASE_URL . '/modules/sales_network/groups.php');
}

// ── POST: Delete group ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_group') {
    require_csrf();
    $gid = (int)($_POST['group_id'] ?? 0);
    db_exec("UPDATE sales_groups SET is_active=0 WHERE id=?", [$gid]);
    audit_log('delete_group', 'sales_groups', $gid);
    flash_success('Group deactivated.');
    redirect(BASE_URL . '/modules/sales_network/groups.php');
}

// ── POST: Save level ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_level') {
    require_role('super_admin');
    require_csrf();
    $lid        = (int)($_POST['level_id']   ?? 0);
    $name       = clean($_POST['level_name'] ?? '');
    $rank       = (int)($_POST['rank_order'] ?? 0);
    $color      = clean($_POST['color']      ?? '#6c757d');

    if ($name) {
        if ($lid) {
            db_exec("UPDATE sales_levels SET name=?, rank_order=?, color=? WHERE id=?", [$name, $rank, $color, $lid]);
            flash_success('Level updated.');
        } else {
            db_exec("INSERT INTO sales_levels (name, rank_order, color, is_active) VALUES (?, ?, ?, 1)", [$name, $rank, $color]);
            flash_success("Level <strong>" . h($name) . "</strong> created.");
        }
    }
    redirect(BASE_URL . '/modules/sales_network/groups.php');
}

$levels = db_rows("SELECT * FROM sales_levels WHERE is_active=1 ORDER BY rank_order ASC");
$groups = db_rows(
    "SELECT sg.*, sl.name AS level_name, sl.color AS level_color, pg.name AS parent_name
     FROM sales_groups sg
     LEFT JOIN sales_levels sl ON sl.id = sg.level_id
     LEFT JOIN sales_groups pg ON pg.id = sg.parent_group_id
     WHERE sg.is_active=1
     ORDER BY sl.rank_order ASC, sg.name ASC"
);

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/sales_network/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-diagram-3 text-success ms-1"></i>
  <h5 class="ms-1">Manage Groups & Levels</h5>
</div>

<div class="page-body">
  <div class="row g-3">

    <!-- Groups list -->
    <div class="col-12 col-md-8">
      <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="bi bi-diagram-3 me-1"></i>Groups
          <button class="btn btn-sm btn-success ms-auto" data-bs-toggle="modal" data-bs-target="#groupModal">
            <i class="bi bi-plus-lg me-1"></i>New Group
          </button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Name</th>
                <th>Level</th>
                <th>Parent</th>
                <th>Region</th>
                <th class="no-print">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($groups as $g): ?>
              <tr>
                <td class="fw-semibold"><?= h($g['name']) ?></td>
                <td>
                  <span class="badge" style="background:<?= h($g['level_color'] ?? '#999') ?>">
                    <?= h($g['level_name'] ?? '—') ?>
                  </span>
                </td>
                <td class="text-muted small"><?= h($g['parent_name'] ?? '—') ?></td>
                <td class="text-muted small"><?= h($g['region'] ?? '—') ?></td>
                <td class="no-print table-action-btns">
                  <a href="<?= BASE_URL ?>/modules/sales_network/members.php?group_id=<?= $g['id'] ?>"
                     class="btn btn-sm btn-outline-primary" title="Members">
                    <i class="bi bi-people"></i>
                  </a>
                  <a href="?edit=<?= $g['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <form method="post" class="d-inline"
                        data-confirm="Deactivate group '<?= h($g['name']) ?>'?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_group">
                    <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Deactivate">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach ?>
              <?php if (empty($groups)): ?>
              <tr><td colspan="5" class="text-center text-muted py-3">No groups yet</td></tr>
              <?php endif ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Levels -->
    <div class="col-12 col-md-4">
      <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="bi bi-layers me-1"></i>Network Levels
          <?php if (current_role() === 'super_admin'): ?>
          <button class="btn btn-sm btn-outline-primary ms-auto" data-bs-toggle="modal" data-bs-target="#levelModal">
            <i class="bi bi-plus-lg"></i>
          </button>
          <?php endif ?>
        </div>
        <ul class="list-group list-group-flush">
          <?php foreach ($levels as $lvl): ?>
          <li class="list-group-item d-flex align-items-center gap-2 py-2">
            <span class="badge" style="background:<?= h($lvl['color']) ?>;min-width:70px;text-align:center">
              <?= h($lvl['name']) ?>
            </span>
            <span class="text-muted small">Rank <?= $lvl['rank_order'] ?></span>
            <?php if (current_role() === 'super_admin'): ?>
            <button class="btn btn-sm btn-outline-secondary ms-auto py-0 edit-level-btn"
                    data-id="<?= $lvl['id'] ?>"
                    data-name="<?= h($lvl['name']) ?>"
                    data-rank="<?= $lvl['rank_order'] ?>"
                    data-color="<?= h($lvl['color']) ?>">
              <i class="bi bi-pencil"></i>
            </button>
            <?php endif ?>
          </li>
          <?php endforeach ?>
          <?php if (empty($levels)): ?>
          <li class="list-group-item text-muted text-center py-3 small">No levels configured</li>
          <?php endif ?>
        </ul>
      </div>
    </div>

  </div>
</div>

<!-- Group Modal -->
<div class="modal fade" id="groupModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="groupModalTitle">
          <i class="bi bi-diagram-3 me-2"></i>
          <span id="groupModalTitleText">New Group</span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_group">
        <input type="hidden" name="group_id" id="groupIdInput" value="0">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Group Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" id="groupNameInput" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label">Level <span class="text-danger">*</span></label>
              <select class="form-select" name="level_id" id="groupLevelInput" required>
                <option value="">Select level…</option>
                <?php foreach ($levels as $lvl): ?>
                <option value="<?= $lvl['id'] ?>"><?= h($lvl['name']) ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Parent Group</label>
              <select class="form-select" name="parent_group_id" id="groupParentInput">
                <option value="">None (top level)</option>
                <?php foreach ($groups as $g): ?>
                <option value="<?= $g['id'] ?>"><?= h($g['name']) ?></option>
                <?php endforeach ?>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Region</label>
            <input type="text" class="form-control" name="region" id="groupRegionInput"
                   placeholder="e.g. Dhaka North">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" id="groupDescInput" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="submit" class="btn btn-success" id="groupSubmitBtn">Create Group</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Level Modal (admin only) -->
<?php if (current_role() === 'super_admin'): ?>
<div class="modal fade" id="levelModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="levelModalTitle">New Level</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_level">
        <input type="hidden" name="level_id" id="levelIdInput" value="0">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Level Name</label>
            <input type="text" class="form-control" name="level_name" id="levelNameInput" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Rank Order</label>
            <input type="number" class="form-control" name="rank_order" id="levelRankInput" value="10" min="1">
          </div>
          <div class="mb-3">
            <label class="form-label">Color</label>
            <input type="color" class="form-control form-control-color" name="color" id="levelColorInput" value="#6c757d">
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="submit" class="btn btn-primary btn-sm">Save Level</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif ?>

<script>
// Pre-fill group modal for editing
<?php if ($edit_group): ?>
document.addEventListener('DOMContentLoaded', function() {
  var modal = new bootstrap.Modal(document.getElementById('groupModal'));
  document.getElementById('groupModalTitleText').textContent = 'Edit Group';
  document.getElementById('groupSubmitBtn').textContent = 'Save Changes';
  document.getElementById('groupIdInput').value = '<?= $edit_group['id'] ?>';
  document.getElementById('groupNameInput').value = '<?= h($edit_group['name']) ?>';
  document.getElementById('groupLevelInput').value = '<?= $edit_group['level_id'] ?>';
  document.getElementById('groupParentInput').value = '<?= $edit_group['parent_group_id'] ?? '' ?>';
  document.getElementById('groupRegionInput').value = '<?= h($edit_group['region'] ?? '') ?>';
  document.getElementById('groupDescInput').value = '<?= h($edit_group['description'] ?? '') ?>';
  modal.show();
});
<?php endif ?>

// Level edit buttons
document.querySelectorAll('.edit-level-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.getElementById('levelModalTitle').textContent = 'Edit Level';
    document.getElementById('levelIdInput').value = this.dataset.id;
    document.getElementById('levelNameInput').value = this.dataset.name;
    document.getElementById('levelRankInput').value = this.dataset.rank;
    document.getElementById('levelColorInput').value = this.dataset.color;
    new bootstrap.Modal(document.getElementById('levelModal')).show();
  });
});
</script>

<?php require ROOT . '/partials/footer.php'; ?>
