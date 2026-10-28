<?php
// photo/admin/logout.php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (media_is_logged()) {
    media_log('logout', 'admin', media_admin_id() ?: null);
}
admin_logout();

header('Location: ' . ARCHIVIO_URL . '/login.php');
exit;
