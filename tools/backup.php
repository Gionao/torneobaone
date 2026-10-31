<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/backup_common.php';

$lock = null;
$exitCode = 0;
try {
    $lock = backup_acquire_lock();
    $path = backup_create();
    echo 'Backup completato: ' . $path . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Errore backup: ' . $e->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    backup_release_lock($lock);
}
exit($exitCode);
