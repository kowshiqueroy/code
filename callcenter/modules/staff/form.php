<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_role('executive');

$id       = (int)($_GET['id'] ?? 0);
$contact  = $id ? db_row("SELECT * FROM contacts WHERE id=?", [$id]) : null;
$profile  = $id ? db_row("SELECT * FROM staff_profiles WHERE contact_id=?", [$id]) : null;
$is_edit  = (bool)$contact;

if ($id && !$contact) redirect(BASE_URL . '/modules/staff/index.php');

$page_title  = $is_edit ? 'Edit Staff Profile' : 'New Staff Member';
$active_page = 'staff';

// ── POST: Save ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // Contact fields
    $name       = clean($_POST['name']       ?? '');
    $phone      = clean_phone($_POST['phone'] ?? '');
    $alt_phone  = clean_phone($_POST['alt_phone'] ?? '');
    $email      = clean($_POST['email']      ?? '');
    $company    = 'Ovijat Group';

    // Staff profile fields
    $employee_id          = clean($_POST['employee_id']     ?? '');
    $department           = clean($_POST['department']      ?? '');
    $position             = clean($_POST['position']        ?? '');
    $join_date            = clean($_POST['join_date']       ?? '');
    $exit_date            = clean($_POST['exit_date']       ?? '');
    $is_active            = isset($_POST['is_active']) ? 1 : 0;
    $notes                = clean($_POST['notes']           ?? '');
    $successor_contact_id = (int)($_POST['successor_contact_id'] ?? 0);

    $errors = [];
    if (!$name)  $errors[] = 'Name is required.';
    if (!$phone) $errors[] = 'Phone is required.';

    if (empty($errors)) {
        if ($is_edit) {
            // Update contact
            db_exec(
                "UPDATE contacts SET name=?, phone=?, alt_phone=?, email=?, company=?,
                  contact_type='internal_staff', notes=?, updated_at=NOW()
                 WHERE id=?",
                [$name, $phone, $alt_phone ?: null, $email ?: null, $company, $notes ?: null, $id]
            );

            // If becoming former, add position history entry
            if ($profile && $profile['is_active'] && !$is_active && $position) {
                db_exec(
                    "INSERT INTO contact_position_history
                      (contact_id, position, department, effective_from, effective_to, notes)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$id, $position, $department ?: null, $profile['join_date'] ?? null,
                     $exit_date ?: date('Y-m-d'), 'Marked as former']
                );
            }

            if ($profile) {
                db_exec(
                    "UPDATE staff_profiles SET employee_id=?, department=?, position=?,
                      join_date=?, exit_date=?, is_active=?, successor_contact_id=?
                     WHERE contact_id=?",
                    [$employee_id ?: null, $department ?: null, $position ?: null,
                     $join_date ?: null, $exit_date ?: null, $is_active,
                     $successor_contact_id ?: null, $id]
                );
            } else {
                db_exec(
                    "INSERT INTO staff_profiles (contact_id, employee_id, department, position,
                      join_date, exit_date, is_active, successor_contact_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$id, $employee_id ?: null, $department ?: null, $position ?: null,
                     $join_date ?: null, $exit_date ?: null, $is_active,
                     $successor_contact_id ?: null]
                );
            }
            audit_log('edit_staff', 'contacts', $id, "Updated staff: $name");
            flash_success("Staff profile updated.");
            redirect(BASE_URL . '/modules/staff/view.php?id=' . $id);
        } else {
            // Create new contact
            $cid = db_exec(
                "INSERT INTO contacts (name, phone, alt_phone, email, company, contact_type, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, 'internal_staff', ?, ?)",
                [$name, $phone, $alt_phone ?: null, $email ?: null, $company, $notes ?: null, current_user_id()]
            );
            db_exec(
                "INSERT INTO staff_profiles (contact_id, employee_id, department, position,
                  join_date, exit_date, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$cid, $employee_id ?: null, $department ?: null, $position ?: null,
                 $join_date ?: null, $exit_date ?: null, 1]
            );
            audit_log('create_staff', 'contacts', $cid, "Created staff: $name");
            flash_success("Staff member <strong>" . h($name) . "</strong> added.");
            redirect(BASE_URL . '/modules/staff/view.php?id=' . $cid);
        }
    }
}

// Existing departments for datalist
$departments = db_rows("SELECT DISTINCT department FROM staff_profiles WHERE department IS NOT NULL ORDER BY department");

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/staff/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-person-badge-fill text-primary ms-1"></i>
  <h5 class="ms-1"><?= $page_title ?></h5>
</div>

<div class="page-body">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8">

      <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul>
      </div>
      <?php endif ?>

      <div class="card">
        <div class="card-header">Contact Information</div>
        <div class="card-body">
          <form method="post">
            <?= csrf_field() ?>
            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" required data-capitalize
                       value="<?= h($contact['name'] ?? '') ?>" placeholder="Enter full name">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Employee ID</label>
                <input type="text" class="form-control" name="employee_id"
                       value="<?= h($profile['employee_id'] ?? '') ?>" placeholder="EMP-001">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Phone <span class="text-danger">*</span></label>
                <input type="tel" class="form-control" name="phone" required data-phone
                       value="<?= h($contact['phone'] ?? '') ?>" placeholder="017XXXXXXXX">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Alt. Phone</label>
                <input type="tel" class="form-control" name="alt_phone" data-phone
                       value="<?= h($contact['alt_phone'] ?? '') ?>" placeholder="018XXXXXXXX">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email"
                       value="<?= h($contact['email'] ?? '') ?>" placeholder="staff@ovijat.com">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Department</label>
                <input type="text" class="form-control" name="department"
                       list="deptList"
                       value="<?= h($profile['department'] ?? '') ?>" placeholder="e.g. Sales, Support">
                <datalist id="deptList">
                  <?php foreach ($departments as $d): ?>
                  <option value="<?= h($d['department']) ?>">
                  <?php endforeach ?>
                </datalist>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Position / Designation</label>
                <input type="text" class="form-control" name="position"
                       value="<?= h($profile['position'] ?? '') ?>" placeholder="e.g. Call Center Executive">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label">Join Date</label>
                <input type="date" class="form-control" name="join_date"
                       value="<?= h($profile['join_date'] ?? '') ?>">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label">Exit Date</label>
                <input type="date" class="form-control" name="exit_date"
                       value="<?= h($profile['exit_date'] ?? '') ?>">
              </div>

              <?php if ($is_edit): ?>
              <div class="col-12">
                <div class="form-check form-switch">
                  <input type="checkbox" class="form-check-input" name="is_active" id="isActive"
                         <?= ($profile['is_active'] ?? 1) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="isActive">Currently Active Staff</label>
                </div>
              </div>

              <div class="col-12" id="successorSection" style="<?= ($profile['is_active'] ?? 1) ? 'display:none' : '' ?>">
                <label class="form-label">Successor / Replacement</label>
                <div class="position-relative">
                  <input type="text" class="form-control" id="successorName"
                         data-autocomplete="contacts" data-ac-id-field="successor_contact_id"
                         value="<?= $profile['successor_contact_id'] ? h(db_val("SELECT name FROM contacts WHERE id=?", [$profile['successor_contact_id']])) : '' ?>"
                         placeholder="Search for successor…">
                  <input type="hidden" name="successor_contact_id" id="successor_contact_id"
                         value="<?= h($profile['successor_contact_id'] ?? '') ?>">
                </div>
                <div class="form-text">Link to the person who replaced this staff member.</div>
              </div>
              <?php else: ?>
              <input type="hidden" name="is_active" value="1">
              <?php endif ?>

              <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" rows="2"
                          placeholder="Any additional notes…"><?= h($contact['notes'] ?? '') ?></textarea>
              </div>
            </div>

            <div class="d-flex gap-2 mt-4">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-2"></i><?= $is_edit ? 'Save Changes' : 'Add Staff Member' ?>
              </button>
              <a href="<?= BASE_URL ?>/modules/staff/index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
(function() {
  var activeChk = document.getElementById('isActive');
  var successorSec = document.getElementById('successorSection');
  if (activeChk && successorSec) {
    activeChk.addEventListener('change', function() {
      successorSec.style.display = this.checked ? 'none' : '';
    });
  }
})();
</script>

<?php require ROOT . '/partials/footer.php'; ?>
