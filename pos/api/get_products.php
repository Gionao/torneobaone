<?php
declare(strict_types=1);

define('POS_API', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/role_check.php';
require_role(['admin', 'operatore']);

try {
    $stmt = $pdo->query("
        SELECT p.id, p.name, p.price, p.category_id,
               c.name AS category_name, c.department
        FROM pos_products p
        INNER JOIN pos_categories c ON c.id = p.category_id
        WHERE p.is_active = 1
        ORDER BY c.department, c.name, p.name
    ");
    $products = $stmt->fetchAll();

    $ingStmt = $pdo->prepare("
        SELECT i.id, i.name, i.is_removable, i.is_extra, i.extra_price
        FROM pos_product_ingredients pi
        INNER JOIN pos_ingredients i ON i.id = pi.ingredient_id
        WHERE pi.product_id = ?
        ORDER BY i.name
    ");

    foreach ($products as &$product) {
        $ingStmt->execute([(int) $product['id']]);
        $rows = $ingStmt->fetchAll();
        $product['ingredients'] = array_map(static function (array $row): array {
            return [
                'id'           => (int) $row['id'],
                'name'         => $row['name'],
                'is_removable' => (int) $row['is_removable'],
                'is_extra'     => (int) $row['is_extra'],
                'extra_price'  => (float) $row['extra_price'],
            ];
        }, $rows);
        $product['id']          = (int) $product['id'];
        $product['price']       = (float) $product['price'];
        $product['category_id'] = (int) $product['category_id'];
        $product['product_kind'] = pos_product_coupon_kind(
            $product['category_name'],
            $product['department'],
            null
        );
    }
    unset($product);

    pos_json(true, $products);
} catch (Throwable $e) {
    if (DEBUG_MODE) {
        pos_json(false, null, $e->getMessage());
    }
    pos_json(false, null, 'Errore nel caricamento prodotti.');
}
