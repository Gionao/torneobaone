<?php
// photo/index.php — Unico ingresso dell'archivio privato.
// L'archivio non ha una pagina pubblica: tutto passa dall'area riservata.
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';

header('Location: ' . ARCHIVIO_URL . '/index.php', true, 302);
header('Cache-Control: no-store');
exit;
