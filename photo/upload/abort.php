<?php
// photo/upload/abort.php — Annullamento di un caricamento
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/media_upload.php';

require_media_api_login();
media_richiedi_post();
media_richiedi_csrf();

media_api_esegui(static function (): void {
    $uuid = trim((string) ($_POST['upload_id'] ?? ''));
    if (!media_uuid_valido($uuid)) {
        throw new RuntimeException('Identificativo del caricamento non valido.');
    }
    $upload = media_upload_per_uuid($uuid);
    if (!$upload) {
        throw new RuntimeException('Caricamento non trovato.');
    }
    if ((int) $upload['id_admin'] !== media_admin_id() && !media_is_admin()) {
        throw new RuntimeException('Caricamento non appartenente a questo utente.');
    }
    if ($upload['stato'] !== 'in_corso') {
        throw new RuntimeException('Il caricamento non è più attivo.');
    }

    media_chiudi_sessione();
    media_upload_annulla_servizio($upload, media_admin_id());
    media_json(['ok' => true]);
});
