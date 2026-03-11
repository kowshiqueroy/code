<?php
/**
 * setup.php - One-Time Database Installer
 * DELETE THIS FILE after setup is complete!
 */
declare(strict_types=1);
define('BASE_PATH', __DIR__);
require_once __DIR__ . '/config/config.php';

$setupKey = getenv('SETUP_KEY');
if ($setupKey && ($_GET['key'] ?? '') !== $setupKey) {
    http_response_code(403); die('<h1>403 Forbidden</h1>');
}

function setup_step(string $msg, bool $ok = true): void {
    $icon = $ok ? 'OK' : 'FAIL';
    $color = $ok ? '#2ecc71' : '#e74c3c';
    echo "<p style='color:{$color};margin:4px 0'>[{$icon}] {$msg}</p>";
    flush();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>POS Setup</title>
<style>
body{font-family:system-ui,sans-serif;max-width:720px;margin:40px auto;padding:20px;
     background:#0f1117;color:#e0e0e0}
h1{color:#7c6af7}h2{color:#aaa;border-bottom:1px solid #333;padding-bottom:6px}
.box{background:#1a1d27;border-radius:10px;padding:20px;margin:16px 0}
.done{background:#0d2b1e;border:1px solid #2ecc71;border-radius:8px;padding:16px}
.fail{background:#2b0d0d;border:1px solid #e74c3c;border-radius:8px;padding:16px}
a{color:#7c6af7}
</style>
</head>
<body>
<h1>POS System Setup</h1>
<?php
$errors = [];

/* ── Step 1: Directories ── */
echo '<div class="box"><h2>Step 1: Storage</h2>';
foreach ([LOG_PATH, CACHE_PATH] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        setup_step("Cannot create: {$dir}", false);
        $errors[] = "mkdir failed: {$dir}";
    } else {
        setup_step("Ready: {$dir}");
        if (!file_exists($dir.'/.htaccess'))
            file_put_contents($dir.'/.htaccess', "Order deny,allow\nDeny from all\n");
    }
}
echo '</div>';

/* ── Step 2: Database ── */
echo '<div class="box"><h2>Step 2: Database</h2>';
try {
    // First connect without dbname to CREATE it
    $dsnNoDB = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT);
    $pdoTmp  = new PDO($dsnNoDB, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdoTmp->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdoTmp  = null;
    setup_step('Connected to MySQL');

    // Reconnect with dbname in DSN so all statements run inside the correct DB
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    setup_step('Database "'.DB_NAME.'" ready');

    $sql = file_get_contents(__DIR__.'/database/schema.sql');
    if (!$sql) throw new RuntimeException('schema.sql not found');

    // Properly parse SQL: respect string literals, comments, multi-line statements
    $statements = [];
    $current    = '';
    $delimiter  = ';';
    $inSingle   = false; // inside '...'
    $inDouble   = false; // inside "..."
    $inLineComment  = false;
    $inBlockComment = false;
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $ch   = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        // -- line comment
        if (!$inSingle && !$inDouble && !$inBlockComment && $ch === '-' && $next === '-') {
            $inLineComment = true;
        }
        if ($inLineComment) {
            if ($ch === "\n") $inLineComment = false;
            else continue; // skip comment chars
        }

        // /* block comment */
        if (!$inSingle && !$inDouble && !$inLineComment && $ch === '/' && $next === '*') {
            $inBlockComment = true; $i++; continue;
        }
        if ($inBlockComment) {
            if ($ch === '*' && $next === '/') { $inBlockComment = false; $i++; }
            continue;
        }

        // String delimiters
        if ($ch === "'" && !$inDouble && !$inLineComment && !$inBlockComment) {
            // handle escaped quote ''
            if ($inSingle && $next === "'") { $current .= $ch; $i++; continue; }
            $inSingle = !$inSingle;
        }
        if ($ch === '"' && !$inSingle && !$inLineComment && !$inBlockComment) {
            $inDouble = !$inDouble;
        }

        // Statement delimiter outside strings
        if (!$inSingle && !$inDouble && $ch === $delimiter) {
            $stmt = trim($current);
            if ($stmt !== '') $statements[] = $stmt;
            $current = '';
            continue;
        }

        $current .= $ch;
    }
    // Catch final statement without trailing semicolon
    if (trim($current) !== '') $statements[] = trim($current);

    $count = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '' || preg_match('/^--/', $stmt)) continue;
        try { $pdo->exec($stmt); $count++; }
        catch (PDOException $e) {
            $msg = $e->getMessage();
            // Silently skip "already exists" and duplicate inserts
            if (str_contains($msg,'already exists') || str_contains($msg,'Duplicate entry')) continue;
            // Surface real errors
            setup_step('SQL Error: '.htmlspecialchars($msg).' | Statement: '.htmlspecialchars(substr($stmt,0,120)), false);
            $errors[] = $msg;
        }
    }
    setup_step("Schema imported ({$count} statements executed)");

    foreach (['settings','users','products','sales','audit_logs','offline_sync_queue','finance_ledger'] as $t) {
        $ok = (bool)$pdo->query("SHOW TABLES LIKE '{$t}'")->rowCount();
        setup_step("Table `{$t}`", $ok);
        if (!$ok) $errors[] = "Missing table: {$t}";
    }
} catch (Throwable $e) {
    setup_step('Error: '.$e->getMessage(), false);
    $errors[] = $e->getMessage();
}
echo '</div>';

/* ── Step 3: Admin ── */
echo '<div class="box"><h2>Step 3: Admin Account</h2>';
if (empty($errors)) {
    try {
        $pdo->exec("USE `".DB_NAME."`");
        if (!$pdo->query("SELECT 1 FROM users WHERE role='admin' LIMIT 1")->fetchColumn()) {
            $hash = password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost'=>12]);
            $pdo->prepare("INSERT IGNORE INTO users (full_name,username,email,password_hash,role)
                           VALUES ('System Admin','admin','admin@store.com',?,'admin')")->execute([$hash]);
            setup_step('Admin created | user: <b>admin</b> | pass: <b>Admin@1234</b>');
        } else {
            setup_step('Admin exists');
        }
    } catch (Throwable $e) { setup_step($e->getMessage(), false); }
} else { setup_step('Skipped', false); }
echo '</div>';

/* ── Step 4: PHP Extensions ── */
echo '<div class="box"><h2>Step 4: Requirements</h2>';
foreach ([
    'PHP >= 8.1'    => version_compare(PHP_VERSION,'8.1.0','>='),
    'pdo'           => extension_loaded('pdo'),
    'pdo_mysql'     => extension_loaded('pdo_mysql'),
    'json'          => extension_loaded('json'),
    'mbstring'      => extension_loaded('mbstring'),
    'openssl'       => extension_loaded('openssl'),
] as $label => $ok) {
    setup_step($label, $ok);
    if (!$ok) $errors[] = "{$label} missing";
}
echo '</div>';

/* ── Summary ── */
if (empty($errors)) {
    echo '<div class="done"><h2 style="color:#2ecc71;margin-top:0">Setup Complete!</h2>
          <p><strong>Delete setup.php immediately.</strong></p>
          <p><a href="'.htmlspecialchars(BASE_URL).'/login.php">Go to Login</a></p></div>';
} else {
    echo '<div class="fail"><h2 style="color:#e74c3c;margin-top:0">Setup Failed</h2><ul>';
    foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>';
    echo '</ul></div>';
}
?>
<p style="color:#555;font-size:12px;margin-top:24px">v<?= APP_VERSION ?> | PHP <?= PHP_VERSION ?></p>
</body></html>
