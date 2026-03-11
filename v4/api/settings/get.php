<?php
// api/settings/get.php — Return shop settings to JS
declare(strict_types=1);
require_once dirname(__FILE__, 3) . '/config/config.php';
session_start_secure();
require_login();
header('Cache-Control: private, max-age=300');
json_response(['settings' => get_settings()]);
