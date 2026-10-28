<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

session_start_secure();

if (!isset($_SESSION['pos_admin_id'])) {
    header('Location: ' . POS_URL . '/login.php');
    exit;
}

$role = $_SESSION['pos_admin_role'] ?? 'operatore';

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
        unset($_SESSION['pos_admin_id'], $_SESSION['pos_admin_user'], $_SESSION['pos_admin_role'], $_SESSION['pos_admin_station']);
        session_regenerate_id(true);
        header('Location: ' . POS_URL . '/login.php');
}

exit;
