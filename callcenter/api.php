<?php
// ============================================================
// api.php — AJAX / JSON endpoints
// All responses are JSON. Auth required.
// ============================================================

require_once __DIR__ . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Require login for all API calls
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated']);
    exit;
}

check_session_timeout();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ── Ping (online check) ─────────────────────────────────
    case 'ping':
        echo json_encode(['ok' => true, 'ts' => time()]);
        break;

    // ── Search contacts (typeahead) ─────────────────────────
    case 'search_contacts':
        $q    = '%' . clean($_GET['q'] ?? '') . '%';
        $limit= min(10, (int)($_GET['limit'] ?? 8));
        $rows = db_rows(
            "SELECT c.id, c.name, c.phone, c.alt_phone, c.company, c.contact_type, c.status,
                    (SELECT MAX(ca.started_at) FROM calls ca WHERE ca.contact_id = c.id) AS last_call
             FROM contacts c
             WHERE c.name LIKE ? OR c.phone LIKE ? OR c.alt_phone LIKE ?
                   OR c.email LIKE ? OR c.company LIKE ?
             ORDER BY c.name ASC LIMIT ?",
            [$q, $q, $q, $q, $q, $limit]
        );
        foreach ($rows as &$r) {
            $r['last_interaction'] = $r['last_call'] ? time_ago($r['last_call']) : null;
        }
        echo json_encode($rows);
        break;

    // ── Global search ───────────────────────────────────────
    case 'global_search':
        $q     = '%' . clean($_GET['q'] ?? '') . '%';
        $contacts = db_rows(
            "SELECT id, name, phone, company, contact_type FROM contacts
             WHERE name LIKE ? OR phone LIKE ? OR alt_phone LIKE ? OR email LIKE ? OR company LIKE ?
             ORDER BY name LIMIT 10",
            [$q, $q, $q, $q, $q]
        );
        $total = (int) db_val(
            "SELECT COUNT(*) FROM contacts
             WHERE name LIKE ? OR phone LIKE ? OR alt_phone LIKE ? OR email LIKE ? OR company LIKE ?",
            [$q, $q, $q, $q, $q]
        );
        echo json_encode(['contacts' => $contacts, 'total' => $total]);
        break;

    // ── Contact interaction history ─────────────────────────
    case 'contact_history':
        $id    = (int)($_GET['id'] ?? 0);
        $limit = min(20, (int)($_GET['limit'] ?? 10));
        if (!$id) { echo json_encode([]); break; }

        $calls = db_rows(
            "SELECT 'call' AS type, ca.id, ca.started_at AS dt, ca.direction,
                    ca.duration_seconds, o.name AS outcome, o.color AS outcome_color,
                    u.name AS agent_name, ca.notes,
                    cs.key_points, cs.sentiment
             FROM calls ca
             LEFT JOIN call_outcomes o ON o.id = ca.outcome_id
             LEFT JOIN users u ON u.id = ca.agent_id
             LEFT JOIN call_summary cs ON cs.call_id = ca.id
             WHERE ca.contact_id = ?
             ORDER BY ca.started_at DESC LIMIT ?",
            [$id, $limit]
        );
        $sms = db_rows(
            "SELECT 'sms' AS type, s.id, s.sent_at AS dt, NULL AS direction,
                    NULL AS duration_seconds, s.status AS outcome, NULL AS outcome_color,
                    u.name AS agent_name, s.message AS notes, NULL AS key_points, NULL AS sentiment
             FROM sms_log s
             LEFT JOIN users u ON u.id = s.agent_id
             WHERE s.contact_id = ?
             ORDER BY s.sent_at DESC LIMIT 5",
            [$id]
        );
        $tasks = db_rows(
            "SELECT 'task' AS type, t.id, t.created_at AS dt, NULL AS direction,
                    NULL AS duration_seconds, t.status AS outcome, NULL AS outcome_color,
                    u.name AS agent_name, t.title AS notes, NULL AS key_points, NULL AS sentiment
             FROM tasks t
             LEFT JOIN users u ON u.id = t.assigned_by
             WHERE t.contact_id = ?
             ORDER BY t.created_at DESC LIMIT 5",
            [$id]
        );

        // Merge and sort
        $all = array_merge($calls, $sms, $tasks);
        usort($all, fn($a,$b) => strtotime($b['dt']) - strtotime($a['dt']));
        $all = array_slice($all, 0, $limit);

        // Format
        foreach ($all as &$r) {
            $r['dt_human'] = time_ago($r['dt']);
            $r['duration_human'] = $r['duration_seconds'] ? format_duration((int)$r['duration_seconds']) : null;
        }
        echo json_encode($all);
        break;

    // ── Contact smart hints ─────────────────────────────────
    case 'contact_hints':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { echo json_encode([]); break; }

        $last_call = db_val(
            "SELECT started_at FROM calls WHERE contact_id = ? ORDER BY started_at DESC LIMIT 1", [$id]
        );
        $days_since = $last_call ? (int)((time() - strtotime($last_call)) / 86400) : null;
        $open_threads = contact_open_threads($id);
        $overdue_cb   = contact_overdue_callbacks($id);

        // Active campaign
        $campaign = db_row(
            "SELECT c.id, c.name FROM campaign_contacts cc
             JOIN campaigns c ON c.id = cc.campaign_id
             WHERE cc.contact_id = ? AND cc.status = 'pending' AND c.status = 'active'
             LIMIT 1",
            [$id]
        );
        // Assigned tasks
        $pending_tasks = (int) db_val(
            "SELECT COUNT(*) FROM tasks WHERE contact_id = ? AND status IN ('pending','in_progress')", [$id]
        );

        echo json_encode([
            'days_since_call'    => $days_since,
            'open_threads'       => $open_threads,
            'overdue_callbacks'  => $overdue_cb,
            'active_campaign'    => $campaign ?: null,
            'pending_tasks'      => $pending_tasks,
        ]);
        break;

    // ── Check duplicate contact ─────────────────────────────
    case 'check_duplicate':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['found'=>false]); break; }
        $name  = clean($_POST['name']  ?? '');
        $phone = clean($_POST['phone'] ?? '');
        $excludeId = (int)($_POST['exclude_id'] ?? 0);

        $matches = [];
        // Phone match (exact or partial)
        if ($phone) {
            $phoneDigits = preg_replace('/\D/', '', $phone);
            $rows = db_rows(
                "SELECT id, name, phone, contact_type FROM contacts
                 WHERE (REPLACE(REPLACE(REPLACE(phone,'-',''),' ',''),'(','') LIKE ?)
                    OR (REPLACE(REPLACE(REPLACE(alt_phone,'-',''),' ',''),'(','') LIKE ?)
                 " . ($excludeId ? "AND id != $excludeId" : '') . " LIMIT 5",
                ['%' . $phoneDigits . '%', '%' . $phoneDigits . '%']
            );
            $matches = array_merge($matches, $rows);
        }
        // Name similarity (soundex or LIKE)
        if ($name && strlen($name) >= 3) {
            $nameParts = explode(' ', $name);
            $firstWord = $nameParts[0];
            $rows = db_rows(
                "SELECT id, name, phone, contact_type FROM contacts
                 WHERE (SOUNDEX(name) = SOUNDEX(?) OR name LIKE ?)
                 " . ($excludeId ? "AND id != $excludeId" : '') . " LIMIT 5",
                [$name, '%' . $firstWord . '%']
            );
            $matches = array_merge($matches, $rows);
        }

        // Deduplicate by id
        $seen = []; $unique = [];
        foreach ($matches as $m) {
            if (!isset($seen[$m['id']])) { $seen[$m['id']] = true; $unique[] = $m; }
        }

        echo json_encode(['found' => count($unique) > 0, 'matches' => $unique]);
        break;

    // ── Get script content ──────────────────────────────────
    case 'get_script':
        $id = (int)($_GET['id'] ?? 0);
        $s  = $id ? db_row("SELECT id, name, content FROM scripts WHERE id = ?", [$id]) : null;
        echo json_encode($s ?: null);
        break;

    // ── Search users ────────────────────────────────────────
    case 'search_users':
        $q   = '%' . clean($_GET['q'] ?? '') . '%';
        $rows = db_rows(
            "SELECT id, name, email, role FROM users WHERE (name LIKE ? OR email LIKE ?) AND status='active' LIMIT 8",
            [$q, $q]
        );
        echo json_encode($rows);
        break;

    // ── Search outcomes ─────────────────────────────────────
    case 'search_outcomes':
        $q   = '%' . clean($_GET['q'] ?? '') . '%';
        $rows = db_rows(
            "SELECT id, name, color, requires_callback FROM call_outcomes WHERE name LIKE ? AND is_active=1 ORDER BY sort_order LIMIT 10",
            [$q]
        );
        echo json_encode($rows);
        break;

    // ── Search campaigns ────────────────────────────────────
    case 'search_campaigns':
        $q   = '%' . clean($_GET['q'] ?? '') . '%';
        $rows = db_rows(
            "SELECT id, name, status, type FROM campaigns WHERE name LIKE ? AND status IN ('active','draft') ORDER BY name LIMIT 8",
            [$q]
        );
        echo json_encode($rows);
        break;

    // ── Send SMS ────────────────────────────────────────────
    case 'send_sms':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false]); break; }
        if (!verify_csrf()) { echo json_encode(['success'=>false,'error'=>'CSRF']); break; }

        $contact_id = (int)($_POST['contact_id'] ?? 0);
        $phone_to   = clean($_POST['phone_to'] ?? '');
        $message    = clean($_POST['message']  ?? '');
        if (!$phone_to || !$message) {
            echo json_encode(['success'=>false,'error'=>'Missing phone or message']); break;
        }

        $gateway_key = setting('sms_api_key');
        $sender_id   = setting('sms_sender_id', 'OvijatCC');
        $ref         = null;
        $status      = 'queued';

        // Attempt real send if gateway configured
        if ($gateway_key) {
            // Placeholder for actual SMS gateway integration
            // $ref = sms_send_via_gateway($phone_to, $message, $gateway_key, $sender_id);
            $status = 'sent';
        }

        $sms_id = db_exec(
            "INSERT INTO sms_log (contact_id, agent_id, phone_to, message, status, gateway_ref, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$contact_id ?: null, current_user_id(), $phone_to, $message, $status, $ref]
        );
        audit_log('send_sms', 'sms', $sms_id, "SMS to $phone_to");
        echo json_encode(['success' => true, 'ref' => $ref, 'id' => $sms_id]);
        break;

    // ── Check-in status ─────────────────────────────────────
    case 'check_in_status':
        $today = date('Y-m-d');
        $row   = db_row(
            "SELECT check_in, check_out, work_mode FROM attendance WHERE user_id=? AND date=?",
            [current_user_id(), $today]
        );
        echo json_encode([
            'checked_in'  => $row && $row['check_in'] && !$row['check_out'],
            'checked_out' => $row && $row['check_out'],
            'time'        => $row ? $row['check_in'] : null,
            'mode'        => $row ? $row['work_mode'] : null,
        ]);
        break;

    // ── Log screen time ─────────────────────────────────────
    case 'log_screen_time':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') break;
        $module  = clean($_POST['module'] ?? 'unknown');
        $start   = clean($_POST['session_start'] ?? '');
        $seconds = (int)($_POST['duration_seconds'] ?? 0);
        if ($seconds > 0 && $start) {
            db_exec(
                "INSERT INTO screen_activity (user_id, module, session_start, session_end, duration_seconds, date)
                 VALUES (?, ?, ?, NOW(), ?, ?)",
                [current_user_id(), $module, $start, $seconds, date('Y-m-d')]
            );
        }
        echo json_encode(['ok' => true]);
        break;

    // ── Sync item (offline → server) ────────────────────────
    case 'sync_item':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false]); break; }
        if (!verify_csrf()) { echo json_encode(['success'=>false,'error'=>'CSRF']); break; }

        $data_type = clean($_POST['data_type'] ?? '');
        $local_id  = clean($_POST['local_id']  ?? '');
        $data_json = $_POST['data_json'] ?? '{}';
        $action_t  = clean($_POST['sync_action'] ?? 'insert');

        try {
            $data = json_decode($data_json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'error'=>'Invalid JSON']); break;
        }

        $server_id = null;
        $conflict  = false;

        switch ($data_type) {
            case 'call':
                $contact_id = (int)($data['contact_id'] ?? 0);
                $server_id  = db_exec(
                    "INSERT INTO calls (contact_id, campaign_id, agent_id, direction, phone_dialed,
                      started_at, ended_at, duration_seconds, outcome_id, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $contact_id ?: null,
                        $data['campaign_id'] ?: null,
                        current_user_id(),
                        $data['direction'] ?? 'outbound',
                        $data['phone_dialed'] ?? '',
                        $data['started_at'] ?? date('Y-m-d H:i:s'),
                        $data['ended_at'] ?: null,
                        (int)($data['duration_seconds'] ?? 0),
                        $data['outcome_id'] ?: null,
                        $data['notes'] ?? '',
                    ]
                );
                // Save summary if present
                if ($server_id && !empty($data['key_points'])) {
                    db_exec("INSERT INTO call_summary (call_id, key_points, follow_up_required, follow_up_date, sentiment)
                             VALUES (?, ?, ?, ?, ?)",
                        [$server_id, $data['key_points'], (int)($data['follow_up_required']??0),
                         $data['follow_up_date']??null, $data['sentiment']??'neutral']
                    );
                }
                break;

            case 'sms':
                $server_id = db_exec(
                    "INSERT INTO sms_log (contact_id, agent_id, phone_to, message, status, sent_at)
                     VALUES (?, ?, ?, ?, 'queued', ?)",
                    [
                        $data['contact_id'] ?: null,
                        current_user_id(),
                        $data['phone_to'] ?? '',
                        $data['message'] ?? '',
                        $data['sent_at'] ?? date('Y-m-d H:i:s'),
                    ]
                );
                break;

            case 'task':
                $server_id = db_exec(
                    "INSERT INTO tasks (title, description, type_id, assigned_to, assigned_by,
                      priority, status, due_date, contact_id, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $data['title'] ?? 'Offline Task',
                        $data['description'] ?? '',
                        $data['type_id'] ?: null,
                        current_user_id(),
                        current_user_id(),
                        $data['priority'] ?? 'medium',
                        $data['status'] ?? 'pending',
                        $data['due_date'] ?? null,
                        $data['contact_id'] ?: null,
                        $data['notes'] ?? '',
                    ]
                );
                break;

            case 'attendance':
                $date = $data['date'] ?? date('Y-m-d');
                // Check for conflict
                $existing = db_row("SELECT id FROM attendance WHERE user_id=? AND date=?", [current_user_id(), $date]);
                if ($existing) {
                    $conflict  = true;
                    $server_id = $existing['id'];
                } else {
                    $server_id = db_exec(
                        "INSERT INTO attendance (user_id, date, check_in, check_out, work_mode)
                         VALUES (?, ?, ?, ?, ?)",
                        [current_user_id(), $date, $data['check_in']??null, $data['check_out']??null,
                         $data['work_mode']??'office']
                    );
                }
                break;
        }

        // Log the sync
        db_exec(
            "INSERT INTO offline_sync_log (user_id, data_type, local_id, data_json, action, status, synced_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [current_user_id(), $data_type, $local_id, $data_json, $action_t,
             $conflict ? 'conflict' : 'synced']
        );
        if ($server_id) audit_log('offline_sync', $data_type, (int)$server_id, "Synced from offline");

        echo json_encode(['success' => !$conflict, 'server_id' => $server_id, 'conflict' => $conflict]);
        break;

    // ── Pending sync count ──────────────────────────────────
    case 'pending_sync_count':
        // The actual count comes from IndexedDB (client-side)
        // This endpoint just returns server acknowledgment
        echo json_encode(['ok' => true]);
        break;

    // ── Sales group tree ────────────────────────────────────
    case 'sales_group_tree':
        $groups = db_rows(
            "SELECT g.id, g.name, g.parent_group_id, g.region, g.is_active,
                    l.name AS level_name, l.color AS level_color, l.rank_order,
                    (SELECT COUNT(*) FROM sales_group_members m WHERE m.group_id = g.id AND m.is_active=1) AS member_count
             FROM sales_groups g
             JOIN sales_levels l ON l.id = g.level_id
             WHERE g.is_active = 1
             ORDER BY l.rank_order, g.name"
        );
        // Build tree
        $tree = buildTree($groups);
        echo json_encode($tree);
        break;

    // ── Dashboard KPIs (AJAX refresh) ──────────────────────
    case 'dashboard_kpis':
        $uid  = current_user_id();
        $role = current_role();
        $isAll = in_array($role, ['super_admin','senior_executive']);
        $today = date('Y-m-d');

        $calls_today = (int) db_val(
            "SELECT COUNT(*) FROM calls WHERE DATE(started_at)=?" . ($isAll ? '' : " AND agent_id=$uid"),
            [$today]
        );
        $calls_week = (int) db_val(
            "SELECT COUNT(*) FROM calls WHERE started_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)" . ($isAll ? '' : " AND agent_id=$uid")
        );
        $pending_cb = (int) db_val(
            "SELECT COUNT(*) FROM callbacks WHERE status='pending' AND scheduled_at <= NOW()" . ($isAll ? '' : " AND assigned_to=$uid")
        );
        $active_campaigns = (int) db_val("SELECT COUNT(*) FROM campaigns WHERE status='active'");

        echo json_encode(compact('calls_today','calls_week','pending_cb','active_campaigns'));
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . h($action)]);
        break;
}

// ── Tree builder helper ──────────────────────────────────────
function buildTree(array $items, int $parentId = 0): array {
    $branch = [];
    foreach ($items as $item) {
        $pid = (int)($item['parent_group_id'] ?? 0);
        if ($pid === $parentId) {
            $item['children'] = buildTree($items, (int)$item['id']);
            $branch[] = $item;
        }
    }
    return $branch;
}
