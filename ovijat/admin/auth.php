<?php
/**
 * OVIJAT GROUP — admin/auth.php v2.0
 * Uses admin_users table. Falls back to admins table if admin_users empty.
 */
require_once dirname(__DIR__).'/includes/config.php';
session_start();

function adminLogin(string $username, string $password): bool {
    // Try admin_users first, fall back to legacy admins
    $tables = ['admin_users','admins'];
    foreach ($tables as $tbl) {
        try {
            $stmt = db()->prepare("SELECT id,username,password FROM $tbl WHERE username=? AND active!=0 LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_user'] = $admin['username'];
                $_SESSION['admin_ip']   = $_SERVER['REMOTE_ADDR'];
                try { logAction('Login','Success'); } catch(Exception $e){}
                return true;
            }
        } catch(Exception $e){ continue; }
    }
    return false;
}

function adminLogout(): void {
    try { logAction('Logout'); } catch(Exception $e){}
    session_unset(); session_destroy();
    redirect(SITE_URL.'/admin/');
}

function requireAdmin(): void {
    if (empty($_SESSION['admin_id'])) redirect(SITE_URL.'/admin/');
    if (!empty($_SESSION['admin_ip']) && $_SESSION['admin_ip'] !== $_SERVER['REMOTE_ADDR']) adminLogout();
}

if (($_GET['action']??'') === 'logout') adminLogout();
