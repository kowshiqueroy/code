<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . '/modules/calls/index.php');

$call = db_row(
    "SELECT ca.*, co.name AS outcome_name, co.color AS outcome_color, co.requires_callback,
            c.id AS contact_id, c.name AS contact_name, c.phone AS contact_phone,
            c.company AS contact_company, c.contact_type,
            u.name AS agent_name,
            camp.name AS campaign_name, camp.id AS campaign_id,
            cs.key_points, cs.follow_up_required, cs.follow_up_date, cs.sentiment
     FROM calls ca
     LEFT JOIN call_outcomes co ON co.id = ca.outcome_id
     LEFT JOIN contacts c ON c.id = ca.contact_id
     LEFT JOIN users u ON u.id = ca.agent_id
     LEFT JOIN campaigns camp ON camp.id = ca.campaign_id
     LEFT JOIN call_summary cs ON cs.call_id = ca.id
     WHERE ca.id = ?",
    [$id]
);
if (!$call) redirect(BASE_URL . '/modules/calls/index.php');

// Permission: executive can only view own calls
if (current_role() === 'executive' && $call['agent_id'] != current_user_id()) {
    redirect(BASE_URL . '/modules/calls/index.php');
}

// Contact history (if linked)
$contact_history = [];
if ($call['contact_id']) {
    $contact_history = db_rows(
        "SELECT ca.id, ca.direction, ca.started_at, ca.duration_seconds,
                co.name AS outcome, co.color,
                u.name AS agent_name,
                cs.key_points, cs.sentiment
         FROM calls ca
         LEFT JOIN call_outcomes co ON co.id = ca.outcome_id
         LEFT JOIN users u ON u.id = ca.agent_id
         LEFT JOIN call_summary cs ON cs.call_id = ca.id
         WHERE ca.contact_id = ? AND ca.id != ?
         ORDER BY ca.started_at DESC LIMIT 15",
        [$call['contact_id'], $id]
    );
}

// Callbacks for this call
$callbacks = db_rows(
    "SELECT cb.*, u.name AS assigned_name FROM callbacks cb
     LEFT JOIN users u ON u.id = cb.assigned_to
     WHERE cb.call_id = ? ORDER BY cb.scheduled_at",
    [$id]
);

$page_title  = 'Call Detail';
$active_page = 'calls';

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/calls/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-telephone-fill text-primary ms-1"></i>
  <h5 class="ms-1">Call Detail #<?= $id ?></h5>
  <div class="ms-auto d-flex gap-2">
    <button data-print class="btn btn-sm btn-outline-secondary no-print">
      <i class="bi bi-printer me-1"></i>Print
    </button>
    <?php
    $wa = sprintf("📞 Call #%d\nContact: %s\nPhone: %s\nDirection: %s | %s\nDuration: %s\nOutcome: %s\nAgent: %s\nDate: %s%s",
        $id,
        $call['contact_name'] ?? 'Unknown',
        $call['phone_dialed'] ?? '—',
        ucfirst($call['direction']),
        $call['campaign_name'] ? 'Campaign: '.$call['campaign_name'] : '',
        format_duration((int)$call['duration_seconds']),
        $call['outcome_name'] ?? '—',
        $call['agent_name'],
        format_datetime($call['started_at']),
        $call['key_points'] ? "\nKey Points: ".$call['key_points'] : ''
    );
    ?>
    <button data-copy-wa data-text="<?= h($wa) ?>" class="btn btn-sm btn-outline-success no-print">
      <i class="bi bi-whatsapp me-1"></i>Copy
    </button>
    <?php if ($call['contact_id']): ?>
    <a href="<?= BASE_URL ?>/modules/workspace/index.php?contact_id=<?= $call['contact_id'] ?>"
       class="btn btn-sm btn-primary no-print">
      <i class="bi bi-telephone-plus me-1"></i>Call Again
    </a>
    <?php endif ?>
  </div>
</div>

<div class="page-body">
  <div class="row g-3">

    <!-- ── Main Call Info ────────────────────────────────── -->
    <div class="col-12 col-md-8">

      <!-- Call details card -->
      <div class="card mb-3">
        <div class="card-header d-flex align-items-center gap-2">
          <?php if ($call['direction'] === 'inbound'): ?>
          <i class="bi bi-telephone-inbound-fill text-success"></i>
          <span class="text-success fw-semibold">Inbound Call</span>
          <?php else: ?>
          <i class="bi bi-telephone-outbound-fill text-primary"></i>
          <span class="text-primary fw-semibold">Outbound Call</span>
          <?php endif ?>
          <?php if ($call['outcome_name']): ?>
          <span class="badge ms-auto" style="background:<?= h($call['outcome_color']) ?>">
            <?= h($call['outcome_name']) ?>
          </span>
          <?php endif ?>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6">
              <div class="text-muted small">Date & Time</div>
              <div class="fw-semibold"><?= format_datetime($call['started_at']) ?></div>
            </div>
            <div class="col-6">
              <div class="text-muted small">Duration</div>
              <div class="fw-semibold"><?= $call['duration_seconds'] ? format_duration((int)$call['duration_seconds']) : '—' ?></div>
            </div>
            <div class="col-6">
              <div class="text-muted small">Phone Dialed</div>
              <div class="fw-semibold"><?= h($call['phone_dialed'] ?? '—') ?></div>
            </div>
            <div class="col-6">
              <div class="text-muted small">Agent</div>
              <div class="fw-semibold"><?= h($call['agent_name']) ?></div>
            </div>
            <?php if ($call['campaign_name']): ?>
            <div class="col-6">
              <div class="text-muted small">Campaign</div>
              <div class="fw-semibold">
                <a href="<?= BASE_URL ?>/modules/campaigns/index.php?id=<?= $call['campaign_id'] ?>">
                  <?= h($call['campaign_name']) ?>
                </a>
              </div>
            </div>
            <?php endif ?>
          </div>

          <?php if ($call['notes']): ?>
          <div class="mt-3 pt-3 border-top">
            <div class="text-muted small mb-1">Notes</div>
            <div><?= nl2br(h($call['notes'])) ?></div>
          </div>
          <?php endif ?>
        </div>
      </div>

      <!-- Call Summary -->
      <?php if ($call['key_points'] || $call['follow_up_required']): ?>
      <div class="card mb-3">
        <div class="card-header">
          <i class="bi bi-journal-text me-2"></i>Call Summary
        </div>
        <div class="card-body">
          <?php if ($call['key_points']): ?>
          <div class="mb-3">
            <div class="text-muted small mb-1">Key Points</div>
            <div><?= nl2br(h($call['key_points'])) ?></div>
          </div>
          <?php endif ?>
          <div class="row g-2">
            <?php if ($call['sentiment']): ?>
            <div class="col-auto">
              <span class="badge <?= $call['sentiment']==='positive' ? 'bg-success' : ($call['sentiment']==='negative' ? 'bg-danger' : 'bg-secondary') ?>">
                <?= ucfirst($call['sentiment']) ?> Sentiment
              </span>
            </div>
            <?php endif ?>
            <?php if ($call['follow_up_required']): ?>
            <div class="col-auto">
              <span class="badge bg-warning text-dark">
                <i class="bi bi-calendar-event me-1"></i>
                Follow-up: <?= $call['follow_up_date'] ? format_date($call['follow_up_date']) : 'TBD' ?>
              </span>
            </div>
            <?php endif ?>
          </div>
        </div>
      </div>
      <?php endif ?>

      <!-- Callbacks -->
      <?php if (!empty($callbacks)): ?>
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-alarm me-2"></i>Callbacks</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($callbacks as $cb): ?>
          <li class="list-group-item d-flex align-items-center gap-2 py-2">
            <?= status_badge($cb['status']) ?>
            <span><?= format_datetime($cb['scheduled_at']) ?></span>
            <span class="text-muted small">→ <?= h($cb['assigned_name']) ?></span>
            <?php if ($cb['notes']): ?><span class="text-muted small text-truncate"><?= h($cb['notes']) ?></span><?php endif ?>
          </li>
          <?php endforeach ?>
        </ul>
      </div>
      <?php endif ?>

      <!-- Quick actions -->
      <div class="d-flex gap-2 flex-wrap no-print">
        <a href="<?= BASE_URL ?>/modules/callbacks/index.php?new=1&contact_id=<?= $call['contact_id'] ?>&call_id=<?= $id ?>"
           class="btn btn-sm btn-outline-warning">
          <i class="bi bi-alarm me-1"></i>Schedule Callback
        </a>
        <?php if ($call['contact_id']): ?>
        <a href="<?= BASE_URL ?>/modules/feedback/form.php?contact_id=<?= $call['contact_id'] ?>"
           class="btn btn-sm btn-outline-danger">
          <i class="bi bi-chat-square-text me-1"></i>Open Feedback Thread
        </a>
        <?php endif ?>
      </div>
    </div>

    <!-- ── Contact Info + History ───────────────────────── -->
    <div class="col-12 col-md-4">
      <?php if ($call['contact_id']): ?>
      <!-- Contact card -->
      <div class="card mb-3">
        <div class="card-header d-flex align-items-center">
          <i class="bi bi-person-fill me-2"></i>Contact
          <a href="<?= BASE_URL ?>/modules/contacts/view.php?id=<?= $call['contact_id'] ?>"
             class="btn btn-sm btn-outline-primary ms-auto no-print">View Profile</a>
        </div>
        <div class="card-body py-3">
          <div class="fw-bold"><?= h($call['contact_name']) ?></div>
          <div class="text-muted small"><?= h($call['contact_phone'] ?? '') ?></div>
          <?php if ($call['contact_company']): ?>
          <div class="text-muted small"><?= h($call['contact_company']) ?></div>
          <?php endif ?>
          <div class="mt-1"><?= type_badge($call['contact_type']) ?></div>
        </div>
      </div>

      <!-- Previous calls with this contact -->
      <div class="card">
        <div class="card-header">
          <i class="bi bi-clock-history me-2"></i>Previous Calls
        </div>
        <ul class="list-group list-group-flush" style="max-height:320px;overflow-y:auto">
          <?php foreach ($contact_history as $h2): ?>
          <li class="list-group-item py-2">
            <a href="view.php?id=<?= $h2['id'] ?>" class="text-decoration-none">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-telephone-<?= $h2['direction']==='inbound' ? 'inbound' : 'outbound' ?>-fill
                   <?= $h2['direction']==='inbound' ? 'text-success' : 'text-primary' ?>"></i>
                <div class="min-w-0 flex-grow-1">
                  <div class="fw-semibold text-truncate" style="font-size:.78rem">
                    <?php if ($h2['outcome']): ?>
                    <span class="badge" style="background:<?= h($h2['color']??'#999') ?>;font-size:.65rem"><?= h($h2['outcome']) ?></span>
                    <?php endif ?>
                    <?= format_duration((int)$h2['duration_seconds']) ?>
                  </div>
                  <div class="text-muted" style="font-size:.7rem">
                    <?= time_ago($h2['started_at']) ?> · <?= h($h2['agent_name']) ?>
                  </div>
                </div>
              </div>
            </a>
          </li>
          <?php endforeach ?>
          <?php if (empty($contact_history)): ?>
          <li class="list-group-item text-center text-muted py-3 small">No previous calls</li>
          <?php endif ?>
        </ul>
      </div>
      <?php endif ?>
    </div>

  </div>
</div>

<?php require ROOT . '/partials/footer.php'; ?>
