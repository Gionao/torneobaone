<?php
declare(strict_types=1);

function pos_update_department_items(PDO $pdo, string $department, string $type, int $id, string $status): void
{
    $transitions = ['ready' => ['pending', 'preparing'], 'delivered' => ['ready'], 'preparing' => ['delivered']];
    if (($type === 'item' && $status !== 'ready') || ($type === 'order' && $status === 'ready')) {
        throw new InvalidArgumentException('Operazione non autorizzata per questo reparto.');
    }
    $source = $transitions[$status] ?? null;
    if ($source === null || !in_array($type, ['item', 'order'], true)) {
        throw new InvalidArgumentException('Operazione non autorizzata per questo reparto.');
    }
    $pdo->beginTransaction();
    try {
        $from = 'pos_order_items oi JOIN pos_products p ON p.id = oi.product_id
                 JOIN pos_categories c ON c.id = p.category_id';
        $match = $type === 'item' ? 'oi.id' : 'oi.order_id';
        $find = $pdo->prepare("SELECT oi.order_id FROM $from WHERE $match = ? AND c.department = ? LIMIT 1");
        $find->execute([$id, $department]);
        $orderId = $find->fetchColumn();
        $lock = $pdo->prepare('SELECT status FROM pos_orders WHERE id = ? FOR UPDATE');
        $lock->execute([$orderId]);
        $orderStatus = $lock->fetchColumn();
        if (!$orderStatus || $orderStatus === 'cancelled') {
            throw new InvalidArgumentException('Ordine non disponibile per questo reparto.');
        }
        $placeholders = implode(', ', array_fill(0, count($source), '?'));
        $change = $pdo->prepare("UPDATE $from SET oi.status = ? WHERE oi.order_id = ?
            AND $match = ? AND c.department = ? AND oi.status IN ($placeholders)");
        $change->execute([$status, $orderId, $id, $department, ...$source]);
        if ($change->rowCount() > 0) {
            $states = $pdo->prepare("SELECT status FROM pos_order_items WHERE order_id = ? AND status <> 'cancelled' FOR UPDATE");
            $states->execute([$orderId]);
            $states = $states->fetchAll(PDO::FETCH_COLUMN);
            $overall = match (true) {
                !$states => 'cancelled',
                !array_diff($states, ['delivered']) => 'delivered',
                !array_diff($states, ['ready', 'delivered']) => 'ready',
                !array_diff($states, ['pending']) => 'pending',
                default => 'preparing',
            };
            $pdo->prepare("UPDATE pos_orders SET status = ?, was_delivered = was_delivered OR ? WHERE id = ?")
                ->execute([$overall, (int) ($overall === 'delivered'), $orderId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
