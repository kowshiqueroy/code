<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_role('super_admin');

$page_title  = 'User Form';
$active_page = 'users';

$id   = (int)($_GET['id'] ?? 0);
$user = $id ? db_row("SELECT * FROM users WHERE id=?", [$id]) : null;
if ($id && !$user) {
    flash_error('User not found.');
    redirect(BASE_URL . '/modules/users/index.php');
}
$page_title = $user ? 'Edit User: ' . $user['name'] : 'Add User';

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name     = clean($_POST['name']     ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $role     = clean($_POST['role']     ?? 'executive');
    $status = isset($_POST['is_active']) ? 'active' : 'inactive';
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';
    $phone     = clean($_POST['phone']   ?? '');

    $errors = [];
    if (!$name)  $errors[] = 'Name is required.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!in_array($role, ['super_admin','senior_executive','executive','viewer'])) $errors[] = 'Invalid role.';

    // Check email uniqueness
    $existing = db_val("SELECT id FROM users WHERE email=? AND id != ?", [$email, $id ?: 0]);
    if ($existing) $errors[] = 'Email already in use.';

    if (!$id && !$password) $errors[] = 'Password is required for new users.';
    if ($password && $password !== $password2) $errors[] = 'Passwords do not match.';
    if ($password && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

    if (empty($errors)) {
        if ($id) {
            $fields = "name=?, email=?, role=?, status=?, phone=?";
            $vals   = [$name, $email, $role, $status, $phone];
            if ($password) {
                $fields .= ", password=?";
                $vals[]  = password_hash($password, PASSWORD_DEFAULT);
            }
            $vals[] = $id;
            db_exec("UPDATE users SET $fields WHERE id=?", $vals);
            audit_log('update_user', 'users', $id, "Updated: $name");
            flash_success('User updated successfully.');
        } else {
            db_exec(
                "INSERT INTO users (name, email, role, password, status, phone) VALUES (?, ?, ?, ?, ?, ?)",
                [$name, $email, $role, password_hash($password, PASSWORD_DEFAULT), $status, $phone]
            );
            $new_id = db_val("SELECT LAST_INSERT_ID()");
            audit_log('create_user', 'users', $new_id, "Created: $name");
            flash_success('User created successfully.');
        }
        redirect(BASE_URL . '/modules/users/index.php');
    }
}

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/users/index.php" class="btn btn-sm btn-outline-secondary me-2">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-person-gear text-primary me-1"></i>
  <h5 class="mb-0"><?= h($page_title) ?></h5>
</div>

<div class="page-body">

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul>
  </div>
  <?php endif ?>

  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card">
        <div class="card-header py-2 fw-semibold">User Details</div>
        <div class="card-body">
          <form method="post">
            <?= csrf_field() ?>

            <div class="mb-3">
              <label class="form-label">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required
                     value="<?= h($_POST['name'] ?? $user['name'] ?? '') ?>"
                     data-capitalize>
            </div>

            <div class="mb-3">
              <label class="form-label">Email Address <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" required
                     value="<?= h($_POST['email'] ?? $user['email'] ?? '') ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" class="form-control" data-phone
                     value="<?= h($_POST['phone'] ?? $user['phone'] ?? '') ?>"
                     placeholder="01X-XXXX-XXXX">
            </div>

            <div class="row mb-3">
              <div class="col">
                <label class="form-label">Role <span class="text-danger">*</span></label>
                <select name="role" class="form-select" required>
                  <?php
                  $roles = [
                    'viewer'           => 'Viewer',
                    'executive'        => 'Executive',
                    'senior_executive' => 'Senior Executive',
                    'super_admin'      => 'Super Admin',
                  ];
                  $selected_role = $_POST['role'] ?? $user['role'] ?? 'executive';
                  foreach ($roles as $val => $label): ?>
                  <option value="<?= $val ?>" <?= $selected_role===$val?'selected':'' ?>><?= $label ?></option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="col-auto">
                <label class="form-label">Status</label>
                <div class="form-check form-switch mt-2">
                  <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive"
                         <?= (($_POST['is_active'] ?? ($user['status'] ?? 'active')) !== 'inactive') ? 'checked' : '' ?>>
                  <label class="form-check-label" for="isActive">Active</label>
                </div>
              </div>
            </div>

            <hr>
            <div class="row mb-3">
              <div class="col">
                <label class="form-label"><?= $id ? 'New Password' : 'Password' ?> <?= !$id ? '<span class="text-danger">*</span>' : '' ?></label>
                <?php if ($id): ?><div class="form-text mb-1">Leave blank to keep current password.</div><?php endif ?>
                <div class="input-group">
                  <input type="password" name="password" id="password" class="form-control"
                         <?= !$id ? 'required' : '' ?> minlength="6"
                         placeholder="<?= $id ? 'Leave blank to keep' : 'Min 6 characters' ?>">
                  <button type="button" class="btn btn-outline-secondary" id="togglePw">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </div>
              <div class="col">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password2" id="password2" class="form-control"
                       placeholder="Repeat password">
              </div>
            </div>

            <!-- Role description -->
            <div class="alert alert-info py-2 small" id="roleDesc">
              Select a role to see permissions.
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i><?= $id ? 'Save Changes' : 'Create User' ?>
              </button>
              <a href="<?= BASE_URL ?>/modules/users/index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
// Password toggle
document.getElementById('togglePw')?.addEventListener('click', function() {
    const pw = document.getElementById('password');
    const eye = this.querySelector('i');
    if (pw.type === 'password') {
        pw.type = 'text';
        eye.className = 'bi bi-eye-slash';
    } else {
        pw.type = 'password';
        eye.className = 'bi bi-eye';
    }
});

// Role descriptions
const roleDescs = {
    viewer: 'View-only: Can see contacts, campaigns, scripts, and run shared reports. Cannot log calls or edit data.',
    executive: 'Standard agent: Can log calls, SMS, manage callbacks, tasks, build report templates, check in/out.',
    senior_executive: 'Team lead: All executive permissions + assign tasks to others, view team performance, refer leaves.',
    super_admin: 'Full access: All features including user management, system settings, leave approvals, and audit logs.',
};
document.querySelector('[name="role"]')?.addEventListener('change', function() {
    const desc = document.getElementById('roleDesc');
    if (desc) desc.textContent = roleDescs[this.value] || '';
});
// Trigger on load
document.querySelector('[name="role"]')?.dispatchEvent(new Event('change'));
</script>

<?php require ROOT . '/partials/footer.php'; ?>
