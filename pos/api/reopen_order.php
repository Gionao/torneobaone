<?php
// pos/api/reopen_order.php — Riapri un ordine delivered (in caso di errore)
// Accessibile a bar, cucina e admin.
declare(strict_types=1);

define('POS_API', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/role_check.php';
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
        default => null,
    };
    if ($departmentScope !== null) {
        $scope = $pdo->prepare('SELECT COUNT(*) FROM pos_order_items oi INNER JOIN pos_products p ON p.id=oi.product_id INNER JOIN pos_categories c ON c.id=p.category_id WHERE oi.order_id=? AND c.department=?');
        $scope->execute([$orderId, $departmentScope]);
        if ((int) $scope->fetchColumn() === 0) {
            pos_json(false, null, 'Ordine non autorizzato per questo dipartimento.');
        }
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

} catch (Throwable $e) {
    if (DEBUG_MODE) {
        pos_json(false, null, $e->getMessage());
    }
    pos_json(false, null, 'Errore riapertura ordine.');
}
