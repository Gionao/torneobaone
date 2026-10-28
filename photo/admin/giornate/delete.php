<?php
// photo/admin/giornate/delete.php — Eliminazione di una giornata vuota
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_media_editor();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_verify()) {
    media_flash('error', 'Richiesta non valida. Ricarica la pagina e riprova.');
    media_redirect_interno('/edizioni/index.php');
}

$id = media_post_int('id');
$giornata = media_giornata($id);
if (!$giornata) {
    media_flash('error', 'Giornata non trovata.');
    media_redirect_interno('/edizioni/index.php');
}
if (!media_puo_vedere_edizione((int) $giornata['id_edizione'])) {
    http_response_code(403);
    require dirname(__DIR__) . '/_403.php';
    exit;
}

try {
    media_giornata_elimina($id);
    media_log('giornata_eliminata', 'media_giornate', $id, ['nome' => $giornata['nome']]);
    media_flash('success', 'Giornata eliminata.');
} catch (Throwable $e) {
    media_flash('error', $e->getMessage());
}

media_redirect_interno('/giornate/index.php?edizione=' . (int) $giornata['id_edizione']);
