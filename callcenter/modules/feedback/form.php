<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'New Feedback Thread';
$active_page = 'feedback';
$uid = current_user_id();

// Prefill contact from URL
$new_contact_id = (int)($_GET['contact_id'] ?? 0);
$new_contact    = $new_contact_id ? db_row("SELECT id, name, phone FROM contacts WHERE id=?", [$new_contact_id]) : null;

// ── POST: Create thread ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $contact_id  = (int)($_POST['contact_id']  ?? 0);
    $title       = clean($_POST['title']       ?? '');
    $problem     = clean($_POST['problem']     ?? '');
    $priority    = clean($_POST['priority']    ?? 'medium');
    $assigned_to = (int)($_POST['assigned_to'] ?? 0);

    $errors = [];
    if (!$title)   $errors[] = 'Thread title is required.';
    if (!$problem) $errors[] = 'Problem description is required.';

    if (empty($errors)) {
        $priority  = in_array($priority, ['low','medium','high','urgent']) ? $priority : 'medium';
        $thread_id = db_exec(
            "INSERT INTO feedback_threads (contact_id, title, problem_description, priority, status, assigned_to, created_by)
             VALUES (?, ?, ?, ?, 'open', ?, ?)",
            [$contact_id ?: null, $title, $problem, $priority, $assigned_to ?: null, $uid]
        );
        // Add initial entry
        db_exec(
            "INSERT INTO feedback_entries (thread_id, agent_id, entry_type, content)
             VALUES (?, ?, 'feedback', ?)",
            [$thread_id, $uid, $problem]
        );
        audit_log('create_feedback', 'feedback_threads', $thread_id, "Thread: $title");
        flash_success("Feedback thread opened.");
        redirect(BASE_URL . '/modules/feedback/thread.php?id=' . $thread_id);
    }
}

$agents = in_array(current_role(), ['super_admin','senior_executive'])
    ? db_rows("SELECT id, name FROM users WHERE status='active' AND role != 'viewer' ORDER BY name")
    : [];

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/feedback/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-chat-square-text-fill text-danger ms-1"></i>
  <h5 class="ms-1">New Feedback Thread</h5>
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
        <div class="card-header">Open Feedback Thread</div>
        <div class="card-body">
          <form method="post">
            <?= csrf_field() ?>
            <div class="row g-3">
              <div class="col-12 position-relative">
                <label class="form-label">Contact</label>
                <input type="text" class="form-control" id="fbContactName"
                       data-autocomplete="contacts" data-ac-id-field="contact_id"
                       value="<?= $new_contact ? h($new_contact['name']) : '' ?>"
                       placeholder="Search contact (optional)…">
                <input type="hidden" name="contact_id" id="contact_id" value="<?= $new_contact_id ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Thread Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="title" required
                       value="<?= h($_POST['title'] ?? '') ?>"
                       placeholder="Brief summary of the issue…">
              </div>
              <div class="col-6 col-md-4">
                <label class="form-label">Priority</label>
                <select class="form-select" name="priority">
                  <?php foreach (['low','medium','high','urgent'] as $p): ?>
                  <option value="<?= $p ?>" <?= ($_POST['priority']??'medium')===$p?'selected':'' ?>>
                    <?= ucfirst($p) ?>
                  </option>
                  <?php endforeach ?>
                </select>
              </div>
              <?php if (!empty($agents)): ?>
              <div class="col-6 col-md-8">
                <label class="form-label">Assign To</label>
                <select class="form-select" name="assigned_to">
                  <option value="">Unassigned</option>
                  <?php foreach ($agents as $a): ?>
                  <option value="<?= $a['id'] ?>" <?= ($a['id']==$uid)?'selected':'' ?>><?= h($a['name']) ?></option>
                  <?php endforeach ?>
                </select>
              </div>
              <?php endif ?>
              <div class="col-12">
                <label class="form-label">Problem Description <span class="text-danger">*</span></label>
                <textarea class="form-control" name="problem" rows="5" required
                          placeholder="Describe the problem, complaint, or feedback in detail…"><?= h($_POST['problem'] ?? '') ?></textarea>
              </div>
            </div>
            <div class="d-flex gap-2 mt-4">
              <button type="submit" class="btn btn-danger">
                <i class="bi bi-chat-square-text me-2"></i>Open Thread
              </button>
              <a href="<?= BASE_URL ?>/modules/feedback/index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require ROOT . '/partials/footer.php'; ?>
