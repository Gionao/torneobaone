<?php
// photo/api/download.php — Download e anteprima autorizzati
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_media_api_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$inline = isset($_GET['inline']) && (string) $_GET['inline'] !== '0';
$id = $id === false || $id === null ? 0 : (int) $id;

if ($id < 1) {
    http_response_code(404);
    exit('File non trovato.');
}

$file = media_file($id);
if (!$file || !empty($file['eliminato_il'])) {
    http_response_code(404);
    exit('File non trovato.');
}
if (!media_puo_vedere_file($file) || !media_puo_scaricare($file)) {
    http_response_code(403);
    exit('Accesso non consentito.');
}

try {
    $assoluto = media_storage_path((string) $file['percorso']);
} catch (Throwable $e) {
    http_response_code(404);
    exit('File non disponibile.');
}
if (!is_file($assoluto)) {
    http_response_code(404);
    exit('File non disponibile.');
}

$estensione = strtolower((string) $file['estensione']);
$formato = media_formato($estensione);
if ($formato === null) {
    http_response_code(415);
    exit('Formato non supportato.');
}
$mime = $formato['mime'][0];
$anteprimabile = in_array($estensione, ['jpg', 'jpeg', 'png', 'mp4', 'mov'], true);
$inline = $inline && $anteprimabile;

$dimensione = (int) filesize($assoluto);
$sha256 = (string) ($file['sha256'] ?? '');
$etag = '"' . substr(hash('sha256', $sha256 . '-' . $dimensione), 0, 32) . '"';

@set_time_limit(0);
media_chiudi_sessione();

header('Accept-Ranges: bytes');
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: ' . ($inline ? 'private, max-age=300' : 'private, no-store, max-age=0'));
header('ETag: ' . $etag);

$nome = (string) $file['nome_originale'];
if ($inline) {
    $nomeAscii = preg_replace('/[^\x20-\x7E]/', '_', $nome) ?: 'file';
    $nomeAscii = str_replace(['"', '\\'], '_', $nomeAscii);
    header('Content-Disposition: inline; filename="' . $nomeAscii . '"; filename*=UTF-8\'\'' . rawurlencode($nome));
} else {
    header('Content-Disposition: ' . media_content_disposition($nome));
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($metodo, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}

$ifNoneMatch = (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
if ($ifNoneMatch !== '' && !isset($_SERVER['HTTP_RANGE']) && trim($ifNoneMatch) === $etag) {
    http_response_code(304);
    exit;
}

$inizio = 0;
$fine = $dimensione - 1;
$parziale = false;

$range = (string) ($_SERVER['HTTP_RANGE'] ?? '');
if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/i', trim($range), $m)) {
    if ($m[1] === '' && $m[2] === '') {
        http_response_code(416);
        header('Content-Range: bytes */' . $dimensione);
        exit;
    }
    if ($m[1] === '') {
        // Intervallo finale: bytes=-500
        $suffisso = (int) $m[2];
        if ($suffisso <= 0) {
            http_response_code(416);
            header('Content-Range: bytes */' . $dimensione);
            exit;
        }
        $inizio = max(0, $dimensione - $suffisso);
    } else {
        $inizio = (int) $m[1];
        if ($m[2] !== '') {
            $fine = min($dimensione - 1, (int) $m[2]);
        }
    }
    if ($inizio > $fine || $inizio >= $dimensione) {
        http_response_code(416);
        header('Content-Range: bytes */' . $dimensione);
        exit;
    }
    $parziale = true;
    http_response_code(206);
    header('Content-Range: bytes ' . $inizio . '-' . $fine . '/' . $dimensione);
}

$lunghezza = $fine - $inizio + 1;
header('Content-Length: ' . $lunghezza);

if ($metodo === 'HEAD') {
    exit;
}

if (!$inline) {
    media_log('file_scaricato', 'media_file', (int) $file['id'], [
        'uuid' => $file['uuid'],
        'bytes' => $lunghezza,
    ]);
}

$handle = @fopen($assoluto, 'rb');
if ($handle === false) {
    http_response_code(500);
    exit;
}
if ($inizio > 0) {
    fseek($handle, $inizio);
}
$rimasti = $lunghezza;
while ($rimasti > 0 && !feof($handle)) {
    $pezzo = fread($handle, min(262144, $rimasti));
    if ($pezzo === false) {
        break;
    }
    if ($pezzo === '') {
        break;
    }
    echo $pezzo;
    $rimasti -= strlen($pezzo);
    if (function_exists('fastcgi_finish_request')) {
        flush();
    }
}
fclose($handle);
exit;
