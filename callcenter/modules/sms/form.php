<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Send SMS';
$active_page = 'sms';

$uid = current_user_id();

// Prefill from URL
$new_contact_id = (int)($_GET['contact_id'] ?? 0);
$new_contact    = $new_contact_id ? db_row("SELECT id, name, phone FROM contacts WHERE id=?", [$new_contact_id]) : null;

// ── POST: Send SMS ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $contact_id = (int)($_POST['contact_id'] ?? 0);
    $phone_to   = clean_phone($_POST['phone_to'] ?? '');
    $message    = clean($_POST['message'] ?? '');

    $errors = [];
    if (!$phone_to) $errors[] = 'Phone number is required.';
    if (!$message)  $errors[] = 'Message is required.';
    if (mb_strlen($message) > 1000) $errors[] = 'Message is too long (max 1000 characters).';

    if (empty($errors)) {
        // In a real system, call SMS gateway here
        // For now, record as 'sent' (simulated)
        $sms_id = db_exec(
            "INSERT INTO sms_log (contact_id, agent_id, phone_to, message, status, sent_at)
             VALUES (?, ?, ?, ?, 'sent', NOW())",
            [$contact_id ?: null, $uid, $phone_to, $message]
        );
        audit_log('send_sms', 'sms_log', $sms_id, "SMS to $phone_to");
        flash_success("SMS sent to <strong>" . h($phone_to) . "</strong>.");

        if ($contact_id) {
            redirect(BASE_URL . '/modules/contacts/view.php?id=' . $contact_id);
        } else {
            redirect(BASE_URL . '/modules/sms/index.php');
        }
    }
}

// SMS templates (from scripts table, type detection)
$templates = db_rows(
    "SELECT id, name, content FROM scripts ORDER BY is_default DESC, name ASC LIMIT 20"
);

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/sms/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-chat-dots-fill text-success ms-1"></i>
  <h5 class="ms-1">Send SMS</h5>
</div>

<div class="page-body">
  <div class="row justify-content-center">
    <div class="col-12 col-md-7">

      <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul>
      </div>
      <?php endif ?>

      <div class="card">
        <div class="card-header"><i class="bi bi-send me-2"></i>Compose SMS</div>
        <div class="card-body">
          <form method="post" id="smsForm">
            <?= csrf_field() ?>

            <div class="mb-3 position-relative">
              <label class="form-label">Contact</label>
              <input type="text" class="form-control" id="smsContactName"
                     data-autocomplete="contacts" data-ac-id-field="contact_id_hidden"
                     value="<?= $new_contact ? h($new_contact['name']) : '' ?>"
                     placeholder="Search contact (optional)…">
              <input type="hidden" name="contact_id" id="contact_id_hidden"
                     value="<?= $new_contact_id ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Phone Number <span class="text-danger">*</span></label>
              <input type="tel" class="form-control" name="phone_to" id="smsPhone"
                     data-phone
                     value="<?= h($new_contact['phone'] ?? ($_POST['phone_to'] ?? '')) ?>"
                     placeholder="017XXXXXXXX" required>
            </div>

            <?php if (!empty($templates)): ?>
            <div class="mb-3">
              <label class="form-label">Use Template</label>
              <select class="form-select" id="templateSelect">
                <option value="">Select a template…</option>
                <?php foreach ($templates as $t): ?>
                <option value="<?= h($t['content']) ?>"><?= h($t['name']) ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <?php endif ?>

            <div class="mb-3">
              <label class="form-label d-flex justify-content-between">
                Message <span class="text-danger">*</span>
                <span class="text-muted small" id="charCount">0 / 160</span>
              </label>
              <textarea class="form-control" name="message" id="smsMessage" rows="5" required
                        maxlength="1000"
                        placeholder="Type your message here…"><?= h($_POST['message'] ?? '') ?></textarea>
              <div class="form-text text-muted">160 chars = 1 SMS · 320 = 2 SMS etc.</div>
            </div>

            <div class="d-flex gap-2 mt-3">
              <button type="submit" class="btn btn-success">
                <i class="bi bi-send me-2"></i>Send SMS
              </button>
              <a href="<?= BASE_URL ?>/modules/sms/index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// Fill phone when contact selected
(function() {
  var orig = window.onContactSelected;
  window.onContactSelected = function(c) {
    if (orig) orig(c);
    document.getElementById('smsPhone').value = c.phone || '';
    document.getElementById('contact_id_hidden').value = c.id || '';
  };

  // Template picker
  var sel = document.getElementById('templateSelect');
  if (sel) {
    sel.addEventListener('change', function() {
      if (this.value) {
        document.getElementById('smsMessage').value = this.value;
        updateCount();
        this.value = '';
      }
    });
  }

  // Char counter
  function updateCount() {
    var msg = document.getElementById('smsMessage');
    var len = msg.value.length;
    var sms = Math.ceil(len / 160) || 1;
    document.getElementById('charCount').textContent =
      len + ' / ' + (sms * 160) + ' (' + sms + ' SMS)';
  }
  var msg = document.getElementById('smsMessage');
  if (msg) {
    msg.addEventListener('input', updateCount);
    updateCount();
  }
})();
</script>

<?php require ROOT . '/partials/footer.php'; ?>
