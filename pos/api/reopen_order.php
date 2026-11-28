<?php
// pos/api/reopen_order.php — Riapri un ordine delivered (in caso di errore)
// Accessibile a bar, cucina e admin.
declare(strict_types=1);

define('POS_API', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/department_orders.php';
require_role(['admin', 'bar', 'cucina']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pos_json(false, null, 'Metodo non consentito.');
}

$raw      = file_get_contents('php://input');
$input    = json_decode($raw ?: '', true);
$orderId  = (int) ($input['order_id'] ?? 0);

if ($orderId < 1) {
    pos_json(false, null, 'ID ordine non valido.');
}

try {
    $departmentScope = match (pos_current_role()) {
        'bar' => 'bar',
        'cucina' => 'kitchen',
        'admin' => in_array($input['department'] ?? null, ['bar', 'kitchen'], true) ? $input['department'] : null,
        default => null,
    };
    if ($departmentScope !== null) {
        pos_update_department_items($pdo, $departmentScope, 'order', $orderId, 'preparing');
        pos_json(true, ['reopened' => true]);
    }

    // Solo ordini delivered possono essere riaperti (non cancelled)
    $stmt = $pdo->prepare("
        UPDATE pos_orders SET status = 'preparing'
        WHERE id = ? AND status = 'delivered'
    ");
    $stmt->execute([$orderId]);

    if ($stmt->rowCount() === 0) {
        pos_json(false, null, 'Ordine non trovato o già in lavorazione.');
    }

    // Riporta gli item in preparing
    $pdo->prepare("
        UPDATE pos_order_items SET status = 'preparing'
        WHERE order_id = ? AND status IN ('ready', 'delivered')
    ")->execute([$orderId]);

    pos_json(true, ['reopened' => true]);

} catch (InvalidArgumentException $e) {
    http_response_code(403);
    pos_json(false, null, $e->getMessage());
} catch (Throwable $e) {
    if (DEBUG_MODE) {
        pos_json(false, null, $e->getMessage());
    }
    pos_json(false, null, 'Errore riapertura ordine.');
}
