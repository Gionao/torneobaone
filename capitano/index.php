<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_capitano.php';

capitano_session_start_secure();
$destination = is_capitano_logged() ? 'dashboard.php' : 'login.php';

header('Location: ' . SITE_URL . '/capitano/' . $destination);
exit;
