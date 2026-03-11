<?php
declare(strict_types=1);
require_once dirname(__FILE__, 3) . '/config/config.php';
session_start_secure();
if (is_logged_in()) {
    audit_log('LOGOUT', 'users', current_user_id());
}
session_unset();
session_destroy();
setcookie(session_name(), '', time() - 3600, '/', '', false, true);
header('Location: ' . APP_URL . '/index.php?page=login');
exit;
