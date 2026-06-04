<?php
// pos/includes/auth_check.php — Guard sessione POS
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    header('Location: ' . SITE_URL . '/pos/login.php');
    exit;
}
