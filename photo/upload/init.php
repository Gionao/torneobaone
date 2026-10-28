<?php
// photo/upload/init.php — Avvio o ripresa di un caricamento a blocchi
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/media_upload.php';

require_media_api_login();
media_richiedi_post();
media_richiedi_csrf();

media_api_esegui(static function (): void {
    $idAlbum = (int) ($_POST['album_id'] ?? 0);
    $nome = (string) ($_POST['nome'] ?? '');
    $dimensione = (int) ($_POST['dimensione'] ?? 0);

    $album = media_album($idAlbum);
    if (!$album) {
        throw new RuntimeException('Album di destinazione non trovato.');
    }
    if (!media_puo_caricare_album($album)) {
        throw new RuntimeException('Non sei autorizzato a caricare in questa raccolta.');
    }

    media_chiudi_sessione();
    $risultato = media_upload_inizia(media_admin_id(), $idAlbum, $nome, $dimensione);
    $upload = is_array($risultato['upload'] ?? null) ? $risultato['upload'] : [];

    media_json([
        'ok' => true,
        'upload' => media_upload_pubblico($upload),
        'chunk_size' => (int) $risultato['chunk_size'],
        'numero_chunk' => (int) $risultato['numero_chunk'],
        'prossimo_blocco' => (int) $risultato['prossimo_blocco'],
    ]);
});
