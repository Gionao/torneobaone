<?php
// photo/admin/_bootstrap.php
// Caricamento comune delle pagine dell'archivio.
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/media_config.php';
require_once dirname(__DIR__) . '/includes/media_auth.php';
require_once dirname(__DIR__) . '/includes/media_repository.php';
require_once dirname(__DIR__) . '/includes/media_storage.php';
require_once dirname(__DIR__) . '/includes/media_validate.php';
require_once dirname(__DIR__) . '/includes/media_zip.php';

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Robots-Tag: noindex, nofollow');
}

/**
 * Redirige solo verso un percorso interno dell'archivio.
 */
function media_redirect_interno(string $percorso = '/index.php'): never
{
    if (!str_starts_with($percorso, '/')) {
        $percorso = '/index.php';
    }
    header('Location: ' . ARCHIVIO_URL . $percorso);
    exit;
}

/**
 * Legge un parametro intero dalla query string.
 */
function media_get_int(string $chiave, int $default = 0): int
{
    $valore = filter_input(INPUT_GET, $chiave, FILTER_VALIDATE_INT);
    return $valore === false || $valore === null ? $default : (int) $valore;
}

function media_post_int(string $chiave, int $default = 0): int
{
    $valore = filter_input(INPUT_POST, $chiave, FILTER_VALIDATE_INT);
    return $valore === false || $valore === null ? $default : (int) $valore;
}

/** Costruisce un URL dell'archivio con parametri validati. */
function media_url(string $pagina, array $parametri = []): string
{
    $puliti = [];
    foreach ($parametri as $chiave => $valore) {
        if ($valore === null || $valore === '' || $valore === 0) {
            continue;
        }
        $puliti[$chiave] = $valore;
    }
    $url = ARCHIVIO_URL . '/' . ltrim($pagina, '/');
    if ($puliti !== []) {
        $url .= '?' . http_build_query($puliti);
    }
    return $url;
}
