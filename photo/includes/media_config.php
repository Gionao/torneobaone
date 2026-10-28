<?php
// includes/media_config.php
// ============================================================
//  Configurazione dell'archivio multimediale.
//  I valori possono essere sovrascritti dalla tabella
//  `impostazioni` (chiavi media_*) o da variabili d'ambiente.
// ============================================================

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';

// Lo storage predefinito è fuori dalla cartella applicativa, in `storage/media`.
// La radice del progetto è protetta dalle regole .htaccess e non è una
// cartella pubblica dedicata ai media.
if (!defined('MEDIA_STORAGE_DEFAULT')) {
    define('MEDIA_STORAGE_DEFAULT', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'media');
}

/**
 * Formati ammessi: estensione => [tipo, MIME accettati].
 * Il MIME reale viene sempre verificato lato server con finfo.
 *
 * @return array<string, array{tipo:string, mime:list<string>}>
 */
function media_formati(): array
{
    return [
        'jpg'  => ['tipo' => 'foto',  'mime' => ['image/jpeg']],
        'jpeg' => ['tipo' => 'foto',  'mime' => ['image/jpeg']],
        'png'  => ['tipo' => 'foto',  'mime' => ['image/png']],
        'heic' => ['tipo' => 'foto',  'mime' => ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence']],
        'mp4'  => ['tipo' => 'video', 'mime' => ['video/mp4', 'application/mp4']],
        'mov'  => ['tipo' => 'video', 'mime' => ['video/quicktime']],
    ];
}

/**
 * Valori predefiniti delle impostazioni media.
 *
 * @return array<string, string>
 */
function media_impostazioni_default(): array
{
    return [
        'media_storage_path'     => '',
        'media_max_upload_mb'    => '1024',
        'media_chunk_mb'         => '4',
        'media_thumb_max_px'     => '480',
        'media_retention_giorni' => '30',
        'media_zip_max_file'     => '2000',
        'media_zip_max_mb'       => '5000',
        'media_zip_parte_mb'     => '1500',
        'media_zip_scadenza_ore' => '24',
    ];
}

/**
 * Legge un'impostazione media: DB -> variabile d'ambiente -> default.
 * Il risultato viene memorizzato per la singola richiesta.
 */
function media_setting(string $chiave): string
{
    static $cache = [];

    if (array_key_exists($chiave, $cache)) {
        return $cache[$chiave];
    }

    $defaults = media_impostazioni_default();
    $valore   = $defaults[$chiave] ?? '';

    $env = getenv(strtoupper($chiave));
    if ($env !== false && trim($env) !== '') {
        $valore = trim($env);
    }

    try {
        $stmt = db()->prepare('SELECT valore FROM impostazioni WHERE chiave = ? LIMIT 1');
        $stmt->execute([$chiave]);
        $dbValore = $stmt->fetchColumn();
        if ($dbValore !== false && trim((string) $dbValore) !== '') {
            $valore = trim((string) $dbValore);
        }
    } catch (Throwable $e) {
        // Impostazioni non ancora migrate: si usano i default.
    }

    return $cache[$chiave] = $valore;
}

function media_setting_int(string $chiave, int $min, int $max): int
{
    $valore = (int) media_setting($chiave);
    return max($min, min($max, $valore));
}

function media_max_upload_bytes(): int
{
    return media_setting_int('media_max_upload_mb', 1, 10240) * 1024 * 1024;
}

/**
 * Converte una dimensione PHP (es. "8M") in byte.
 */
function media_php_bytes(string|int|float|null $valore): int
{
    if ($valore === null || $valore === '' || $valore === false) {
        return 0;
    }
    if (is_numeric($valore)) {
        return max(0, (int) $valore);
    }

    $testo = trim((string) $valore);
    if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([KMGTP]?)/i', $testo, $m)) {
        return 0;
    }
    $numero = (float) $m[1];
    $unita  = strtoupper($m[2]);
    $fattore = match ($unita) {
        'K' => 1024,
        'M' => 1024 ** 2,
        'G' => 1024 ** 3,
        'T' => 1024 ** 4,
        'P' => 1024 ** 5,
        default => 1,
    };
    return (int) round($numero * $fattore);
}

/**
 * Restituisce i limiti PHP rilevanti per l'archivio.
 *
 * @return array<string, int|string|false>
 */
function media_limiti_php(): array
{
    $upload = media_php_bytes(ini_get('upload_max_filesize'));
    $post   = media_php_bytes(ini_get('post_max_size'));
    $memory = media_php_bytes(ini_get('memory_limit'));
    $tempo  = (int) ini_get('max_execution_time');

    $limiteBlocco = media_setting_int('media_chunk_mb', 1, 32) * 1024 * 1024;
    $caps = [];
    if ($upload > 0) {
        $caps[] = (int) floor($upload * 0.8);
    }
    if ($post > 0) {
        $caps[] = (int) floor($post * 0.8);
    }
    if ($caps !== []) {
        $limiteBlocco = min($limiteBlocco, min($caps));
    }
    // Con upload_max_filesize molto basso il blocco resta comunque utilizzabile.
    $limiteBlocco = max(256 * 1024, $limiteBlocco);

    return [
        'upload_max_filesize' => $upload,
        'post_max_size'       => $post,
        'memory_limit'        => $memory,
        'max_execution_time'  => $tempo,
        'max_file_uploads'    => (int) ini_get('max_file_uploads'),
        'max_input_time'      => (int) ini_get('max_input_time'),
        'chunk_size'          => $limiteBlocco,
        'zip'                 => class_exists('ZipArchive'),
        'gd'                  => function_exists('imagecreatefromjpeg'),
        'fileinfo'            => class_exists('finfo'),
        'exif'                => function_exists('exif_read_data'),
    ];
}

function media_chunk_bytes(): int
{
    // Il blocco deve restare sotto upload_max_filesize e post_max_size.
    $limiti = media_limiti_php();
    return (int) $limiti['chunk_size'];
}

function media_thumb_max_px(): int
{
    return media_setting_int('media_thumb_max_px', 96, 1600);
}

function media_retention_days(): int
{
    return media_setting_int('media_retention_giorni', 1, 3650);
}

function media_zip_max_file(): int
{
    return media_setting_int('media_zip_max_file', 1, 20000);
}

function media_zip_max_bytes(): int
{
    return media_setting_int('media_zip_max_mb', 10, 20480) * 1024 * 1024;
}

function media_zip_parte_bytes(): int
{
    return media_setting_int('media_zip_parte_mb', 10, 4096) * 1024 * 1024;
}

function media_zip_scadenza_ore(): int
{
    return media_setting_int('media_zip_scadenza_ore', 1, 168);
}
