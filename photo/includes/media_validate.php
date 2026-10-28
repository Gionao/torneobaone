<?php
// includes/media_validate.php
// ============================================================
//  Validazione di nomi file, estensioni e tipi reali.
//  Il MIME dichiarato dal browser non è mai considerato
//  attendibile: si usa sempre finfo sul file su disco.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/media_config.php';
require_once __DIR__ . '/media_storage.php';

/** Normalizza il nome originale senza perdere l'estensione. */
function media_nome_originale(string $nome): string
{
    $nome = str_replace(["\\", "\0"], '/', $nome);
    $nome = basename($nome);
    $nome = preg_replace('/[\x00-\x1F\x7F]/u', '', $nome) ?? '';
    $nome = trim($nome);
    $nome = preg_replace('/\s+/u', ' ', $nome) ?? '';

    if ($nome === '' || $nome === '.' || $nome === '..') {
        return '';
    }

    if (mb_strlen($nome, 'UTF-8') > 200) {
        $ext  = pathinfo($nome, PATHINFO_EXTENSION);
        $base = pathinfo($nome, PATHINFO_FILENAME);
        $base = mb_substr($base, 0, 200 - mb_strlen($ext, 'UTF-8') - 1, 'UTF-8');
        $nome = $base . ($ext !== '' ? '.' . $ext : '');
    }

    return $nome;
}

function media_estensione(string $nome): string
{
    return strtolower((string) pathinfo($nome, PATHINFO_EXTENSION));
}

/**
 * @return array{tipo:string, mime:list<string>}|null
 */
function media_formato(string $estensione): ?array
{
    $formati = media_formati();
    return $formati[strtolower($estensione)] ?? null;
}

function media_mime_reale(string $path): string
{
    if (!is_file($path)) {
        throw new RuntimeException('File non trovato.');
    }

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($path);
    }
    if ($mime === '' && function_exists('mime_content_type')) {
        $mime = (string) mime_content_type($path);
    }

    return strtolower(trim(explode(';', $mime)[0]));
}

/**
 * Valida un file già presente su disco.
 *
 * @return array{tipo:string, mime:string, larghezza:?int, altezza:?int}
 * @throws RuntimeException con messaggio utente in italiano
 */
function media_valida_file(string $path, string $nomeOriginale): array
{
    $estensione = media_estensione($nomeOriginale);
    $formato    = media_formato($estensione);
    if ($formato === null) {
        throw new RuntimeException('Formato non supportato. Ammessi: JPG, JPEG, PNG, HEIC, MP4, MOV.');
    }

    $mime = media_mime_reale($path);
    if (!in_array($mime, $formato['mime'], true)) {
        throw new RuntimeException('Il contenuto del file non corrisponde al formato dichiarato.');
    }

    $larghezza = null;
    $altezza   = null;

    if ($formato['tipo'] === 'foto' && in_array($estensione, ['jpg', 'jpeg', 'png'], true)) {
        $info = @getimagesize($path);
        if ($info === false) {
            throw new RuntimeException('Immagine non valida o danneggiata.');
        }
        $atteso = $estensione === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
        if (($info[2] ?? 0) !== $atteso) {
            throw new RuntimeException('Il tipo reale dell\'immagine non corrisponde all\'estensione.');
        }
        $larghezza = (int) $info[0];
        $altezza   = (int) $info[1];
        if ($larghezza < 1 || $altezza < 1) {
            throw new RuntimeException('Dimensioni immagine non valide.');
        }
    }

    return [
        'tipo'      => $formato['tipo'],
        'mime'      => $mime,
        'larghezza' => $larghezza,
        'altezza'   => $altezza,
    ];
}

/**
 * Verifica che un blocco abbia la dimensione attesa.
 */
function media_valida_blocco(int $indice, int $numeroChunk, int $chunkSize, int $dimensioneTotale, int $dimensioneBlocco): bool
{
    if ($indice < 0 || $indice >= $numeroChunk || $dimensioneBlocco <= 0) {
        return false;
    }
    $offset      = $indice * $chunkSize;
    $attesa      = min($chunkSize, $dimensioneTotale - $offset);
    return $attesa > 0 && $dimensioneBlocco === $attesa;
}

/** Header Content-Disposition sicuro per nomi Unicode. */
function media_content_disposition(string $nome): string
{
    $nome = media_nome_originale($nome) ?: 'file';
    $ascii = preg_replace('/[^\x20-\x7E]/', '_', $nome) ?? 'file';
    $ascii = str_replace(['"', '\\'], '_', $ascii);

    return 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nome);
}
