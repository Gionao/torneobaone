<?php
// pos/includes/db.php — Connessione PDO al database torneobaone (stesso DB del sito)
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/db.php';

if (!defined('POS_URL')) {
    define('POS_URL', SITE_URL . '/pos');
}

$pdo = db();

/**
 * Risposta JSON standard per le API POS.
 */
function pos_json(bool $success, mixed $data = null, ?string $error = null): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['success' => $success, 'data' => $data, 'error' => $error],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/**
 * Tipo coupon di un prodotto (per abbinamento con pos_coupons.product_type_restriction).
 */
function pos_product_coupon_kind(string $categoryName, string $department, ?string $productKind): string
{
    if ($productKind !== null && $productKind !== '') {
        return $productKind;
    }
    $n = strtolower($categoryName);
    if (preg_match('/bev|drink|bibit|acqua|birra|caff|cola/', $n)) {
        return 'drink';
    }
    if (preg_match('/snack|patat|chip|dolc|dolci/', $n)) {
        return 'snack';
    }
    if (preg_match('/panin|sandwich|piad|burger|hot/', $n)) {
        return 'sandwich';
    }
    return $department === 'bar' ? 'drink' : 'sandwich';
}
