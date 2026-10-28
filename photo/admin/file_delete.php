<?php
// photo/admin/file_delete.php — Spostamento di un file nel cestino
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_media_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_verify()) {
    media_flash('error', 'Richiesta non valida. Ricarica la pagina e riprova.');
    media_redirect_interno('/galleria.php');
}

$id = media_post_int('id');
$file = media_file($id);
if (!$file) {
    media_flash('error', 'File non trovato.');
    media_redirect_interno('/galleria.php');
}
if (!media_puo_vedere_file($file) || !media_puo_eliminare($file)) {
    http_response_code(403);
    require __DIR__ . '/_403.php';
    exit;
}
if (!empty($file['eliminato_il'])) {
    media_flash('error', 'Il file è già nel cestino.');
    media_redirect_interno('/galleria.php?album=' . (int) $file['id_album']);
}

if (media_file_soft_delete($id, media_admin_id())) {
    media_log('file_nel_cestino', 'media_file', $id, ['uuid' => $file['uuid']]);
    media_flash('success', 'File spostato nel cestino. Un amministratore può ripristinarlo.');
} else {
    media_flash('error', 'Impossibile spostare il file nel cestino.');
}

media_redirect_interno('/galleria.php?album=' . (int) $file['id_album']);
