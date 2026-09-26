<?php
// pos/includes/db.php — Connessione PDO al database torneobaone (stesso DB del sito)
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';

if (!defined('POS_URL')) {
    define('POS_URL', SITE_URL . '/pos');
}

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    DB_HOST,
    DB_NAME,
    DB_CHARSET
);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    if (defined('POS_API') && POS_API) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'data' => null, 'error' => 'Database non disponibile'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (DEBUG_MODE) {
        http_response_code(503);
        die('<pre style="background:#1a1a2e;color:#e8c76a;padding:1.5rem">'
            . '<strong>Errore connessione DB POS</strong><br>'
            . htmlspecialchars($e->getMessage()) . '</pre>');
    }
    http_response_code(503);
    die('Servizio POS temporaneamente non disponibile.');
}

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
