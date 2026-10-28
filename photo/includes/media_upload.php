<?php
// includes/media_upload.php
// ============================================================
//  Logica del protocollo di upload a blocchi.
//  Un solo file .part per upload, blocchi in ordine stretto,
//  verifica SHA-256 per blocco (se fornito) e verifica finale
//  del file completo prima dello spostamento atomico.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/media_config.php';
require_once __DIR__ . '/media_storage.php';
require_once __DIR__ . '/media_validate.php';
require_once __DIR__ . '/media_repository.php';
require_once __DIR__ . '/media_thumbnail.php';

/**
 * Dati di un upload sicuri da restituire al browser (senza percorsi).
 */
function media_upload_pubblico(array $upload): array
{
    return [
        'uuid'               => (string) ($upload['uuid'] ?? ''),
        'id_album'           => (int) ($upload['id_album'] ?? 0),
        'nome_originale'     => (string) ($upload['nome_originale'] ?? ''),
        'dimensione_totale'  => (int) ($upload['dimensione_totale'] ?? 0),
        'dimensione_ricevuta' => (int) ($upload['dimensione_ricevuta'] ?? 0),
        'chunk_size'         => (int) ($upload['chunk_size'] ?? 0),
        'numero_chunk'       => (int) ($upload['numero_chunk'] ?? 0),
        'chunk_ricevuti'     => (int) ($upload['chunk_ricevuti'] ?? 0),
        'stato'              => (string) ($upload['stato'] ?? ''),
        'created_at'         => (string) ($upload['created_at'] ?? ''),
        'updated_at'         => (string) ($upload['updated_at'] ?? ''),
    ];
}

/**
 * @return array{upload:array, chunk_size:int, numero_chunk:int, prossimo_blocco:int}
 */
function media_upload_inizia(int $adminId, int $idAlbum, string $nomeOriginale, int $dimensione): array
{
    $album = media_album($idAlbum);
    if (!$album) {
        throw new RuntimeException('Album non trovato.');
    }
    if ($dimensione < 1) {
        throw new RuntimeException('File vuoto o dimensione non valida.');
    }
    if ($dimensione > media_max_upload_bytes()) {
        throw new RuntimeException('File troppo grande. Limite: ' . media_bytes_leggibili(media_max_upload_bytes()) . '.');
    }

    $nome = media_nome_originale($nomeOriginale);
    if ($nome === '') {
        throw new RuntimeException('Nome file non valido.');
    }
    $estensione = media_estensione($nome);
    if (media_formato($estensione) === null) {
        throw new RuntimeException('Formato non supportato. Ammessi: JPG, JPEG, PNG, HEIC, MP4, MOV.');
    }

    // Evita di saturare lo spazio disponibile.
    $libero = media_spazio_libero();
    if ($libero !== false && $libero < $dimensione + 50 * 1024 * 1024) {
        throw new RuntimeException('Spazio su disco insufficiente per completare il caricamento.');
    }

    $attivi = db()->prepare('SELECT COUNT(*) FROM media_upload WHERE id_admin = ? AND stato = \'in_corso\'');
    $attivi->execute([$adminId]);
    if ((int) $attivi->fetchColumn() >= 10) {
        throw new RuntimeException('Troppi caricamenti in corso. Completa o annulla quelli esistenti.');
    }

    media_storage_boot();
    $uuid     = media_uuid();
    $chunk    = media_chunk_bytes();
    $numero   = (int) ceil($dimensione / $chunk);
    $relativo = 'upload_tmp/' . $uuid . '.part';
    $assoluto = media_storage_path($relativo);

    $handle = @fopen($assoluto, 'xb');
    if ($handle === false) {
        throw new RuntimeException('Impossibile preparare il caricamento.');
    }
    fclose($handle);
    @chmod($assoluto, 0664);

    media_upload_crea([
        'uuid'            => $uuid,
        'id_admin'        => $adminId,
        'id_album'        => $idAlbum,
        'nome_originale'  => $nome,
        'dimensione_totale' => $dimensione,
        'chunk_size'      => $chunk,
        'numero_chunk'    => $numero,
        'percorso_tmp'    => $relativo,
    ]);

    media_log('upload_iniziato', 'media_upload', null, [
        'uuid'   => $uuid,
        'album'  => $idAlbum,
        'file'   => $nome,
        'bytes'  => $dimensione,
    ], $adminId);

    return [
        'upload'         => media_upload_per_uuid($uuid) ?? [],
        'chunk_size'     => $chunk,
        'numero_chunk'   => $numero,
        'prossimo_blocco' => 0,
    ];
}

/**
 * Scrive un blocco in coda al file .part.
 *
 * @return array{prossimo_blocco:int, chunk_ricevuti:int, numero_chunk:int, dimensione_ricevuta:int}
 */
function media_upload_scrivi_blocco(array $upload, int $adminId, int $indice, string $tmpFile, ?string $sha256Dichiarato): array
{
    if ((int) $upload['id_admin'] !== $adminId) {
        throw new RuntimeException('Caricamento non appartenente a questo utente.');
    }
    if ($upload['stato'] !== 'in_corso') {
        throw new RuntimeException('Caricamento non più attivo.');
    }

    $numeroChunk = (int) $upload['numero_chunk'];
    $chunkSize   = (int) $upload['chunk_size'];
    $totale      = (int) $upload['dimensione_totale'];
    $ricevuti    = (int) $upload['chunk_ricevuti'];

    if ($indice !== $ricevuti) {
        throw new RuntimeException('Blocco fuori sequenza. Atteso: ' . $ricevuti . '.');
    }
    if ($indice < 0 || $indice >= $numeroChunk) {
        throw new RuntimeException('Indice blocco non valido.');
    }
    if (!is_file($tmpFile)) {
        throw new RuntimeException('Blocco non ricevuto.');
    }

    $dimensioneBlocco = (int) filesize($tmpFile);
    if (!media_valida_blocco($indice, $numeroChunk, $chunkSize, $totale, $dimensioneBlocco)) {
        throw new RuntimeException('Dimensione del blocco non valida.');
    }

    if ($sha256Dichiarato !== null && $sha256Dichiarato !== '') {
        if (!preg_match('/^[0-9a-f]{64}$/i', $sha256Dichiarato)) {
            throw new RuntimeException('Hash del blocco non valido.');
        }
        $hashReale = hash_file('sha256', $tmpFile);
        if ($hashReale === false || !hash_equals(strtolower($sha256Dichiarato), $hashReale)) {
            throw new RuntimeException('Il blocco è arrivato danneggiato. Riprova.');
        }
    }

    $partPath = media_storage_path((string) $upload['percorso_tmp']);
    $handle = @fopen($partPath, 'c+b');
    if ($handle === false) {
        throw new RuntimeException('Impossibile scrivere il caricamento.');
    }

    $offset = 0;
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Caricamento temporaneamente bloccato. Riprova.');
        }
        $stat = fstat($handle);
        $offset = (int) ($stat['size'] ?? 0);
        if ($offset !== $indice * $chunkSize) {
            throw new RuntimeException('Stato del caricamento incoerente. Annulla e ricomincia.');
        }
        if (fseek($handle, $offset) !== 0) {
            throw new RuntimeException('Impossibile posizionarsi nel file di caricamento.');
        }

        $sorgente = @fopen($tmpFile, 'rb');
        if ($sorgente === false) {
            throw new RuntimeException('Impossibile leggere il blocco.');
        }
        $scritti = 0;
        while (!feof($sorgente)) {
            $pezzo = fread($sorgente, 1024 * 1024);
            if ($pezzo === false) {
                fclose($sorgente);
                throw new RuntimeException('Errore di lettura del blocco.');
            }
            if ($pezzo === '') {
                break;
            }
            $out = fwrite($handle, $pezzo);
            if ($out === false || $out !== strlen($pezzo)) {
                fclose($sorgente);
                throw new RuntimeException('Errore di scrittura del caricamento.');
            }
            $scritti += $out;
        }
        fclose($sorgente);

        if ($scritti !== $dimensioneBlocco) {
            throw new RuntimeException('Scrittura incompleta del blocco.');
        }
        fflush($handle);
        if (function_exists('fsync')) {
            @fsync($handle);
        }
        flock($handle, LOCK_UN);
    } catch (Throwable $e) {
        // Riporta il file all'offset precedente: nessun blocco parziale.
        @ftruncate($handle, $offset);
        @fflush($handle);
        @flock($handle, LOCK_UN);
        throw $e;
    } finally {
        fclose($handle);
    }

    $nuoviRicevuti = $ricevuti + 1;
    $nuovaDimensione = (int) $upload['dimensione_ricevuta'] + $dimensioneBlocco;
    try {
        media_upload_aggiorna_progresso((int) $upload['id'], $nuoviRicevuti, $nuovaDimensione);
    } catch (Throwable $e) {
        // Se il database non registra il blocco, riporta il file all'offset
        // precedente per non rendere incoerente il caricamento.
        $ripristino = @fopen($partPath, 'c+b');
        if ($ripristino !== false) {
            @ftruncate($ripristino, $offset);
            @fflush($ripristino);
            @fclose($ripristino);
        }
        throw new RuntimeException('Impossibile aggiornare lo stato del caricamento.');
    }

    return [
        'prossimo_blocco'      => $nuoviRicevuti,
        'chunk_ricevuti'       => $nuoviRicevuti,
        'numero_chunk'         => $numeroChunk,
        'dimensione_ricevuta'  => $nuovaDimensione,
    ];
}

/**
 * Conclude l'upload: verifica, sposta l'originale e crea il record.
 *
 * @return array{file_id:int, duplicati:array, avviso:?string}
 */
function media_upload_concludi(array $upload, int $adminId): array
{
    if ((int) $upload['id_admin'] !== $adminId) {
        throw new RuntimeException('Caricamento non appartenente a questo utente.');
    }
    if ($upload['stato'] !== 'in_corso') {
        throw new RuntimeException('Caricamento non più attivo.');
    }
    if ((int) $upload['chunk_ricevuti'] !== (int) $upload['numero_chunk']) {
        throw new RuntimeException('Caricamento incompleto: mancano dei blocchi.');
    }

    $partPath = media_storage_path((string) $upload['percorso_tmp']);
    if (!is_file($partPath)) {
        throw new RuntimeException('File temporaneo non trovato.');
    }
    clearstatcache(true, $partPath);
    if ((int) filesize($partPath) !== (int) $upload['dimensione_totale']) {
        throw new RuntimeException('Dimensione finale non corretta. Annulla e ricomincia.');
    }

    $sha256 = hash_file('sha256', $partPath);
    if ($sha256 === false) {
        throw new RuntimeException('Impossibile calcolare la verifica del file.');
    }

    $nomeOriginale = (string) $upload['nome_originale'];
    $validazione = media_valida_file($partPath, $nomeOriginale);

    $album = media_album((int) $upload['id_album']);
    if (!$album) {
        throw new RuntimeException('Album non trovato.');
    }

    $duplicati = media_file_duplicati($sha256);

    $uuid         = media_uuid();
    $estensione   = media_estensione($nomeOriginale);
    $relativo     = media_storage_original_relative($uuid, $estensione);
    $destinazione = media_storage_path($relativo);

    if (!@rename($partPath, $destinazione)) {
        throw new RuntimeException('Impossibile completare il salvataggio del file.');
    }
    @chmod($destinazione, 0664);

    try {
        $idFile = media_file_crea([
            'uuid'            => $uuid,
            'id_edizione'     => (int) $album['id_edizione'],
            'id_giornata'     => (int) $album['id_giornata'],
            'id_album'        => (int) $album['id'],
            'tipo'            => $validazione['tipo'],
            'nome_originale'  => $nomeOriginale,
            'nome_file'       => $uuid . '.' . $estensione,
            'percorso'        => $relativo,
            'mime_type'       => $validazione['mime'],
            'estensione'      => $estensione,
            'dimensione'      => (int) $upload['dimensione_totale'],
            'sha256'          => $sha256,
            'larghezza'       => $validazione['larghezza'],
            'altezza'         => $validazione['altezza'],
            'stato'           => 'senza_miniatura',
            'id_uploader'     => $adminId,
        ]);
    } catch (Throwable $e) {
        // Non lasciare il file fuori dal controllo del database.
        $quarantena = 'quarantena/' . $uuid . '.' . $estensione;
        try {
            @rename($destinazione, media_storage_path($quarantena));
        } catch (Throwable $e2) {
            // ignore
        }
        media_upload_annulla((int) $upload['id'], 'errore', 'Registrazione file non riuscita.');
        throw new RuntimeException('Errore durante la registrazione del file.');
    }

    $stato = 'senza_miniatura';
    $miniatura = null;
    if ($validazione['tipo'] === 'foto' && in_array($estensione, ['jpg', 'jpeg', 'png'], true)) {
        $relativaMiniatura = media_storage_thumb_relative($uuid);
        if (media_genera_miniatura($destinazione, $relativaMiniatura, $estensione)) {
            $miniatura = $relativaMiniatura;
            $stato = 'pronto';
        }
    }
    media_file_aggiorna_miniatura($idFile, $miniatura, $stato);
    media_upload_completa((int) $upload['id'], $sha256);
    media_log('file_caricato', 'media_file', $idFile, [
        'uuid'       => $uuid,
        'album'      => (int) $album['id'],
        'dimensione' => (int) $upload['dimensione_totale'],
        'sha256'     => $sha256,
        'duplicati'  => count($duplicati),
    ], $adminId);

    $avviso = null;
    if ($duplicati !== []) {
        $avviso = 'Attenzione: questo file sembra già presente nell\'archivio ('
            . count($duplicati) . ' possibile/i duplicato/i). Il file è stato comunque salvato.';
    }

    return [
        'file_id'    => $idFile,
        'duplicati'  => $duplicati,
        'avviso'     => $avviso,
    ];
}

function media_upload_annulla_servizio(array $upload, int $adminId, bool $eliminaFile = true): void
{
    if ((int) $upload['id_admin'] !== $adminId && !media_is_admin()) {
        throw new RuntimeException('Caricamento non appartenente a questo utente.');
    }
    if ($eliminaFile && !empty($upload['percorso_tmp'])) {
        media_storage_delete((string) $upload['percorso_tmp']);
    }
    media_upload_annulla((int) $upload['id']);
    media_log('upload_annullato', 'media_upload', (int) $upload['id'], ['uuid' => $upload['uuid']], $adminId);
}
