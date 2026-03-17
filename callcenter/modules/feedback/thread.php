<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . '/modules/feedback/index.php');

$thread = db_row(
    "SELECT ft.*, c.id AS contact_id, c.name AS contact_name, c.phone AS contact_phone,
             u.name AS creator_name, ua.name AS assigned_name
     FROM feedback_threads ft
     LEFT JOIN contacts c ON c.id = ft.contact_id
     LEFT JOIN users u ON u.id = ft.created_by
     LEFT JOIN users ua ON ua.id = ft.assigned_to
     WHERE ft.id=?",
    [$id]
);
if (!$thread) redirect(BASE_URL . '/modules/feedback/index.php');

$uid   = current_user_id();
$isAll = in_array(current_role(), ['super_admin','senior_executive']);

// ── POST: Add entry ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_entry') {
    require_csrf();
    $entry_type = clean($_POST['entry_type'] ?? 'note');
    $content    = clean($_POST['content']    ?? '');

    if ($entry_type && $content) {
        db_exec(
            "INSERT INTO feedback_entries (thread_id, agent_id, entry_type, content)
             VALUES (?, ?, ?, ?)",
            [$id, $uid, $entry_type, $content]
        );
        audit_log('add_feedback_entry', 'feedback_threads', $id);
        flash_success('Entry added.');
    }
    redirect(BASE_URL . '/modules/feedback/thread.php?id=' . $id . '#bottom');
}

// ── POST: Update status ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    require_csrf();
    $new_status = clean($_POST['new_status'] ?? '');
    $valid = ['open','in_progress','resolved','closed'];
    if (in_array($new_status, $valid)) {
        $resolved_at = in_array($new_status, ['resolved','closed']) ? 'NOW()' : 'NULL';
        db_exec(
            "UPDATE feedback_threads SET status=?, resolved_at=" . $resolved_at . " WHERE id=?",
            [$new_status, $id]
        );
        // Add status change entry
        db_exec(
            "INSERT INTO feedback_entries (thread_id, agent_id, entry_type, content)
             VALUES (?, ?, 'update', ?)",
            [$id, $uid, "Status changed to: " . ucwords(str_replace('_',' ',$new_status))]
        );
        audit_log('update_feedback_status', 'feedback_threads', $id, "Status: $new_status");
        flash_success('Status updated.');
    }
    redirect(BASE_URL . '/modules/feedback/thread.php?id=' . $id);
}

// ── POST: Update assignment ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reassign' && $isAll) {
    require_csrf();
    $new_agent = (int)($_POST['assigned_to'] ?? 0);
    db_exec("UPDATE feedback_threads SET assigned_to=? WHERE id=?", [$new_agent ?: null, $id]);
    audit_log('reassign_feedback', 'feedback_threads', $id);
    flash_success('Reassigned.');
    redirect(BASE_URL . '/modules/feedback/thread.php?id=' . $id);
}

// Load entries
$entries = db_rows(
    "SELECT fe.*, u.name AS agent_name
     FROM feedback_entries fe
     LEFT JOIN users u ON u.id = fe.agent_id
     WHERE fe.thread_id=?
     ORDER BY fe.created_at ASC",
    [$id]
);

$agents = $isAll ? db_rows("SELECT id, name FROM users WHERE status='active' AND role != 'viewer' ORDER BY name") : [];

// WhatsApp summary
$wa_lines = ["🧵 Feedback Thread #$id: " . $thread['title']];
if ($thread['contact_name']) $wa_lines[] = "Contact: " . $thread['contact_name'];
$wa_lines[] = "Status: " . ucwords(str_replace('_',' ',$thread['status'])) . " | Priority: " . ucfirst($thread['priority']);
$wa_lines[] = "Opened: " . format_datetime($thread['created_at']);
foreach ($entries as $e) {
    $wa_lines[] = "---";
    $wa_lines[] = "[" . strtoupper($e['entry_type']) . "] " . $e['agent_name'] . " (" . format_datetime($e['created_at']) . ")";
    $wa_lines[] = $e['content'];
}
$wa_text = implode("\n", $wa_lines);

$page_title  = 'Thread: ' . h($thread['title']);
$active_page = 'feedback';

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/feedback/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-chat-square-text-fill text-danger ms-1"></i>
  <h5 class="ms-1 text-truncate" style="max-width:300px"><?= h($thread['title']) ?></h5>
  <div class="ms-auto d-flex gap-2 no-print">
    <button data-print class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-printer"></i>
    </button>
    <button class="btn btn-sm btn-outline-success" data-copy-wa data-text="<?= h($wa_text) ?>">
      <i class="bi bi-whatsapp me-1"></i>Copy
    </button>
  </div>
</div>

<div class="page-body">
  <div class="row g-3">

    <!-- Thread entries -->
    <div class="col-12 col-md-8">

      <!-- Status badges + actions -->
      <div class="card mb-3 no-print">
        <div class="card-body py-2 d-flex align-items-center gap-2 flex-wrap">
          <?php
          $priorityBadge = ['urgent'=>'danger','high'=>'warning','medium'=>'primary','low'=>'secondary'];
          $pc = $priorityBadge[$thread['priority']] ?? 'secondary';
          $statusBadge = ['open'=>'danger','in_progress'=>'warning','resolved'=>'success','closed'=>'secondary'];
          $sc = $statusBadge[$thread['status']] ?? 'secondary';
          ?>
          <span class="badge bg-<?= $sc ?>">
            <?= ucwords(str_replace('_',' ',$thread['status'])) ?>
          </span>
          <span class="badge bg-<?= $pc ?>"><?= ucfirst($thread['priority']) ?> Priority</span>

          <!-- Status change buttons -->
          <?php if ($thread['status'] !== 'closed'): ?>
          <?php foreach (['in_progress'=>['warning','clock'],'resolved'=>['success','check2-circle'],'closed'=>['secondary','x-circle']] as $ns => [$cls,$ico]): ?>
          <?php if ($thread['status'] !== $ns): ?>
          <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="new_status" value="<?= $ns ?>">
            <button type="submit" class="btn btn-sm btn-outline-<?= $cls ?>">
              <i class="bi bi-<?= $ico ?> me-1"></i><?= ucwords(str_replace('_',' ',$ns)) ?>
            </button>
          </form>
          <?php endif ?>
          <?php endforeach ?>
          <?php endif ?>

          <span class="text-muted small ms-auto">
            <?= count($entries) ?> entries · Opened <?= time_ago($thread['created_at']) ?>
          </span>
        </div>
      </div>

      <!-- Entries -->
      <?php
      $typeColors = [
        'feedback'  => 'border-danger',
        'update'    => 'border-warning',
        'solution'  => 'border-success',
        'follow_up' => 'border-primary',
        'note'      => 'border-secondary',
      ];
      $typeIcons = [
        'feedback'  => 'chat-text',
        'update'    => 'arrow-clockwise',
        'solution'  => 'check2-circle',
        'follow_up' => 'calendar-check',
        'note'      => 'sticky',
      ];
      ?>
      <?php foreach ($entries as $e): ?>
      <div class="card mb-2 border-start border-4 <?= $typeColors[$e['entry_type']] ?? 'border-secondary' ?> thread-entry">
        <div class="card-body py-2 px-3">
          <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bi bi-<?= $typeIcons[$e['entry_type']] ?? 'chat' ?> text-muted"></i>
            <span class="small fw-semibold"><?= h($e['agent_name'] ?? 'System') ?></span>
            <span class="badge bg-light text-dark border" style="font-size:.6rem">
              <?= ucwords(str_replace('_',' ',$e['entry_type'])) ?>
            </span>
            <span class="text-muted small ms-auto"><?= format_datetime($e['created_at']) ?></span>
          </div>
          <div style="white-space:pre-wrap;font-size:.88rem"><?= nl2br(h($e['content'])) ?></div>
        </div>
      </div>
      <?php endforeach ?>

      <div id="bottom"></div>

      <!-- Add entry form -->
      <?php if ($thread['status'] !== 'closed'): ?>
      <div class="card mt-3">
        <div class="card-header small fw-semibold">Add Entry</div>
        <div class="card-body">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_entry">
            <div class="row g-2 mb-2">
              <div class="col-12 col-md-4">
                <select class="form-select form-select-sm" name="entry_type">
                  <option value="note">Note</option>
                  <option value="update">Update</option>
                  <option value="solution">Solution</option>
                  <option value="follow_up">Follow-up</option>
                  <option value="feedback">Feedback</option>
                </select>
              </div>
            </div>
            <textarea class="form-control mb-2" name="content" rows="3" required
                      placeholder="Add your note, update, or solution…"></textarea>
            <button type="submit" class="btn btn-sm btn-primary">
              <i class="bi bi-send me-1"></i>Add Entry
            </button>
          </form>
        </div>
      </div>
      <?php endif ?>

    </div>

    <!-- Sidebar info -->
    <div class="col-12 col-md-4">

      <!-- Contact -->
      <?php if ($thread['contact_id']): ?>
      <div class="card mb-3">
        <div class="card-header small fw-semibold">
          <i class="bi bi-person me-1"></i>Contact
        </div>
        <div class="card-body py-2">
          <a href="<?= BASE_URL ?>/modules/contacts/view.php?id=<?= $thread['contact_id'] ?>"
             class="fw-semibold"><?= h($thread['contact_name']) ?></a>
          <div class="text-muted small"><?= h($thread['contact_phone'] ?? '') ?></div>
          <div class="mt-2 no-print">
            <a href="<?= BASE_URL ?>/modules/workspace/index.php?contact_id=<?= $thread['contact_id'] ?>"
               class="btn btn-sm btn-outline-primary btn-sm">
              <i class="bi bi-telephone-plus me-1"></i>Call
            </a>
          </div>
        </div>
      </div>
      <?php endif ?>

      <!-- Thread details -->
      <div class="card mb-3">
        <div class="card-header small fw-semibold">Thread Details</div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex py-2">
            <span class="text-muted small me-auto">Created by</span>
            <span class="small"><?= h($thread['creator_name'] ?? '—') ?></span>
          </li>
          <li class="list-group-item d-flex py-2">
            <span class="text-muted small me-auto">Assigned to</span>
            <span class="small"><?= h($thread['assigned_name'] ?? 'Unassigned') ?></span>
          </li>
          <li class="list-group-item d-flex py-2">
            <span class="text-muted small me-auto">Opened</span>
            <span class="small"><?= format_datetime($thread['created_at']) ?></span>
          </li>
          <?php if ($thread['resolved_at']): ?>
          <li class="list-group-item d-flex py-2">
            <span class="text-muted small me-auto">Resolved</span>
            <span class="small"><?= format_datetime($thread['resolved_at']) ?></span>
          </li>
          <?php endif ?>
        </ul>
      </div>

      <!-- Reassign (senior+) -->
      <?php if ($isAll && !empty($agents) && $thread['status'] !== 'closed'): ?>
      <div class="card no-print">
        <div class="card-header small fw-semibold">Reassign</div>
        <div class="card-body py-2">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reassign">
            <select class="form-select form-select-sm mb-2" name="assigned_to">
              <option value="">Unassigned</option>
              <?php foreach ($agents as $a): ?>
              <option value="<?= $a['id'] ?>" <?= $thread['assigned_to']==$a['id']?'selected':'' ?>>
                <?= h($a['name']) ?>
              </option>
              <?php endforeach ?>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-primary w-100">Reassign</button>
          </form>
        </div>
      </div>
      <?php endif ?>

    </div>

  </div>
</div>

<?php require ROOT . '/partials/footer.php'; ?>
