<?php
/**
 * OVIJAT GROUP — admin/index.php
 * Admin login portal.
 */
require_once __DIR__ . '/auth.php';

// Already logged in → redirect to dashboard
if (!empty($_SESSION['admin_id'])) {
    redirect(SITE_URL . '/admin/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeText($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        if (adminLogin($username, $password)) {
            redirect(SITE_URL . '/admin/dashboard.php');
        } else {
            $error = 'Invalid username or password. Please try again.';
            // Slight delay to slow brute force
            sleep(1);
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — Ovijat Group</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-login-body">
<div class="login-container">
  <div class="login-card">
    <div class="login-brand">
      <div class="login-logo-text">Ovijat Group</div>
      <p>Admin Control Panel</p>
    </div>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="" class="login-form">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" class="form-input"
               placeholder="Enter username" required autocomplete="username"
               value="<?= e($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="form-input"
               placeholder="Enter password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary btn-full">Sign In →</button>
    </form>
    <div class="login-footer">
      <a href="<?= SITE_URL ?>/" target="_blank">← View Public Site</a>
    </div>
  </div>
</div>
</body>
</html>
