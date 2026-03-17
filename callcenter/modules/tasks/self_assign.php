<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Self Assign Task';
$active_page = 'tasks';
$uid = current_user_id();

// Only task types marked as self-assignable
$self_types = db_rows("SELECT * FROM task_types WHERE is_self_assignable=1 AND is_active=1 ORDER BY sort_order");

// ── POST: Self assign ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $type_id    = (int)($_POST['type_id']   ?? 0);
    $title      = clean($_POST['title']     ?? '');
    $due_date   = clean($_POST['due_date']  ?? '');
    $notes      = clean($_POST['notes']     ?? '');
    $contact_id = (int)($_POST['contact_id']?? 0);

    // Validate type is self-assignable
    $valid_type = $type_id ? db_val("SELECT id FROM task_types WHERE id=? AND is_self_assignable=1 AND is_active=1", [$type_id]) : null;

    $errors = [];
    if (!$title)      $errors[] = 'Task title is required.';
    if (!$type_id || !$valid_type) $errors[] = 'Please select a valid activity type.';

    if (empty($errors)) {
        $tid = db_exec(
            "INSERT INTO tasks (title, type_id, assigned_to, assigned_by, priority, status, due_date, contact_id, notes)
             VALUES (?, ?, ?, ?, 'medium', 'in_progress', ?, ?, ?)",
            [$title, $type_id, $uid, $uid, $due_date ?: null, $contact_id ?: null, $notes ?: null]
        );
        audit_log('self_assign_task', 'tasks', $tid, "Self-assigned: $title");
        flash_success("Task started.");
        redirect(BASE_URL . '/modules/tasks/index.php?status=in_progress');
    }
}

// My active self-assigned tasks
$my_active = db_rows(
    "SELECT t.*, tt.name AS type_name, tt.icon AS type_icon, tt.color AS type_color
     FROM tasks t
     LEFT JOIN task_types tt ON tt.id = t.type_id
     WHERE t.assigned_to=? AND t.assigned_by=? AND tt.is_self_assignable=1
       AND t.status IN ('pending','in_progress')
     ORDER BY t.created_at DESC",
    [$uid, $uid]
);

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/tasks/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-person-check text-primary ms-1"></i>
  <h5 class="ms-1">Self-Assign Activity</h5>
</div>

<div class="page-body">
  <div class="row g-3">

    <div class="col-12 col-md-6">
      <?php if (!empty($errors ?? [])): ?>
      <div class="alert alert-danger">
        <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul>
      </div>
      <?php endif ?>

      <div class="card">
        <div class="card-header">Start a New Activity</div>
        <div class="card-body">
          <form method="post">
            <?= csrf_field() ?>

            <div class="mb-3">
              <label class="form-label">Activity Type <span class="text-danger">*</span></label>
              <div class="row g-2">
                <?php foreach ($self_types as $tt): ?>
                <div class="col-6">
                  <label class="d-flex align-items-center gap-2 p-2 border rounded cursor-pointer
                                <?= ($_POST['type_id'] ?? '') == $tt['id'] ? 'border-primary bg-primary bg-opacity-10' : '' ?>"
                         style="cursor:pointer">
                    <input type="radio" name="type_id" value="<?= $tt['id'] ?>"
                           <?= ($_POST['type_id'] ?? '') == $tt['id'] ? 'checked' : '' ?> required>
                    <span class="badge me-1" style="background:<?= h($tt['color'] ?? '#6c757d') ?>">
                      <i class="bi bi-<?= h($tt['icon'] ?? 'check2') ?>"></i>
                    </span>
                    <span class="small"><?= h($tt['name']) ?></span>
                  </label>
                </div>
                <?php endforeach ?>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">What are you doing? <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="title" required
                     value="<?= h($_POST['title'] ?? '') ?>"
                     placeholder="Brief description of activity…">
            </div>

            <div class="mb-3 position-relative">
              <label class="form-label">Related Contact</label>
              <input type="text" class="form-control" id="selfContactName"
                     data-autocomplete="contacts" data-ac-id-field="contact_id"
                     placeholder="Search contact (optional)…">
              <input type="hidden" name="contact_id" id="contact_id">
            </div>

            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label">Target Date</label>
                <input type="date" class="form-control" name="due_date"
                       value="<?= h($_POST['due_date'] ?? date('Y-m-d')) ?>">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea class="form-control" name="notes" rows="2"
                        placeholder="Any additional notes…"><?= h($_POST['notes'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-play me-2"></i>Start Activity
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6">
      <div class="card">
        <div class="card-header">My Active Activities</div>
        <?php if (!empty($my_active)): ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($my_active as $t): ?>
          <li class="list-group-item d-flex align-items-center gap-2 py-2">
            <span class="badge" style="background:<?= h($t['type_color'] ?? '#6c757d') ?>">
              <i class="bi bi-<?= h($t['type_icon'] ?? 'check2') ?>"></i>
            </span>
            <div class="flex-grow-1">
              <div class="small fw-semibold"><?= h($t['title']) ?></div>
              <div class="text-muted" style="font-size:.7rem"><?= h($t['type_name']) ?> · <?= time_ago($t['created_at']) ?></div>
            </div>
            <form method="post" action="<?= BASE_URL ?>/modules/tasks/index.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
              <input type="hidden" name="new_status" value="completed">
              <button type="submit" class="btn btn-sm btn-outline-success" title="Complete">
                <i class="bi bi-check2"></i>
              </button>
            </form>
          </li>
          <?php endforeach ?>
        </ul>
        <?php else: ?>
        <div class="card-body text-muted text-center py-3 small">No active self-assigned activities</div>
        <?php endif ?>
      </div>
    </div>

  </div>
</div>

<?php require ROOT . '/partials/footer.php'; ?>
