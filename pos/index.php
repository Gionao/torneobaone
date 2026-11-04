<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

session_start_secure();
$principal = staff_current_user();
if ($principal === null) {
    header('Location: ' . POS_URL . '/login.php');
    exit;
}

$role = $principal['is_super_admin'] ? 'admin' : match (staff_role('pos')) {
    'amministratore' => 'admin',
    'operatore', 'bar', 'cucina' => staff_role('pos'),
    default => '',
};
staff_require_authenticated();

switch ($role) {
    case 'admin':
    case 'operatore':
        header('Location: ' . POS_URL . '/operator_panel.php');
        break;
    case 'bar':
        header('Location: ' . POS_URL . '/bar_display.php');
        break;
    case 'cucina':
        header('Location: ' . POS_URL . '/kitchen_display.php');
        break;
    default:
        header('Location: ' . STAFF_URL . '/index.php');
}

exit;
