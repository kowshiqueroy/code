<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Run Report';
$active_page = 'reports';

$uid  = current_user_id();
$role = current_role();

// --- Built-in reports ------------------------------------------------
$builtin = $_GET['builtin'] ?? '';

// --- Template-based reports ------------------------------------------
$template_id = (int)($_GET['template_id'] ?? 0);
$template    = null;
$columns     = [];
$filters_def = [];
$input_fields = [];

if ($template_id) {
    // Check access
    $template = db_row(
        "SELECT rt.* FROM report_templates rt
         WHERE rt.id=? AND rt.is_active=1
           AND (rt.visibility='public'
                OR rt.created_by=?
                OR EXISTS (SELECT 1 FROM report_template_permissions rtp WHERE rtp.template_id=rt.id AND rtp.user_id=?))",
        [$template_id, $uid, $uid]
    );
    if (!$template) {
        flash_error('Template not found or access denied.');
        redirect(BASE_URL . '/modules/reports/index.php');
    }
    $page_title  = 'Run: ' . $template['name'];
    $columns     = json_decode($template['columns']     ?? '[]', true) ?: [];
    $filters_def = json_decode($template['filters']     ?? '[]', true) ?: [];
    $input_fields = json_decode($template['input_fields'] ?? '[]', true) ?: [];
    $sources     = json_decode($template['source_modules'] ?? '[]', true) ?: [];
}

// --- Execute report on POST ------------------------------------------
$results   = null;
$wa_text   = '';
$run_params = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // Gather user-supplied params
    foreach ($input_fields as $field) {
        $run_params[$field['name']] = clean($_POST[$field['name']] ?? '');
    }
    // Built-in filter params
    $run_params['date_from'] = clean($_POST['date_from'] ?? '');
    $run_params['date_to']   = clean($_POST['date_to']   ?? '');
    $run_params['agent_id']  = (int)($_POST['agent_id'] ?? 0);

    if ($template_id && $template) {
        // Dynamic query builder based on source modules
        // We build a unified calls-centric query and apply column selection
        $where  = ['1=1'];
        $params = [];

        if ($run_params['date_from']) { $where[] = 'DATE(cl.created_at) >= ?'; $params[] = $run_params['date_from']; }
        if ($run_params['date_to'])   { $where[] = 'DATE(cl.created_at) <= ?'; $params[] = $run_params['date_to']; }
        if ($run_params['agent_id'])  { $where[] = 'cl.agent_id = ?';           $params[] = $run_params['agent_id']; }

        // Custom input-field filters
        foreach ($filters_def as $fd) {
            $val = $run_params[$fd['field']] ?? '';
            if ($val !== '') {
                $where[] = "cl.{$fd['field']} = ?";
                $params[] = $val;
            }
        }

        $w = implode(' AND ', $where);
        $results = db_rows(
            "SELECT cl.id, cl.direction, cl.duration_seconds, cl.phone_dialed, cl.created_at, cl.notes,
                    co.name AS contact_name, co.company, co.contact_type,
                    u.name AS agent_name,
                    out.name AS outcome_name,
                    cam.name AS campaign_name,
                    cs.key_points, cs.sentiment, cs.follow_up_date
             FROM calls cl
             LEFT JOIN contacts co ON co.id=cl.contact_id
             LEFT JOIN users u ON u.id=cl.agent_id
             LEFT JOIN call_outcomes out ON out.id=cl.outcome_id
             LEFT JOIN campaigns cam ON cam.id=cl.campaign_id
             LEFT JOIN call_summary cs ON cs.call_id=cl.id
             WHERE $w
             ORDER BY cl.created_at DESC
             LIMIT 1000",
            $params
        );

        // Save run log
        db_exec(
            "INSERT INTO report_runs (template_id, run_by, input_data, filter_overrides) VALUES (?, ?, ?, ?)",
            [$template_id, $uid, json_encode($run_params), json_encode([])]
        );

        // Build WhatsApp summary
        $wa_text  = "*Report: {$template['name']}*\n";
        $wa_text .= "Date: " . ($run_params['date_from'] ?: '—') . " to " . ($run_params['date_to'] ?: '—') . "\n";
        $wa_text .= "Rows: " . count($results) . "\n\n";
        foreach (array_slice($results, 0, 20) as $r) {
            $wa_text .= "• {$r['contact_name']} | {$r['agent_name']} | {$r['outcome_name']} | " . format_datetime($r['created_at']) . "\n";
        }
        if (count($results) > 20) $wa_text .= '… and ' . (count($results)-20) . " more rows\n";
    }

    if ($builtin === 'sr_tracking') {
        $where = ['sg.level_id = (SELECT id FROM sales_levels WHERE code=? OR name LIKE ? LIMIT 1)'];
        $params = ['SR', '%SR%'];
        if ($run_params['date_from']) { $where[] = 'DATE(cl.created_at) >= ?'; $params[] = $run_params['date_from']; }
        if ($run_params['date_to'])   { $where[] = 'DATE(cl.created_at) <= ?'; $params[] = $run_params['date_to']; }

        $results = db_rows(
            "SELECT sg.name AS group_name, sl.name AS level_name,
                    COUNT(DISTINCT cl.id) AS calls,
                    SUM(cl.duration_seconds) AS talk_time,
                    COUNT(DISTINCT cl.contact_id) AS contacts_reached
             FROM sales_groups sg
             JOIN sales_levels sl ON sl.id=sg.level_id
             LEFT JOIN executive_group_assignments ega ON ega.group_id=sg.id
             LEFT JOIN calls cl ON cl.agent_id=ega.user_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY sg.id, sg.name, sl.name
             ORDER BY calls DESC",
            $params
        );
    }

    if ($builtin === 'attendance') {
        $where  = ['1=1'];
        $params = [];
        if ($run_params['date_from']) { $where[] = 'DATE(a.check_in) >= ?'; $params[] = $run_params['date_from']; }
        if ($run_params['date_to'])   { $where[] = 'DATE(a.check_in) <= ?'; $params[] = $run_params['date_to']; }
        if ($run_params['agent_id'])  { $where[] = 'a.user_id = ?';         $params[] = $run_params['agent_id']; }

        $results = db_rows(
            "SELECT u.name AS agent_name, DATE(a.check_in) AS work_date, a.work_mode,
                    a.check_in, a.check_out,
                    TIMESTAMPDIFF(MINUTE, a.check_in, COALESCE(a.check_out, NOW())) AS minutes
             FROM attendance a
             JOIN users u ON u.id=a.user_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY a.check_in DESC
             LIMIT 500",
            $params
        );
    }
}

// Load agents for filter
$agents = db_rows("SELECT id, name FROM users WHERE status='active' ORDER BY name");

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/reports/index.php" class="btn btn-sm btn-outline-secondary me-2">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-play-circle-fill text-primary me-1"></i>
  <h5 class="mb-0"><?= h($page_title) ?></h5>
  <div class="ms-auto d-flex gap-2 no-print">
    <?php if ($results !== null): ?>
    <button data-copy-wa="#waText" class="btn btn-sm btn-success">
      <i class="bi bi-whatsapp me-1"></i>Copy WA
    </button>
    <button data-print class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-printer"></i>
    </button>
    <?php endif ?>
  </div>
</div>

<div class="page-body">

  <?php foreach (get_flashes() as $f): ?>
  <div class="alert alert-<?= $f['type']==='error'?'danger':'success' ?> alert-dismissible fade show">
    <?= h($f['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endforeach ?>

  <!-- Filter form -->
  <div class="card mb-3 no-print">
    <div class="card-header py-2 fw-semibold">Run Parameters</div>
    <div class="card-body">
      <form method="post">
        <?= csrf_field() ?>
        <div class="row g-2">
          <div class="col-md-3">
            <label class="form-label small mb-1">Date From</label>
            <input type="date" name="date_from" class="form-control form-control-sm"
                   value="<?= h($run_params['date_from'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Date To</label>
            <input type="date" name="date_to" class="form-control form-control-sm"
                   value="<?= h($run_params['date_to'] ?? '') ?>">
          </div>
          <?php if (role_level($role) >= role_level('senior_executive')): ?>
          <div class="col-md-3">
            <label class="form-label small mb-1">Agent</label>
            <select name="agent_id" class="form-select form-select-sm">
              <option value="">All Agents</option>
              <?php foreach ($agents as $a): ?>
              <option value="<?= $a['id'] ?>" <?= (($run_params['agent_id'] ?? 0) == $a['id']) ? 'selected' : '' ?>>
                <?= h($a['name']) ?>
              </option>
              <?php endforeach ?>
            </select>
          </div>
          <?php endif ?>
          <?php foreach ($input_fields as $field): ?>
          <div class="col-md-3">
            <label class="form-label small mb-1"><?= h($field['label'] ?? $field['name']) ?></label>
            <?php if (($field['type'] ?? 'text') === 'select' && !empty($field['options'])): ?>
            <select name="<?= h($field['name']) ?>" class="form-select form-select-sm">
              <option value="">— Any —</option>
              <?php foreach ($field['options'] as $opt): ?>
              <option value="<?= h($opt) ?>" <?= (($run_params[$field['name']] ?? '') === $opt) ? 'selected' : '' ?>><?= h($opt) ?></option>
              <?php endforeach ?>
            </select>
            <?php else: ?>
            <input type="<?= h($field['type'] ?? 'text') ?>" name="<?= h($field['name']) ?>"
                   class="form-control form-control-sm"
                   value="<?= h($run_params[$field['name']] ?? '') ?>">
            <?php endif ?>
          </div>
          <?php endforeach ?>
          <div class="col-12">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-play me-1"></i>Run Report</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <?php if ($results !== null): ?>
  <!-- Results -->
  <div class="card">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="fw-semibold">Results</span>
      <span class="badge bg-secondary"><?= count($results) ?> rows</span>
      <input type="text" class="form-control form-control-sm ms-auto no-print" style="max-width:200px"
             placeholder="Filter rows…" data-filter-table="resultsTable">
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" id="resultsTable">
        <thead class="table-light">
          <tr>
            <?php if ($builtin === 'sr_tracking'): ?>
            <th>Group</th><th>Level</th><th>Calls</th><th>Talk Time</th><th>Contacts Reached</th>
            <?php elseif ($builtin === 'attendance'): ?>
            <th>Agent</th><th>Date</th><th>Mode</th><th>Check In</th><th>Check Out</th><th>Hours</th>
            <?php else: ?>
            <th>Contact</th><th>Company</th><th>Agent</th><th>Date/Time</th><th>Duration</th><th>Direction</th><th>Outcome</th><th>Campaign</th>
            <?php endif ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($results)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">No data for selected parameters.</td></tr>
          <?php elseif ($builtin === 'sr_tracking'): ?>
          <?php foreach ($results as $r): ?>
          <tr>
            <td><?= h($r['group_name']) ?></td>
            <td><span class="badge bg-secondary"><?= h($r['level_name']) ?></span></td>
            <td class="fw-semibold"><?= number_format($r['calls']) ?></td>
            <td><?= format_duration($r['talk_time'] ?? 0) ?></td>
            <td><?= number_format($r['contacts_reached']) ?></td>
          </tr>
          <?php endforeach ?>
          <?php elseif ($builtin === 'attendance'): ?>
          <?php foreach ($results as $r): ?>
          <tr>
            <td><?= h($r['agent_name']) ?></td>
            <td><?= format_date($r['work_date']) ?></td>
            <td><span class="badge bg-info"><?= h($r['work_mode']) ?></span></td>
            <td><?= $r['check_in'] ? date('H:i', strtotime($r['check_in'])) : '—' ?></td>
            <td><?= $r['check_out'] ? date('H:i', strtotime($r['check_out'])) : '—' ?></td>
            <td><?= $r['check_out'] ? round($r['minutes']/60, 1) . 'h' : '<span class="text-warning">Active</span>' ?></td>
          </tr>
          <?php endforeach ?>
          <?php else: ?>
          <?php foreach ($results as $r): ?>
          <tr>
            <td>
              <a href="<?= BASE_URL ?>/modules/contacts/view.php?id=<?= $r['id'] ?>" class="text-decoration-none">
                <?= h($r['contact_name'] ?? 'Unknown') ?>
              </a>
            </td>
            <td class="text-muted small"><?= h($r['company'] ?? '—') ?></td>
            <td class="text-muted small"><?= h($r['agent_name'] ?? '—') ?></td>
            <td class="text-muted small"><?= format_datetime($r['created_at']) ?></td>
            <td><?= $r['duration_seconds'] ? format_duration($r['duration_seconds']) : '—' ?></td>
            <td>
              <span class="badge bg-<?= $r['direction']==='inbound' ? 'success' : 'primary' ?>">
                <?= ucfirst($r['direction'] ?? '—') ?>
              </span>
            </td>
            <td><?= $r['outcome_name'] ? '<span class="badge bg-secondary">'.h($r['outcome_name']).'</span>' : '—' ?></td>
            <td class="text-muted small"><?= h($r['campaign_name'] ?? '—') ?></td>
          </tr>
          <?php endforeach ?>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Hidden WA text -->
  <textarea id="waText" class="d-none"><?= h($wa_text) ?></textarea>
  <?php endif ?>

</div>

<?php require ROOT . '/partials/footer.php'; ?>
