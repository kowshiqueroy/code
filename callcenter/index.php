<?php
require_once 'config.php';
require_once 'includes/auth.php';
if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
