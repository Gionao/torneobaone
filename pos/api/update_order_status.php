<?php
declare(strict_types=1);

define('POS_API', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/role_check.php';
require_role(['admin', 'operatore', 'bar', 'cucina']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pos_json(false, null, 'Metodo non consentito.');
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);

$type   = $input['type'] ?? '';
$id     = (int) ($input['id'] ?? 0);
$status = $input['status'] ?? '';

$allowed = ['pending', 'preparing', 'ready', 'delivered', 'cancelled'];

if (!in_array($status, $allowed, true) || $id < 1) {
    pos_json(false, null, 'Parametri non validi.');
}

try {
    if ($type === 'order') {
        $stmt = $pdo->prepare('UPDATE pos_orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        if ($stmt->rowCount() === 0) {
            pos_json(false, null, 'Ordine non trovato.');
        }
    } elseif ($type === 'item') {
        $stmt = $pdo->prepare('UPDATE pos_order_items SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        if ($stmt->rowCount() === 0) {
            pos_json(false, null, 'Riga ordine non trovata.');
        }

        if ($status === 'ready') {
            $orderStmt = $pdo->prepare('SELECT order_id FROM pos_order_items WHERE id = ?');
            $orderStmt->execute([$id]);
            $orderId = (int) $orderStmt->fetchColumn();

            if ($orderId > 0) {
                $check = $pdo->prepare("
                    SELECT COUNT(*) FROM pos_order_items
                    WHERE order_id = ? AND status NOT IN ('ready', 'delivered', 'cancelled')
                ");
                $check->execute([$orderId]);
                if ((int) $check->fetchColumn() === 0) {
                    $pdo->prepare("UPDATE pos_orders SET status = 'ready' WHERE id = ?")->execute([$orderId]);
                }
            }
        }
    } else {
        pos_json(false, null, 'Tipo aggiornamento non valido.');
    }

    pos_json(true, ['updated' => true]);
} catch (Throwable $e) {
    if (DEBUG_MODE) {
        pos_json(false, null, $e->getMessage());
    }
    pos_json(false, null, 'Errore aggiornamento stato.');
}
