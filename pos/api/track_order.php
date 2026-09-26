<?php
// pos/api/track_order.php — API pubblica tracciamento ordine (nessuna auth)
// Usata dalla pagina /pos/stato-ordine.php
declare(strict_types=1);

define('POS_API', true);
require_once __DIR__ . '/../includes/db.php';
// Nota: nessuna autenticazione — è pubblica per design.

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    pos_json(false, null, 'Metodo non consentito.');
}

$number = (int) ($_GET['number'] ?? 0);
$date   = $_GET['date'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
if ($number < 1 || $number > 9999) {
    pos_json(false, null, 'Numero ordine non valido.');
}

try {
    $stmt = $pdo->prepare("
        SELECT id, daily_number, customer_name, table_number, is_takeaway,
               covers, status, total_amount, created_at
        FROM pos_orders
        WHERE daily_number = ? AND DATE(created_at) = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$number, $date]);
    $order = $stmt->fetch();

    if (!$order) {
        pos_json(false, null, 'Ordine non trovato. Verifica il numero.');
    }

    // Recupera i prodotti dell'ordine (info essenziali, no prezzi)
    $itemStmt = $pdo->prepare("
        SELECT p.name AS product_name, oi.quantity, oi.item_note, oi.status,
               c.department
        FROM pos_order_items oi
        INNER JOIN pos_products   p ON p.id = oi.product_id
        INNER JOIN pos_categories c ON c.id = p.category_id
        WHERE oi.order_id = ?
          AND oi.status != 'cancelled'
        ORDER BY oi.id
    ");
    $itemStmt->execute([(int) $order['id']]);
    $items = $itemStmt->fetchAll();

    // Calcola stato globale leggibile
    $statusLabels = [
        'pending'   => 'In attesa',
        'preparing' => 'In preparazione',
        'ready'     => 'Pronto per la consegna',
        'delivered' => 'Consegnato',
        'cancelled' => 'Annullato',
    ];

    pos_json(true, [
        'daily_number'  => (int) $order['daily_number'],
        'customer_name' => $order['customer_name'],
        'table_number'  => $order['table_number'],
        'is_takeaway'   => (bool) $order['is_takeaway'],
        'status'        => $order['status'],
        'status_label'  => $statusLabels[$order['status']] ?? $order['status'],
        'created_at'    => $order['created_at'],
        'items'         => array_map(fn($i) => [
            'product_name' => $i['product_name'],
            'quantity'     => (int) $i['quantity'],
            'item_note'    => $i['item_note'],
            'status'       => $i['status'],
            'status_label' => $statusLabels[$i['status']] ?? $i['status'],
            'department'   => $i['department'],
        ], $items),
    ]);

} catch (Throwable $e) {
    if (DEBUG_MODE) {
        pos_json(false, null, $e->getMessage());
    }
    pos_json(false, null, 'Errore tracciamento ordine.');
}
