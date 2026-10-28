<?php
// photo/tools/maintenance.php — Pulizie periodiche da riga di comando.
// Eseguire solo da CLI: non è un endpoint web.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/media_config.php';
require_once dirname(__DIR__) . '/includes/media_maintenance.php';

try {
    $esiti = media_pulizie_periodiche();
    echo 'Caricamenti scaduti rimossi: ' . $esiti['upload'] . PHP_EOL;
    echo 'File rimossi dal cestino: ' . $esiti['cestino'] . PHP_EOL;
    echo 'ZIP scaduti rimossi: ' . $esiti['zip'] . PHP_EOL;
    echo 'ZIP bloccati ripristinati: ' . $esiti['zip_bloccati'] . PHP_EOL;
    echo 'File in quarantena rimossi: ' . $esiti['quarantena'] . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Errore: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
