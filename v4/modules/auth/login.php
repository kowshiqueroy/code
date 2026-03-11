<?php
// modules/auth/login.php — Login Page & Handler
declare(strict_types=1);
if (!defined('APP_ROOT')) {
    require_once dirname(__FILE__, 3) . '/config/config.php';
    session_start_secure();
    if (is_logged_in()) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

$error   = '';
$timeout = !empty($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_verify($token)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email  = sanitize_email($_POST['email']    ?? '');
        $pass   = $_POST['password'] ?? '';

        if (empty($email) || empty($pass)) {
            $error = 'Email and password are required.';
        } else {
            try {
                $stmt = db()->prepare('SELECT id, name, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && $user['is_active'] && password_verify($pass, $user['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['last_activity'] = time();

                    // Update last login
                    db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
                    audit_log('LOGIN', 'users', $user['id']);

                    header('Location: ' . APP_URL . '/index.php?page=pos');
                    exit;
                } else {
                    $error = 'Invalid credentials or account disabled.';
                    audit_log('FAILED_LOGIN', 'users', null, ['email' => $email]);
                }
            } catch (PDOException $e) {
                $error = 'Database error. Please try again.';
                error_log('Login error: ' . $e->getMessage());
            }
        }
    }
}
?>
<style>
.login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--c-bg);
    padding: 20px;
}
.login-box {
    width: min(420px, 100%);
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: var(--radius-lg);
    padding: 40px 36px;
    box-shadow: var(--shadow-lg);
}
.login-logo {
    text-align: center;
    margin-bottom: 28px;
}
.login-logo-icon {
    width: 56px; height: 56px;
    background: var(--c-accent);
    color: var(--c-accent-text);
    font-size: 24px;
    font-weight: 900;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 12px;
}
.login-title { font-size: 20px; font-weight: 800; text-align: center; margin-bottom: 6px; }
.login-sub   { font-size: 13px; color: var(--c-text-muted); text-align: center; }
.login-error {
    background: rgba(255,90,90,0.1);
    border: 1px solid var(--c-danger);
    color: var(--c-danger);
    padding: 10px 14px;
    border-radius: var(--radius);
    font-size: 13px;
    margin-bottom: 16px;
}
.login-timeout {
    background: rgba(255,179,71,0.1);
    border: 1px solid var(--c-warning);
    color: var(--c-warning);
    padding: 10px 14px;
    border-radius: var(--radius);
    font-size: 13px;
    margin-bottom: 16px;
}
</style>

<div class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <div class="login-logo-icon">P</div>
            <h1 class="login-title">POS System</h1>
            <p class="login-sub">Sign in to continue</p>
        </div>

        <?php if ($timeout): ?>
        <div class="login-timeout">⏱ Session expired due to inactivity.</div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="login-error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="on" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-input"
                       placeholder="admin@pos.local"
                       value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>"
                       required autocomplete="email" autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input"
                       placeholder="Enter your password"
                       required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:8px">
                Sign In →
            </button>
        </form>

        <p style="text-align:center;margin-top:20px;font-size:11px;color:var(--c-text-faint)">
            Secured with CSRF protection &amp; bcrypt hashing
        </p>
    </div>
</div>
