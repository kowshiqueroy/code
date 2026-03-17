<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_role('super_admin');

$page_title  = 'System Settings';
$active_page = 'settings';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $tab = clean($_POST['tab'] ?? 'general');

    $keys = [];
    switch ($tab) {
        case 'general':
            $keys = ['company_name','company_phone','company_address','timezone','date_format','items_per_page'];
            break;
        case 'calls':
            $keys = ['default_call_direction','session_timeout','idle_warn_seconds','auto_callback_days'];
            break;
        case 'sms':
            $keys = ['sms_gateway_url','sms_api_key','sms_sender_id','sms_enabled'];
            break;
        case 'sales':
            $keys = ['sales_levels_config','allow_multiple_group_assign'];
            break;
        case 'leave':
            $keys = ['leave_types_visible','annual_leave_days','casual_leave_days'];
            break;
    }

    foreach ($keys as $key) {
        $val = clean($_POST[$key] ?? '');
        $existing = db_val("SELECT `key` FROM settings WHERE `key`=?", [$key]);
        if ($existing) {
            db_exec("UPDATE settings SET value=? WHERE `key`=?", [$val, $key]);
        } else {
            db_exec("INSERT INTO settings (`key`, value) VALUES (?,?)", [$key, $val]);
        }
    }
    audit_log('update_settings', 'settings', 0, "Settings tab '$tab' saved");
    flash_success('Settings saved.');
    redirect(BASE_URL . '/modules/settings/index.php?tab=' . $tab);
}

$active_tab = clean($_GET['tab'] ?? 'general');

// Load all settings
$all_settings = db_rows("SELECT `key`, value FROM settings");
$cfg = [];
foreach ($all_settings as $s) $cfg[$s['key']] = $s['value'];

// Helper to get setting with default
$s = fn(string $key, string $default = '') => $cfg[$key] ?? $default;

// Load call outcomes for management
$outcomes = db_rows("SELECT * FROM call_outcomes ORDER BY sort_order, name");
// Load task types
$task_types = db_rows("SELECT * FROM task_types ORDER BY name");
// Load leave types
$leave_types = db_rows("SELECT * FROM leave_types ORDER BY name");
// Load sales levels
$sales_levels = db_rows("SELECT * FROM sales_levels ORDER BY rank_order");

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-gear-fill text-primary me-1"></i>
  <h5 class="mb-0">System Settings</h5>
</div>

<div class="page-body">

  <?php foreach (get_flashes() as $f): ?>
  <div class="alert alert-<?= $f['type']==='error'?'danger':'success' ?> alert-dismissible fade show">
    <?= h($f['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endforeach ?>

  <div class="row g-3">
    <!-- Tab sidebar -->
    <div class="col-md-3">
      <div class="list-group">
        <?php
        $tabs = [
          'general'  => ['gear',            'General'],
          'calls'    => ['telephone',        'Calls & Sessions'],
          'sms'      => ['chat-dots',        'SMS Gateway'],
          'sales'    => ['diagram-3',        'Sales Network'],
          'leave'    => ['calendar-check',   'Leave & HR'],
          'outcomes' => ['list-check',       'Call Outcomes'],
          'tasktypes'=> ['check2-square',    'Task Types'],
          'levels'   => ['bar-chart-steps',  'Sales Levels'],
        ];
        foreach ($tabs as $key => [$icon, $label]): ?>
        <a href="?tab=<?= $key ?>" class="list-group-item list-group-item-action <?= $active_tab===$key?'active':'' ?>">
          <i class="bi bi-<?= $icon ?> me-2"></i><?= $label ?>
        </a>
        <?php endforeach ?>
      </div>
    </div>

    <!-- Tab content -->
    <div class="col-md-9">

      <?php if ($active_tab === 'general'): ?>
      <div class="card">
        <div class="card-header py-2 fw-semibold">General Settings</div>
        <div class="card-body">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="tab" value="general">
            <div class="mb-3">
              <label class="form-label">Company Name</label>
              <input type="text" name="company_name" class="form-control"
                     value="<?= h($s('company_name', 'Ovijat Group')) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Company Phone</label>
              <input type="tel" name="company_phone" class="form-control" data-phone
                     value="<?= h($s('company_phone')) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Company Address</label>
              <textarea name="company_address" class="form-control" rows="2"><?= h($s('company_address')) ?></textarea>
            </div>
            <div class="row mb-3">
              <div class="col">
                <label class="form-label">Timezone</label>
                <select name="timezone" class="form-select">
                  <option value="Asia/Dhaka" <?= $s('timezone')==='Asia/Dhaka'?'selected':'' ?>>Asia/Dhaka (BD)</option>
                  <option value="UTC" <?= $s('timezone')==='UTC'?'selected':'' ?>>UTC</option>
                </select>
              </div>
              <div class="col">
                <label class="form-label">Items per Page</label>
                <select name="items_per_page" class="form-select">
                  <?php foreach ([10,20,25,50,100] as $n): ?>
                  <option value="<?= $n ?>" <?= $s('items_per_page','20')==$n?'selected':'' ?>><?= $n ?></option>
                  <?php endforeach ?>
                </select>
              </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save</button>
          </form>
        </div>
      </div>

      <?php elseif ($active_tab === 'calls'): ?>
      <div class="card">
        <div class="card-header py-2 fw-semibold">Calls & Session Settings</div>
        <div class="card-body">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="tab" value="calls">
            <div class="row mb-3">
              <div class="col">
                <label class="form-label">Default Call Direction</label>
                <select name="default_call_direction" class="form-select">
                  <option value="outbound" <?= $s('default_call_direction','outbound')==='outbound'?'selected':'' ?>>Outbound</option>
                  <option value="inbound"  <?= $s('default_call_direction','outbound')==='inbound' ?'selected':'' ?>>Inbound</option>
                </select>
              </div>
              <div class="col">
                <label class="form-label">Auto-callback After (days)</label>
                <input type="number" name="auto_callback_days" class="form-control" min="1" max="90"
                       value="<?= h($s('auto_callback_days','3')) ?>">
              </div>
            </div>
            <div class="row mb-3">
              <div class="col">
                <label class="form-label">Session Timeout (seconds)</label>
                <input type="number" name="session_timeout" class="form-control" min="60" max="3600"
                       value="<?= h($s('session_timeout','300')) ?>">
                <div class="form-text">Default: 300 (5 minutes)</div>
              </div>
              <div class="col">
                <label class="form-label">Idle Warning Before Logout (seconds)</label>
                <input type="number" name="idle_warn_seconds" class="form-control" min="10" max="300"
                       value="<?= h($s('idle_warn_seconds','60')) ?>">
              </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save</button>
          </form>
        </div>
      </div>

      <?php elseif ($active_tab === 'sms'): ?>
      <div class="card">
        <div class="card-header py-2 fw-semibold">SMS Gateway Settings</div>
        <div class="card-body">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="tab" value="sms">
            <div class="mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="sms_enabled" value="1" id="smsEn"
                       <?= $s('sms_enabled','0') ? 'checked' : '' ?>>
                <label class="form-check-label" for="smsEn">Enable SMS Gateway</label>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Gateway API URL</label>
              <input type="url" name="sms_gateway_url" class="form-control"
                     value="<?= h($s('sms_gateway_url')) ?>" placeholder="https://api.smsprovider.com/send">
            </div>
            <div class="mb-3">
              <label class="form-label">API Key / Token</label>
              <input type="text" name="sms_api_key" class="form-control"
                     value="<?= h($s('sms_api_key')) ?>" placeholder="Your API key">
            </div>
            <div class="mb-3">
              <label class="form-label">Sender ID / From</label>
              <input type="text" name="sms_sender_id" class="form-control"
                     value="<?= h($s('sms_sender_id')) ?>" placeholder="OVIJAT" maxlength="11">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save</button>
          </form>
        </div>
      </div>

      <?php elseif ($active_tab === 'leave'): ?>
      <div class="card">
        <div class="card-header py-2 fw-semibold">Leave & HR Settings</div>
        <div class="card-body">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="tab" value="leave">
            <div class="row mb-3">
              <div class="col">
                <label class="form-label">Annual Leave Days</label>
                <input type="number" name="annual_leave_days" class="form-control" min="0" max="365"
                       value="<?= h($s('annual_leave_days','20')) ?>">
              </div>
              <div class="col">
                <label class="form-label">Casual Leave Days</label>
                <input type="number" name="casual_leave_days" class="form-control" min="0" max="365"
                       value="<?= h($s('casual_leave_days','10')) ?>">
              </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save</button>
          </form>
        </div>
      </div>

      <?php elseif ($active_tab === 'outcomes'): ?>
      <!-- Call Outcomes Management -->
      <div class="card">
        <div class="card-header py-2 d-flex align-items-center justify-content-between fw-semibold">
          <span>Call Outcomes</span>
          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addOutcomeModal">
            <i class="bi bi-plus-lg"></i> Add
          </button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Color</th><th>Requires Callback</th><th>Active</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($outcomes as $o): ?>
              <tr>
                <td><?= h($o['name']) ?></td>
                <td><span class="badge" style="background:<?= h($o['color'] ?? '#6c757d') ?>"><?= h($o['color'] ?? '—') ?></span></td>
                <td><?= $o['requires_callback'] ? '<i class="bi bi-check-circle text-success"></i>' : '—' ?></td>
                <td><?= $o['is_active'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                <td>
                  <a href="?tab=outcomes&edit_outcome=<?= $o['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil"></i></a>
                </td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add Outcome Modal -->
      <div class="modal fade" id="addOutcomeModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
          <form method="post" action="<?= BASE_URL ?>/modules/settings/outcome_action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_outcome">
            <div class="modal-header"><h6 class="modal-title">Add Call Outcome</h6>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-2"><label class="form-label small">Name</label>
                <input type="text" name="name" class="form-control form-control-sm" required></div>
              <div class="mb-2"><label class="form-label small">Color (hex)</label>
                <input type="color" name="color" class="form-control form-control-sm form-control-color" value="#6c757d"></div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="requires_callback" value="1" id="rcbAdd">
                <label class="form-check-label" for="rcbAdd">Requires Callback</label>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary btn-sm">Add Outcome</button>
            </div>
          </form>
        </div></div>
      </div>

      <?php elseif ($active_tab === 'tasktypes'): ?>
      <div class="card">
        <div class="card-header py-2 d-flex align-items-center justify-content-between fw-semibold">
          <span>Task Types</span>
          <form method="post" action="<?= BASE_URL ?>/modules/settings/outcome_action.php" class="d-flex gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_task_type">
            <input type="text" name="name" class="form-control form-control-sm" placeholder="New type name" required style="width:180px">
            <button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i></button>
          </form>
        </div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Active</th></tr></thead>
            <tbody>
              <?php foreach ($task_types as $tt): ?>
              <tr>
                <td><?= h($tt['name']) ?></td>
                <td><?= $tt['is_active'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php elseif ($active_tab === 'levels'): ?>
      <div class="card">
        <div class="card-header py-2 fw-semibold">Sales Hierarchy Levels</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Order</th><th>Code</th><th>Name</th><th>Parent Level</th></tr></thead>
            <tbody>
              <?php foreach ($sales_levels as $sl): ?>
              <tr>
                <td><?= $sl['rank_order'] ?></td>
                <td><?= h($sl['name']) ?></td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer text-muted small">
          Sales levels are seeded during setup. To modify hierarchy, re-run <a href="<?= BASE_URL ?>/setup.php">setup.php</a> (admin only).
        </div>
      </div>

      <?php else: ?>
      <div class="card"><div class="card-body text-muted">Select a tab.</div></div>
      <?php endif ?>

    </div>
  </div>

</div>

<?php require ROOT . '/partials/footer.php'; ?>
