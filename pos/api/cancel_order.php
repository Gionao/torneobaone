<?php
declare(strict_types=1);

define('POS_API', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/role_check.php';
require_role(['admin', 'operatore']);
require_once __DIR__ . '/../includes/staff_orders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pos_json(false, null, 'Metodo non consentito.');
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
$orderId = (int) ($input['order_id'] ?? 0);

if ($orderId < 1) {
    pos_json(false, null, 'ID ordine non valido.');
}

try {
    $result = pos_cancel_order($pdo, $orderId);
    pos_json(true, $result);
} catch (Throwable $e) {
    pos_json(false, null, $e instanceof InvalidArgumentException || DEBUG_MODE ? $e->getMessage() : 'Errore annullamento ordine.');
}
