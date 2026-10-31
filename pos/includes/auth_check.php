<?php
// pos/includes/auth_check.php — Guard sessione POS
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
session_start_secure();

if (!isset($_SESSION['pos_admin_id'])) {
    if (defined('POS_API') && POS_API) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['success' => false, 'data' => null, 'error' => 'Non autenticato'],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    require_once dirname(__DIR__, 2) . '/includes/config.php';
    header('Location: ' . POS_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/db.php';
$stmt = $pdo->prepare('SELECT id, username, role, station_name FROM admin WHERE id = ? LIMIT 1');
$stmt->execute([(int) $_SESSION['pos_admin_id']]);
$account = $stmt->fetch();

if (!$account || !in_array((string) $account['role'], ['admin', 'operatore', 'bar', 'cucina'], true)) {
    unset($_SESSION['pos_admin_id'], $_SESSION['pos_admin_user'], $_SESSION['pos_admin_role'], $_SESSION['pos_admin_station']);
    if (defined('POS_API') && POS_API) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['success' => false, 'data' => null, 'error' => 'Sessione non valida'],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }
    require_once dirname(__DIR__, 2) . '/includes/config.php';
    header('Location: ' . POS_URL . '/login.php');
    exit;
}

unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_role'], $_SESSION['admin_token']);
$_SESSION['pos_admin_user'] = (string) $account['username'];
$_SESSION['pos_admin_role'] = (string) $account['role'];
$_SESSION['pos_admin_station'] = $account['station_name'];
