<?php
// api/offline/sync.php — Receive offline sales from client
declare(strict_types=1);
require_once dirname(__FILE__, 3) . '/config/config.php';
session_start_secure();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

// CSRF check via header
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!csrf_verify($csrfHeader)) {
    json_response(['error' => 'CSRF validation failed'], 403);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['local_id'])) {
    json_response(['error' => 'Invalid payload'], 422);
}

$localId    = sanitize_string($input['local_id'],    60);
$queuedAt   = sanitize_string($input['queued_at'],   30);
$deviceInfo = sanitize_string($input['device_info'] ?? '', 200);
$payload    = json_encode($input['payload'] ?? $input);

try {
    // Check for duplicate
    $check = db()->prepare('SELECT id FROM offline_sync_queue WHERE local_id = ?');
    $check->execute([$localId]);
    if ($check->fetch()) {
        json_response(['success' => true, 'message' => 'Already queued', 'duplicate' => true]);
    }

    // Insert into queue (status: pending — admin must confirm)
    $stmt = db()->prepare(
        'INSERT INTO offline_sync_queue (local_id, payload, device_info, queued_at, status)
         VALUES (?, ?, ?, ?, "pending")'
    );
    $stmt->execute([$localId, $payload, $deviceInfo, $queuedAt]);

    audit_log('OFFLINE_SYNC_RECEIVED', 'offline_sync_queue', $localId, null, ['local_id' => $localId]);

    json_response([
        'success' => true,
        'message' => 'Sale queued for admin review.',
        'status'  => 'pending',
    ]);
} catch (PDOException $e) {
    error_log('Offline sync error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Server error'], 500);
}
