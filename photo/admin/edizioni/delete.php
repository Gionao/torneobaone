<?php
// photo/admin/edizioni/delete.php — Eliminazione edizione vuota
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_media_editor();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_verify()) {
    media_flash('error', 'Richiesta non valida. Ricarica la pagina e riprova.');
    media_redirect_interno('/edizioni/index.php');
}

$id = media_post_int('id');
$edizione = media_edizione($id);
if (!$edizione) {
    media_flash('error', 'Edizione non trovata.');
    media_redirect_interno('/edizioni/index.php');
}

try {
    media_edizione_elimina($id);
    media_log('edizione_eliminata', 'media_edizioni', $id, ['nome' => $edizione['nome'], 'anno' => $edizione['anno']]);
    media_flash('success', 'Edizione eliminata.');
} catch (Throwable $e) {
    media_flash('error', $e->getMessage());
}

media_redirect_interno('/edizioni/index.php');
