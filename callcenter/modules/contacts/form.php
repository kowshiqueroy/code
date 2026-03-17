<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_role('executive');

$id      = (int)($_GET['id'] ?? 0);
$contact = $id ? db_row("SELECT * FROM contacts WHERE id=?", [$id]) : null;
$is_edit = (bool)$contact;

if ($id && !$contact) redirect(BASE_URL . '/modules/contacts/index.php');

$page_title  = $is_edit ? 'Edit Contact' : 'New Contact';
$active_page = 'contacts';

// ── POST: Save ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name         = clean($_POST['name']         ?? '');
    $phone        = clean_phone($_POST['phone']  ?? '');
    $alt_phone    = clean_phone($_POST['alt_phone'] ?? '');
    $email        = clean($_POST['email']        ?? '');
    $company      = clean($_POST['company']      ?? '');
    $contact_type = clean($_POST['contact_type'] ?? 'customer');
    $status       = clean($_POST['status']       ?? 'active');
    $notes        = clean($_POST['notes']        ?? '');
    $assigned_to  = (int)($_POST['assigned_to'] ?? 0);
    $tags         = clean($_POST['tags']         ?? '');

    $errors = [];
    if (!$name)  $errors[] = 'Name is required.';
    if (!$phone) $errors[] = 'Phone is required.';

    if (empty($errors)) {
        if ($is_edit) {
            db_exec(
                "UPDATE contacts SET name=?, phone=?, alt_phone=?, email=?, company=?,
                  contact_type=?, status=?, notes=?, assigned_to=?, updated_at=NOW()
                 WHERE id=?",
                [$name, $phone, $alt_phone ?: null, $email ?: null, $company ?: null,
                 $contact_type, $status, $notes ?: null, $assigned_to ?: null, $id]
            );
            // Update tags
            db_exec("DELETE FROM contact_tags WHERE contact_id=?", [$id]);
            foreach (array_filter(array_map('trim', explode(',', $tags))) as $tag) {
                db_exec("INSERT INTO contact_tags (contact_id, tag) VALUES (?,?)", [$id, $tag]);
            }
            audit_log('edit_contact', 'contacts', $id, "Updated contact: $name");
            flash_success("Contact <strong>" . h($name) . "</strong> updated.");
            redirect(BASE_URL . '/modules/contacts/view.php?id=' . $id);
        } else {
            $cid = db_exec(
                "INSERT INTO contacts (name, phone, alt_phone, email, company, contact_type,
                  status, notes, assigned_to, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$name, $phone, $alt_phone ?: null, $email ?: null, $company ?: null,
                 $contact_type, $status, $notes ?: null, $assigned_to ?: null, current_user_id()]
            );
            // Save tags
            foreach (array_filter(array_map('trim', explode(',', $tags))) as $tag) {
                db_exec("INSERT INTO contact_tags (contact_id, tag) VALUES (?,?)", [$cid, $tag]);
            }
            audit_log('create_contact', 'contacts', $cid, "Created contact: $name");
            flash_success("Contact <strong>" . h($name) . "</strong> created.");
            redirect(BASE_URL . '/modules/contacts/view.php?id=' . $cid);
        }
    }
    // If errors, keep posted values
    $contact = array_merge($contact ?? [], $_POST);
}

// Prefill from URL (workspace "New" button)
$prefill = clean($_GET['prefill'] ?? '');
if (!$is_edit && $prefill && !isset($_POST['name'])) {
    // Try to detect if prefill is a phone or name
    $contact = ['name' => preg_match('/\d/', $prefill) ? '' : $prefill,
                'phone' => preg_match('/\d/', $prefill) ? $prefill : ''];
}

$contact_types = ['internal_staff','sr','asm','dsm','tsm','dealer','distributor','shop_owner','customer','other'];
$agents = db_rows("SELECT id, name FROM users WHERE status='active' ORDER BY name");

$existing_tags = '';
if ($is_edit) {
    $tagRows = db_rows("SELECT tag FROM contact_tags WHERE contact_id=?", [$id]);
    $existing_tags = implode(', ', array_column($tagRows, 'tag'));
}

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/contacts/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-person-plus text-primary ms-1"></i>
  <h5 class="ms-1"><?= $page_title ?></h5>
</div>

<div class="page-body">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">

      <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
          <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?>
        </ul>
      </div>
      <?php endif ?>

      <div class="card">
        <div class="card-header"><?= $is_edit ? 'Edit' : 'New' ?> Contact</div>
        <div class="card-body">
          <form method="post" id="contactForm">
            <?= csrf_field() ?>
            <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <?php endif ?>
            <input type="hidden" name="force_create" value="0">

            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" required
                       data-capitalize
                       value="<?= h($contact['name'] ?? '') ?>"
                       placeholder="Enter full name">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Phone <span class="text-danger">*</span></label>
                <input type="tel" class="form-control" name="phone" required
                       data-phone
                       value="<?= h($contact['phone'] ?? '') ?>"
                       placeholder="017XXXXXXXX">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Alt. Phone</label>
                <input type="tel" class="form-control" name="alt_phone"
                       data-phone
                       value="<?= h($contact['alt_phone'] ?? '') ?>"
                       placeholder="018XXXXXXXX">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email"
                       value="<?= h($contact['email'] ?? '') ?>"
                       placeholder="email@example.com">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Company / Organization</label>
                <input type="text" class="form-control" name="company"
                       value="<?= h($contact['company'] ?? '') ?>"
                       placeholder="Company name">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Contact Type</label>
                <select class="form-select" name="contact_type">
                  <?php foreach ($contact_types as $t): ?>
                  <option value="<?= $t ?>" <?= ($contact['contact_type']??'customer')===$t?'selected':'' ?>>
                    <?= ucwords(str_replace('_',' ',$t)) ?>
                  </option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                  <option value="active"   <?= ($contact['status']??'active')==='active'  ?'selected':'' ?>>Active</option>
                  <option value="inactive" <?= ($contact['status']??'')==='inactive'?'selected':'' ?>>Inactive</option>
                  <option value="blocked"  <?= ($contact['status']??'')==='blocked' ?'selected':'' ?>>Blocked</option>
                  <option value="former"   <?= ($contact['status']??'')==='former'  ?'selected':'' ?>>Former</option>
                </select>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Assigned To</label>
                <select class="form-select" name="assigned_to">
                  <option value="">Unassigned</option>
                  <?php foreach ($agents as $a): ?>
                  <option value="<?= $a['id'] ?>" <?= ($contact['assigned_to']??0)==$a['id']?'selected':'' ?>>
                    <?= h($a['name']) ?>
                  </option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Tags <span class="form-hint">(comma separated)</span></label>
                <input type="text" class="form-control" name="tags"
                       value="<?= h($is_edit ? $existing_tags : ($contact['tags'] ?? '')) ?>"
                       placeholder="e.g. priority, dhaka, B2B">
              </div>
              <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" rows="3"
                          placeholder="Any additional notes…"><?= h($contact['notes'] ?? '') ?></textarea>
              </div>
            </div>

            <div class="d-flex gap-2 mt-4">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-2"></i><?= $is_edit ? 'Save Changes' : 'Create Contact' ?>
              </button>
              <a href="<?= BASE_URL ?>/modules/contacts/index.php" class="btn btn-outline-secondary">
                Cancel
              </a>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require ROOT . '/partials/footer.php'; ?>
