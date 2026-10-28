<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

session_start_secure();

unset(
    $_SESSION['pos_admin_id'],
    $_SESSION['pos_admin_user'],
    $_SESSION['pos_admin_role'],
    $_SESSION['pos_admin_station']
);

header('Location: ' . POS_URL . '/login.php');
exit;
