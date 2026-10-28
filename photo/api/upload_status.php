<?php
// photo/api/upload_status.php — Stato dei caricamenti dell'utente
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/media_upload.php';

require_media_api_login();

media_api_esegui(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        throw new RuntimeException('Metodo non consentito.');
    }
    $uuid = trim((string) ($_GET['uuid'] ?? ''));
    $adminId = media_admin_id();

    if ($uuid !== '') {
        if (!media_uuid_valido($uuid)) {
            throw new RuntimeException('Identificativo del caricamento non valido.');
        }
        $upload = media_upload_per_uuid($uuid);
        if (!$upload || (int) $upload['id_admin'] !== $adminId) {
            throw new RuntimeException('Caricamento non trovato.');
        }
        media_json(['ok' => true, 'upload' => media_upload_pubblico($upload)]);
    }

    $attivi = [];
    foreach (media_upload_attivi($adminId, 30) as $u) {
        $attivi[] = media_upload_pubblico($u) + [
            'nome_album'    => (string) ($u['nome_album'] ?? ''),
            'nome_giornata' => (string) ($u['nome_giornata'] ?? ''),
            'nome_edizione' => (string) ($u['nome_edizione'] ?? ''),
            'anno_edizione' => (int) ($u['anno_edizione'] ?? 0),
        ];
    }
    media_json(['ok' => true, 'uploads' => $attivi]);
});
