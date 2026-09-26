<?php
// pos/api/get_active_orders.php — Ordini attivi v2
// Modifiche: aggiunge table_number, is_takeaway, item_note nella risposta.
declare(strict_types=1);

define('POS_API', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/role_check.php';
require_role(['admin', 'operatore', 'bar', 'cucina']);

$department = $_GET['department'] ?? null;
if ($department !== null && !in_array($department, ['kitchen', 'bar'], true)) {
    pos_json(false, null, 'Dipartimento non valido.');
}

try {
    // Query ordini attivi (pending/preparing/ready)
    $sql = "
        SELECT DISTINCT o.id, o.daily_number, o.customer_name, o.table_number,
               o.is_takeaway, o.covers, o.station_name, o.status, o.created_at
        FROM pos_orders o
        INNER JOIN pos_order_items oi ON oi.order_id = o.id
        INNER JOIN pos_products p     ON p.id = oi.product_id
        INNER JOIN pos_categories c   ON c.id = p.category_id
        WHERE o.status IN ('pending', 'preparing', 'ready')
          AND oi.status IN ('pending', 'preparing', 'ready')
    ";
    $params = [];

    if ($department !== null) {
        $sql .= ' AND c.department = ?';
        $params[] = $department;
    }
    $sql .= ' ORDER BY o.created_at ASC';

    $stmt   = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Query item per ordine
    $itemSql = "
        SELECT oi.id, oi.order_id, oi.product_id, oi.quantity, oi.item_note,
               oi.status, oi.coupon_id, oi.unit_price, oi.discount_applied,
               p.name AS product_name, c.department, c.name AS category_name
        FROM pos_order_items oi
        INNER JOIN pos_products   p ON p.id = oi.product_id
        INNER JOIN pos_categories c ON c.id = p.category_id
        WHERE oi.order_id = ?
          AND oi.status IN ('pending', 'preparing', 'ready')
    ";
    if ($department !== null) {
        $itemSql .= ' AND c.department = ?';
    }
    $itemSql .= ' ORDER BY oi.id';
    $itemStmt = $pdo->prepare($itemSql);

    // Query modifiche ingredienti
    $modStmt = $pdo->prepare("
        SELECT oim.ingredient_id, oim.action, oim.extra_price, i.name AS ingredient_name
        FROM pos_order_item_modifications oim
        INNER JOIN pos_ingredients i ON i.id = oim.ingredient_id
        WHERE oim.order_item_id = ?
    ");

    $result = [];

    foreach ($orders as $order) {
        $orderId    = (int) $order['id'];
        $itemParams = [$orderId];
        if ($department !== null) {
            $itemParams[] = $department;
        }
        $itemStmt->execute($itemParams);
        $items = $itemStmt->fetchAll();

        if ($department !== null && count($items) === 0) {
            continue;
        }

        $hasCoupon      = false;
        $formattedItems = [];

        foreach ($items as $item) {
            if ($item['coupon_id'] !== null) {
                $hasCoupon = true;
            }
            $modStmt->execute([(int) $item['id']]);
            $mods = array_map(static fn(array $m): array => [
                'ingredient_id'   => (int) $m['ingredient_id'],
                'ingredient_name' => $m['ingredient_name'],
                'action'          => $m['action'],
                'extra_price'     => (float) $m['extra_price'],
            ], $modStmt->fetchAll());

            $formattedItems[] = [
                'id'               => (int) $item['id'],
                'product_id'       => (int) $item['product_id'],
                'product_name'     => $item['product_name'],
                'quantity'         => (int) $item['quantity'],
                'item_note'        => $item['item_note'],
                'status'           => $item['status'],
                'department'       => $item['department'],
                'category_name'    => $item['category_name'],
                'coupon_id'        => $item['coupon_id'] !== null ? (int) $item['coupon_id'] : null,
                'unit_price'       => (float) $item['unit_price'],
                'discount_applied' => (float) $item['discount_applied'],
                'modifications'    => $mods,
            ];
        }

        $result[] = [
            'id'            => $orderId,
            'daily_number'  => (int) $order['daily_number'],
            'customer_name' => $order['customer_name'] ?? '',
            'table_number'  => $order['table_number'],
            'is_takeaway'   => (bool) $order['is_takeaway'],
            'covers'        => (int) ($order['covers'] ?? 0),
            'station_name'  => $order['station_name'],
            'status'        => $order['status'],
            'created_at'    => $order['created_at'],
            'has_coupon'    => $hasCoupon,
            'items'         => $formattedItems,
        ];
    }

    pos_json(true, $result);

} catch (Throwable $e) {
    if (DEBUG_MODE) {
        pos_json(false, null, $e->getMessage());
    }
    pos_json(false, null, 'Errore caricamento ordini attivi.');
}
