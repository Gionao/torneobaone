<?php
// photo/api/zip_status.php — Stato e preparazione di un archivio ZIP
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_media_api_login();

media_api_esegui(static function (): void {
    if (!media_is_editor()) {
        media_json(['ok' => false, 'errore' => 'Il tuo ruolo non permette di scaricare archivi ZIP.'], 403);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        throw new RuntimeException('Metodo non consentito.');
    }
    $uuid = trim((string) ($_GET['uuid'] ?? ''));
    if (!media_uuid_valido($uuid)) {
        throw new RuntimeException('Identificativo archivio non valido.');
    }
    $job = media_zip_job_per_uuid($uuid);
    if (!$job) {
        throw new RuntimeException('Archivio non trovato.');
    }
    if ((int) $job['id_admin'] !== media_admin_id() && !media_is_admin()) {
        media_json(['ok' => false, 'errore' => 'Archivio non appartenente a questo utente.'], 403);
    }

    if ($job['stato'] === 'in_attesa') {
        @set_time_limit(0);
        media_chiudi_sessione();
        try {
            media_zip_processa_job($job);
        } catch (Throwable $e) {
            // Lo stato dell'errore è già stato registrato dal processore.
        }
        $job = media_zip_job_per_uuid($uuid);
    }

    if (!$job) {
        throw new RuntimeException('Archivio non trovato.');
    }

    $parti = [];
    if ($job['stato'] === 'completato' && !empty($job['parti'])) {
        $lette = json_decode((string) $job['parti'], true);
        if (is_array($lette)) {
            $indice = 0;
            foreach ($lette as $parte) {
                if (!is_array($parte) || empty($parte['percorso'])) {
                    continue;
                }
                $indice++;
                $parti[] = [
                    'indice' => $indice,
                    'nome' => (string) ($parte['nome'] ?? ('archivio-parte-' . $indice . '.zip')),
                    'dimensione' => (int) ($parte['dimensione'] ?? 0),
                    'url' => MEDIA_API_URL . '/zip_download.php?uuid=' . rawurlencode($uuid) . '&parte=' . $indice,
                ];
            }
        }
    }

    media_json([
        'ok' => true,
        'stato' => (string) $job['stato'],
        'numero_file' => (int) ($job['numero_file'] ?? 0),
        'dimensione' => (int) ($job['dimensione'] ?? 0),
        'errore' => $job['stato'] === 'errore' ? (string) ($job['errore'] ?? 'Errore nella creazione dell\'archivio.') : null,
        'parti' => $parti,
    ]);
});
