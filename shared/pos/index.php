<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['pos_admin_id'])) {
    header('Location: ' . SITE_URL . '/pos/login.php');
    exit;
}

$role = $_SESSION['pos_admin_role'] ?? 'operatore';

switch ($role) {
    case 'admin':
    case 'operatore':
        header('Location: ' . SITE_URL . '/pos/operator_panel.php');
        break;
    case 'bar':
        header('Location: ' . SITE_URL . '/pos/bar_display.php');
        break;
    case 'cucina':
        header('Location: ' . SITE_URL . '/pos/kitchen_display.php');
        break;
    default:
        header('Location: ' . SITE_URL . '/pos/login.php');
}

exit;
