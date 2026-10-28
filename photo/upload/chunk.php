<?php
// photo/upload/chunk.php — Ricezione di un blocco
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/media_upload.php';

require_media_api_login();
media_richiedi_post();
media_richiedi_csrf();

media_api_esegui(static function (): void {
    $uuid = trim((string) ($_POST['upload_id'] ?? ''));
    $indice = (int) ($_POST['indice'] ?? -1);
    $sha256 = trim((string) ($_POST['sha256'] ?? ''));

    if (!media_uuid_valido($uuid)) {
        throw new RuntimeException('Identificativo del caricamento non valido.');
    }
    if (!isset($_FILES['chunk']) || !is_array($_FILES['chunk'])) {
        throw new RuntimeException('Blocco non ricevuto.');
    }

    $file = $_FILES['chunk'];
    $erroreUpload = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($erroreUpload !== UPLOAD_ERR_OK) {
        $messaggi = [
            UPLOAD_ERR_INI_SIZE   => 'Il blocco supera il limite upload_max_filesize del server.',
            UPLOAD_ERR_FORM_SIZE  => 'Il blocco supera il limite consentito dal modulo.',
            UPLOAD_ERR_PARTIAL    => 'Il blocco è arrivato incompleto. Riprova.',
            UPLOAD_ERR_NO_FILE    => 'Nessun blocco ricevuto.',
            UPLOAD_ERR_NO_TMP_DIR => 'Cartella temporanea del server non disponibile.',
            UPLOAD_ERR_CANT_WRITE => 'Il server non riesce a scrivere il blocco temporaneo.',
            UPLOAD_ERR_EXTENSION  => 'Caricamento bloccato da un\'estensione del server.',
        ];
        throw new RuntimeException($messaggi[$erroreUpload] ?? 'Errore durante la ricezione del blocco.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Blocco non valido.');
    }

    $upload = media_upload_per_uuid($uuid);
    if (!$upload) {
        throw new RuntimeException('Caricamento non trovato.');
    }
    if ((int) $upload['id_admin'] !== media_admin_id()) {
        throw new RuntimeException('Caricamento non appartenente a questo utente.');
    }

    media_chiudi_sessione();
    $esito = media_upload_scrivi_blocco($upload, media_admin_id(), $indice, $tmp, $sha256 !== '' ? $sha256 : null);

    media_json(['ok' => true] + $esito);
});
