<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['pos_admin_id'])) {
    header('Location: ' . SITE_URL . '/pos/operator_panel.php');
} else {
    header('Location: ' . SITE_URL . '/pos/login.php');
}
exit;
