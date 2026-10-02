<?php
// pos/api/get_completed_orders.php — Storico ordini completati per Bar/Cucina
// Restituisce gli ordini delivered del giorno corrente (ultimi 200)
// filtrati per dipartimento.
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

// Supporta anche filtro data (default = oggi)
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

try {
    $sql = "
        SELECT DISTINCT o.id, o.daily_number, o.customer_name, o.table_number,
               o.is_takeaway, o.covers, o.station_name, o.status, o.created_at
        FROM pos_orders o
        INNER JOIN pos_order_items oi ON oi.order_id = o.id
        INNER JOIN pos_products p     ON p.id = oi.product_id
        INNER JOIN pos_categories c   ON c.id = p.category_id
        WHERE o.status = 'delivered'
          AND DATE(o.created_at) = ?
    ";
    $params = [$date];

    if ($department !== null) {
        $sql .= ' AND c.department = ?';
        $params[] = $department;
    }

    $sql .= ' ORDER BY o.created_at DESC LIMIT 200';

    $stmt   = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    $itemSql = "
        SELECT oi.id, oi.product_id, oi.quantity, oi.item_note,
               oi.status, p.name AS product_name, c.department
        FROM pos_order_items oi
        INNER JOIN pos_products   p ON p.id = oi.product_id
        INNER JOIN pos_categories c ON c.id = p.category_id
        WHERE oi.order_id = ?
    ";
    if ($department !== null) {
        $itemSql .= ' AND c.department = ?';
    }
    $itemSql  .= ' ORDER BY oi.id';
    $itemStmt  = $pdo->prepare($itemSql);

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

        $result[] = [
            'id'            => $orderId,
            'daily_number'  => (int) $order['daily_number'],
            'customer_name' => $order['customer_name'] ?? '',
            'table_number'  => $order['table_number'],
            'is_takeaway'   => (bool) $order['is_takeaway'],
            'covers'        => (int) $order['covers'],
            'station_name'  => $order['station_name'],
            'status'        => $order['status'],
            'created_at'    => $order['created_at'],
            'items'         => array_map(fn($i) => [
                'id'           => (int) $i['id'],
                'product_name' => $i['product_name'],
                'quantity'     => (int) $i['quantity'],
                'item_note'    => $i['item_note'],
                'status'       => $i['status'],
                'department'   => $i['department'],
            ], $items),
        ];
    }

    pos_json(true, $result);

} catch (Throwable $e) {
    if (DEBUG_MODE) {
        pos_json(false, null, $e->getMessage());
    }
    pos_json(false, null, 'Errore caricamento storico ordini.');
}
