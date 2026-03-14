<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login — BanglaEdu CMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#004d2e 0%,#006B3F 50%,#008a52 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.login-wrap{width:100%;max-width:420px}
.login-logo{text-align:center;margin-bottom:28px}
.login-logo .icon{width:72px;height:72px;background:rgba(255,255,255,.15);border-radius:50%;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:2rem;backdrop-filter:blur(4px);border:2px solid rgba(255,255,255,.3)}
.login-logo h1{color:#fff;font-size:1.5rem;font-weight:800}
.login-logo p{color:rgba(255,255,255,.7);font-size:.88rem;margin-top:4px}
.login-card{background:#fff;border-radius:20px;padding:40px;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.form-group{margin-bottom:18px}
label{display:block;font-weight:600;font-size:.85rem;color:#444;margin-bottom:6px}
input{width:100%;padding:12px 16px;border:2px solid #e5e7eb;border-radius:10px;font-size:.95rem;outline:none;transition:.2s;background:#fafafa}
input:focus{border-color:#006B3F;background:#fff}
.btn-login{width:100%;padding:14px;background:linear-gradient(135deg,#006B3F,#009954);color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;transition:.2s;margin-top:8px}
.btn-login:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(0,107,63,.4)}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:18px;font-size:.88rem}
.alert-error{background:#fff0f0;border:1px solid #fecaca;color:#c00}
.alert-info{background:#f0fff4;border:1px solid #a7f3d0;color:#006B3F}
.divider{text-align:center;color:#9ca3af;font-size:.82rem;margin:16px 0}
.back-link{display:block;text-align:center;margin-top:16px;color:rgba(255,255,255,.7);font-size:.85rem}
.back-link:hover{color:#fff}
.input-wrap{position:relative}
.toggle-pass{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;color:#9ca3af}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-logo">
    <div class="icon">🏫</div>
    <h1>BanglaEdu CMS</h1>
    <p>Administrator Login</p>
  </div>

  <div class="login-card">
    <?php if ($login_msg === 'logged_out'): ?>
    <div class="alert alert-info">✅ You have been logged out successfully.</div>
    <?php endif; ?>
    <?php if ($login_error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($login_error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="on">
      <div class="form-group">
        <label for="username">Username or Email</label>
        <input type="text" id="username" name="username" placeholder="admin" autocomplete="username" required
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrap">
          <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
          <button type="button" class="toggle-pass" onclick="togglePass()" aria-label="Toggle password">👁️</button>
        </div>
      </div>
      <button type="submit" class="btn-login">🔐 Login to Admin Panel</button>
    </form>

    <div class="divider">— BanglaEdu CMS v1.0 —</div>
    <div style="text-align:center;font-size:.78rem;color:#9ca3af">
      🇧🇩 Educational Website System for Bangladeshi Schools & Colleges
    </div>
  </div>
  <a href="/" class="back-link">← Back to Public Website</a>
</div>
<script>
function togglePass() {
  const p = document.getElementById('password');
  p.type = p.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
