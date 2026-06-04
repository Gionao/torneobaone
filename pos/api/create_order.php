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

if (!is_array($input) || empty($input['items']) || !is_array($input['items'])) {
    pos_json(false, null, 'Payload ordine non valido.');
}

$items = $input['items'];

try {
    $pdo->beginTransaction();

    $numStmt = $pdo->query("
        SELECT COALESCE(MAX(daily_number), 0) + 1 AS next_num
        FROM pos_orders
        WHERE DATE(created_at) = CURDATE()
    ");
    $dailyNumber = (int) $numStmt->fetchColumn();
    if ($dailyNumber < 1) {
        $dailyNumber = 1;
    }

    $orderStmt = $pdo->prepare("
        INSERT INTO pos_orders (daily_number, status, total_amount, created_at)
        VALUES (?, 'pending', 0, NOW())
    ");
    $orderStmt->execute([$dailyNumber]);
    $orderId = (int) $pdo->lastInsertId();

    $productStmt = $pdo->prepare("
        SELECT id, price, is_active FROM pos_products WHERE id = ? LIMIT 1
    ");
    $itemStmt = $pdo->prepare("
        INSERT INTO pos_order_items
            (order_id, product_id, quantity, unit_price, discount_applied, coupon_id, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $modStmt = $pdo->prepare("
        INSERT INTO pos_order_item_modifications
            (order_item_id, ingredient_id, action, extra_price)
        VALUES (?, ?, ?, ?)
    ");
    $couponMarkStmt = $pdo->prepare("
        UPDATE pos_coupons SET is_used = 1, used_at = NOW() WHERE id = ? AND is_used = 0
    ");
    $couponCheckStmt = $pdo->prepare("
        SELECT id, product_type_restriction, is_used FROM pos_coupons WHERE id = ? LIMIT 1
    ");

    $usedCouponIds = [];
    $orderTotal    = 0.0;

    foreach ($items as $item) {
        $productId = (int) ($item['product_id'] ?? 0);
        $quantity  = max(1, (int) ($item['quantity'] ?? 1));
        $couponId  = isset($item['coupon_id']) && $item['coupon_id'] !== null
            ? (int) $item['coupon_id']
            : null;

        $productStmt->execute([$productId]);
        $product = $productStmt->fetch();

        if (!$product || (int) $product['is_active'] !== 1) {
            throw new RuntimeException('Prodotto non valido o non attivo.');
        }

        $unitPrice = (float) $product['price'];
        $discount  = 0.0;
        $lineExtra = 0.0;

        $mods = $item['modifications'] ?? [];
        if (is_array($mods)) {
            foreach ($mods as $mod) {
                if (($mod['action'] ?? '') === 'added') {
                    $lineExtra += (float) ($mod['extra_price'] ?? 0);
                }
            }
        }

        if ($couponId !== null) {
            if (in_array($couponId, $usedCouponIds, true)) {
                throw new RuntimeException('Coupon già applicato in questo ordine.');
            }
            $couponCheckStmt->execute([$couponId]);
            $coupon = $couponCheckStmt->fetch();
            if (!$coupon || (int) $coupon['is_used'] === 1) {
                throw new RuntimeException('Coupon non valido.');
            }
            $discount = $unitPrice;
            $couponMarkStmt->execute([$couponId]);
            if ($couponMarkStmt->rowCount() === 0) {
                throw new RuntimeException('Coupon già utilizzato.');
            }
            $usedCouponIds[] = $couponId;
        }

        $lineTotal = ($unitPrice + $lineExtra) * $quantity - ($discount * $quantity);
        $orderTotal += $lineTotal;

        $itemStmt->execute([
            $orderId,
            $productId,
            $quantity,
            $unitPrice,
            $discount,
            $couponId,
        ]);
        $orderItemId = (int) $pdo->lastInsertId();

        if (is_array($mods)) {
            foreach ($mods as $mod) {
                $ingredientId = (int) ($mod['ingredient_id'] ?? 0);
                $action       = $mod['action'] ?? '';
                if (!in_array($action, ['removed', 'added'], true) || $ingredientId < 1) {
                    continue;
                }
                $extraPrice = (float) ($mod['extra_price'] ?? 0);
                $modStmt->execute([$orderItemId, $ingredientId, $action, $extraPrice]);
            }
        }
    }

    $pdo->prepare('UPDATE pos_orders SET total_amount = ? WHERE id = ?')
        ->execute([round($orderTotal, 2), $orderId]);

    $pdo->commit();

    pos_json(true, [
        'order_id'     => $orderId,
        'daily_number' => $dailyNumber,
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
