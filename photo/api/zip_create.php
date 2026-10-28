<?php
// photo/api/zip_create.php — Creazione di un archivio ZIP
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_media_api_login();
media_richiedi_post();
media_richiedi_csrf();

media_api_esegui(static function (): void {
    if (!media_is_editor()) {
        media_json(['ok' => false, 'errore' => 'Il tuo ruolo non permette di creare archivi ZIP.'], 403);
    }
    if (!media_zip_disponibile()) {
        throw new RuntimeException('La creazione di archivi ZIP non è disponibile su questo server.');
    }
    if (media_zip_job_attivo_per_admin(media_admin_id())) {
        throw new RuntimeException('Hai già un archivio in preparazione. Attendi che sia completato.');
    }

    $ambito = (string) ($_POST['ambito'] ?? '');
    $fileIdsRaw = (string) ($_POST['file_ids'] ?? '');
    $fileIds = [];
    if ($fileIdsRaw !== '') {
        $decodificati = json_decode($fileIdsRaw, true);
        if (!is_array($decodificati)) {
            throw new RuntimeException('Selezione dei file non valida.');
        }
        $fileIds = array_values(array_unique(array_filter(array_map('intval', $decodificati), static fn (int $id): bool => $id > 0)));
        if ($fileIds === []) {
            throw new RuntimeException('Nessun file selezionato.');
        }
        if (count($fileIds) > media_zip_max_file()) {
            throw new RuntimeException('Hai selezionato troppi file. Limite per archivio: ' . media_zip_max_file() . '.');
        }
        $ambito = 'selezione';
    }

    if ($ambito === 'selezione') {
        $file = media_file_per_ids($fileIds);
        if ($file === []) {
            throw new RuntimeException('I file selezionati non sono più disponibili.');
        }
        $idAmbito = 0;
    } elseif (in_array($ambito, ['edizione', 'giornata', 'album'], true)) {
        $idAmbito = (int) ($_POST['id'] ?? 0);
        if ($idAmbito < 1) {
            throw new RuntimeException('Raccolta non valida.');
        }
        if ($ambito === 'edizione' && !media_edizione($idAmbito)) {
            throw new RuntimeException('Edizione non trovata.');
        }
        if ($ambito === 'giornata' && !media_giornata($idAmbito)) {
            throw new RuntimeException('Giornata non trovata.');
        }
        if ($ambito === 'album' && !media_album($idAmbito)) {
            throw new RuntimeException('Album non trovato.');
        }
        $file = media_file_per_ambito($ambito, $idAmbito);
        if ($file === []) {
            throw new RuntimeException('Nessun file da includere nell\'archivio.');
        }
    } else {
        throw new RuntimeException('Tipo di archivio non valido.');
    }

    if (count($file) > media_zip_max_file()) {
        throw new RuntimeException('Troppi file per un singolo archivio (limite: ' . media_zip_max_file() . ').');
    }
    $totale = 0;
    foreach ($file as $f) {
        $totale += (int) $f['dimensione'];
    }
    if ($totale > media_zip_max_bytes()) {
        throw new RuntimeException('Archivio troppo grande (limite: ' . media_bytes_leggibili(media_zip_max_bytes()) . ').');
    }

    $uuid = media_uuid();
    media_zip_job_crea([
        'uuid'      => $uuid,
        'id_admin'  => media_admin_id(),
        'ambito'    => $ambito,
        'id_ambito' => $idAmbito,
        'file_ids'  => $ambito === 'selezione' ? $fileIds : null,
    ]);
    media_log('zip_richiesto', 'media_zip_job', null, [
        'uuid'   => $uuid,
        'ambito' => $ambito,
        'id'     => $idAmbito,
        'file'   => count($file),
        'bytes'  => $totale,
    ]);

    media_json([
        'ok' => true,
        'uuid' => $uuid,
        'numero_file' => count($file),
        'dimensione' => $totale,
    ]);
});
