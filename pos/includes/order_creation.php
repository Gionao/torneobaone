<?php
declare(strict_types=1);

require_once __DIR__ . '/staff_orders.php';

function pos_create_order(PDO $pdo, array $input, int $adminId, ?string $stationName): array
{
    if (empty($input['items']) || !is_array($input['items'])) {
        throw new InvalidArgumentException('Payload ordine non valido.');
    }

    // ── Leggi tariffe dalle impostazioni ─────────────────────────
    $settingsStmt = $pdo->query("SELECT `key`, `value` FROM pos_settings WHERE `key` IN ('cover_fee','takeaway_fee')");
    $settings = [];
    foreach ($settingsStmt->fetchAll() as $row) {
        $settings[$row['key']] = (float) $row['value'];
    }
    $coverFeeUnit   = $settings['cover_fee']    ?? 1.50;
    $takeawayFeeVal = $settings['takeaway_fee'] ?? 1.00;

    $details = pos_order_details($input, $coverFeeUnit, $takeawayFeeVal);
    $isStaff = $details['is_staff'];
    $staffReason = $isStaff ? pos_staff_reason($input) : null;
    $trackingToken = $isStaff ? null : bin2hex(random_bytes(16));
    $customerName = $details['customer_name'];
    $tableNumber = $details['table_number'];
    $isTakeaway = $details['is_takeaway'];
    $covers = $details['covers'];
    $appliedCoverFee = $details['cover_fee'];
    $appliedTakeawayFee = $details['takeaway_fee'];

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
                 cover_fee, takeaway_fee, admin_id, station_name, is_staff, tracking_token_hash, status, total_amount, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, NOW())
        ");
        $orderStmt->execute([
            $dailyNumber, $customerName, $tableNumber ?: null,
            $isTakeaway ? 1 : 0, $covers,
            $appliedCoverFee, $appliedTakeawayFee,
            $adminId, $stationName, $isStaff ? 1 : 0,
            $trackingToken === null ? null : hash('sha256', $trackingToken),
        ]);
        $orderId = (int) $pdo->lastInsertId();

        // Statement riutilizzabili
        $productStmt = $pdo->prepare('SELECT p.id, p.price, p.is_active, p.product_kind, c.name AS category_name, c.department FROM pos_products p JOIN pos_categories c ON c.id = p.category_id WHERE p.id = ? LIMIT 1');
        $couponStmt  = $pdo->prepare('SELECT id, product_type_restriction, discount_type, discount_value, is_used FROM pos_coupons WHERE code = ? LIMIT 1');
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
            if (isset($item['coupon_id'])) {
                throw new InvalidArgumentException('Presenta il codice del coupon.');
            }
            $couponCode = $item['coupon_code'] ?? null;
            if ($couponCode !== null && (!is_string($couponCode) || trim($couponCode) === '')) {
                throw new InvalidArgumentException('Codice coupon non valido.');
            }
            $couponId = null;

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
            if ($couponCode !== null) {
                if ($isStaff) {
                    throw new InvalidArgumentException('Gli ordini staff non possono utilizzare coupon.');
                }
                $couponStmt->execute([trim($couponCode)]);
                $coupon = $couponStmt->fetch();
                if (!$coupon || (int) $coupon['is_used'] === 1) {
                    throw new RuntimeException('Coupon non valido o già utilizzato.');
                }
                if ($coupon['product_type_restriction'] !== 'any' && $coupon['product_type_restriction'] !== $productKind) {
                    throw new RuntimeException('Coupon non valido per questo prodotto.');
                }

                $couponId = (int) $coupon['id'];
                if (in_array($couponId, $usedCouponIds, true)) {
                    throw new RuntimeException('Coupon gia applicato in questo ordine.');
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

            $lineCost    = ($unitPrice + $lineExtra) * $quantity - $discount;
            $orderTotal += $lineCost;

            $lines = $couponId !== null && $quantity > 1
                ? [[1, $discount, $couponId], [$quantity - 1, 0.0, null]]
                : [[$quantity, $discount, $couponId]];

            // Modifiche ingredienti
            foreach ($lines as [$lineQuantity, $lineDiscount, $lineCoupon]) {
                $itemStmt->execute([$orderId, $productId, $lineQuantity, $itemNote ?: null, $unitPrice, $lineDiscount, $lineCoupon]);
                $orderItemId = (int) $pdo->lastInsertId();
                foreach ($validatedMods as [$ingredientId, $action, $extraPrice]) {
                    $modStmt->execute([$orderItemId, $ingredientId, $action, $extraPrice]);
                }
            }
        }

        $orderTotalBeforeStaff = $orderTotal;
        $orderTotal = $isStaff ? 0.0 : $orderTotal;
        // Aggiorna totale ordine
        $pdo->prepare('UPDATE pos_orders SET total_amount = ? WHERE id = ?')
            ->execute([round($orderTotal, 2), $orderId]);

        if ($isStaff) {
            record_operation_audit($pdo, $adminId, 'pos', 'ordine_staff_creato', [
                'order_id' => $orderId, 'reason' => $staffReason, 'previous_total' => round($orderTotalBeforeStaff, 2), 'new_total' => 0,
            ]);
        }
        $pdo->commit();

        return [
            'order_id'     => $orderId,
            'daily_number' => $dailyNumber,
            'tracking_token' => $trackingToken,
            'total'        => round($orderTotal, 2),
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
