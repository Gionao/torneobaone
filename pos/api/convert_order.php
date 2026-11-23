<?php
declare(strict_types=1);

define('POS_API', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/staff_orders.php';
require_role(['admin', 'operatore']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    pos_json(false, null, 'Metodo non consentito.');
}
$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input) || (int) ($input['order_id'] ?? 0) < 1 || !isset($input['is_staff']) || !is_bool($input['is_staff'])) {
    pos_json(false, null, 'Dati trasformazione non validi.');
}
try {
    $result = pos_convert_order($pdo, (int) $input['order_id'], $input);
    pos_json(true, $result);
} catch (InvalidArgumentException $e) {
    pos_json(false, null, $e->getMessage());
} catch (Throwable $e) {
    pos_json(false, null, DEBUG_MODE ? $e->getMessage() : 'Errore trasformazione ordine.');
}
