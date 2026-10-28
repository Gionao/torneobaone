<?php
// photo/upload/complete.php — Finalizzazione atomica del caricamento
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
    if ((int) $upload['id_admin'] !== media_admin_id()) {
        throw new RuntimeException('Caricamento non appartenente a questo utente.');
    }

    // La verifica SHA-256 e la creazione della miniatura possono richiedere
    // più tempo del normale limite PHP su file grandi.
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    media_chiudi_sessione();

    $esito = media_upload_concludi($upload, media_admin_id());
    media_json([
        'ok' => true,
        'file_id' => (int) $esito['file_id'],
        'duplicati' => count($esito['duplicati']),
        'avviso' => $esito['avviso'],
    ]);
});
