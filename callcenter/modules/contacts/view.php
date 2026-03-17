<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . '/modules/contacts/index.php');

$contact = db_row(
    "SELECT c.*, sp.employee_id, sp.department, sp.position, sp.join_date, sp.exit_date, sp.is_active AS staff_active,
            sp.successor_contact_id,
            sc.name AS successor_name,
            u.name AS assigned_name,
            ub.name AS created_by_name,
            (SELECT GROUP_CONCAT(tag SEPARATOR ', ') FROM contact_tags WHERE contact_id=c.id) AS tags
     FROM contacts c
     LEFT JOIN staff_profiles sp ON sp.contact_id = c.id
     LEFT JOIN contacts sc ON sc.id = sp.successor_contact_id
     LEFT JOIN users u ON u.id = c.assigned_to
     LEFT JOIN users ub ON ub.id = c.created_by
     WHERE c.id = ?",
    [$id]
);
if (!$contact) redirect(BASE_URL . '/modules/contacts/index.php');

// Call history
$calls = db_rows(
    "SELECT ca.id, ca.direction, ca.started_at, ca.duration_seconds,
            co.name AS outcome, co.color, u.name AS agent_name,
            cs.key_points, cs.sentiment, cs.follow_up_required
     FROM calls ca
     LEFT JOIN call_outcomes co ON co.id = ca.outcome_id
     LEFT JOIN users u ON u.id = ca.agent_id
     LEFT JOIN call_summary cs ON cs.call_id = ca.id
     WHERE ca.contact_id = ?
     ORDER BY ca.started_at DESC LIMIT 50",
    [$id]
);

// SMS history
$sms = db_rows(
    "SELECT s.id, s.sent_at, s.message, s.status, u.name AS agent_name
     FROM sms_log s LEFT JOIN users u ON u.id = s.agent_id
     WHERE s.contact_id = ? ORDER BY s.sent_at DESC LIMIT 20",
    [$id]
);

// Feedback threads
$threads = db_rows(
    "SELECT ft.id, ft.title, ft.status, ft.priority, ft.created_at,
            u.name AS created_by_name,
            (SELECT COUNT(*) FROM feedback_entries fe WHERE fe.thread_id=ft.id) AS entry_count
     FROM feedback_threads ft
     LEFT JOIN users u ON u.id = ft.created_by
     WHERE ft.contact_id = ?
     ORDER BY ft.created_at DESC",
    [$id]
);

// Callbacks
$callbacks = db_rows(
    "SELECT cb.id, cb.scheduled_at, cb.status, cb.notes, u.name AS assigned_name
     FROM callbacks cb LEFT JOIN users u ON u.id = cb.assigned_to
     WHERE cb.contact_id = ? ORDER BY cb.scheduled_at DESC LIMIT 10",
    [$id]
);

// Tasks
$tasks = db_rows(
    "SELECT t.id, t.title, t.status, t.priority, t.due_date,
            tt.name AS type_name, tt.icon, u.name AS assigned_name
     FROM tasks t
     LEFT JOIN task_types tt ON tt.id = t.type_id
     LEFT JOIN users u ON u.id = t.assigned_to
     WHERE t.contact_id = ? ORDER BY t.created_at DESC LIMIT 10",
    [$id]
);

// Sales groups
$groups = db_rows(
    "SELECT sg.id, sg.name, sl.name AS level_name, sl.color, sgm.role_in_group, sgm.joined_date
     FROM sales_group_members sgm
     JOIN sales_groups sg ON sg.id = sgm.group_id
     JOIN sales_levels sl ON sl.id = sg.level_id
     WHERE sgm.contact_id = ? AND sgm.is_active = 1",
    [$id]
);

// Stats
$total_calls     = (int) db_val("SELECT COUNT(*) FROM calls WHERE contact_id=?", [$id]);
$total_talk_sec  = (int) db_val("SELECT COALESCE(SUM(duration_seconds),0) FROM calls WHERE contact_id=?", [$id]);
$last_call       = db_val("SELECT MAX(started_at) FROM calls WHERE contact_id=?", [$id]);
$open_threads    = (int) db_val("SELECT COUNT(*) FROM feedback_threads WHERE contact_id=? AND status IN ('open','in_progress')", [$id]);

$page_title  = h($contact['name']);
$active_page = 'contacts';

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/contacts/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="ms-2"><?= h($contact['name']) ?></h5>
  <?= type_badge($contact['contact_type']) ?>
  <?= status_badge($contact['status']) ?>
  <div class="ms-auto d-flex gap-2">
    <button data-print class="btn btn-sm btn-outline-secondary no-print">
      <i class="bi bi-printer"></i>
    </button>
    <?php
    $wa = sprintf("👤 Contact: %s\nPhone: %s%s%s\n%s\nTotal Calls: %d | Talk: %s\nLast Call: %s",
        $contact['name'], $contact['phone'],
        $contact['alt_phone'] ? ' / '.$contact['alt_phone'] : '',
        $contact['company'] ? "\nCompany: ".$contact['company'] : '',
        ucwords(str_replace('_',' ',$contact['contact_type'])),
        $total_calls, format_duration($total_talk_sec),
        $last_call ? format_datetime($last_call) : 'Never'
    );
    ?>
    <button data-copy-wa data-text="<?= h($wa) ?>" class="btn btn-sm btn-outline-success no-print">
      <i class="bi bi-whatsapp me-1"></i>Copy
    </button>
    <a href="<?= BASE_URL ?>/modules/workspace/index.php?contact_id=<?= $id ?>"
       class="btn btn-sm btn-success no-print">
      <i class="bi bi-telephone-plus me-1"></i>Call
    </a>
    <?php if (can('edit')): ?>
    <a href="<?= BASE_URL ?>/modules/contacts/form.php?id=<?= $id ?>"
       class="btn btn-sm btn-outline-primary no-print">
      <i class="bi bi-pencil me-1"></i>Edit
    </a>
    <?php endif ?>
  </div>
</div>

<div class="page-body">
  <div class="row g-3">

    <!-- ── Contact Info ──────────────────────────────── -->
    <div class="col-12 col-md-4">

      <!-- Profile card -->
      <div class="card mb-3">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div style="width:56px;height:56px;border-radius:50%;background:#e9ecef;
                        display:flex;align-items:center;justify-content:center;
                        font-size:1.8rem;color:#6c757d;flex-shrink:0">
              <i class="bi bi-person-fill"></i>
            </div>
            <div>
              <div class="fw-bold fs-6"><?= h($contact['name']) ?></div>
              <div class="text-muted small"><?= h($contact['contact_type']) ?></div>
            </div>
          </div>

          <table class="table table-sm table-borderless mb-0" style="font-size:.8rem">
            <tr><td class="text-muted w-40">Phone</td><td class="fw-semibold"><?= h($contact['phone']) ?></td></tr>
            <?php if ($contact['alt_phone']): ?>
            <tr><td class="text-muted">Alt Phone</td><td><?= h($contact['alt_phone']) ?></td></tr>
            <?php endif ?>
            <?php if ($contact['email']): ?>
            <tr><td class="text-muted">Email</td><td><?= h($contact['email']) ?></td></tr>
            <?php endif ?>
            <?php if ($contact['company']): ?>
            <tr><td class="text-muted">Company</td><td><?= h($contact['company']) ?></td></tr>
            <?php endif ?>
            <?php if ($contact['assigned_name']): ?>
            <tr><td class="text-muted">Assigned</td><td><?= h($contact['assigned_name']) ?></td></tr>
            <?php endif ?>
            <tr><td class="text-muted">Added</td><td><?= format_date($contact['created_at']) ?></td></tr>
          </table>

          <?php if ($contact['notes']): ?>
          <div class="mt-2 pt-2 border-top">
            <div class="text-muted small mb-1">Notes</div>
            <div style="font-size:.8rem"><?= nl2br(h($contact['notes'])) ?></div>
          </div>
          <?php endif ?>

          <?php if ($contact['tags']): ?>
          <div class="mt-2 d-flex flex-wrap gap-1">
            <?php foreach (explode(', ', $contact['tags']) as $tag): ?>
            <span class="badge bg-light text-dark border" style="font-size:.7rem"><?= h(trim($tag)) ?></span>
            <?php endforeach ?>
          </div>
          <?php endif ?>
        </div>
      </div>

      <!-- Staff profile if applicable -->
      <?php if ($contact['contact_type'] === 'internal_staff' && $contact['position']): ?>
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-building me-2"></i>Staff Info</div>
        <div class="card-body" style="font-size:.8rem">
          <?php if ($contact['employee_id']): ?>
          <div><span class="text-muted">Employee ID:</span> <?= h($contact['employee_id']) ?></div>
          <?php endif ?>
          <div><span class="text-muted">Position:</span> <?= h($contact['position']) ?></div>
          <div><span class="text-muted">Department:</span> <?= h($contact['department'] ?? '—') ?></div>
          <?php if ($contact['join_date']): ?>
          <div><span class="text-muted">Joined:</span> <?= format_date($contact['join_date']) ?></div>
          <?php endif ?>
          <?php if ($contact['successor_name']): ?>
          <div class="mt-2">
            <span class="badge bg-info text-dark">Succeeded by:
              <a href="view.php?id=<?= $contact['successor_contact_id'] ?>"><?= h($contact['successor_name']) ?></a>
            </span>
          </div>
          <?php endif ?>
        </div>
      </div>
      <?php endif ?>

      <!-- Sales groups -->
      <?php if (!empty($groups)): ?>
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-diagram-3 me-2"></i>Sales Groups</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($groups as $g): ?>
          <li class="list-group-item py-2" style="font-size:.8rem">
            <span class="badge" style="background:<?= h($g['color']) ?>"><?= h($g['level_name']) ?></span>
            <a href="<?= BASE_URL ?>/modules/sales_network/index.php?group=<?= $g['id'] ?>">
              <?= h($g['name']) ?>
            </a>
            <?php if ($g['role_in_group']): ?>
            <span class="text-muted"> · <?= h($g['role_in_group']) ?></span>
            <?php endif ?>
          </li>
          <?php endforeach ?>
        </ul>
      </div>
      <?php endif ?>

      <!-- Stats -->
      <div class="card">
        <div class="card-header"><i class="bi bi-bar-chart me-2"></i>Stats</div>
        <div class="card-body py-2">
          <div class="row g-2 text-center">
            <div class="col-4">
              <div class="fw-bold"><?= $total_calls ?></div>
              <div class="text-muted small" style="font-size:.7rem">Total Calls</div>
            </div>
            <div class="col-4">
              <div class="fw-bold"><?= format_duration($total_talk_sec) ?></div>
              <div class="text-muted small" style="font-size:.7rem">Talk Time</div>
            </div>
            <div class="col-4">
              <div class="fw-bold"><?= $open_threads ?></div>
              <div class="text-muted small" style="font-size:.7rem">Open Threads</div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /col-md-4 -->

    <!-- ── Interaction Timeline ──────────────────────── -->
    <div class="col-12 col-md-8">

      <!-- Tabs -->
      <ul class="nav nav-tabs mb-3 no-print" id="contactTabs">
        <li class="nav-item">
          <a class="nav-link active" data-bs-toggle="tab" href="#tabCalls">
            <i class="bi bi-telephone me-1"></i>Calls
            <span class="badge bg-secondary ms-1"><?= count($calls) ?></span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="tab" href="#tabSms">
            <i class="bi bi-chat me-1"></i>SMS
            <span class="badge bg-secondary ms-1"><?= count($sms) ?></span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="tab" href="#tabThreads">
            <i class="bi bi-chat-square-text me-1"></i>Threads
            <?php if ($open_threads): ?><span class="badge bg-danger ms-1"><?= $open_threads ?></span><?php endif ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="tab" href="#tabCallbacks">
            <i class="bi bi-alarm me-1"></i>Callbacks
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="tab" href="#tabTasks">
            <i class="bi bi-check-square me-1"></i>Tasks
          </a>
        </li>
      </ul>

      <div class="tab-content">

        <!-- Calls tab -->
        <div class="tab-pane fade show active" id="tabCalls">
          <div class="card">
            <div class="card-header d-flex align-items-center no-print">
              <span>Call History</span>
              <a href="<?= BASE_URL ?>/modules/workspace/index.php?contact_id=<?= $id ?>"
                 class="btn btn-sm btn-primary ms-auto">
                <i class="bi bi-telephone-plus me-1"></i>Log Call
              </a>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                  <tr><th>Date</th><th>Dir</th><th>Outcome</th><th>Duration</th><th>Agent</th><th>Summary</th><th class="no-print"></th></tr>
                </thead>
                <tbody>
                  <?php foreach ($calls as $c): ?>
                  <tr>
                    <td style="font-size:.75rem">
                      <?= format_date($c['started_at'],'d M Y') ?><br>
                      <span class="text-muted"><?= date('h:i A', strtotime($c['started_at'])) ?></span>
                    </td>
                    <td>
                      <?= $c['direction']==='inbound'
                        ? '<i class="bi bi-telephone-inbound-fill text-success"></i>'
                        : '<i class="bi bi-telephone-outbound-fill text-primary"></i>' ?>
                    </td>
                    <td>
                      <?php if ($c['outcome']): ?>
                      <span class="badge" style="background:<?= h($c['color']) ?>;font-size:.65rem"><?= h($c['outcome']) ?></span>
                      <?php endif ?>
                    </td>
                    <td><?= format_duration((int)$c['duration_seconds']) ?></td>
                    <td class="text-muted small"><?= h($c['agent_name']) ?></td>
                    <td style="font-size:.72rem;max-width:120px" class="text-truncate">
                      <?php if ($c['sentiment']): ?>
                      <span class="badge <?= $c['sentiment']==='positive'?'bg-success':($c['sentiment']==='negative'?'bg-danger':'bg-secondary') ?>"
                            style="font-size:.6rem"><?= ucfirst($c['sentiment']) ?></span>
                      <?php endif ?>
                      <?= h(truncate($c['key_points']??'',50)) ?>
                    </td>
                    <td class="no-print">
                      <a href="<?= BASE_URL ?>/modules/calls/view.php?id=<?= $c['id'] ?>"
                         class="btn btn-xs btn-outline-secondary py-0 px-1"><i class="bi bi-eye"></i></a>
                    </td>
                  </tr>
                  <?php endforeach ?>
                  <?php if (empty($calls)): ?>
                  <tr><td colspan="7" class="text-center text-muted py-3">No calls yet</td></tr>
                  <?php endif ?>
                </tbody>
              </table>
            </div>
          </div>
        </div><!-- /tabCalls -->

        <!-- SMS tab -->
        <div class="tab-pane fade" id="tabSms">
          <div class="card">
            <div class="card-header d-flex align-items-center no-print">
              SMS History
              <a href="<?= BASE_URL ?>/modules/sms/form.php?contact_id=<?= $id ?>" class="btn btn-sm btn-success ms-auto">
                <i class="bi bi-send me-1"></i>Send SMS
              </a>
            </div>
            <?php foreach ($sms as $s): ?>
            <div class="px-3 py-2 border-bottom" style="font-size:.8rem">
              <div class="d-flex justify-content-between">
                <span class="fw-semibold"><?= h($s['agent_name']) ?></span>
                <span class="text-muted"><?= time_ago($s['sent_at']) ?></span>
              </div>
              <div><?= h($s['message']) ?></div>
              <div><?= status_badge($s['status']) ?></div>
            </div>
            <?php endforeach ?>
            <?php if (empty($sms)): ?>
            <div class="text-center text-muted py-4 small">No SMS sent</div>
            <?php endif ?>
          </div>
        </div>

        <!-- Threads tab -->
        <div class="tab-pane fade" id="tabThreads">
          <div class="card">
            <div class="card-header d-flex align-items-center no-print">
              Feedback Threads
              <a href="<?= BASE_URL ?>/modules/feedback/form.php?contact_id=<?= $id ?>" class="btn btn-sm btn-danger ms-auto">
                <i class="bi bi-plus me-1"></i>New Thread
              </a>
            </div>
            <?php foreach ($threads as $t): ?>
            <div class="px-3 py-2 border-bottom">
              <a href="<?= BASE_URL ?>/modules/feedback/thread.php?id=<?= $t['id'] ?>" class="fw-semibold text-decoration-none">
                <?= h($t['title']) ?>
              </a>
              <div class="d-flex gap-2 mt-1">
                <?= status_badge($t['status']) ?>
                <span class="badge bg-<?= $t['priority']==='urgent'?'danger':($t['priority']==='high'?'warning text-dark':'secondary') ?>">
                  <?= ucfirst($t['priority']) ?>
                </span>
                <span class="text-muted small"><?= $t['entry_count'] ?> replies · <?= time_ago($t['created_at']) ?></span>
              </div>
            </div>
            <?php endforeach ?>
            <?php if (empty($threads)): ?>
            <div class="text-center text-muted py-4 small">No feedback threads</div>
            <?php endif ?>
          </div>
        </div>

        <!-- Callbacks tab -->
        <div class="tab-pane fade" id="tabCallbacks">
          <div class="card">
            <div class="card-header no-print">Callbacks</div>
            <?php foreach ($callbacks as $cb): ?>
            <div class="px-3 py-2 border-bottom d-flex align-items-center gap-2" style="font-size:.8rem">
              <?= status_badge($cb['status']) ?>
              <span><?= format_datetime($cb['scheduled_at']) ?></span>
              <span class="text-muted">→ <?= h($cb['assigned_name']) ?></span>
              <?php if ($cb['notes']): ?><span class="text-muted text-truncate"><?= h($cb['notes']) ?></span><?php endif ?>
            </div>
            <?php endforeach ?>
            <?php if (empty($callbacks)): ?>
            <div class="text-center text-muted py-4 small">No callbacks</div>
            <?php endif ?>
          </div>
        </div>

        <!-- Tasks tab -->
        <div class="tab-pane fade" id="tabTasks">
          <div class="card">
            <div class="card-header no-print">Tasks</div>
            <?php foreach ($tasks as $t): ?>
            <div class="px-3 py-2 border-bottom d-flex align-items-center gap-2" style="font-size:.8rem">
              <?= status_badge($t['status']) ?>
              <span class="fw-semibold"><?= h($t['title']) ?></span>
              <span class="text-muted ms-auto"><?= h($t['assigned_name']) ?></span>
            </div>
            <?php endforeach ?>
            <?php if (empty($tasks)): ?>
            <div class="text-center text-muted py-4 small">No tasks</div>
            <?php endif ?>
          </div>
        </div>

      </div><!-- /tab-content -->
    </div><!-- /col-md-8 -->

  </div>
</div>

<?php require ROOT . '/partials/footer.php'; ?>
