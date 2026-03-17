<?php
require_once __DIR__ . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';

// Redirect if already logged in
if (is_logged_in()) {
    check_session_timeout();
    redirect(BASE_URL . '/modules/dashboard/index.php');
}

$error   = '';
$timeout = isset($_GET['timeout']);
$next    = $_GET['next'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (empty($email) || empty($pass)) {
        $error = 'Please enter both email and password.';
    } elseif (!attempt_login($email, $pass)) {
        $error = 'Invalid email or password.';
        // Small delay to prevent brute-force
        sleep(1);
    } else {
        $dest = ($next && str_starts_with($next, '/')) ? $next : BASE_URL . '/modules/dashboard/index.php';
        redirect($dest);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login — <?= h(APP_NAME) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background: linear-gradient(135deg, #1e2330 0%, #2d3548 100%); min-height: 100vh; }
    .login-card { border: none; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.4); }
    .login-logo { width: 64px; height: 64px; background: #0d6efd; border-radius: 16px;
                  display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; }
    .form-control:focus { box-shadow: 0 0 0 .25rem rgba(13,110,253,.2); }
    @media print { body { display: none; } }
  </style>
</head>
<body class="d-flex align-items-center justify-content-center" style="padding:1rem">
<div style="width:100%;max-width:420px">
  <div class="card login-card">
    <div class="card-body p-4 p-md-5">
      <div class="login-logo mb-3">
        <i class="bi bi-headset fs-2 text-white"></i>
      </div>
      <h4 class="text-center fw-bold mb-1"><?= h(APP_NAME) ?></h4>
      <p class="text-center text-muted small mb-4">Sign in to your account</p>

      <?php if ($timeout): ?>
      <div class="alert alert-warning py-2">
        <i class="bi bi-clock me-1"></i> Session expired due to inactivity. Please sign in again.
      </div>
      <?php endif ?>

      <?php if ($error): ?>
      <div class="alert alert-danger py-2">
        <i class="bi bi-exclamation-circle me-1"></i> <?= h($error) ?>
      </div>
      <?php endif ?>

      <form method="post" novalidate>
        <input type="hidden" name="next" value="<?= h($next) ?>">
        <div class="mb-3">
          <label class="form-label fw-semibold">Email Address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" class="form-control"
                   value="<?= h($_POST['email'] ?? '') ?>"
                   placeholder="your@email.com" autofocus required>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" id="passInput" class="form-control"
                   placeholder="••••••••" required>
            <button class="btn btn-outline-secondary" type="button" id="togglePass" tabindex="-1">
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>
        <div class="d-grid">
          <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
          </button>
        </div>
      </form>
    </div>
  </div>

  <p class="text-center text-white-50 small mt-3">
    <?= h(APP_NAME) ?> &copy; <?= date('Y') ?> &middot; Ovijat Group
  </p>
</div>

<script>
document.getElementById('togglePass').addEventListener('click', function() {
  const inp = document.getElementById('passInput');
  const ico = document.getElementById('eyeIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'bi bi-eye-slash';
  } else {
    inp.type = 'password';
    ico.className = 'bi bi-eye';
  }
});
</script>
</body>
</html>
