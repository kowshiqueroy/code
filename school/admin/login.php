<?php
// admin/login.php
session_name('school_admin_sess');
session_start();
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) { redirect("../admin"); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = getDB()->prepare("SELECT * FROM users WHERE username=? AND status=1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            // Update last login
            getDB()->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
            redirect("../admin");
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter username and password.';
    }
}
$siteName = getSetting('site_name_en', 'School Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login — <?= h($siteName) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#006a4e 0%,#004d39 50%,#1a1a2e 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:16px;padding:44px;max-width:400px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.35)}
.logo{text-align:center;margin-bottom:32px}
.logo .icon{font-size:3rem;margin-bottom:10px}
.logo h1{font-size:1.3rem;color:#006a4e;font-weight:700}
.logo p{font-size:.82rem;color:#888;margin-top:4px}
.form-group{margin-bottom:18px}
label{display:block;font-size:.85rem;font-weight:600;color:#444;margin-bottom:6px}
input[type=text],input[type=password]{width:100%;padding:12px 14px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:.95rem;outline:none;transition:border-color .2s}
input:focus{border-color:#006a4e}
.btn{display:block;width:100%;padding:13px;background:#006a4e;color:#fff;font-size:1rem;font-weight:700;border:none;border-radius:8px;cursor:pointer;transition:background .2s;font-family:inherit;margin-top:8px}
.btn:hover{background:#004d39}
.error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:16px}
.back-link{display:block;text-align:center;margin-top:18px;font-size:.82rem;color:#888}
.back-link a{color:#006a4e}
.divider{border:none;border-top:1px solid #f0f0f0;margin:16px 0}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="icon">🏫</div>
    <h1><?= h($siteName) ?></h1>
    <p>Admin Panel Login</p>
  </div>

  <?php if ($error): ?><div class="error">⚠️ <?= h($error) ?></div><?php endif; ?>

  <form method="POST" novalidate>
    <div class="form-group">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" value="<?= h($_POST['username'] ?? '') ?>" autocomplete="username" required autofocus>
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn">Login →</button>
  </form>

  <hr class="divider">
  <a href="<?= BASE_URL ?>/?page=index" class="back-link">← Back to website</a>
</div>
</body>
</html>
