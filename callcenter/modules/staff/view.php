<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . '/modules/staff/index.php');

$contact = db_row("SELECT * FROM contacts WHERE id=? AND contact_type='internal_staff'", [$id]);
if (!$contact) redirect(BASE_URL . '/modules/staff/index.php');

$profile = db_row("SELECT * FROM staff_profiles WHERE contact_id=?", [$id]);
$position_history = db_rows(
    "SELECT cph.*, c.name AS replaced_by_name FROM contact_position_history cph
     LEFT JOIN contacts c ON c.id = cph.replaced_by_contact_id
     WHERE cph.contact_id=? ORDER BY cph.effective_from DESC",
    [$id]
);

$predecessor = null;
if ($profile && $profile['is_active']) {
    // Find if anyone's successor is this person
    $predecessor = db_row(
        "SELECT c.id, c.name, sp.position, sp.department FROM staff_profiles sp
         JOIN contacts c ON c.id = sp.contact_id
         WHERE sp.successor_contact_id=?",
        [$id]
    );
}

$successor = null;
if ($profile && $profile['successor_contact_id']) {
    $successor = db_row("SELECT id, name FROM contacts WHERE id=?", [$profile['successor_contact_id']]);
}

// Call history
$calls = db_rows(
    "SELECT ca.id, ca.direction, ca.started_at, ca.duration_seconds, ca.notes,
            co.name AS outcome, co.color
     FROM calls ca
     LEFT JOIN call_outcomes co ON co.id = ca.outcome_id
     WHERE ca.contact_id=?
     ORDER BY ca.started_at DESC LIMIT 20",
    [$id]
);

// Tasks assigned to this contact's related user (or tasks about this contact)
$tasks = db_rows(
    "SELECT t.*, tt.name AS type_name, tt.icon AS type_icon,
            u.name AS assigned_name
     FROM tasks t
     LEFT JOIN task_types tt ON tt.id = t.type_id
     LEFT JOIN users u ON u.id = t.assigned_to
     WHERE t.contact_id=?
     ORDER BY t.created_at DESC LIMIT 10",
    [$id]
);

// Open feedback threads
$threads = db_rows(
    "SELECT ft.id, ft.title, ft.status, ft.priority, ft.created_at,
            u.name AS creator_name
     FROM feedback_threads ft
     LEFT JOIN users u ON u.id = ft.created_by
     WHERE ft.contact_id=?
     ORDER BY ft.created_at DESC LIMIT 10",
    [$id]
);

$page_title  = h($contact['name']) . ' — Staff Profile';
$active_page = 'staff';

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/staff/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-person-badge-fill text-primary ms-1"></i>
  <h5 class="ms-1"><?= h($contact['name']) ?></h5>
  <div class="ms-auto d-flex gap-2 no-print">
    <button data-print class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-printer"></i>
    </button>
    <a href="<?= BASE_URL ?>/modules/workspace/index.php?contact_id=<?= $id ?>"
       class="btn btn-sm btn-primary">
      <i class="bi bi-telephone-plus me-1"></i>Call
    </a>
    <?php if (can('edit', 'staff')): ?>
    <a href="<?= BASE_URL ?>/modules/staff/form.php?id=<?= $id ?>"
       class="btn btn-sm btn-outline-warning">
      <i class="bi bi-pencil me-1"></i>Edit
    </a>
    <?php endif ?>
  </div>
</div>

<div class="page-body">
  <div class="row g-3">

    <!-- Left: Profile -->
    <div class="col-12 col-md-4">

      <div class="card mb-3">
        <div class="card-body text-center pt-4 pb-3">
          <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3"
               style="width:72px;height:72px;font-size:1.8rem">
            <?= strtoupper(mb_substr($contact['name'], 0, 1)) ?>
          </div>
          <h5 class="mb-1"><?= h($contact['name']) ?></h5>
          <?php if ($profile): ?>
          <div class="text-muted"><?= h($profile['position'] ?? '') ?></div>
          <div class="text-muted small"><?= h($profile['department'] ?? '') ?></div>
          <div class="mt-2">
            <?php if ($profile['is_active']): ?>
            <span class="badge bg-success">Active</span>
            <?php else: ?>
            <span class="badge bg-secondary">Former</span>
            <?php endif ?>
          </div>
          <?php endif ?>
        </div>
        <ul class="list-group list-group-flush">
          <?php if ($profile && $profile['employee_id']): ?>
          <li class="list-group-item d-flex py-2">
            <span class="text-muted small me-auto">Employee ID</span>
            <span class="small fw-semibold"><?= h($profile['employee_id']) ?></span>
          </li>
          <?php endif ?>
          <li class="list-group-item d-flex py-2">
            <span class="text-muted small me-auto">Phone</span>
            <span class="small"><?= h($contact['phone']) ?></span>
          </li>
          <?php if ($contact['alt_phone']): ?>
          <li class="list-group-item d-flex py-2">
            <span class="text-muted small me-auto">Alt. Phone</span>
            <span class="small"><?= h($contact['alt_phone']) ?></span>
          </li>
          <?php endif ?>
          <?php if ($contact['email']): ?>
          <li class="list-group-item d-flex py-2">
            <span class="text-muted small me-auto">Email</span>
            <span class="small"><?= h($contact['email']) ?></span>
          </li>
          <?php endif ?>
          <?php if ($profile): ?>
          <?php if ($profile['join_date']): ?>
          <li class="list-group-item d-flex py-2">
            <span class="text-muted small me-auto">Joined</span>
            <span class="small"><?= format_date($profile['join_date']) ?></span>
          </li>
          <?php endif ?>
          <?php if ($profile['exit_date']): ?>
          <li class="list-group-item d-flex py-2">
            <span class="text-muted small me-auto">Exit Date</span>
            <span class="small"><?= format_date($profile['exit_date']) ?></span>
          </li>
          <?php endif ?>
          <?php endif ?>
        </ul>
      </div>

      <!-- Succession -->
      <?php if ($predecessor || $successor): ?>
      <div class="card mb-3">
        <div class="card-header small fw-semibold">Succession Chain</div>
        <ul class="list-group list-group-flush">
          <?php if ($predecessor): ?>
          <li class="list-group-item py-2">
            <div class="text-muted" style="font-size:.7rem">PRECEDED BY</div>
            <a href="<?= BASE_URL ?>/modules/staff/view.php?id=<?= $predecessor['id'] ?>">
              <?= h($predecessor['name']) ?>
            </a>
            <div class="text-muted small"><?= h($predecessor['position'] ?? '') ?></div>
          </li>
          <?php endif ?>
          <li class="list-group-item py-2 bg-light">
            <div class="text-muted" style="font-size:.7rem">CURRENT</div>
            <span class="fw-semibold"><?= h($contact['name']) ?></span>
          </li>
          <?php if ($successor): ?>
          <li class="list-group-item py-2">
            <div class="text-muted" style="font-size:.7rem">SUCCEEDED BY</div>
            <a href="<?= BASE_URL ?>/modules/staff/view.php?id=<?= $successor['id'] ?>">
              <?= h($successor['name']) ?>
            </a>
          </li>
          <?php endif ?>
        </ul>
      </div>
      <?php endif ?>

      <!-- Position history -->
      <?php if (!empty($position_history)): ?>
      <div class="card">
        <div class="card-header small fw-semibold">Position History</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($position_history as $ph): ?>
          <li class="list-group-item py-2">
            <div class="fw-semibold small"><?= h($ph['position']) ?></div>
            <?php if ($ph['department']): ?>
            <div class="text-muted" style="font-size:.7rem"><?= h($ph['department']) ?></div>
            <?php endif ?>
            <div class="text-muted" style="font-size:.7rem">
              <?= $ph['effective_from'] ? format_date($ph['effective_from']) : '?' ?>
              <?= $ph['effective_to'] ? ' – ' . format_date($ph['effective_to']) : '' ?>
            </div>
          </li>
          <?php endforeach ?>
        </ul>
      </div>
      <?php endif ?>

    </div>

    <!-- Right: Activity -->
    <div class="col-12 col-md-8">

      <!-- Call history -->
      <div class="card mb-3">
        <div class="card-header d-flex align-items-center">
          <i class="bi bi-telephone-fill me-2 text-primary"></i>Call History
          <span class="badge bg-primary ms-2"><?= count($calls) ?></span>
        </div>
        <?php if (!empty($calls)): ?>
        <ul class="list-group list-group-flush" style="max-height:280px;overflow-y:auto">
          <?php foreach ($calls as $ca): ?>
          <li class="list-group-item py-2">
            <a href="<?= BASE_URL ?>/modules/calls/view.php?id=<?= $ca['id'] ?>" class="text-decoration-none">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-telephone-<?= $ca['direction']==='inbound' ? 'inbound' : 'outbound' ?>-fill
                   <?= $ca['direction']==='inbound' ? 'text-success' : 'text-primary' ?>"></i>
                <div class="flex-grow-1 min-w-0">
                  <?php if ($ca['outcome']): ?>
                  <span class="badge" style="background:<?= h($ca['color']??'#999') ?>;font-size:.62rem">
                    <?= h($ca['outcome']) ?>
                  </span>
                  <?php endif ?>
                  <span class="text-muted small ms-1"><?= format_duration((int)$ca['duration_seconds']) ?></span>
                  <div class="text-muted" style="font-size:.7rem"><?= time_ago($ca['started_at']) ?></div>
                </div>
              </div>
            </a>
          </li>
          <?php endforeach ?>
        </ul>
        <?php else: ?>
        <div class="card-body text-muted small text-center py-3">No call history</div>
        <?php endif ?>
      </div>

      <!-- Tasks -->
      <?php if (!empty($tasks)): ?>
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-check2-square me-2"></i>Tasks</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($tasks as $t): ?>
          <li class="list-group-item py-2 d-flex gap-2">
            <i class="bi bi-<?= h($t['type_icon'] ?? 'check2') ?> text-muted mt-1"></i>
            <div class="flex-grow-1">
              <div class="small fw-semibold"><?= h($t['title']) ?></div>
              <div class="text-muted" style="font-size:.7rem">
                <?= h($t['assigned_name'] ?? '—') ?> · <?= time_ago($t['created_at']) ?>
              </div>
            </div>
            <?= status_badge($t['status']) ?>
          </li>
          <?php endforeach ?>
        </ul>
      </div>
      <?php endif ?>

      <!-- Feedback threads -->
      <?php if (!empty($threads)): ?>
      <div class="card">
        <div class="card-header"><i class="bi bi-chat-square-text me-2"></i>Feedback Threads</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($threads as $ft): ?>
          <li class="list-group-item py-2">
            <a href="<?= BASE_URL ?>/modules/feedback/thread.php?id=<?= $ft['id'] ?>" class="text-decoration-none">
              <div class="d-flex align-items-center gap-2">
                <div class="flex-grow-1">
                  <div class="small fw-semibold"><?= h($ft['title']) ?></div>
                  <div class="text-muted" style="font-size:.7rem"><?= time_ago($ft['created_at']) ?></div>
                </div>
                <?= status_badge($ft['status']) ?>
              </div>
            </a>
          </li>
          <?php endforeach ?>
        </ul>
      </div>
      <?php endif ?>

    </div>
  </div>
</div>

<?php require ROOT . '/partials/footer.php'; ?>
