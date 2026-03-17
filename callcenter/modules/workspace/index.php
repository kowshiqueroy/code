<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_role('executive');

$page_title  = 'Call Workspace';
$active_page = 'workspace';

// ── POST: Save interaction ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $ch = clean($_POST['channel'] ?? 'call');

    if ($ch === 'call') {
        // Save call
        $contact_id = (int)($_POST['contact_id'] ?? 0);
        $phone      = clean($_POST['phone_dialed'] ?? '');
        $direction  = in_array($_POST['direction']??'', ['inbound','outbound']) ? $_POST['direction'] : 'outbound';
        $outcome_id = (int)($_POST['outcome_id'] ?? 0);
        $campaign_id= (int)($_POST['campaign_id'] ?? 0);
        $notes      = clean($_POST['notes'] ?? '');
        $duration   = (int)($_POST['duration_seconds'] ?? 0);
        $started_at = clean($_POST['started_at'] ?? date('Y-m-d H:i:s'));

        $call_id = db_exec(
            "INSERT INTO calls (contact_id, campaign_id, agent_id, direction, phone_dialed,
              started_at, duration_seconds, outcome_id, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$contact_id ?: null, $campaign_id ?: null, current_user_id(),
             $direction, $phone ?: null, $started_at, $duration,
             $outcome_id ?: null, $notes]
        );

        // Save summary if provided
        $key_points = clean($_POST['key_points'] ?? '');
        if ($key_points || isset($_POST['follow_up_required'])) {
            db_exec(
                "INSERT INTO call_summary (call_id, key_points, follow_up_required, follow_up_date, sentiment)
                 VALUES (?, ?, ?, ?, ?)",
                [$call_id, $key_points,
                 isset($_POST['follow_up_required']) ? 1 : 0,
                 clean($_POST['follow_up_date'] ?? '') ?: null,
                 in_array($_POST['sentiment']??'', ['positive','neutral','negative']) ? $_POST['sentiment'] : 'neutral']
            );
        }

        // Auto-schedule callback if outcome requires it
        if ($outcome_id) {
            $req = db_val("SELECT requires_callback FROM call_outcomes WHERE id=?", [$outcome_id]);
            if ($req && !empty($_POST['callback_date'])) {
                db_exec(
                    "INSERT INTO callbacks (call_id, contact_id, assigned_to, scheduled_at, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$call_id, $contact_id ?: null, current_user_id(),
                     clean($_POST['callback_date']),
                     'Scheduled from workspace', current_user_id()]
                );
            }
        }

        audit_log('log_call', 'calls', $call_id, "Call logged via workspace");

        if ($contact_id) {
            // Update campaign_contacts if linked
            if ($campaign_id) {
                db_exec(
                    "UPDATE campaign_contacts SET status='called', last_called_at=NOW()
                     WHERE campaign_id=? AND contact_id=?",
                    [$campaign_id, $contact_id]
                );
            }
        }

        // Return JSON if AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'call_id' => $call_id]);
            exit;
        }
        flash_success('Call logged successfully. <a href="' . BASE_URL . '/modules/calls/view.php?id=' . $call_id . '">View detail</a>');
        redirect(BASE_URL . '/modules/workspace/index.php' . ($contact_id ? '?contact_id=' . $contact_id : ''));

    } elseif ($ch === 'sms') {
        $contact_id = (int)($_POST['contact_id'] ?? 0);
        $phone_to   = clean($_POST['phone_to'] ?? '');
        $message    = clean($_POST['message'] ?? '');
        if ($phone_to && $message) {
            $sms_id = db_exec(
                "INSERT INTO sms_log (contact_id, agent_id, phone_to, message, status, sent_at)
                 VALUES (?, ?, ?, ?, 'queued', NOW())",
                [$contact_id ?: null, current_user_id(), $phone_to, $message]
            );
            audit_log('send_sms', 'sms', $sms_id, "SMS via workspace");
            flash_success('SMS queued.');
        }
        redirect(BASE_URL . '/modules/workspace/index.php' . ($contact_id ? '?contact_id=' . $contact_id : ''));

    } else {
        // Other channel: save as task
        $contact_id = (int)($_POST['contact_id'] ?? 0);
        $type_id    = (int)($_POST['type_id'] ?? 0);
        $notes      = clean($_POST['notes'] ?? '');
        $title      = clean($_POST['title'] ?? 'Activity');
        $task_id = db_exec(
            "INSERT INTO tasks (title, description, type_id, assigned_to, assigned_by, priority, status, contact_id, notes)
             VALUES (?, ?, ?, ?, ?, 'medium', 'completed', ?, ?)",
            [$title, '', $type_id ?: null, current_user_id(), current_user_id(), $contact_id ?: null, $notes]
        );
        audit_log('log_activity', 'tasks', $task_id, "Activity logged via workspace: $ch");
        flash_success('Activity logged.');
        redirect(BASE_URL . '/modules/workspace/index.php' . ($contact_id ? '?contact_id=' . $contact_id : ''));
    }
}

// ── Pre-load contact if passed via URL ────────────────────────
$preload_contact = null;
$preload_id = (int)($_GET['contact_id'] ?? 0);
if ($preload_id) {
    $preload_contact = db_row(
        "SELECT c.*, sp.position, sp.department, sp.employee_id,
                (SELECT GROUP_CONCAT(tag SEPARATOR ', ') FROM contact_tags WHERE contact_id=c.id) AS tags
         FROM contacts c
         LEFT JOIN staff_profiles sp ON sp.contact_id = c.id
         WHERE c.id=?",
        [$preload_id]
    );
}

// ── Load call outcomes + task types + campaigns ───────────────
$call_outcomes = db_rows("SELECT id, name, color, requires_callback FROM call_outcomes WHERE is_active=1 ORDER BY sort_order");
$task_types    = db_rows("SELECT id, name, icon, color FROM task_types WHERE is_active=1 ORDER BY sort_order");
$active_campaigns = db_rows("SELECT id, name FROM campaigns WHERE status='active' ORDER BY name");
$default_script   = db_row("SELECT id, name, content FROM scripts WHERE is_default=1 LIMIT 1");

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-telephone-inbound-fill text-success"></i>
  <h5>Call Workspace</h5>
  <div class="ms-auto d-flex gap-2">
    <!-- Quick inbound button -->
    <button class="btn btn-outline-success btn-sm" id="quickInboundBtn">
      <i class="bi bi-telephone-inbound me-1"></i>Inbound Call
    </button>
    <a href="<?= BASE_URL ?>/modules/contacts/form.php" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-person-plus me-1"></i>New Contact
    </a>
  </div>
</div>

<!-- ── Global Contact Search Bar ──────────────────────────── -->
<div class="px-3 pt-3 pb-0">
  <div class="card border-primary">
    <div class="card-body py-2 px-3">
      <div class="input-group">
        <span class="input-group-text bg-primary text-white border-primary">
          <i class="bi bi-search"></i>
        </span>
        <input type="text" class="form-control form-control-lg border-primary"
               id="wsContactSearch"
               placeholder="Search by name, phone, company, email, group…"
               autocomplete="off" autofocus
               data-autocomplete="contacts">
        <button class="btn btn-primary" type="button" id="wsNewContact">
          <i class="bi bi-plus-lg me-1"></i>New
        </button>
      </div>
      <div id="wsSearchDropdown" class="ac-dropdown" style="display:none;width:100%;max-width:100%"></div>
    </div>
  </div>
</div>

<!-- ── Workspace Split Layout ────────────────────────────── -->
<div class="workspace-layout">

  <!-- ── LEFT: Contact Panel ─────────────────────────── -->
  <div class="workspace-contact-panel" id="contactPanel">
    <div class="contact-panel-header" id="contactPanelHeader">
      <!-- Placeholder when no contact selected -->
      <div id="noContactMsg" class="text-center py-4 text-muted">
        <i class="bi bi-person-circle" style="font-size:3rem;opacity:.2"></i>
        <p class="mt-2 mb-0">Search for a contact above to begin</p>
      </div>

      <!-- Contact info (hidden until selected) -->
      <div id="contactInfo" style="display:none">
        <div class="d-flex align-items-start gap-3">
          <div style="width:48px;height:48px;border-radius:50%;background:#e9ecef;
                      display:flex;align-items:center;justify-content:center;font-size:1.4rem;
                      flex-shrink:0;color:#6c757d">
            <i class="bi bi-person-fill"></i>
          </div>
          <div class="min-w-0 flex-grow-1">
            <div class="fw-bold fs-6" id="ciName">—</div>
            <div class="small text-muted d-flex flex-wrap gap-2" id="ciMeta">
              <span id="ciPhone"></span>
              <span id="ciCompany"></span>
            </div>
            <div class="mt-1 d-flex flex-wrap gap-1" id="ciBadges"></div>
          </div>
          <button class="btn btn-sm btn-outline-secondary flex-shrink-0" id="viewContactBtn">
            <i class="bi bi-box-arrow-up-right"></i>
          </button>
        </div>

        <!-- Smart hints -->
        <div class="hint-pills mt-2" id="hintPills"></div>

        <!-- Tags -->
        <div id="ciTags" class="mt-1" style="display:none"></div>
      </div>
    </div>

    <!-- Recent History -->
    <div id="historyPanel">
      <div class="px-3 py-2 border-bottom d-flex align-items-center">
        <span class="fw-semibold small">Recent Interactions</span>
        <a href="#" id="viewAllHistoryBtn" class="ms-auto small text-primary" style="display:none">View all →</a>
      </div>
      <div id="historyList">
        <div class="text-center text-muted py-4 small" id="historyPlaceholder">
          Select a contact to see history
        </div>
      </div>
    </div>

    <!-- Assigned Tasks for this contact -->
    <div id="contactTasksSection" style="display:none">
      <div class="px-3 py-2 border-top border-bottom">
        <span class="fw-semibold small">Open Tasks</span>
      </div>
      <div id="contactTasksList"></div>
    </div>
  </div>

  <!-- ── RIGHT: Action Panel ─────────────────────────── -->
  <div class="workspace-action-panel" id="actionPanel">

    <!-- Channel selector tabs -->
    <div class="p-2 border-bottom">
      <div class="btn-group w-100 flex-wrap" id="channelSelector" role="group">
        <input type="radio" class="btn-check" name="channelRadio" id="chCall" value="call" checked>
        <label class="btn btn-outline-primary btn-sm" for="chCall">
          <i class="bi bi-telephone-fill me-1"></i>Call
        </label>

        <input type="radio" class="btn-check" name="channelRadio" id="chSms" value="sms">
        <label class="btn btn-outline-success btn-sm" for="chSms">
          <i class="bi bi-chat-fill me-1"></i>SMS
        </label>

        <input type="radio" class="btn-check" name="channelRadio" id="chFb" value="fb_message">
        <label class="btn btn-outline-primary btn-sm" for="chFb">
          <i class="bi bi-facebook me-1"></i>FB
        </label>

        <input type="radio" class="btn-check" name="channelRadio" id="chWa" value="whatsapp">
        <label class="btn btn-outline-success btn-sm" for="chWa">
          <i class="bi bi-whatsapp me-1"></i>WA
        </label>

        <input type="radio" class="btn-check" name="channelRadio" id="chMeet" value="meeting">
        <label class="btn btn-outline-warning btn-sm" for="chMeet">
          <i class="bi bi-people-fill me-1"></i>Meet
        </label>

        <input type="radio" class="btn-check" name="channelRadio" id="chOther" value="other">
        <label class="btn btn-outline-secondary btn-sm" for="chOther">
          <i class="bi bi-three-dots me-1"></i>Other
        </label>
      </div>
    </div>

    <!-- ── CALL FORM ─────────────────────────────────── -->
    <form method="post" id="callForm" style="display:block">
      <?= csrf_field() ?>
      <input type="hidden" name="channel" value="call">
      <input type="hidden" name="contact_id" id="wsContactId" value="<?= $preload_id ?: '' ?>">

      <div class="p-3">
        <!-- Phone + Direction -->
        <div class="row g-2 mb-2">
          <div class="col-7">
            <label class="form-label">Phone Dialed</label>
            <input type="tel" class="form-control" name="phone_dialed" id="wsPhone"
                   data-phone placeholder="017XXXXXXXX"
                   value="<?= $preload_contact ? h($preload_contact['phone']) : '' ?>">
          </div>
          <div class="col-5">
            <label class="form-label">Direction</label>
            <select class="form-select" name="direction" id="wsDirection">
              <option value="outbound">⬆ Outbound</option>
              <option value="inbound">⬇ Inbound</option>
            </select>
          </div>
        </div>

        <!-- Duration + Timer -->
        <div class="row g-2 mb-2">
          <div class="col-7">
            <label class="form-label">Duration (mm:ss)</label>
            <div class="input-group">
              <input type="text" class="form-control" name="duration_display" id="durationDisplay"
                     placeholder="00:00" pattern="\d+:\d{2}">
              <input type="hidden" name="duration_seconds" id="durationSeconds" value="0">
              <button type="button" class="btn btn-outline-secondary btn-sm" id="timerToggle" title="Start timer">
                <i class="bi bi-play-fill" id="timerIcon"></i>
              </button>
            </div>
          </div>
          <div class="col-5">
            <label class="form-label">Started At</label>
            <input type="datetime-local" class="form-control" name="started_at" id="wsStartedAt"
                   value="<?= date('Y-m-d\TH:i') ?>">
          </div>
        </div>

        <!-- Outcome (typeahead) -->
        <div class="mb-2 position-relative">
          <label class="form-label">Outcome <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="outcomeDisplay"
                 placeholder="Start typing outcome…" autocomplete="off"
                 data-autocomplete="outcomes" data-ac-id-field="outcome_id" required>
          <input type="hidden" name="outcome_id" id="outcome_id">
          <div class="mt-1 d-flex flex-wrap gap-1" id="quickOutcomes">
            <?php foreach (array_slice($call_outcomes, 0, 5) as $o): ?>
            <button type="button" class="btn btn-xs btn-outline-secondary quick-outcome-btn py-0 px-2"
                    data-id="<?= $o['id'] ?>" data-name="<?= h($o['name']) ?>"
                    style="font-size:.7rem;border-color:<?= h($o['color']) ?>;color:<?= h($o['color']) ?>">
              <?= h($o['name']) ?>
            </button>
            <?php endforeach ?>
          </div>
        </div>

        <!-- Campaign -->
        <div class="mb-2 position-relative">
          <label class="form-label">Campaign (optional)</label>
          <input type="text" class="form-control" id="campaignDisplay"
                 placeholder="Search campaign…" autocomplete="off"
                 data-autocomplete="campaigns" data-ac-id-field="campaign_id">
          <input type="hidden" name="campaign_id" id="campaign_id">
          <?php if ($default_script): ?>
          <div class="mt-1">
            <button type="button" class="btn btn-xs btn-outline-info py-0 px-2" id="loadScriptBtn"
                    data-script-id="<?= $default_script['id'] ?>" style="font-size:.7rem">
              <i class="bi bi-file-text me-1"></i>Load Default Script
            </button>
          </div>
          <?php endif ?>
        </div>

        <!-- Notes -->
        <div class="mb-2">
          <label class="form-label">Notes</label>
          <textarea class="form-control" name="notes" rows="2"
                    placeholder="Call notes…"></textarea>
        </div>

        <!-- Summary (collapsible) -->
        <div class="border rounded p-2 mb-2 bg-light">
          <a href="#callSummary" data-bs-toggle="collapse" class="text-decoration-none text-dark d-flex align-items-center">
            <i class="bi bi-chevron-down me-2 small"></i>
            <span class="fw-semibold small">Call Summary & Follow-up</span>
          </a>
          <div class="collapse mt-2" id="callSummary">
            <div class="mb-2">
              <label class="form-label">Key Points</label>
              <textarea class="form-control form-control-sm" name="key_points" rows="2"
                        placeholder="Key discussion points…"></textarea>
            </div>
            <div class="row g-2 mb-2">
              <div class="col-6">
                <label class="form-label">Sentiment</label>
                <select class="form-select form-select-sm" name="sentiment">
                  <option value="neutral">😐 Neutral</option>
                  <option value="positive">😊 Positive</option>
                  <option value="negative">😟 Negative</option>
                </select>
              </div>
              <div class="col-6">
                <div class="form-check mt-4">
                  <input class="form-check-input" type="checkbox" name="follow_up_required" id="followUpCheck">
                  <label class="form-check-label" for="followUpCheck">Follow-up needed</label>
                </div>
              </div>
            </div>
            <div id="followUpDateRow" style="display:none">
              <label class="form-label">Follow-up Date</label>
              <input type="date" class="form-control form-control-sm" name="follow_up_date"
                     min="<?= date('Y-m-d') ?>">
            </div>
            <!-- Callback scheduling -->
            <div id="callbackScheduleRow" style="display:none">
              <label class="form-label">Schedule Callback</label>
              <input type="datetime-local" class="form-control form-control-sm" name="callback_date"
                     min="<?= date('Y-m-d\TH:i') ?>">
            </div>
          </div>
        </div>

        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-primary" id="saveCallBtn">
            <i class="bi bi-save me-2"></i>Save Call
          </button>
          <a href="<?= BASE_URL ?>/modules/calls/index.php" class="btn btn-outline-secondary btn-sm">
            View Call History
          </a>
        </div>
      </div>
    </form>

    <!-- ── SMS FORM ─────────────────────────────────────── -->
    <form method="post" id="smsForm" style="display:none">
      <?= csrf_field() ?>
      <input type="hidden" name="channel" value="sms">
      <input type="hidden" name="contact_id" id="smsContactId">
      <div class="p-3">
        <div class="mb-2">
          <label class="form-label">Send To</label>
          <input type="tel" class="form-control" name="phone_to" id="smsPhone"
                 data-phone placeholder="017XXXXXXXX">
        </div>
        <div class="mb-2">
          <label class="form-label d-flex align-items-center">
            Message
            <span class="ms-auto small text-muted"><span id="smsCharCount">0</span>/160</span>
          </label>
          <textarea class="form-control" name="message" id="smsMessage" rows="4"
                    placeholder="Type your message…" maxlength="320"></textarea>
        </div>
        <button type="submit" class="btn btn-success w-100">
          <i class="bi bi-send-fill me-2"></i>Send SMS
        </button>
      </div>
    </form>

    <!-- ── ACTIVITY FORM (FB, WA, Meeting, Other) ──────── -->
    <form method="post" id="activityForm" style="display:none">
      <?= csrf_field() ?>
      <input type="hidden" name="channel" id="activityChannel">
      <input type="hidden" name="contact_id" id="activityContactId">
      <div class="p-3">
        <div class="mb-2">
          <label class="form-label">Activity Type</label>
          <select class="form-select" name="type_id" id="activityTypeId">
            <?php foreach ($task_types as $tt): ?>
            <option value="<?= $tt['id'] ?>"><?= h($tt['name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Title / Summary</label>
          <input type="text" class="form-control" name="title" placeholder="e.g. FB message about pricing">
        </div>
        <div class="mb-2">
          <label class="form-label">Notes</label>
          <textarea class="form-control" name="notes" rows="3" placeholder="Details…"></textarea>
        </div>
        <button type="submit" class="btn btn-info w-100">
          <i class="bi bi-check-circle me-2"></i>Log Activity
        </button>
      </div>
    </form>

    <!-- ── Quick action buttons ─────────────────────────── -->
    <div class="px-3 pb-3 d-flex gap-2 flex-wrap" id="quickActionBtns" style="display:none !important">
      <button class="btn btn-sm btn-outline-warning" id="wsAddCallback">
        <i class="bi bi-alarm me-1"></i>Schedule Callback
      </button>
      <button class="btn btn-sm btn-outline-danger" id="wsAddThread">
        <i class="bi bi-chat-square-text me-1"></i>Open Thread
      </button>
      <button class="btn btn-sm btn-outline-purple" id="wsAddTask">
        <i class="bi bi-plus-square me-1"></i>Add Task
      </button>
    </div>

  </div>
</div><!-- /.workspace-layout -->

<!-- ── Script Modal ────────────────────────────────────────── -->
<div class="modal fade" id="scriptModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="scriptModalTitle">Call Script</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <pre id="scriptContent" style="white-space:pre-wrap;font-family:inherit;font-size:.875rem"></pre>
      </div>
    </div>
  </div>
</div>

<!-- ── Quick Inbound Modal ──────────────────────────────────── -->
<div class="modal fade" id="inboundModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h6 class="modal-title"><i class="bi bi-telephone-inbound me-2"></i>Inbound Call</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="quickInboundForm">
        <?= csrf_field() ?>
        <input type="hidden" name="channel" value="call">
        <input type="hidden" name="direction" value="inbound">
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Caller Phone</label>
            <input type="tel" class="form-control" name="phone_dialed" data-phone
                   placeholder="017XXXXXXXX" autofocus>
          </div>
          <div class="mb-2">
            <label class="form-label">Outcome</label>
            <select class="form-select" name="outcome_id">
              <option value="">— Select —</option>
              <?php foreach ($call_outcomes as $o): ?>
              <option value="<?= $o['id'] ?>"><?= h($o['name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Duration (seconds)</label>
            <input type="number" class="form-control" name="duration_seconds" placeholder="60">
          </div>
          <textarea class="form-control" name="notes" rows="2" placeholder="Quick notes…"></textarea>
        </div>
        <div class="modal-footer py-2">
          <button type="submit" class="btn btn-success btn-sm w-100">
            <i class="bi bi-save me-1"></i>Log Call
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ── Workspace JS ──────────────────────────────────────────────
const WS = {
  contactId:   <?= $preload_id ?: 'null' ?>,
  contactData: <?= $preload_contact ? json_encode($preload_contact) : 'null' ?>,
};

// Wire up contact search to workspace
const wsSearch = document.getElementById('wsContactSearch');
const wsDropdown = document.getElementById('wsSearchDropdown');

if (wsSearch) {
  const ac = new Autocomplete(wsSearch, {
    source: 'contacts',
    onSelect: function(item) {
      loadContact(item.id);
    }
  });
  // Override dropdown to use wsDropdown
  wsSearch._ac = ac;
}

// ── Load contact into panel ───────────────────────────────────
function loadContact(id) {
  // Fetch contact data
  fetch(`${APP.apiUrl}?action=search_contacts&q=&limit=1&id=${id}`)
    .catch(() => {});

  // Update hidden fields
  document.getElementById('wsContactId').value = id;
  document.getElementById('smsContactId').value = id;
  document.getElementById('activityContactId').value = id;

  // Show view button
  document.getElementById('viewContactBtn').onclick = () => {
    window.open(`${APP.baseUrl}/modules/contacts/view.php?id=${id}`, '_blank');
  };
  document.getElementById('viewAllHistoryBtn').href = `${APP.baseUrl}/modules/contacts/view.php?id=${id}`;
  document.getElementById('viewAllHistoryBtn').style.display = 'block';

  // Load contact details
  fetch(`${APP.apiUrl}?action=search_contacts&q=${id}`)
    .catch(() => {});

  // Load history
  loadHistory(id);
  loadHints(id);
}

function displayContact(c) {
  document.getElementById('noContactMsg').style.display = 'none';
  document.getElementById('contactInfo').style.display = 'block';
  document.getElementById('ciName').textContent = c.name || '—';
  document.getElementById('ciPhone').textContent = c.phone || '';
  document.getElementById('ciCompany').textContent = c.company ? '· ' + c.company : '';
  document.getElementById('wsPhone').value = c.phone || '';

  // Badges
  const badges = document.getElementById('ciBadges');
  badges.innerHTML = '';
  if (c.contact_type) {
    badges.innerHTML += `<span class="badge bg-secondary" style="font-size:.7rem">${c.contact_type.replace('_',' ')}</span>`;
  }
  if (c.status && c.status !== 'active') {
    badges.innerHTML += `<span class="badge bg-warning text-dark" style="font-size:.7rem">${c.status}</span>`;
  }
}

async function loadHistory(contactId) {
  document.getElementById('historyPlaceholder').style.display = 'block';
  document.getElementById('historyList').innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
  try {
    const resp = await fetch(`${APP.apiUrl}?action=contact_history&id=${contactId}&limit=7`);
    const items = await resp.json();
    renderHistory(items);
  } catch(e) {
    document.getElementById('historyList').innerHTML = '<div class="text-center text-muted py-3 small">Failed to load history</div>';
  }
}

function renderHistory(items) {
  const list = document.getElementById('historyList');
  document.getElementById('historyPlaceholder').style.display = 'none';
  if (!items || items.length === 0) {
    list.innerHTML = '<div class="text-center text-muted py-3 small">No interaction history</div>';
    return;
  }
  list.innerHTML = items.map(item => {
    const icons = { call:'bi-telephone-fill text-primary', sms:'bi-chat-fill text-success',
                    task:'bi-check-square-fill text-purple' };
    const icon = icons[item.type] || 'bi-circle';
    const dir = item.direction === 'inbound' ? '⬇' : '⬆';
    return `
    <div class="history-item ${item.type}" onclick="if(item.type==='call') window.open('${APP.baseUrl}/modules/calls/view.php?id=${item.id}')">
      <div class="history-icon" style="background:#f0f4ff">
        <i class="bi ${icon}" style="font-size:.7rem"></i>
      </div>
      <div class="min-w-0 flex-grow-1">
        <div class="fw-semibold text-truncate" style="font-size:.78rem">
          ${item.type === 'call' ? dir + ' ' : ''}
          ${item.outcome ? item.outcome : item.notes ? item.notes.substring(0,40) : item.type}
        </div>
        <div class="text-muted d-flex gap-2">
          <span>${item.dt_human || ''}</span>
          ${item.duration_human ? `<span>${item.duration_human}</span>` : ''}
          <span>${item.agent_name || ''}</span>
        </div>
        ${item.key_points ? `<div class="text-muted text-truncate" style="font-size:.7rem">${item.key_points.substring(0,60)}</div>` : ''}
      </div>
      ${item.type === 'call' ? `<i class="bi bi-box-arrow-up-right text-muted flex-shrink-0" style="font-size:.7rem"></i>` : ''}
    </div>`;
  }).join('');
}

async function loadHints(contactId) {
  try {
    const resp = await fetch(`${APP.apiUrl}?action=contact_hints&id=${contactId}`);
    const h = await resp.json();
    const pills = document.getElementById('hintPills');
    pills.innerHTML = '';
    if (h.days_since_call !== null && h.days_since_call > 30) {
      pills.innerHTML += `<span class="hint-pill"><i class="bi bi-clock"></i>Last called ${h.days_since_call}d ago</span>`;
    }
    if (h.open_threads > 0) {
      pills.innerHTML += `<span class="hint-pill danger"><i class="bi bi-chat-square-text"></i>${h.open_threads} open thread${h.open_threads>1?'s':''}</span>`;
    }
    if (h.overdue_callbacks > 0) {
      pills.innerHTML += `<span class="hint-pill danger"><i class="bi bi-alarm"></i>${h.overdue_callbacks} overdue callback${h.overdue_callbacks>1?'s':''}</span>`;
    }
    if (h.active_campaign) {
      pills.innerHTML += `<span class="hint-pill info"><i class="bi bi-megaphone"></i>Campaign: ${h.active_campaign.name}</span>`;
    }
    if (h.pending_tasks > 0) {
      pills.innerHTML += `<span class="hint-pill"><i class="bi bi-check-square"></i>${h.pending_tasks} pending task${h.pending_tasks>1?'s':''}</span>`;
    }
  } catch(e) {}
}

// ── Channel switching ─────────────────────────────────────────
document.querySelectorAll('input[name="channelRadio"]').forEach(radio => {
  radio.addEventListener('change', function() {
    const ch = this.value;
    document.getElementById('callForm').style.display     = ch === 'call'  ? 'block' : 'none';
    document.getElementById('smsForm').style.display      = ch === 'sms'   ? 'block' : 'none';
    document.getElementById('activityForm').style.display = !['call','sms'].includes(ch) ? 'block' : 'none';
    if (!['call','sms'].includes(ch)) {
      document.getElementById('activityChannel').value = ch;
      // Auto-select matching task type
      const sel = document.getElementById('activityTypeId');
      const chNames = { fb_message:'Facebook Message', whatsapp:'WhatsApp', meeting:'Meeting', other:'Other' };
      const target = chNames[ch] || '';
      for (let opt of sel.options) {
        if (opt.textContent.includes(target)) { sel.value = opt.value; break; }
      }
    }
  });
});

// ── Quick outcome buttons ─────────────────────────────────────
document.querySelectorAll('.quick-outcome-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.getElementById('outcomeDisplay').value = this.dataset.name;
    document.getElementById('outcome_id').value     = this.dataset.id;
    // Show callback schedule if needed
    checkCallbackRequired(this.dataset.id);
  });
});

async function checkCallbackRequired(outcomeId) {
  const outcomes = <?= json_encode($call_outcomes) ?>;
  const o = outcomes.find(x => x.id == outcomeId);
  const row = document.getElementById('callbackScheduleRow');
  if (o && o.requires_callback) {
    document.getElementById('callSummary').classList.add('show');
    row.style.display = 'block';
  } else {
    row.style.display = 'none';
  }
}

// ── Follow-up check toggle ────────────────────────────────────
document.getElementById('followUpCheck').addEventListener('change', function() {
  document.getElementById('followUpDateRow').style.display = this.checked ? 'block' : 'none';
});

// ── Timer ────────────────────────────────────────────────────
let timerRunning = false;
document.getElementById('timerToggle').addEventListener('click', function() {
  const display = document.getElementById('durationDisplay');
  const seconds = document.getElementById('durationSeconds');
  const icon    = document.getElementById('timerIcon');
  if (!timerRunning) {
    timerRunning = true;
    icon.className = 'bi bi-stop-fill';
    this.classList.replace('btn-outline-secondary', 'btn-danger');
    document.getElementById('wsStartedAt').value = new Date().toISOString().slice(0,16);
    startCallTimer({ textContent: '' });
    const interval = setInterval(() => {
      display.value = secondsToMmss(callTimerSeconds);
    }, 1000);
    this._interval = interval;
  } else {
    timerRunning = false;
    icon.className = 'bi bi-play-fill';
    this.classList.replace('btn-danger', 'btn-outline-secondary');
    const s = stopCallTimer(null);
    seconds.value = s;
    display.value = secondsToMmss(s);
    clearInterval(this._interval);
  }
});

// ── Duration display to seconds ───────────────────────────────
document.getElementById('durationDisplay').addEventListener('blur', function() {
  document.getElementById('durationSeconds').value = parseDuration(this.value);
});

// ── Load script ───────────────────────────────────────────────
document.getElementById('loadScriptBtn')?.addEventListener('click', async function() {
  const sid = document.getElementById('campaign_id').value || this.dataset.scriptId;
  if (!sid) { showToast('Select a campaign first, or using default script', 'info'); }
  const scriptId = sid || this.dataset.scriptId;
  const resp = await fetch(`${APP.apiUrl}?action=get_script&id=${scriptId}`);
  const data = await resp.json();
  if (data) {
    document.getElementById('scriptModalTitle').textContent = data.name;
    document.getElementById('scriptContent').textContent = data.content;
    new bootstrap.Modal(document.getElementById('scriptModal')).show();
  }
});

// Also load script when campaign changes
document.getElementById('campaign_id').addEventListener('change', async function() {
  if (!this.value) return;
  const camp = await (await fetch(`${APP.apiUrl}?action=search_campaigns&q=${this.value}`)).json();
  // Script will be loaded via button
});

// ── Quick inbound button ─────────────────────────────────────
document.getElementById('quickInboundBtn').addEventListener('click', function() {
  new bootstrap.Modal(document.getElementById('inboundModal')).show();
});

// ── SMS character counter ─────────────────────────────────────
document.getElementById('smsMessage').addEventListener('input', function() {
  document.getElementById('smsCharCount').textContent = this.value.length;
});

// ── New contact button ────────────────────────────────────────
document.getElementById('wsNewContact').addEventListener('click', function() {
  const q = wsSearch.value.trim();
  window.location = `${APP.baseUrl}/modules/contacts/form.php` + (q ? `?prefill=${encodeURIComponent(q)}` : '');
});

// ── Pre-load contact if ID in URL ─────────────────────────────
if (WS.contactData) {
  displayContact(WS.contactData);
  loadHistory(WS.contactData.id);
  loadHints(WS.contactData.id);
  wsSearch.value = WS.contactData.name;
}

// ── Wire Autocomplete to call panel ──────────────────────────
window.onContactSelected = function(item) {
  loadContact(item.id);
  displayContact(item);
  document.getElementById('wsPhone').value = item.phone || '';
  document.getElementById('wsContactId').value = item.id;
  document.getElementById('smsContactId').value = item.id;
  document.getElementById('activityContactId').value = item.id;
  document.getElementById('smsPhone').value = item.phone || '';
};
</script>

<?php require ROOT . '/partials/footer.php'; ?>
