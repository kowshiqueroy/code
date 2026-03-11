<?php
/**
 * ============================================================
 * POS System — Setup & First-Run Installer
 * ============================================================
 * FILE: setup.php  (DELETE OR PROTECT AFTER RUNNING!)
 *
 * Access: http://yoursite.com/setup.php
 * Steps:
 *   1. Checks PHP + extension requirements
 *   2. Creates database tables (runs database.sql)
 *   3. Creates default admin account
 *   4. Seeds basic categories
 *   5. Writes lock file to prevent re-running
 */

declare(strict_types=1);

// ── Guard: block if already set up ──────────────────────────
if (file_exists(__DIR__ . '/config/.setup_done')) {
    http_response_code(403);
    die('<h2>Setup already completed. Delete /config/.setup_done to re-run.</h2>');
}

// ── Load config (without DB auto-connect on fail) ────────────
define('APP_ENV', 'development');
define('ROOT_PATH',   __DIR__);
define('CONFIG_PATH', __DIR__ . '/config');
define('ASSET_PATH',  __DIR__ . '/assets');
define('UPLOAD_PATH', __DIR__ . '/uploads');
define('LOG_PATH',    __DIR__ . '/logs');

// Read env or defaults
define('DB_HOST',    $_POST['db_host']    ?? getenv('DB_HOST')    ?: '127.0.0.1');
define('DB_PORT',    $_POST['db_port']    ?? getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    $_POST['db_name']    ?? getenv('DB_NAME')    ?: 'pos_db');
define('DB_USER',    $_POST['db_user']    ?? getenv('DB_USER')    ?: 'root');
define('DB_PASS',    $_POST['db_pass']    ?? getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');
define('BCRYPT_COST', 12);
define('SESSION_NAME',      'pos_sess');
define('SESSION_LIFETIME',  300);
define('SESSION_SECURE',    false);
define('SESSION_HTTP_ONLY', true);
define('SESSION_SAME_SITE', 'Strict');

require_once CONFIG_PATH . '/Database.php';
require_once CONFIG_PATH . '/Session.php';
require_once CONFIG_PATH . '/Security.php';
require_once CONFIG_PATH . '/Helpers.php';

// ── Requirement Check ─────────────────────────────────────────
$requirements = [
    'PHP 8.1+'      => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO'           => extension_loaded('pdo'),
    'PDO MySQL'     => extension_loaded('pdo_mysql'),
    'mbstring'      => extension_loaded('mbstring'),
    'json'          => extension_loaded('json'),
    'openssl'       => extension_loaded('openssl'),
    'Writable /uploads' => is_writable(UPLOAD_PATH) || mkdir(UPLOAD_PATH, 0755, true),
    'Writable /logs'    => is_writable(LOG_PATH)    || mkdir(LOG_PATH,    0755, true),
];

$errors   = [];
$warnings = [];
$step     = 'check';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Step 2: CSRF not yet active, but validate nonce ───────
    $formNonce = $_POST['_nonce'] ?? '';
    if (!hash_equals($_SESSION['setup_nonce'] ?? '', $formNonce)) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    }

    if (empty($errors)) {
        $adminName  = Security::sanitizeString($_POST['admin_name']  ?? 'System Admin');
        $adminUser  = Security::sanitizeString($_POST['admin_user']  ?? 'admin', 60);
        $adminPass  = $_POST['admin_pass']  ?? '';
        $adminPass2 = $_POST['admin_pass2'] ?? '';
        $shopName   = Security::sanitizeString($_POST['shop_name']   ?? 'My Shop');
        $shopType   = $_POST['shop_type']   ?? 'general';

        if (strlen($adminPass) < 8) {
            $errors[] = 'Admin password must be at least 8 characters.';
        }
        if ($adminPass !== $adminPass2) {
            $errors[] = 'Passwords do not match.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo = Database::getInstance();

            // ── Run SQL schema ────────────────────────────────
            $sql = file_get_contents(__DIR__ . '/database.sql');
            // Split on semicolons, ignore empty
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => strlen($s) > 3
            );

            foreach ($statements as $stmt) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    // Ignore "already exists" errors on re-run
                    if ($e->getCode() !== '42S01' && $e->getCode() !== '42000') {
                        throw $e;
                    }
                }
            }

            // ── Create admin user ──────────────────────────────
            $hash = Security::hashPassword($adminPass);
            $pdo->prepare(
                "UPDATE users SET name=?, username=?, password_hash=? WHERE id=1"
            )->execute([$adminName, $adminUser, $hash]);

            // ── Update shop settings ───────────────────────────
            $pdo->prepare(
                "UPDATE settings SET shop_name=?, shop_type=? WHERE id=1"
            )->execute([$shopName, $shopType]);

            // ── Seed default categories by shop type ──────────
            $cats = match ($shopType) {
                'bookshop'  => [['Fiction','📚','#6C63FF'],['Non-Fiction','📖','#FF6584'],['Children','🧒','#43D9AD'],['Academic','🎓','#F5A623'],['Comics','🎨','#E91E63']],
                'foodshop'  => [['Beverages','☕','#FF9800'],['Snacks','🍿','#F44336'],['Meals','🍱','#4CAF50'],['Desserts','🍰','#E91E63'],['Bakery','🥐','#FF5722']],
                'clothshop' => [['Men','👔','#2196F3'],['Women','👗','#E91E63'],['Kids','🧒','#FF9800'],['Accessories','👜','#9C27B0'],['Footwear','👟','#795548']],
                'showroom'  => [['Electronics','📺','#607D8B'],['Furniture','🛋️','#795548'],['Appliances','🏠','#FF5722'],['Decor','🎨','#9C27B0'],['Outdoor','🌿','#4CAF50']],
                default     => [['General','📦','#607D8B'],['Services','⚙️','#FF9800'],['Other','🏷️','#9E9E9E']],
            };

            $catStmt = $pdo->prepare(
                "INSERT IGNORE INTO categories (name, icon, color) VALUES (?,?,?)"
            );
            foreach ($cats as [$name, $icon, $color]) {
                $catStmt->execute([$name, $icon, $color]);
            }

            // ── Write lock file ────────────────────────────────
            file_put_contents(CONFIG_PATH . '/.setup_done', date('c') . "\n");

            $step = 'done';

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        } catch (Throwable $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
} else {
    // GET — generate nonce
    session_name(SESSION_NAME);
    session_start();
    $_SESSION['setup_nonce'] = bin2hex(random_bytes(16));
}

$nonce = $_SESSION['setup_nonce'] ?? '';

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>POS Setup Wizard</title>
<style>
  :root{--ink:#0d1117;--paper:#f6f8fa;--accent:#2563eb;--ok:#16a34a;--err:#dc2626;--border:#d0d7de}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,sans-serif;background:var(--paper);color:var(--ink);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem}
  .card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:2.5rem;width:100%;max-width:520px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
  h1{font-size:1.5rem;margin-bottom:0.25rem}
  .sub{color:#57606a;font-size:.875rem;margin-bottom:1.5rem}
  .req{list-style:none;margin-bottom:1.5rem}
  .req li{padding:.35rem 0;display:flex;align-items:center;gap:.5rem;font-size:.875rem}
  .ok{color:var(--ok)}.fail{color:var(--err)}
  label{display:block;font-size:.875rem;font-weight:600;margin:.8rem 0 .3rem}
  input,select{width:100%;padding:.55rem .75rem;border:1px solid var(--border);border-radius:6px;font-size:.9rem}
  input:focus,select:focus{outline:2px solid var(--accent);border-color:var(--accent)}
  .btn{display:block;width:100%;padding:.75rem;background:var(--accent);color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer;margin-top:1.5rem}
  .btn:hover{opacity:.9}
  .errors{background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:1rem;margin-bottom:1rem}
  .errors li{color:var(--err);font-size:.875rem;margin:.2rem 0}
  .success{text-align:center;padding:2rem 0}
  .success h2{color:var(--ok);font-size:1.75rem;margin-bottom:.5rem}
  .success a{display:inline-block;margin-top:1rem;padding:.6rem 1.5rem;background:var(--accent);color:#fff;border-radius:8px;text-decoration:none;font-weight:600}
  h3{font-size:1rem;margin:1.25rem 0 .5rem;color:#57606a;border-top:1px solid var(--border);padding-top:1rem}
</style>
</head>
<body>
<div class="card">

<?php if ($step === 'done'): ?>
  <div class="success">
    <div style="font-size:3rem">🎉</div>
    <h2>Setup Complete!</h2>
    <p>Your POS system is ready. The setup file is now locked.</p>
    <a href="/login.php">Go to Login →</a>
  </div>

<?php else: ?>
  <h1>⚡ POS Setup Wizard</h1>
  <p class="sub">One-time installation. This file locks itself after completion.</p>

  <h3>System Requirements</h3>
  <ul class="req">
    <?php foreach ($requirements as $label => $ok): ?>
      <li>
        <span class="<?= $ok ? 'ok' : 'fail' ?>"><?= $ok ? '✔' : '✘' ?></span>
        <?= htmlspecialchars($label) ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($errors): ?>
    <ul class="errors">
      <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="_nonce" value="<?= htmlspecialchars($nonce) ?>">

    <h3>Database Connection</h3>
    <label>Host <input name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? '127.0.0.1') ?>"></label>
    <label>Port <input name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>"></label>
    <label>Database Name <input name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'pos_db') ?>"></label>
    <label>Username <input name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>"></label>
    <label>Password <input type="password" name="db_pass"></label>

    <h3>Shop Configuration</h3>
    <label>Shop Name <input name="shop_name" value="<?= htmlspecialchars($_POST['shop_name'] ?? 'My Shop') ?>"></label>
    <label>Shop Type
      <select name="shop_type">
        <?php foreach (['general','bookshop','foodshop','clothshop','showroom'] as $t): ?>
          <option value="<?= $t ?>" <?= (($_POST['shop_type'] ?? 'general') === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <h3>Admin Account</h3>
    <label>Full Name <input name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? 'System Admin') ?>"></label>
    <label>Username <input name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? 'admin') ?>"></label>
    <label>Password <input type="password" name="admin_pass" placeholder="Min 8 characters"></label>
    <label>Confirm Password <input type="password" name="admin_pass2"></label>

    <button class="btn" type="submit">🚀 Install Now</button>
  </form>

<?php endif; ?>
</div>
</body>
</html>
