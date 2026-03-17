<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Campaign Detail';
$active_page = 'campaigns';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { flash_error('Campaign not found.'); redirect(BASE_URL . '/modules/campaigns/index.php'); }

$c = db_row("SELECT c.*, u.name AS creator_name FROM campaigns c LEFT JOIN users u ON u.id=c.created_by WHERE c.id=?", [$id]);
if (!$c) { flash_error('Campaign not found.'); redirect(BASE_URL . '/modules/campaigns/index.php'); }

// Script for this campaign
$script = $c['script_id'] ? db_row("SELECT * FROM scripts WHERE id=?", [$c['script_id']]) : null;

// Stats
$total_contacts   = (int) db_val("SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id=?", [$id]);
$pending_contacts = (int) db_val("SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id=? AND status='pending'", [$id]);
$called_contacts  = (int) db_val("SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id=? AND status='called'", [$id]);
$total_calls      = (int) db_val("SELECT COUNT(*) FROM calls WHERE campaign_id=?", [$id]);
$total_duration   = (int) db_val("SELECT COALESCE(SUM(duration_seconds),0) FROM calls WHERE campaign_id=?", [$id]);
$progress         = $total_contacts > 0 ? round(($called_contacts / $total_contacts) * 100) : 0;

// Recent calls
$calls = db_rows(
    "SELECT cl.*, co.name AS contact_name, u.name AS agent_name, out.name AS outcome_name
     FROM calls cl
     LEFT JOIN contacts co ON co.id=cl.contact_id
     LEFT JOIN users u ON u.id=cl.agent_id
     LEFT JOIN call_outcomes out ON out.id=cl.outcome_id
     WHERE cl.campaign_id=?
     ORDER BY cl.created_at DESC LIMIT 50",
    [$id]
);

// Outcome breakdown
$outcome_stats = db_rows(
    "SELECT out.name, COUNT(*) AS cnt
     FROM calls cl
     LEFT JOIN call_outcomes out ON out.id=cl.outcome_id
     WHERE cl.campaign_id=?
     GROUP BY cl.outcome_id, out.name
     ORDER BY cnt DESC",
    [$id]
);

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/campaigns/index.php" class="btn btn-sm btn-outline-secondary me-2">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-megaphone-fill text-primary me-1"></i>
  <h5 class="mb-0"><?= h($c['name']) ?></h5>
  <?php
  $sBadge = ['active'=>'success','paused'=>'warning','draft'=>'secondary','completed'=>'primary','archived'=>'light text-dark'];
  $sc = $sBadge[$c['status']] ?? 'secondary';
  ?>
  <span class="badge bg-<?= $sc ?> ms-2"><?= ucfirst($c['status']) ?></span>
  <div class="ms-auto d-flex gap-2 no-print">
    <button data-print class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
    <?php if (can('edit', 'campaigns')): ?>
    <a href="<?= BASE_URL ?>/modules/campaigns/form.php?id=<?= $id ?>" class="btn btn-sm btn-warning">
      <i class="bi bi-pencil me-1"></i>Edit
    </a>
    <?php endif ?>
    <a href="<?= BASE_URL ?>/modules/campaigns/add_contact.php?campaign_id=<?= $id ?>" class="btn btn-sm btn-primary">
      <i class="bi bi-person-plus me-1"></i>Add Contacts
    </a>
  </div>
</div>

<div class="page-body">

  <?php foreach (get_flashes() as $f): ?>
  <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show">
    <?= h($f['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endforeach ?>

  <div class="row g-3 mb-3">
    <!-- KPI Cards -->
    <div class="col-6 col-md-3">
      <div class="card text-center">
        <div class="card-body py-2">
          <div class="fs-4 fw-bold"><?= number_format($total_contacts) ?></div>
          <div class="text-muted small">Total Contacts</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center border-success">
        <div class="card-body py-2">
          <div class="fs-4 fw-bold text-success"><?= number_format($called_contacts) ?></div>
          <div class="text-muted small">Called</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center border-primary">
        <div class="card-body py-2">
          <div class="fs-4 fw-bold text-primary"><?= number_format($total_calls) ?></div>
          <div class="text-muted small">Total Calls</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center border-info">
        <div class="card-body py-2">
          <div class="fs-4 fw-bold text-info"><?= format_duration($total_duration) ?></div>
          <div class="text-muted small">Talk Time</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- Left: Details + Progress -->
    <div class="col-md-4">
      <div class="card mb-3">
        <div class="card-header py-2 fw-semibold">Campaign Info</div>
        <div class="card-body">
          <?php if ($c['description']): ?>
          <p class="text-muted small mb-2"><?= nl2br(h($c['description'])) ?></p>
          <hr class="my-2">
          <?php endif ?>
          <div class="small">
            <div class="row mb-1"><div class="col-5 text-muted">Type</div>
              <div class="col-7"><?php
              $typeColors = ['outbound'=>'primary','inbound'=>'success','sms'=>'warning','mixed'=>'info'];
              $tc = $typeColors[$c['type']] ?? 'secondary';
              ?><span class="badge bg-<?= $tc ?>"><?= ucfirst($c['type']) ?></span></div></div>
            <div class="row mb-1"><div class="col-5 text-muted">Start Date</div>
              <div class="col-7"><?= $c['start_date'] ? format_date($c['start_date']) : '—' ?></div></div>
            <div class="row mb-1"><div class="col-5 text-muted">End Date</div>
              <div class="col-7"><?= $c['end_date'] ? format_date($c['end_date']) : '—' ?></div></div>
            <div class="row mb-1"><div class="col-5 text-muted">Created By</div>
              <div class="col-7"><?= h($c['creator_name'] ?? '—') ?></div></div>
            <div class="row mb-1"><div class="col-5 text-muted">Created</div>
              <div class="col-7"><?= format_date($c['created_at']) ?></div></div>
          </div>
        </div>
      </div>

      <!-- Progress -->
      <div class="card mb-3">
        <div class="card-header py-2 fw-semibold">Progress</div>
        <div class="card-body">
          <div class="d-flex justify-content-between mb-1">
            <span class="small text-muted"><?= $called_contacts ?> / <?= $total_contacts ?> called</span>
            <span class="fw-bold"><?= $progress ?>%</span>
          </div>
          <div class="progress mb-3" style="height:10px">
            <div class="progress-bar bg-success" style="width:<?= $progress ?>%"></div>
          </div>
          <div class="d-flex justify-content-between text-center small">
            <div><div class="fw-bold text-warning"><?= $pending_contacts ?></div><div class="text-muted">Pending</div></div>
            <div><div class="fw-bold text-success"><?= $called_contacts ?></div><div class="text-muted">Called</div></div>
            <div><div class="fw-bold text-secondary"><?= $total_contacts - $called_contacts - $pending_contacts ?></div><div class="text-muted">Other</div></div>
          </div>
        </div>
      </div>

      <!-- Outcome Breakdown -->
      <?php if (!empty($outcome_stats)): ?>
      <div class="card mb-3">
        <div class="card-header py-2 fw-semibold">Outcomes</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <?php foreach ($outcome_stats as $os): ?>
            <tr>
              <td><?= h($os['name'] ?? 'Unknown') ?></td>
              <td class="text-end fw-semibold"><?= $os['cnt'] ?></td>
              <td class="text-muted small" style="width:40%">
                <?php $pct = $total_calls > 0 ? round(($os['cnt']/$total_calls)*100) : 0; ?>
                <div class="progress" style="height:5px"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
              </td>
            </tr>
            <?php endforeach ?>
          </table>
        </div>
      </div>
      <?php endif ?>

      <!-- Script -->
      <?php if ($script): ?>
      <div class="card">
        <div class="card-header py-2 fw-semibold d-flex justify-content-between">
          <span>Script</span>
          <a href="<?= BASE_URL ?>/modules/scripts/form.php?id=<?= $script['id'] ?>&view=1" class="btn btn-sm btn-outline-secondary py-0">View</a>
        </div>
        <div class="card-body">
          <div class="fw-semibold small mb-1"><?= h($script['title']) ?></div>
          <div class="text-muted small"><?= h(truncate($script['content'] ?? '', 100)) ?></div>
        </div>
      </div>
      <?php endif ?>
    </div>

    <!-- Right: Call Log -->
    <div class="col-md-8">
      <div class="card">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
          <span class="fw-semibold">Recent Calls</span>
          <span class="badge bg-secondary"><?= $total_calls ?></span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Contact</th>
                <th>Agent</th>
                <th>Date/Time</th>
                <th>Duration</th>
                <th>Outcome</th>
                <th class="no-print">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($calls as $cl): ?>
              <tr>
                <td>
                  <a href="<?= BASE_URL ?>/modules/contacts/view.php?id=<?= $cl['contact_id'] ?>" class="text-decoration-none">
                    <?= h($cl['contact_name'] ?? 'Unknown') ?>
                  </a>
                </td>
                <td class="text-muted small"><?= h($cl['agent_name'] ?? '—') ?></td>
                <td class="text-muted small"><?= format_datetime($cl['started_at'] ?? $cl['created_at']) ?></td>
                <td><?= $cl['duration_seconds'] ? format_duration($cl['duration_seconds']) : '—' ?></td>
                <td><?php if ($cl['outcome_name']): ?>
                  <span class="badge bg-secondary"><?= h($cl['outcome_name']) ?></span>
                <?php else: ?>—<?php endif ?></td>
                <td class="no-print">
                  <a href="<?= BASE_URL ?>/modules/calls/view.php?id=<?= $cl['id'] ?>"
                     class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                </td>
              </tr>
              <?php endforeach ?>
              <?php if (empty($calls)): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No calls logged yet</td></tr>
              <?php endif ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<?php require ROOT . '/partials/footer.php'; ?>
