<?php
// photo/api/_bootstrap.php
// Bootstrap comune degli endpoint JSON dell'archivio.
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/media_config.php';
require_once dirname(__DIR__) . '/includes/media_auth.php';
require_once dirname(__DIR__) . '/includes/media_repository.php';
require_once dirname(__DIR__) . '/includes/media_storage.php';
require_once dirname(__DIR__) . '/includes/media_validate.php';
require_once dirname(__DIR__) . '/includes/media_zip.php';

if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer');
}

/**
 * Segnala una richiesta troppo grande quando PHP ha già scartato POST.
 */
function media_api_controlla_limite_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $lunghezza = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $limite = media_php_bytes(ini_get('post_max_size'));
    if ($limite > 0 && $lunghezza > $limite && $_POST === [] && $_FILES === []) {
        media_json([
            'ok' => false,
            'errore' => 'La richiesta supera il limite del server. Riduci la dimensione del blocco o contatta l\'amministratore.',
        ], 413);
    }
}

/**
 * Esegue un endpoint con gestione uniforme degli errori.
 *
 * @param callable():void $azione
 */
function media_api_esegui(callable $azione): never
{
    try {
        media_api_controlla_limite_post();
        $azione();
        media_json(['ok' => false, 'errore' => 'Risposta non prodotta.'], 500);
    } catch (RuntimeException $e) {
        media_json(['ok' => false, 'errore' => $e->getMessage()], 400);
    } catch (Throwable $e) {
        error_log('Archivio media: ' . $e->getMessage());
        media_json([
            'ok' => false,
            'errore' => 'Errore interno del server. Riprova tra poco o contatta l\'amministratore.',
        ], 500);
    }
}

/** Richiede un ruolo editor o amministratore. */
function media_api_richiedi_editor(): void
{
    require_media_api_login();
    if (!media_is_editor()) {
        media_json(['ok' => false, 'errore' => 'Il tuo ruolo non permette questa operazione.'], 403);
    }
}

/** Richiede un ruolo amministratore. */
function media_api_richiedi_admin(): void
{
    require_media_api_login();
    if (!media_is_admin()) {
        media_json(['ok' => false, 'errore' => 'Operazione riservata agli amministratori.'], 403);
    }
}

/** Legge un body JSON, con limite di dimensione. */
function media_api_json_input(int $maxByte = 262144): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    if (strlen($raw) > $maxByte) {
        throw new RuntimeException('Richiesta troppo grande.');
    }
    $dati = json_decode($raw, true);
    if (!is_array($dati)) {
        throw new RuntimeException('Dati della richiesta non validi.');
    }
    return $dati;
}
