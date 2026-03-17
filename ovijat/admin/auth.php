<?php
/**
 * OVIJAT GROUP — admin/auth.php v2.1
 * Consolidated to use only 'admin_users' table.
 */
require_once dirname(__DIR__).'/includes/config.php';
session_start();

function adminLogin(string $username, string $password): bool {
    try {
        $stmt = db()->prepare("SELECT id, username, password, role FROM admin_users WHERE username=? AND active=1 LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_user'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_ip']   = $_SERVER['REMOTE_ADDR'];
            
            logAction('Login','Success');
            return true;
        }
    } catch(Exception $e){
        error_log("Login Error: " . $e->getMessage());
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

function requireRole(string $role): void {
    requireAdmin();
    if (($_SESSION['admin_role'] ?? '') !== $role && ($_SESSION['admin_role'] ?? '') !== 'superadmin') {
        flash('Access Denied: You do not have permission for this action.', 'error');
        redirect(SITE_URL . '/admin/dashboard.php');
    }
}

if (($_GET['action']??'') === 'logout') adminLogout();
