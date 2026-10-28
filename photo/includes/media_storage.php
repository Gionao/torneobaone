<?php
// includes/media_storage.php
// ============================================================
//  Gestione sicura dei file su disco.
//  I file NON sono mai serviti direttamente dal web: vengono
//  letti solo tramite gli endpoint PHP autorizzati.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/media_config.php';

/**
 * Restituisce il percorso assoluto della radice dell'archivio.
 * Se non esiste, la crea (solo se $crea è true).
 */
function media_storage_root(bool $crea = true): string
{
    $configurato = trim(media_setting('media_storage_path'));
    $root = $configurato !== '' ? $configurato : MEDIA_STORAGE_DEFAULT;

    // Percorso relativo: interpretato rispetto alla radice del progetto.
    if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $root)) {
        $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $root;
    }

    $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);

    if ($crea && !is_dir($root)) {
        if (!@mkdir($root, 0775, true) && !is_dir($root)) {
            throw new RuntimeException('Impossibile creare la cartella dell\'archivio.');
        }
    }

    return $root;
}

/** Crea la struttura delle cartelle e le protezioni web. */
function media_storage_boot(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $root = media_storage_root(true);
    foreach (['originali', 'miniature', 'upload_tmp', 'zip_tmp', 'quarantena', 'log'] as $dir) {
        $path = $root . DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Impossibile creare la struttura dell\'archivio.');
        }
    }

    // Se l'archivio è dentro la document root, nega l'accesso diretto.
    // La radice del progetto è la document root nelle installazioni Laragon.
    $docRoot = realpath(dirname(__DIR__, 2));
    $realRoot = realpath($root);
    if ($docRoot !== false && $realRoot !== false && str_starts_with($realRoot, $docRoot . DIRECTORY_SEPARATOR)) {
        media_storage_scrivi_protezioni($root);
    }

    if (!is_writable($root)) {
        throw new RuntimeException('La cartella dell\'archivio non è scrivibile.');
    }

    $done = true;
}

/**
 * Verifica la configurazione dello storage senza sollevare eccezioni.
 *
 * @return array{ok:bool, messaggio:string}
 */
function media_storage_check(): array
{
    try {
        media_storage_boot();
        $probe = media_storage_root() . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . '.probe_' . bin2hex(random_bytes(4));
        if (@file_put_contents($probe, 'ok') === false) {
            return ['ok' => false, 'messaggio' => 'Scrittura non consentita nella cartella dell\'archivio.'];
        }
        @unlink($probe);
        return ['ok' => true, 'messaggio' => 'Archivio scrivibile.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'messaggio' => $e->getMessage()];
    }
}

/** Scrive .htaccess e index.html di protezione nella cartella indicata. */
function media_storage_scrivi_protezioni(string $cartella): void
{
    if (!is_dir($cartella)) {
        return;
    }
    $htaccess = $cartella . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, implode("\n", [
            '# Archivio privato: nessun accesso diretto',
            'Options -Indexes',
            'Require all denied',
            '<IfModule !mod_authz_core.c>',
            '  Order allow,deny',
            '  Deny from all',
            '</IfModule>',
            '',
        ]));
    }
    $index = $cartella . DIRECTORY_SEPARATOR . 'index.html';
    if (!is_file($index)) {
        @file_put_contents($index, '');
    }
}

/** Indica se lo storage si trova dentro la document root del progetto. */
function media_storage_e_pubblico(string $root): bool
{
    $docRoot = realpath(dirname(__DIR__, 2));
    $realRoot = realpath($root);
    return $docRoot !== false
        && $realRoot !== false
        && ($realRoot === $docRoot || str_starts_with($realRoot, $docRoot . DIRECTORY_SEPARATOR));
}

/**
 * Verifica che un percorso di storage sia utilizzabile senza modificare
 * le impostazioni salvate.
 *
 * @return array{ok:bool, percorso:string, messaggio:string}
 */
function media_storage_valida_percorso(string $configurato): array
{
    $configurato = trim($configurato);
    $root = $configurato !== '' ? $configurato : MEDIA_STORAGE_DEFAULT;
    if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $root)) {
        $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $root;
    }
    $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);
    if ($root === '' || str_contains($root, "\0")) {
        return ['ok' => false, 'percorso' => '', 'messaggio' => 'Percorso non valido.'];
    }

    if (!is_dir($root) && !@mkdir($root, 0775, true) && !is_dir($root)) {
        return ['ok' => false, 'percorso' => $root, 'messaggio' => 'Impossibile creare la cartella indicata.'];
    }
    if (!is_writable($root)) {
        return ['ok' => false, 'percorso' => $root, 'messaggio' => 'La cartella indicata non è scrivibile.'];
    }

    $probe = $root . DIRECTORY_SEPARATOR . '.probe_' . bin2hex(random_bytes(4));
    if (@file_put_contents($probe, 'ok') === false) {
        return ['ok' => false, 'percorso' => $root, 'messaggio' => 'Scrittura non consentita nella cartella indicata.'];
    }
    @unlink($probe);

    if (media_storage_e_pubblico($root)) {
        media_storage_scrivi_protezioni($root);
    }

    return ['ok' => true, 'percorso' => $root, 'messaggio' => 'Percorso scrivibile.'];
}

function media_storage_dir(string $sottocartella): string
{
    media_storage_boot();
    $safe = preg_replace('/[^a-z0-9_]/', '', strtolower($sottocartella));
    if ($safe === '') {
        throw new RuntimeException('Cartella archivio non valida.');
    }
    return media_storage_root() . DIRECTORY_SEPARATOR . $safe;
}

/** Percorso assoluto da un percorso relativo salvato nel DB. */
function media_storage_path(string $relativo): string
{
    $relativo = str_replace('\\', '/', trim($relativo));
    if ($relativo === '' || str_contains($relativo, "\0") || str_starts_with($relativo, '/')) {
        throw new RuntimeException('Percorso archivio non valido.');
    }
    foreach (explode('/', $relativo) as $segmento) {
        if ($segmento === '' || $segmento === '.' || $segmento === '..') {
            throw new RuntimeException('Percorso archivio non valido.');
        }
    }
    if (!preg_match('#^[A-Za-z0-9._/-]+$#', $relativo)) {
        throw new RuntimeException('Percorso archivio non valido.');
    }

    return media_storage_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativo);
}

/** Costruisce un percorso relativo originale/YYYY/MM/uuid.ext */
function media_storage_original_relative(string $uuid, string $estensione, string $data = ''): string
{
    $ts = $data !== '' ? strtotime($data) : time();
    if ($ts === false) {
        $ts = time();
    }
    $anno = date('Y', $ts);
    $mese = date('m', $ts);
    $dir  = media_storage_dir('originali') . DIRECTORY_SEPARATOR . $anno . DIRECTORY_SEPARATOR . $mese;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossibile creare la cartella dei file.');
    }
    $ext = strtolower(preg_replace('/[^a-z0-9]/', '', $estensione));
    return 'originali/' . $anno . '/' . $mese . '/' . $uuid . '.' . $ext;
}

function media_storage_thumb_relative(string $uuid, string $data = ''): string
{
    $ts = $data !== '' ? strtotime($data) : time();
    if ($ts === false) {
        $ts = time();
    }
    $anno = date('Y', $ts);
    $mese = date('m', $ts);
    $dir  = media_storage_dir('miniature') . DIRECTORY_SEPARATOR . $anno . DIRECTORY_SEPARATOR . $mese;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossibile creare la cartella delle miniature.');
    }
    return 'miniature/' . $anno . '/' . $mese . '/' . $uuid . '.jpg';
}

function media_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function media_uuid_valido(string $uuid): bool
{
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
}

function media_bytes_leggibili(int|float $bytes): string
{
    $unita = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $valore = (float) $bytes;
    while ($valore >= 1024 && $i < count($unita) - 1) {
        $valore /= 1024;
        $i++;
    }
    return number_format($valore, $i === 0 ? 0 : 1, ',', '.') . ' ' . $unita[$i];
}

/** Elimina un file relativo, se esiste. Non segue percorsi non validi. */
function media_storage_delete(string $relativo): bool
{
    try {
        $path = media_storage_path($relativo);
    } catch (Throwable $e) {
        return false;
    }
    return is_file($path) ? @unlink($path) : false;
}

function media_spazio_libero(): int|false
{
    try {
        return @disk_free_space(media_storage_root(false));
    } catch (Throwable $e) {
        return false;
    }
}
