<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_role('executive');
require_csrf();

$campaign_id = (int)($_POST['campaign_id'] ?? 0);
$contact_id  = (int)($_POST['contact_id']  ?? 0);
$assigned_to = (int)($_POST['assigned_to'] ?? 0);

if ($campaign_id && $contact_id) {
    $exists = db_val(
        "SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id=? AND contact_id=?",
        [$campaign_id, $contact_id]
    );
    if (!$exists) {
        db_exec(
            "INSERT INTO campaign_contacts (campaign_id, contact_id, assigned_to, status)
             VALUES (?, ?, ?, 'pending')",
            [$campaign_id, $contact_id, $assigned_to ?: null]
        );
        audit_log('add_campaign_contact', 'campaigns', $campaign_id);
        flash_success('Contact added to campaign.');
    } else {
        flash_error('Contact is already in this campaign.');
    }
}

redirect(BASE_URL . '/modules/campaigns/form.php?id=' . $campaign_id . '&view=1');
