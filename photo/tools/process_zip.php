<?php
// photo/tools/process_zip.php — Elaborazione dei job ZIP da riga di comando.
// Eseguire solo da CLI: non è un endpoint web.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/media_config.php';
require_once dirname(__DIR__) . '/includes/media_storage.php';
require_once dirname(__DIR__) . '/includes/media_zip.php';

$max = 3;
foreach (array_slice($argv ?? [], 1) as $argomento) {
    if (str_starts_with($argomento, '--max=')) {
        $max = max(1, min(20, (int) substr($argomento, 6)));
    }
}

try {
    $esiti = media_zip_processa_in_attesa($max);
    foreach ($esiti as $esito) {
        echo 'Job ' . $esito['id'] . ': ' . ($esito['ok'] ? 'completato' : 'errore - ' . ($esito['errore'] ?? '')) . PHP_EOL;
    }
    if ($esiti === []) {
        echo 'Nessun job ZIP in attesa.' . PHP_EOL;
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Errore: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
