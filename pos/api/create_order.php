<?php
// Creazione ordini normali e consumi staff.
declare(strict_types=1);

define('POS_API', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/order_creation.php';
require_role(['admin', 'operatore']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    pos_json(false, null, 'Metodo non consentito.');
}
$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) pos_json(false, null, 'Payload ordine non valido.');
if (!empty($input['is_staff'])) {
    require_role(['admin']);
}
$station = staff_is_super_admin() ? null : staff_station('pos');
try {
    $result = pos_create_order($pdo, $input, staff_id(), $station !== null ? substr($station, 0, 80) : null);
    pos_json(true, $result);
} catch (Throwable $e) {
    pos_json(false, null, $e instanceof InvalidArgumentException || DEBUG_MODE ? $e->getMessage() : "Errore durante la creazione dell'ordine.");
}
