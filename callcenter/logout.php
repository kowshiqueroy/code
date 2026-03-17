<?php
require_once __DIR__ . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';

if (is_logged_in()) {
    audit_log('logout', 'auth', 0, 'User logged out');
}
logout();
redirect(BASE_URL . '/login.php');
