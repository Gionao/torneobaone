<?php
// photo/api/thumb.php — Miniatura privata autorizzata
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_media_api_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$id = $id === false || $id === null ? 0 : (int) $id;
if ($id < 1) {
    http_response_code(404);
    exit;
}

$file = media_file($id);
if (!$file || !empty($file['eliminato_il']) || empty($file['miniatura'])) {
    http_response_code(404);
    exit;
}
if (!media_puo_vedere_file($file)) {
    http_response_code(403);
    exit;
}

try {
    $assoluto = media_storage_path((string) $file['miniatura']);
} catch (Throwable $e) {
    http_response_code(404);
    exit;
}
if (!is_file($assoluto)) {
    http_response_code(404);
    exit;
}

$mtime = (int) filemtime($assoluto);
$etag = '"' . substr(hash('sha256', $id . '-' . $mtime . '-' . (int) filesize($assoluto)), 0, 24) . '"';
$ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
}

media_chiudi_sessione();
header('Content-Type: image/jpeg');
header('Content-Length: ' . (int) filesize($assoluto));
header('Cache-Control: private, max-age=86400');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
readfile($assoluto);
exit;
