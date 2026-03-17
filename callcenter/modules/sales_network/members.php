<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Group Members';
$active_page = 'sales_network';

$group_id = (int)($_GET['group_id'] ?? 0);
$group    = $group_id ? db_row(
    "SELECT sg.*, sl.name AS level_name, sl.color AS level_color
     FROM sales_groups sg LEFT JOIN sales_levels sl ON sl.id=sg.level_id
     WHERE sg.id=?", [$group_id]
) : null;

// ── POST: Add member ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_member') {
    require_role('senior_executive');
    require_csrf();
    $gid        = (int)($_POST['group_id']   ?? 0);
    $contact_id = (int)($_POST['contact_id'] ?? 0);
    $role_in    = clean($_POST['role_in_group'] ?? '');
    $joined     = clean($_POST['joined_date'] ?? date('Y-m-d'));

    if ($gid && $contact_id) {
        $exists = db_val("SELECT COUNT(*) FROM sales_group_members WHERE group_id=? AND contact_id=? AND is_active=1", [$gid, $contact_id]);
        if (!$exists) {
            db_exec("INSERT INTO sales_group_members (group_id, contact_id, role_in_group, joined_date, is_active) VALUES (?,?,?,?,1)",
                [$gid, $contact_id, $role_in ?: null, $joined]);
            audit_log('add_group_member', 'sales_groups', $gid);
            flash_success('Member added.');
        } else {
            flash_error('Contact is already a member of this group.');
        }
    }
    redirect(BASE_URL . '/modules/sales_network/members.php?group_id=' . $gid);
}

// ── POST: Remove member ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_member') {
    require_role('senior_executive');
    require_csrf();
    $member_id = (int)($_POST['member_id'] ?? 0);
    $gid       = (int)($_POST['group_id']  ?? 0);
    db_exec("UPDATE sales_group_members SET is_active=0, left_date=CURDATE() WHERE id=?", [$member_id]);
    audit_log('remove_group_member', 'sales_groups', $gid);
    flash_success('Member removed.');
    redirect(BASE_URL . '/modules/sales_network/members.php?group_id=' . $gid);
}

// ── POST: Assign executive ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_exec') {
    require_role('senior_executive');
    require_csrf();
    $gid     = (int)($_POST['group_id'] ?? 0);
    $user_id = (int)($_POST['user_id']  ?? 0);
    if ($gid && $user_id) {
        $exists = db_val("SELECT COUNT(*) FROM executive_group_assignments WHERE group_id=? AND user_id=? AND is_active=1", [$gid, $user_id]);
        if (!$exists) {
            db_exec("INSERT INTO executive_group_assignments (group_id, user_id, assigned_by, assigned_at, is_active) VALUES (?,?,?,NOW(),1)",
                [$gid, $user_id, current_user_id()]);
            audit_log('assign_exec_group', 'sales_groups', $gid);
            flash_success('Executive assigned.');
        }
    }
    redirect(BASE_URL . '/modules/sales_network/members.php?group_id=' . $gid);
}

// ── POST: Unassign executive ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unassign_exec') {
    require_role('senior_executive');
    require_csrf();
    $assign_id = (int)($_POST['assign_id'] ?? 0);
    $gid       = (int)($_POST['group_id']  ?? 0);
    db_exec("UPDATE executive_group_assignments SET is_active=0 WHERE id=?", [$assign_id]);
    flash_success('Executive unassigned.');
    redirect(BASE_URL . '/modules/sales_network/members.php?group_id=' . $gid);
}

// Members list
$members = [];
$assigned_execs = [];
if ($group) {
    $members = db_rows(
        "SELECT sgm.id AS member_id, sgm.role_in_group, sgm.joined_date, sgm.left_date,
                c.id, c.name, c.phone, c.contact_type,
                (SELECT MAX(ca.started_at) FROM calls ca WHERE ca.contact_id=c.id) AS last_call
         FROM sales_group_members sgm
         JOIN contacts c ON c.id = sgm.contact_id
         WHERE sgm.group_id=? AND sgm.is_active=1
         ORDER BY sgm.role_in_group, c.name ASC",
        [$group_id]
    );
    $assigned_execs = db_rows(
        "SELECT ega.id AS assign_id, u.id AS user_id, u.name AS user_name, u.role, ega.assigned_at
         FROM executive_group_assignments ega
         JOIN users u ON u.id = ega.user_id
         WHERE ega.group_id=? AND ega.is_active=1
         ORDER BY u.name",
        [$group_id]
    );
}

// All groups for filter
$all_groups = db_rows("SELECT sg.id, sg.name, sl.name AS level_name FROM sales_groups sg LEFT JOIN sales_levels sl ON sl.id=sg.level_id WHERE sg.is_active=1 ORDER BY sl.rank_order, sg.name");
$all_execs  = in_array(current_role(), ['super_admin','senior_executive'])
    ? db_rows("SELECT id, name FROM users WHERE status='active' AND role IN ('executive','senior_executive') ORDER BY name")
    : [];

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/sales_network/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-people-fill text-success ms-1"></i>
  <h5 class="ms-1">
    <?php if ($group): ?>
    <span class="badge me-2" style="background:<?= h($group['level_color'] ?? '#999') ?>"><?= h($group['level_name'] ?? '') ?></span>
    <?= h($group['name']) ?>
    <?php else: ?>All Members<?php endif ?>
  </h5>
  <?php if ($group): ?>
  <div class="ms-auto d-flex gap-2 no-print">
    <button data-print class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
    <?php if (in_array(current_role(), ['super_admin','senior_executive'])): ?>
    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addMemberModal">
      <i class="bi bi-person-plus me-1"></i>Add Member
    </button>
    <?php endif ?>
  </div>
  <?php endif ?>
</div>

<div class="page-body">

  <!-- Group filter -->
  <?php if (!$group): ?>
  <div class="card mb-3">
    <div class="card-body py-2 px-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
          <select class="form-select form-select-sm" onchange="location='?group_id='+this.value">
            <option value="">Select a group to view members…</option>
            <?php foreach ($all_groups as $g): ?>
            <option value="<?= $g['id'] ?>" <?= $group_id==$g['id']?'selected':'' ?>>
              [<?= h($g['level_name']) ?>] <?= h($g['name']) ?>
            </option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
    </div>
  </div>
  <?php endif ?>

  <?php if ($group): ?>
  <div class="row g-3">

    <!-- Members -->
    <div class="col-12 col-md-8">
      <div class="card">
        <div class="card-header">
          <i class="bi bi-people me-2"></i>Members
          <span class="badge bg-primary ms-1"><?= count($members) ?></span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Role in Group</th>
                <th>Joined</th>
                <th>Last Call</th>
                <th class="no-print">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($members as $m): ?>
              <tr>
                <td>
                  <a href="<?= BASE_URL ?>/modules/contacts/view.php?id=<?= $m['id'] ?>">
                    <?= h($m['name']) ?>
                  </a>
                  <div class="text-muted small"><?= h($m['phone']) ?></div>
                </td>
                <td><?= type_badge($m['contact_type']) ?></td>
                <td class="text-muted small"><?= h($m['role_in_group'] ?? '—') ?></td>
                <td class="text-muted small"><?= $m['joined_date'] ? format_date($m['joined_date']) : '—' ?></td>
                <td class="text-muted small"><?= $m['last_call'] ? time_ago($m['last_call']) : 'Never' ?></td>
                <td class="no-print table-action-btns">
                  <a href="<?= BASE_URL ?>/modules/workspace/index.php?contact_id=<?= $m['id'] ?>"
                     class="btn btn-sm btn-outline-primary" title="Call">
                    <i class="bi bi-telephone-plus"></i>
                  </a>
                  <?php if (in_array(current_role(), ['super_admin','senior_executive'])): ?>
                  <form method="post" class="d-inline" data-confirm="Remove <?= h($m['name']) ?> from this group?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove_member">
                    <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                    <input type="hidden" name="group_id" value="<?= $group_id ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                      <i class="bi bi-person-dash"></i>
                    </button>
                  </form>
                  <?php endif ?>
                </td>
              </tr>
              <?php endforeach ?>
              <?php if (empty($members)): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No members yet</td></tr>
              <?php endif ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Assigned Executives -->
    <div class="col-12 col-md-4">
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <i class="bi bi-person-workspace me-2"></i>Assigned Executives
          <?php if (in_array(current_role(), ['super_admin','senior_executive'])): ?>
          <button class="btn btn-sm btn-outline-primary ms-auto" data-bs-toggle="modal" data-bs-target="#assignExecModal">
            <i class="bi bi-plus-lg"></i>
          </button>
          <?php endif ?>
        </div>
        <ul class="list-group list-group-flush">
          <?php foreach ($assigned_execs as $ae): ?>
          <li class="list-group-item d-flex align-items-center gap-2 py-2">
            <i class="bi bi-person-circle text-primary"></i>
            <div class="flex-grow-1">
              <div class="small fw-semibold"><?= h($ae['user_name']) ?></div>
              <div class="text-muted" style="font-size:.7rem"><?= role_badge($ae['role']) ?></div>
            </div>
            <?php if (in_array(current_role(), ['super_admin','senior_executive'])): ?>
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="unassign_exec">
              <input type="hidden" name="assign_id" value="<?= $ae['assign_id'] ?>">
              <input type="hidden" name="group_id" value="<?= $group_id ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger py-0" title="Unassign">
                <i class="bi bi-x"></i>
              </button>
            </form>
            <?php endif ?>
          </li>
          <?php endforeach ?>
          <?php if (empty($assigned_execs)): ?>
          <li class="list-group-item text-muted text-center py-3 small">No executives assigned</li>
          <?php endif ?>
        </ul>
      </div>

      <?php if ($group['description']): ?>
      <div class="card mt-3">
        <div class="card-body small text-muted"><?= nl2br(h($group['description'])) ?></div>
      </div>
      <?php endif ?>
    </div>

  </div>
  <?php endif ?>

</div>

<?php if ($group && in_array(current_role(), ['super_admin','senior_executive'])): ?>
<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Member to <?= h($group['name']) ?></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_member">
        <input type="hidden" name="group_id" value="<?= $group_id ?>">
        <div class="modal-body">
          <div class="mb-3 position-relative">
            <label class="form-label">Contact <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="memberContactName"
                   data-autocomplete="contacts" data-ac-id-field="contact_id"
                   placeholder="Search contact…" required>
            <input type="hidden" name="contact_id" id="contact_id">
          </div>
          <div class="mb-3">
            <label class="form-label">Role in Group</label>
            <input type="text" class="form-control" name="role_in_group"
                   placeholder="e.g. Owner, Manager, Member">
          </div>
          <div class="mb-3">
            <label class="form-label">Joined Date</label>
            <input type="date" class="form-control" name="joined_date" value="<?= date('Y-m-d') ?>">
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="submit" class="btn btn-success">Add Member</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Assign Executive Modal -->
<div class="modal fade" id="assignExecModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-person-workspace me-2"></i>Assign Executive</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="assign_exec">
        <input type="hidden" name="group_id" value="<?= $group_id ?>">
        <div class="modal-body">
          <select class="form-select" name="user_id" required>
            <option value="">Select executive…</option>
            <?php foreach ($all_execs as $e): ?>
            <option value="<?= $e['id'] ?>"><?= h($e['name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="modal-footer py-2">
          <button type="submit" class="btn btn-primary btn-sm">Assign</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif ?>

<?php require ROOT . '/partials/footer.php'; ?>
