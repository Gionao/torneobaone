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
    // Gli annullamenti staff passano dall'API dedicata, che controlla ruolo e giornata.
    if ($status === 'cancelled') {
        $staffCheck = $pdo->prepare($type === 'item'
            ? 'SELECT o.is_staff FROM pos_orders o INNER JOIN pos_order_items oi ON oi.order_id = o.id WHERE oi.id = ?'
            : 'SELECT is_staff FROM pos_orders WHERE id = ?');
        $staffCheck->execute([$id]);
        if ($staffCheck->fetchColumn()) {
            pos_json(false, null, 'Per annullare un ordine staff usa Annulla dalla cassa o dallo storico.');
        }
    }
    $departmentScope = match (pos_current_role()) {
        'bar' => 'bar',
        'cucina' => 'kitchen',
        default => null,
    };
    if ($type === 'order') {
        if ($departmentScope !== null) {
            $scopeCheck = $pdo->prepare('SELECT COUNT(*) FROM pos_order_items oi INNER JOIN pos_products p ON p.id=oi.product_id INNER JOIN pos_categories c ON c.id=p.category_id WHERE oi.order_id=? AND c.department=?');
            $scopeCheck->execute([$id, $departmentScope]);
            if ((int) $scopeCheck->fetchColumn() === 0) {
                pos_json(false, null, 'Ordine non autorizzato per questo dipartimento.');
            }
        }
        $stmt = $pdo->prepare('UPDATE pos_orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        if ($stmt->rowCount() === 0) {
            $exists = $pdo->prepare('SELECT 1 FROM pos_orders WHERE id = ?');
            $exists->execute([$id]);
            if ($exists->fetchColumn()) {
                pos_json(true, ['updated' => true]);
            }
            pos_json(false, null, 'Ordine non trovato.');
        }
        if ($status === 'delivered') {
            $pdo->prepare("UPDATE pos_order_items SET status = 'delivered' WHERE order_id = ? AND status = 'ready'")->execute([$id]);
        }
    } elseif ($type === 'item') {
        if ($departmentScope !== null) {
            $scopeCheck = $pdo->prepare('SELECT 1 FROM pos_order_items oi INNER JOIN pos_products p ON p.id=oi.product_id INNER JOIN pos_categories c ON c.id=p.category_id WHERE oi.id=? AND c.department=? LIMIT 1');
            $scopeCheck->execute([$id, $departmentScope]);
            if (!$scopeCheck->fetchColumn()) {
                pos_json(false, null, 'Riga ordine non autorizzata per questo dipartimento.');
            }
        }
        $stmt = $pdo->prepare('UPDATE pos_order_items SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        if ($stmt->rowCount() === 0) {
            $exists = $pdo->prepare('SELECT 1 FROM pos_order_items WHERE id = ?');
            $exists->execute([$id]);
            if ($exists->fetchColumn()) {
                pos_json(true, ['updated' => true]);
            }
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
