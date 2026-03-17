<?php
/**
 * Quick POST handler for adding call outcomes and task types from Settings.
 * Returns to settings page after action.
 */
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_role('super_admin');
require_csrf();

$action = clean($_POST['action'] ?? '');

if ($action === 'add_outcome') {
    $name     = clean($_POST['name'] ?? '');
    $color    = clean($_POST['color'] ?? '#6c757d');
    $req_cb   = (int)($_POST['requires_callback'] ?? 0);
    if ($name) {
        db_exec(
            "INSERT INTO call_outcomes (name, color, requires_callback, is_active, sort_order) VALUES (?,?,?,1,99)",
            [$name, $color, $req_cb]
        );
        audit_log('add_outcome', 'settings', 0, "Added outcome: $name");
        flash_success("Outcome '$name' added.");
    }
    redirect(BASE_URL . '/modules/settings/index.php?tab=outcomes');
}

if ($action === 'add_task_type') {
    $name = clean($_POST['name'] ?? '');
    if ($name) {
        db_exec("INSERT INTO task_types (name, is_active) VALUES (?,1)", [$name]);
        audit_log('add_task_type', 'settings', 0, "Added task type: $name");
        flash_success("Task type '$name' added.");
    }
    redirect(BASE_URL . '/modules/settings/index.php?tab=tasktypes');
}

redirect(BASE_URL . '/modules/settings/index.php');
