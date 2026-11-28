<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/operation_audit.php';

function pos_staff_reason(array $input): string
{
    $reason = is_string($input['staff_reason'] ?? null) ? trim($input['staff_reason']) : '';
    if ($reason === '' || strlen($reason) > 250) {
        throw new InvalidArgumentException('Indica la motivazione (massimo 250 byte).');
    }
    return $reason;
}

/** Normalizza i dati senza fidarsi dei totali inviati dalla cassa. */
function pos_order_details(array $input, float $coverFee, float $takeawayFee): array
{
    $staff = !empty($input['is_staff']);
    $takeaway = !$staff && !empty($input['is_takeaway']);
    $name = $staff ? 'Staff' : substr(trim((string) ($input['customer_name'] ?? '')), 0, 100);
    $table = $staff || $takeaway ? null : substr(trim((string) ($input['table_number'] ?? '')), 0, 20);
    $covers = $staff || $takeaway ? 0 : (int) ($input['covers'] ?? 1);
    if ($name === '') {
        throw new InvalidArgumentException('Nome cliente obbligatorio.');
    }
    if (!$staff && !$takeaway && ($covers < 1 || $covers > 255)) {
        throw new InvalidArgumentException('Numero coperti non valido (1–255).');
    }
    if (!$staff && !$takeaway && $table === '') {
        throw new InvalidArgumentException('Numero tavolo obbligatorio oppure selezionare Asporto.');
    }
    return [
        'is_staff' => $staff, 'customer_name' => $name, 'table_number' => $table,
        'is_takeaway' => $takeaway, 'covers' => $covers,
        'cover_fee' => $staff || $takeaway ? 0.0 : round($coverFee * $covers, 2),
        'takeaway_fee' => $takeaway ? $takeawayFee : 0.0,
    ];
}

function pos_require_current_order(array $order): void
{
    if (!(bool) $order['is_today']) {
        throw new InvalidArgumentException('Operazione consentita solo per gli ordini della giornata corrente.');
    }
    if ($order['status'] === 'cancelled') {
        throw new InvalidArgumentException('Un ordine annullato non può essere trasformato.');
    }
}

/** La transazione serializza conversioni e annullamenti dello stesso ordine. */
function pos_convert_order(PDO $pdo, int $orderId, array $input, ?int $actorId = null): array
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT *, DATE(created_at) = CURDATE() AS is_today FROM pos_orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new InvalidArgumentException('Ordine non trovato.');
        }
        pos_require_current_order($order);
        if (!$order['is_staff'] && !empty($input['is_staff']) && ($order['was_delivered'] || $order['status'] === 'delivered')) {
            throw new InvalidArgumentException('Un ordine normale consegnato non puo diventare gratuito.');
        }
        if ((bool) $order['is_staff'] === !empty($input['is_staff'])) {
            throw new InvalidArgumentException('Il tipo di ordine è già quello richiesto. Ricarica la pagina.');
        }
        $settings = $pdo->query("SELECT `key`, `value` FROM pos_settings WHERE `key` IN ('cover_fee','takeaway_fee')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $details = pos_order_details($input, (float) ($settings['cover_fee'] ?? 1.50), (float) ($settings['takeaway_fee'] ?? 1.00));

        // Il coupon viene restituito e scollegato nella stessa transazione.
        if ($details['is_staff']) {
            $pdo->prepare('UPDATE pos_coupons c INNER JOIN pos_order_items oi ON oi.coupon_id = c.id SET c.is_used = 0, c.used_at = NULL WHERE oi.order_id = ?')->execute([$orderId]);
            $pdo->prepare('UPDATE pos_order_items SET coupon_id = NULL, discount_applied = 0 WHERE order_id = ?')->execute([$orderId]);
        }
        $value = $pdo->prepare("SELECT COALESCE(SUM((oi.unit_price - oi.discount_applied + COALESCE((SELECT SUM(m.extra_price) FROM pos_order_item_modifications m WHERE m.order_item_id = oi.id AND m.action = 'added'), 0)) * oi.quantity), 0) FROM pos_order_items oi WHERE oi.order_id = ? AND oi.status != 'cancelled'");
        $value->execute([$orderId]);
        $total = $details['is_staff'] ? 0.0 : round((float) $value->fetchColumn() + $details['cover_fee'] + $details['takeaway_fee'], 2);
        $pdo->prepare('UPDATE pos_orders SET is_staff = ?, customer_name = ?, table_number = ?, is_takeaway = ?, covers = ?, cover_fee = ?, takeaway_fee = ?, total_amount = ? WHERE id = ?')->execute([
            (int) $details['is_staff'], $details['customer_name'], $details['table_number'],
            (int) $details['is_takeaway'], $details['covers'], $details['cover_fee'], $details['takeaway_fee'], $total, $orderId,
        ]);
        if ($actorId !== null) {
            record_operation_audit($pdo, $actorId, 'pos', 'ordine_convertito', [
                'order_id' => $orderId, 'is_staff' => $details['is_staff'], 'reason' => pos_staff_reason($input),
                'previous_total' => $order['total_amount'], 'new_total' => $total,
            ]);
        }
        $pdo->commit();
        return ['order_id' => $orderId, 'is_staff' => $details['is_staff'], 'total' => $total];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function pos_staff_consumption(PDO $pdo, string $from, string $to): array
{
    $stmt = $pdo->prepare("SELECT p.id AS product_id, p.name AS product_name,
        SUM(oi.quantity) AS quantity,
        SUM((oi.unit_price + COALESCE((SELECT SUM(m.extra_price)
            FROM pos_order_item_modifications m WHERE m.order_item_id = oi.id AND m.action = 'added'), 0)) * oi.quantity) AS consumed_value
        FROM pos_order_items oi
        INNER JOIN pos_orders o ON o.id = oi.order_id
        INNER JOIN pos_products p ON p.id = oi.product_id
        WHERE o.is_staff = 1 AND o.status != 'cancelled' AND oi.status != 'cancelled'
          AND o.created_at >= ? AND o.created_at < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY p.id, p.name ORDER BY quantity DESC, p.name");
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}

function pos_cancel_order(PDO $pdo, int $orderId): array
{
    try {
        $pdo->beginTransaction();

        $check = $pdo->prepare('SELECT id, status, is_staff, DATE(created_at) = CURDATE() AS is_today FROM pos_orders WHERE id = ? FOR UPDATE');
        $check->execute([$orderId]);
        $order = $check->fetch();

        if (!$order) {
            throw new InvalidArgumentException('Ordine non trovato.');
        }

        if ($order['status'] === 'cancelled') {
            $pdo->commit();
            return ['cancelled' => true];
        }

        if ($order['is_staff'] && !$order['is_today']) {
            throw new InvalidArgumentException('Gli ordini staff possono essere annullati solo nella giornata corrente.');
        }

        $pdo->prepare("
            UPDATE pos_coupons c
            INNER JOIN pos_order_items oi ON oi.coupon_id = c.id
            SET c.is_used = 0, c.used_at = NULL
            WHERE oi.order_id = ?
        ")->execute([$orderId]);

        $pdo->prepare("UPDATE pos_orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
        $pdo->prepare("UPDATE pos_order_items SET status = 'cancelled' WHERE order_id = ?")->execute([$orderId]);

        $pdo->commit();
        return ['cancelled' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
