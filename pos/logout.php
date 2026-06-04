<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset(
    $_SESSION['pos_admin_id'],
    $_SESSION['pos_admin_user'],
    $_SESSION['pos_admin_role'],
    $_SESSION['pos_admin_station']
);

header('Location: ' . SITE_URL . '/pos/login.php');
exit;
