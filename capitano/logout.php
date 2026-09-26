<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_capitano.php';

capitano_logout();
header('Location: ' . SITE_URL . '/capitano/login.php');
exit;
