<?php
declare(strict_types=1);

// Tabelle TEMPORARY: nessuna modifica ai dati dell'applicazione.
if (PHP_SAPI !== 'cli') exit(2);
require_once dirname(__DIR__, 2) . '/pos/includes/db.php';
require_once dirname(__DIR__, 2) . '/pos/includes/staff_orders.php';
require_once dirname(__DIR__, 2) . '/pos/includes/order_creation.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    ++$checks;
    echo "PASS $message\n";
}
function rejects(callable $action, string $message): void
{
    try { $action(); } catch (InvalidArgumentException $e) { check(true, $message); return; }
    throw new RuntimeException($message);
}

$details = pos_order_details(['is_staff' => true, 'customer_name' => 'Mario', 'is_takeaway' => true, 'covers' => 10], 1.5, 1);
check($details['customer_name'] === 'Staff' && $details['covers'] === 0 && !$details['is_takeaway'] && $details['table_number'] === null && $details['cover_fee'] === 0.0 && $details['takeaway_fee'] === 0.0, 'Staff: cliente generico e nessuna tariffa anche con dati manipolati');
rejects(fn() => pos_order_details(['customer_name' => ''], 1.5, 1), 'Ordine normale: nome obbligatorio');
rejects(fn() => pos_order_details(['customer_name' => 'Mario', 'covers' => 0], 1.5, 1), 'Ordine normale: coperti validi');
rejects(fn() => pos_order_details(['customer_name' => 'Mario', 'covers' => 1], 1.5, 1), 'Ordine normale: tavolo o asporto obbligatori');

foreach (['pos_orders', 'pos_order_items', 'pos_order_item_modifications', 'pos_products', 'pos_coupons', 'pos_settings', 'pos_categories', 'pos_ingredients', 'pos_product_ingredients'] as $table) {
    $pdo->exec("CREATE TEMPORARY TABLE test_$table LIKE $table");
    $pdo->exec("ALTER TABLE test_$table RENAME TO $table");
}
// Verifica la migrazione sulla copia temporanea se lo schema di partenza è precedente.
if (!$pdo->query("SHOW COLUMNS FROM pos_orders LIKE 'is_staff'")->fetch()) {
    $pdo->exec(file_get_contents(dirname(__DIR__, 2) . '/database/migrations/20261123_pos_staff_orders.sql'));
}
$pdo->exec("INSERT INTO pos_settings (`key`, `value`) VALUES ('cover_fee', '1.50'), ('takeaway_fee', '1.00')");
$pdo->exec("INSERT INTO pos_products (id, category_id, name, price) VALUES (1, 1, 'Panino', 999), (2, 1, 'Acqua', 999)");
$pdo->exec("INSERT INTO pos_coupons (id, code, is_used, used_at) VALUES (1, 'TEST', 1, NOW())");
$pdo->exec("INSERT INTO pos_orders (id, daily_number, customer_name, admin_id, status, total_amount, created_at) VALUES
    (1, 1, 'Mario', 1, 'delivered', 13, NOW()),
    (2, 2, 'Ieri', 1, 'pending', 5, DATE_SUB(NOW(), INTERVAL 1 DAY)),
    (3, 3, 'Annullato', 1, 'cancelled', 5, NOW())");
$pdo->exec("INSERT INTO pos_order_items (id, order_id, product_id, quantity, unit_price, discount_applied, coupon_id, status) VALUES
    (1, 1, 1, 2, 5, 1, 1, 'delivered'), (2, 1, 2, 1, 2, 0, NULL, 'delivered')");
$pdo->exec("INSERT INTO pos_order_item_modifications (order_item_id, ingredient_id, action, extra_price) VALUES (1, 1, 'added', 1.50), (1, 2, 'added', .50), (1, 3, 'removed', 100)");

$converted = pos_convert_order($pdo, 1, ['is_staff' => true]);
check($converted['total'] === 0.0, 'Ordine completato di oggi trasformato in staff a zero');
$order = $pdo->query('SELECT * FROM pos_orders WHERE id = 1')->fetch();
check($order['customer_name'] === 'Staff' && (int) $order['covers'] === 0 && $order['status'] === 'delivered', 'Trasformazione mantiene stato e azzera coperti');
check((int) $pdo->query('SELECT is_used FROM pos_coupons WHERE id = 1')->fetchColumn() === 0, 'Coupon nuovamente disponibile');
$item = $pdo->query('SELECT * FROM pos_order_items WHERE id = 1')->fetch();
check($item['coupon_id'] === null && (float) $item['discount_applied'] === 0.0 && (float) $item['unit_price'] === 5.0, 'Coupon scollegato, sconto rimosso e prezzo storico conservato');
$today = $pdo->query('SELECT CURDATE()')->fetchColumn();
$report = pos_staff_consumption($pdo, $today, $today);
check(count($report) === 2 && (int) $report[0]['quantity'] === 2 && (float) $report[0]['consumed_value'] === 14.0 && array_sum(array_column($report, 'consumed_value')) === 16.0, 'Report per prodotto: quantità ed extra moltiplicati senza duplicazioni, prezzi storici');
check((float) $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM pos_orders WHERE is_staff = 0 AND status != 'cancelled' AND DATE(created_at) = CURDATE()")->fetchColumn() === 0.0, 'Consumi staff esclusi dagli incassi');
rejects(fn() => pos_convert_order($pdo, 1, ['is_staff' => false, 'customer_name' => '']), 'Trasformazione incompleta rifiutata');
check((int) $pdo->query('SELECT is_staff FROM pos_orders WHERE id = 1')->fetchColumn() === 1, 'Errore di validazione non modifica ordine');
rejects(fn() => pos_convert_order($pdo, 2, ['is_staff' => true]), 'Ordine di ieri non trasformabile');
rejects(fn() => pos_convert_order($pdo, 3, ['is_staff' => true]), 'Ordine annullato non trasformabile');
rejects(fn() => pos_convert_order($pdo, 999, ['is_staff' => true]), 'Ordine inesistente rifiutato');
rejects(fn() => pos_convert_order($pdo, 1, ['is_staff' => true]), 'Doppia trasformazione rifiutata');
$normal = pos_convert_order($pdo, 1, ['is_staff' => false, 'customer_name' => 'Luca', 'table_number' => 'A3', 'covers' => 2]);
check($normal['total'] === 19.0, 'Staff a normale: prezzi originali con extra e due coperti');
check(pos_staff_consumption($pdo, $today, $today) === [], 'Ordine tornato normale escluso dal report staff');
pos_convert_order($pdo, 1, ['is_staff' => true]);
$takeaway = pos_convert_order($pdo, 1, ['is_staff' => false, 'customer_name' => 'Luca', 'is_takeaway' => true]);
check($takeaway['total'] === 17.0, 'Staff a normale asporto: solo tariffa asporto');
pos_convert_order($pdo, 1, ['is_staff' => true]);
$pdo->exec("UPDATE pos_order_items SET status = 'cancelled' WHERE id = 1");
$report = pos_staff_consumption($pdo, $today, $today);
check(count($report) === 1 && (float) $report[0]['consumed_value'] === 2.0, 'Righe annullate escluse dal report');
$pdo->exec("UPDATE pos_orders SET status = 'cancelled' WHERE id = 1");
check(pos_staff_consumption($pdo, $today, $today) === [], 'Ordini annullati esclusi dal report');

$pdo->exec("INSERT INTO pos_categories (id, name, department) VALUES (1, 'Panini', 'kitchen')");
$pdo->exec("UPDATE pos_products SET price = 5 WHERE id = 1");
$pdo->exec("INSERT INTO pos_ingredients (id, name, is_extra, extra_price) VALUES (1, 'Extra', 1, 1.50)");
if ($pdo->query("SHOW COLUMNS FROM pos_product_ingredients LIKE 'is_removable'")->fetch()) {
    $pdo->exec("INSERT INTO pos_product_ingredients (product_id, ingredient_id, is_removable, is_extra, extra_price) VALUES (1, 1, 1, 1, 1.50)");
} else {
    $pdo->exec("INSERT INTO pos_product_ingredients (product_id, ingredient_id) VALUES (1, 1)");
}
$payload = ['is_staff' => true, 'items' => [['product_id' => 1, 'quantity' => 2, 'modifications' => [['ingredient_id' => 1, 'action' => 'added', 'extra_price' => 999]]]]];
$created = pos_create_order($pdo, $payload, 1, 'Cassa test');
check($created['total'] === 0.0, 'Creazione staff senza nome/tavolo: totale zero');
$createdId = (int) $created['order_id'];
$createdOrder = $pdo->query("SELECT * FROM pos_orders WHERE id = $createdId")->fetch();
check($createdOrder['customer_name'] === 'Staff' && (int) $createdOrder['is_staff'] === 1 && (int) $createdOrder['covers'] === 0 && (float) $createdOrder['cover_fee'] === 0.0, 'Creazione staff: metadati e tariffe corretti');
$report = pos_staff_consumption($pdo, $today, $today);
check(count($report) === 1 && (float) $report[0]['consumed_value'] === 13.0, 'Creazione staff: quantità e prezzi extra validati dal server');
$normalPayload = $payload;
$normalPayload['is_staff'] = false;
$normalPayload['customer_name'] = 'Cliente';
$normalPayload['table_number'] = '1';
$normalPayload['covers'] = 2;
$createdNormal = pos_create_order($pdo, $normalPayload, 1, null);
check($createdNormal['total'] === 16.0, 'Creazione normale conserva prezzi, extra e coperti');
$before = (int) $pdo->query('SELECT COUNT(*) FROM pos_orders')->fetchColumn();
$payload['items'][0]['coupon_id'] = 1;
rejects(fn() => pos_create_order($pdo, $payload, 1, null), 'Creazione staff con coupon rifiutata');
check((int) $pdo->query('SELECT COUNT(*) FROM pos_orders')->fetchColumn() === $before && !$pdo->inTransaction(), 'Creazione rifiutata non lascia ordini parziali');
$pdo->exec('UPDATE pos_orders SET is_staff = 1 WHERE id = 2');
rejects(fn() => pos_cancel_order($pdo, 2), 'Annullamento staff di ieri rifiutato');
check($pdo->query('SELECT status FROM pos_orders WHERE id = 2')->fetchColumn() === 'pending', 'Annullamento rifiutato mantiene stato');
pos_cancel_order($pdo, $createdId);
check($pdo->query("SELECT status FROM pos_orders WHERE id = $createdId")->fetchColumn() === 'cancelled', 'Annullamento staff di oggi consentito');
check($pdo->query("SELECT status FROM pos_order_items WHERE order_id = $createdId")->fetchColumn() === 'cancelled', 'Annullamento staff chiude anche le righe in coda');
check(pos_staff_consumption($pdo, $today, $today) === [], 'Annullamento staff elimina i consumi dal report');
echo "OK: $checks verifiche; nessun dato applicativo modificato.\n";
