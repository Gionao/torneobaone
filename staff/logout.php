<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
admin_logout();
header('Location: ' . STAFF_URL . '/login.php');
exit;
