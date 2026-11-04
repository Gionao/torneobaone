<?php
// pos/includes/auth_check.php — Guard sessione POS
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
session_start_secure();

function pos_current_role(): string
{
    $principal = staff_current_user();
    if ($principal === null) {
        return '';
    }
    if ($principal['is_super_admin']) {
        return 'admin';
    }
    return match (staff_role('pos')) {
        'amministratore' => 'admin',
        'operatore', 'bar', 'cucina' => staff_role('pos'),
        default => '',
    };
}

$posPrincipal = staff_current_user();
if ($posPrincipal === null) {
    if (defined('POS_API') && POS_API) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['success' => false, 'data' => null, 'error' => 'Non autenticato'],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    header('Location: ' . POS_URL . '/login.php');
    exit;
}

if (defined('POS_API') && POS_API && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !staff_csrf_verify()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'data' => null, 'error' => 'Token di sicurezza non valido'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($posPrincipal['must_change_password']) {
    if (defined('POS_API') && POS_API) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'data' => null, 'error' => 'È necessario cambiare la password'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . STAFF_URL . '/change-password.php');
    exit;
}
if (pos_current_role() === '') {
    if (defined('POS_API') && POS_API) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'data' => null, 'error' => 'Accesso non autorizzato'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    staff_forbidden();
}
