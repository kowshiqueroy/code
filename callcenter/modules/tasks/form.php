<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$id     = (int)($_GET['id'] ?? 0);
$task   = $id ? db_row("SELECT * FROM tasks WHERE id=?", [$id]) : null;
$is_edit= (bool)$task;

if ($id && !$task) redirect(BASE_URL . '/modules/tasks/index.php');

$page_title  = $is_edit ? 'Edit Task' : 'New Task';
$active_page = 'tasks';
$uid         = current_user_id();
$isAll       = in_array(current_role(), ['super_admin','senior_executive']);

// ── POST: Save ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title       = clean($_POST['title']       ?? '');
    $description = clean($_POST['description'] ?? '');
    $type_id     = (int)($_POST['type_id']     ?? 0);
    $assigned_to = (int)($_POST['assigned_to'] ?? $uid);
    $priority    = clean($_POST['priority']    ?? 'medium');
    $due_date    = clean($_POST['due_date']    ?? '');
    $contact_id  = (int)($_POST['contact_id'] ?? 0);
    $notes       = clean($_POST['notes']       ?? '');

    $errors = [];
    if (!$title) $errors[] = 'Task title is required.';

    if (empty($errors)) {
        $priority = in_array($priority, ['low','medium','high','urgent']) ? $priority : 'medium';
        if ($is_edit) {
            db_exec(
                "UPDATE tasks SET title=?, description=?, type_id=?, assigned_to=?, priority=?,
                  due_date=?, contact_id=?, notes=? WHERE id=?",
                [$title, $description ?: null, $type_id ?: null, $assigned_to, $priority,
                 $due_date ?: null, $contact_id ?: null, $notes ?: null, $id]
            );
            audit_log('edit_task', 'tasks', $id, "Updated: $title");
            flash_success('Task updated.');
        } else {
            $tid = db_exec(
                "INSERT INTO tasks (title, description, type_id, assigned_to, assigned_by, priority,
                  status, due_date, contact_id, notes)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)",
                [$title, $description ?: null, $type_id ?: null, $assigned_to, $uid, $priority,
                 $due_date ?: null, $contact_id ?: null, $notes ?: null]
            );
            audit_log('create_task', 'tasks', $tid, "Created: $title");
            flash_success("Task created.");
        }
        redirect(BASE_URL . '/modules/tasks/index.php');
    }
    $task = array_merge($task ?? [], $_POST);
}

// Prefill contact from URL
$pre_contact_id = (int)($_GET['contact_id'] ?? 0);
$pre_contact    = $pre_contact_id ? db_row("SELECT id, name FROM contacts WHERE id=?", [$pre_contact_id]) : null;

$task_types = db_rows("SELECT * FROM task_types WHERE is_active=1 ORDER BY sort_order");
$agents = $isAll
    ? db_rows("SELECT id, name FROM users WHERE status='active' AND role != 'viewer' ORDER BY name")
    : [['id' => $uid, 'name' => current_user()['name'] ?? 'Me']];

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/tasks/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-check2-square text-primary ms-1"></i>
  <h5 class="ms-1"><?= $page_title ?></h5>
</div>

<div class="page-body">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8">

      <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul>
      </div>
      <?php endif ?>

      <div class="card">
        <div class="card-header"><?= $is_edit ? 'Edit Task' : 'New Task' ?></div>
        <div class="card-body">
          <form method="post">
            <?= csrf_field() ?>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Task Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="title" required
                       value="<?= h($task['title'] ?? '') ?>" placeholder="Describe the task…">
              </div>
              <div class="col-6">
                <label class="form-label">Type</label>
                <select class="form-select" name="type_id">
                  <option value="">General</option>
                  <?php foreach ($task_types as $tt): ?>
                  <option value="<?= $tt['id'] ?>" <?= ($task['type_id']??0)==$tt['id']?'selected':'' ?>>
                    <?= h($tt['name']) ?>
                  </option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">Priority</label>
                <select class="form-select" name="priority">
                  <?php foreach (['low','medium','high','urgent'] as $pr): ?>
                  <option value="<?= $pr ?>" <?= ($task['priority']??'medium')===$pr?'selected':'' ?>><?= ucfirst($pr) ?></option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">Assign To</label>
                <select class="form-select" name="assigned_to">
                  <?php foreach ($agents as $a): ?>
                  <option value="<?= $a['id'] ?>" <?= ($task['assigned_to']??$uid)==$a['id']?'selected':'' ?>>
                    <?= h($a['name']) ?><?= $a['id']==$uid?' (Me)':'' ?>
                  </option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">Due Date</label>
                <input type="date" class="form-control" name="due_date"
                       value="<?= h($task['due_date'] ?? '') ?>">
              </div>
              <div class="col-12 position-relative">
                <label class="form-label">Related Contact</label>
                <input type="text" class="form-control" id="taskContactName"
                       data-autocomplete="contacts" data-ac-id-field="contact_id"
                       value="<?= ($task['contact_id'] ?? $pre_contact_id) ? h($pre_contact['name'] ?? db_val("SELECT name FROM contacts WHERE id=?", [$task['contact_id'] ?? $pre_contact_id]) ?? '') : '' ?>"
                       placeholder="Search contact (optional)…">
                <input type="hidden" name="contact_id" id="contact_id"
                       value="<?= $task['contact_id'] ?? $pre_contact_id ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3"
                          placeholder="Detailed instructions…"><?= h($task['description'] ?? '') ?></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" rows="2"
                          placeholder="Additional notes…"><?= h($task['notes'] ?? '') ?></textarea>
              </div>
            </div>
            <div class="d-flex gap-2 mt-4">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-2"></i><?= $is_edit ? 'Save Changes' : 'Create Task' ?>
              </button>
              <a href="<?= BASE_URL ?>/modules/tasks/index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require ROOT . '/partials/footer.php'; ?>
