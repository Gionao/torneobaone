<?php
declare(strict_types=1);

define('POS_API', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pos_json(false, null, 'Metodo non consentito.');
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
$code = trim((string) ($input['code'] ?? ''));

if ($code === '') {
    pos_json(false, null, 'Codice coupon mancante.');
}

try {
    $stmt = $pdo->prepare("
        SELECT id, code, product_type_restriction, is_used
        FROM pos_coupons
        WHERE code = ?
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        pos_json(false, null, 'Coupon non trovato');
    }

    if ((int) $coupon['is_used'] === 1) {
        pos_json(false, null, 'Coupon già utilizzato');
    }

    pos_json(true, [
        'id'                       => (int) $coupon['id'],
        'code'                     => $coupon['code'],
        'product_type_restriction' => $coupon['product_type_restriction'],
    ]);
} catch (Throwable $e) {
    if (DEBUG_MODE) {
        pos_json(false, null, $e->getMessage());
    }
    pos_json(false, null, 'Errore verifica coupon.');
}
