<?php
// api/customers/search.php — Search customer by phone
declare(strict_types=1);
require_once dirname(__FILE__, 3) . '/config/config.php';
session_start_secure();
require_login();

$phone = sanitize_string($_GET['phone'] ?? '', 25);
if (empty($phone)) json_response(['customer' => null]);

try {
    $stmt = db()->prepare(
        'SELECT id, name, phone, email, loyalty_points, total_spend FROM customers WHERE phone LIKE ? LIMIT 5'
    );
    $stmt->execute(['%' . $phone . '%']);
    $customers = $stmt->fetchAll();

    json_response([
        'customer'  => $customers[0] ?? null,
        'customers' => $customers,
    ]);
} catch (PDOException $e) {
    json_response(['customer' => null, 'error' => 'DB error']);
}
