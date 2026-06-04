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
$orderId = (int) ($input['order_id'] ?? 0);

if ($orderId < 1) {
    pos_json(false, null, 'ID ordine non valido.');
}

try {
    $pdo->beginTransaction();

    $check = $pdo->prepare('SELECT id, status FROM pos_orders WHERE id = ? LIMIT 1');
    $check->execute([$orderId]);
    $order = $check->fetch();

    if (!$order) {
        $pdo->rollBack();
        pos_json(false, null, 'Ordine non trovato.');
    }

    if ($order['status'] === 'cancelled') {
        $pdo->commit();
        pos_json(true, ['cancelled' => true]);
    }

    $pdo->prepare("
        UPDATE pos_coupons c
        INNER JOIN pos_order_items oi ON oi.coupon_id = c.id
        SET c.is_used = 0, c.used_at = NULL
        WHERE oi.order_id = ?
    ")->execute([$orderId]);

    $pdo->prepare("UPDATE pos_orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
    $pdo->prepare("UPDATE pos_order_items SET status = 'cancelled' WHERE order_id = ?")->execute([$orderId]);

    $pdo->commit();
    pos_json(true, ['cancelled' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (DEBUG_MODE) {
        pos_json(false, null, $e->getMessage());
    }
    pos_json(false, null, 'Errore annullamento ordine.');
}
