<?php
// photo/admin/album/delete.php — Eliminazione di un album vuoto
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_media_editor();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_verify()) {
    media_flash('error', 'Richiesta non valida. Ricarica la pagina e riprova.');
    media_redirect_interno('/edizioni/index.php');
}

$id = media_post_int('id');
$album = media_album($id);
if (!$album) {
    media_flash('error', 'Album non trovato.');
    media_redirect_interno('/edizioni/index.php');
}
if (!media_puo_vedere_edizione((int) $album['id_edizione'])) {
    http_response_code(403);
    require dirname(__DIR__) . '/_403.php';
    exit;
}

try {
    media_album_elimina($id);
    media_log('album_eliminato', 'media_album', $id, ['nome' => $album['nome']]);
    media_flash('success', 'Album eliminato.');
} catch (Throwable $e) {
    media_flash('error', $e->getMessage());
}

media_redirect_interno('/album/index.php?giornata=' . (int) $album['id_giornata']);
