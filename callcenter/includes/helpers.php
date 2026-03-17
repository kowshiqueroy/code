<?php
// ============================================================
// includes/helpers.php — Utility functions
// ============================================================

// ── Flash messages ────────────────────────────────────────────
function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function flash_success(string $msg): void { flash('success', $msg); }
function flash_error(string $msg): void   { flash('danger',  $msg); }
function flash_warn(string $msg): void    { flash('warning', $msg); }
function flash_info(string $msg): void    { flash('info',    $msg); }

function get_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ── Audit log ─────────────────────────────────────────────────
function audit_log(string $action, string $module = '', int $record_id = 0, string $description = ''): void {
    try {
        db_exec(
            "INSERT INTO audit_logs (user_id, action, module, record_id, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)",
            [current_user_id(), $action, $module, $record_id ?: null, $description,
             $_SERVER['REMOTE_ADDR'] ?? '']
        );
    } catch (Throwable $e) {
        // Never let logging break the app
    }
}

// ── Formatting ───────────────────────────────────────────────
function format_phone(string $phone): string {
    // Normalize: remove non-digits then format BD style
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        return '0' . substr($digits, 1, 2) . '-' . substr($digits, 3, 4) . '-' . substr($digits, 7);
    }
    return $phone;
}

function format_duration(int $seconds): string {
    if ($seconds < 60)   return $seconds . 's';
    if ($seconds < 3600) return floor($seconds/60) . 'm ' . ($seconds%60) . 's';
    $h = floor($seconds/3600);
    $m = floor(($seconds%3600)/60);
    return $h . 'h ' . $m . 'm';
}

function format_date(string|null $dt, string $fmt = 'd M Y'): string {
    if (!$dt) return '—';
    return date($fmt, strtotime($dt));
}

function format_datetime(string|null $dt): string {
    return format_date($dt, 'd M Y, h:i A');
}

function time_ago(string|null $dt): string {
    if (!$dt) return '—';
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60) . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return format_date($dt);
}

function h(string|null $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
}

function truncate(string $s, int $len = 80): string {
    return mb_strlen($s) > $len ? mb_substr($s, 0, $len) . '…' : $s;
}

// ── Role badge ───────────────────────────────────────────────
function role_badge(string $role): string {
    $map = [
        'super_admin'      => 'danger',
        'senior_executive' => 'warning text-dark',
        'executive'        => 'primary',
        'viewer'           => 'secondary',
    ];
    $label = ucwords(str_replace('_', ' ', $role));
    $cls   = $map[$role] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . $label . '</span>';
}

// ── Contact type badge ────────────────────────────────────────
function type_badge(string $type): string {
    $map = [
        'internal_staff' => ['dark',    'Staff'],
        'sr'             => ['primary', 'SR'],
        'asm'            => ['info text-dark', 'ASM'],
        'dsm'            => ['warning text-dark', 'DSM'],
        'tsm'            => ['danger',  'TSM'],
        'dealer'         => ['success', 'Dealer'],
        'distributor'    => ['teal',    'Distributor'],
        'shop_owner'     => ['purple',  'Shop Owner'],
        'customer'       => ['secondary','Customer'],
        'other'          => ['light text-dark', 'Other'],
    ];
    [$cls, $label] = $map[$type] ?? ['secondary', ucfirst($type)];
    return '<span class="badge bg-' . $cls . '">' . $label . '</span>';
}

// ── Status badge ─────────────────────────────────────────────
function status_badge(string $status): string {
    $map = [
        'active'      => 'success',
        'inactive'    => 'secondary',
        'blocked'     => 'danger',
        'former'      => 'dark',
        'open'        => 'danger',
        'in_progress' => 'warning text-dark',
        'resolved'    => 'success',
        'closed'      => 'secondary',
        'pending'     => 'warning text-dark',
        'referred'    => 'info text-dark',
        'approved'    => 'success',
        'rejected'    => 'danger',
        'completed'   => 'success',
        'cancelled'   => 'secondary',
        'missed'      => 'danger',
        'sent'        => 'success',
        'failed'      => 'danger',
        'queued'      => 'secondary',
    ];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . ucfirst(str_replace('_',' ',$status)) . '</span>';
}

// ── Pagination ───────────────────────────────────────────────
function paginate(int $total, int $page, int $per_page = 20): array {
    $pages = (int) ceil($total / $per_page);
    $page  = max(1, min($page, max(1, $pages)));
    return [
        'total'    => $total,
        'page'     => $page,
        'per_page' => $per_page,
        'pages'    => $pages,
        'offset'   => ($page - 1) * $per_page,
    ];
}

function pagination_html(array $p, string $url_base): string {
    if ($p['pages'] <= 1) return '';
    $html = '<nav><ul class="pagination pagination-sm mb-0">';
    $html .= '<li class="page-item' . ($p['page'] <= 1 ? ' disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . $url_base . '&page=' . ($p['page']-1) . '">‹</a></li>';
    for ($i = max(1, $p['page']-2); $i <= min($p['pages'], $p['page']+2); $i++) {
        $active = $i === $p['page'] ? ' active' : '';
        $html  .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $url_base . '&page=' . $i . '">' . $i . '</a></li>';
    }
    $html .= '<li class="page-item' . ($p['page'] >= $p['pages'] ? ' disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . $url_base . '&page=' . ($p['page']+1) . '">›</a></li>';
    $html .= '</ul></nav>';
    return $html;
}

// ── Redirect helper ───────────────────────────────────────────
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ── JSON response ─────────────────────────────────────────────
function json_response(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── Sanitize ─────────────────────────────────────────────────
function clean(string|null $s): string {
    return trim(strip_tags((string)$s));
}

function clean_phone(string|null $s): string {
    return preg_replace('/[^\d+\-\s()]/', '', (string)$s);
}

// ── Contact last interaction ──────────────────────────────────
function contact_last_interaction(int $contact_id): array|null {
    // Looks in calls, sms_log, tasks for most recent
    $row = db_row(
        "SELECT 'call' AS type, started_at AS dt, u.name AS agent_name
         FROM calls c LEFT JOIN users u ON u.id = c.agent_id
         WHERE c.contact_id = ?
         UNION ALL
         SELECT 'sms', sent_at, u.name FROM sms_log s LEFT JOIN users u ON u.id = s.agent_id
         WHERE s.contact_id = ?
         UNION ALL
         SELECT 'task', created_at, u.name FROM tasks t LEFT JOIN users u ON u.id = t.assigned_by
         WHERE t.contact_id = ?
         ORDER BY dt DESC LIMIT 1",
        [$contact_id, $contact_id, $contact_id]
    );
    return $row ?: null;
}

// ── Open feedback thread count for a contact ─────────────────
function contact_open_threads(int $contact_id): int {
    return (int) db_val(
        "SELECT COUNT(*) FROM feedback_threads WHERE contact_id = ? AND status IN ('open','in_progress')",
        [$contact_id]
    );
}

// ── Overdue callbacks count for a contact ────────────────────
function contact_overdue_callbacks(int $contact_id): int {
    return (int) db_val(
        "SELECT COUNT(*) FROM callbacks WHERE contact_id = ? AND status = 'pending' AND scheduled_at < NOW()",
        [$contact_id]
    );
}
