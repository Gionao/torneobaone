<?php
// admin/logout.php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
admin_logout();
header('Location: ' . ADMIN_PATH . '/login.php');
exit;
