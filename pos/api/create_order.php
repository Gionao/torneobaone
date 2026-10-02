<?php
// pos/api/create_order.php — Creazione ordine v2
// Modifiche: table_number + is_takeaway + cover_fee/takeaway_fee,
//            item_note per riga, coupon fixed/percent.
declare(strict_types=1);

define('POS_API', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/role_check.php';
require_role(['admin', 'operatore']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pos_json(false, null, 'Metodo non consentito.');
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);

if (!is_array($input) || empty($input['items']) || !is_array($input['items'])) {
    pos_json(false, null, 'Payload ordine non valido.');
}

// ── Campi ordine ─────────────────────────────────────────────
$customerName = substr(trim((string) ($input['customer_name'] ?? '')), 0, 100);
$tableNumber  = isset($input['table_number']) ? substr(trim((string) $input['table_number']), 0, 20) : null;
$isTakeaway   = !empty($input['is_takeaway']);
$covers       = $isTakeaway ? 0 : min(255, max(1, (int) ($input['covers'] ?? 1)));

if ($customerName === '') {
    pos_json(false, null, 'Nome cliente obbligatorio.');
}

// ── Leggi tariffe dalle impostazioni ─────────────────────────
$settingsStmt = $pdo->query("SELECT `key`, `value` FROM pos_settings WHERE `key` IN ('cover_fee','takeaway_fee')");
$settings = [];
foreach ($settingsStmt->fetchAll() as $row) {
    $settings[$row['key']] = (float) $row['value'];
}
$coverFeeUnit   = $settings['cover_fee']    ?? 1.50;
$takeawayFeeVal = $settings['takeaway_fee'] ?? 1.00;

$appliedCoverFee   = $isTakeaway ? 0.0 : round($coverFeeUnit * $covers, 2);
$appliedTakeawayFee = $isTakeaway ? $takeawayFeeVal : 0.0;

$adminId     = (int) $_SESSION['pos_admin_id'];
$stationName = isset($_SESSION['pos_admin_station'])
    ? substr((string) $_SESSION['pos_admin_station'], 0, 80)
    : null;

$items = $input['items'];

try {
    $pdo->beginTransaction();

    // Numero progressivo giornaliero
    $numStmt     = $pdo->query("SELECT COALESCE(MAX(daily_number), 0) + 1 AS next_num FROM pos_orders WHERE DATE(created_at) = CURDATE()");
    $dailyNumber = max(1, (int) $numStmt->fetchColumn());

    // Inserisci testata ordine
    $orderStmt = $pdo->prepare("
        INSERT INTO pos_orders
            (daily_number, customer_name, table_number, is_takeaway, covers,
             cover_fee, takeaway_fee, admin_id, station_name, status, total_amount, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, NOW())
    ");
    $orderStmt->execute([
        $dailyNumber, $customerName, $tableNumber ?: null,
        $isTakeaway ? 1 : 0, $covers,
        $appliedCoverFee, $appliedTakeawayFee,
        $adminId, $stationName,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    // Statement riutilizzabili
    $productStmt = $pdo->prepare('SELECT p.id, p.price, p.is_active, p.product_kind, c.name AS category_name, c.department FROM pos_products p JOIN pos_categories c ON c.id = p.category_id WHERE p.id = ? LIMIT 1');
    $couponStmt  = $pdo->prepare('SELECT id, product_type_restriction, discount_type, discount_value, is_used FROM pos_coupons WHERE id = ? LIMIT 1');
    $couponMarkStmt = $pdo->prepare('UPDATE pos_coupons SET is_used = 1, used_at = NOW() WHERE id = ? AND is_used = 0');

    $itemStmt = $pdo->prepare("
        INSERT INTO pos_order_items
            (order_id, product_id, quantity, item_note, unit_price, discount_applied, coupon_id, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $modStmt = $pdo->prepare("
        INSERT INTO pos_order_item_modifications
            (order_item_id, ingredient_id, action, extra_price)
        VALUES (?, ?, ?, ?)
    ");
    $ingredientStmt = $pdo->prepare('SELECT i.id, i.is_removable, i.is_extra, i.extra_price FROM pos_product_ingredients pi JOIN pos_ingredients i ON i.id = pi.ingredient_id WHERE pi.product_id = ? AND i.id = ? LIMIT 1');

    $usedCouponIds = [];
    $orderTotal    = $appliedCoverFee + $appliedTakeawayFee;

    foreach ($items as $item) {
        $productId = (int) ($item['product_id'] ?? 0);
        $quantity  = (int) ($item['quantity'] ?? 1);
        if ($quantity < 1 || $quantity > 255) {
            throw new RuntimeException('Quantità non valida (1–255).');
        }
        $itemNote  = isset($item['item_note']) ? substr(trim((string) $item['item_note']), 0, 255) : null;
        $couponId  = isset($item['coupon_id']) && $item['coupon_id'] !== null
            ? (int) $item['coupon_id']
            : null;

        // Verifica prodotto
        $productStmt->execute([$productId]);
        $product = $productStmt->fetch();
        if (!$product || (int) $product['is_active'] !== 1) {
            throw new RuntimeException('Prodotto non valido o non attivo.');
        }

        $unitPrice = (float) $product['price'];
        $productKind = pos_product_coupon_kind($product['category_name'], $product['department'], $product['product_kind']);
        $discount  = 0.0;

        // Extra dagli ingredienti aggiunti
        $lineExtra = 0.0;
        $mods = $item['modifications'] ?? [];
        $validatedMods = [];
        if (!is_array($mods)) {
            throw new RuntimeException('Modifiche prodotto non valide.');
        }
        if (is_array($mods)) {
            foreach ($mods as $mod) {
                $action = $mod['action'] ?? '';
                $ingredientId = (int) ($mod['ingredient_id'] ?? 0);
                if (!in_array($action, ['removed', 'added'], true) || $ingredientId < 1) {
                    throw new RuntimeException('Modifica ingrediente non valida.');
                }
                $ingredientStmt->execute([$productId, $ingredientId]);
                $ingredient = $ingredientStmt->fetch();
                if (!$ingredient || ($action === 'removed' && !(int) $ingredient['is_removable']) || ($action === 'added' && !(int) $ingredient['is_extra'])) {
                    throw new RuntimeException('Ingrediente non disponibile per questo prodotto.');
                }
                $extraPrice = $action === 'added' ? (float) $ingredient['extra_price'] : 0.0;
                if ($action === 'added') $lineExtra += $extraPrice;
                $validatedMods[] = [$ingredientId, $action, $extraPrice];
            }
        }

        // Applica coupon (fixed o percent)
        if ($couponId !== null) {
            if (in_array($couponId, $usedCouponIds, true)) {
                throw new RuntimeException('Coupon già applicato in questo ordine.');
            }
            $couponStmt->execute([$couponId]);
            $coupon = $couponStmt->fetch();
            if (!$coupon || (int) $coupon['is_used'] === 1) {
                throw new RuntimeException('Coupon non valido o già utilizzato.');
            }
            if ($coupon['product_type_restriction'] !== 'any' && $coupon['product_type_restriction'] !== $productKind) {
                throw new RuntimeException('Coupon non valido per questo prodotto.');
            }

            $discountValue = (float) $coupon['discount_value'];
            if ($coupon['discount_type'] === 'percent') {
                // Percentuale sul prezzo unitario (senza extra)
                $discount = round($unitPrice * $discountValue / 100, 2);
            } else {
                // Fisso: massimo il prezzo del prodotto
                $discount = min($discountValue, $unitPrice);
            }

            $couponMarkStmt->execute([$couponId]);
            if ($couponMarkStmt->rowCount() === 0) {
                throw new RuntimeException('Coupon già utilizzato da un altro operatore.');
            }
            $usedCouponIds[] = $couponId;
        }

        $lineCost    = ($unitPrice + $lineExtra) * $quantity - ($discount * $quantity);
        $orderTotal += $lineCost;

        $itemStmt->execute([$orderId, $productId, $quantity, $itemNote ?: null, $unitPrice, $discount, $couponId]);
        $orderItemId = (int) $pdo->lastInsertId();

        // Modifiche ingredienti
        foreach ($validatedMods as [$ingredientId, $action, $extraPrice]) {
            $modStmt->execute([$orderItemId, $ingredientId, $action, $extraPrice]);
        }
    }

    // Aggiorna totale ordine
    $pdo->prepare('UPDATE pos_orders SET total_amount = ? WHERE id = ?')
        ->execute([round($orderTotal, 2), $orderId]);

    $pdo->commit();

    pos_json(true, [
        'order_id'     => $orderId,
        'daily_number' => $dailyNumber,
        'total'        => round($orderTotal, 2),
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (DEBUG_MODE) {
        pos_json(false, null, $e->getMessage());
    }
    pos_json(false, null, 'Errore durante la creazione dell\'ordine.');
}
