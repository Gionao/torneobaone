<?php
// includes/media_repository.php
// ============================================================
//  Accesso ai dati dell'archivio (PDO, prepared statements).
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/media_config.php';
require_once __DIR__ . '/media_auth.php';

function media_log(string $azione, string $entita, ?int $idEntita = null, array $dettagli = [], ?int $adminId = null): void
{
    try {
        $logAdminId = $adminId !== null ? $adminId : (int) ($_SESSION['admin_id'] ?? 0);
        $stmt = db()->prepare('
            INSERT INTO media_log (id_admin, azione, entita, id_entita, dettagli, ip, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $logAdminId > 0 ? $logAdminId : null,
            substr($azione, 0, 60),
            substr($entita, 0, 40),
            $idEntita,
            $dettagli === [] ? null : json_encode($dettagli, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            media_ip(),
            media_user_agent(),
        ]);
    } catch (Throwable $e) {
        // Il log non deve bloccare l'operazione principale.
    }
}

// ------------------------------------------------------------
//  Edizioni
// ------------------------------------------------------------

function media_edizioni(bool $soloAttive = false, ?array $idConsentiti = null): array
{
    $sql = 'SELECT e.*,
                   (SELECT COUNT(*) FROM media_giornate g WHERE g.id_edizione = e.id) AS numero_giornate,
                   (SELECT COUNT(*) FROM media_file f WHERE f.id_edizione = e.id AND f.eliminato_il IS NULL) AS numero_file
            FROM media_edizioni e';
    $dove = [];
    $parametri = [];
    if ($soloAttive) {
        $dove[] = 'e.attiva = 1';
    }
    if ($idConsentiti !== null) {
        if ($idConsentiti === []) {
            return [];
        }
        $segnaposto = implode(',', array_fill(0, count($idConsentiti), '?'));
        $dove[] = 'e.id IN (' . $segnaposto . ')';
        $parametri = array_map('intval', $idConsentiti);
    }
    if ($dove !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $dove);
    }
    $sql .= ' ORDER BY e.anno DESC, e.nome ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($parametri);
    return $stmt->fetchAll();
}

function media_edizione(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM media_edizioni WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function media_edizione_crea(array $dati, int $adminId): int
{
    $stmt = db()->prepare('
        INSERT INTO media_edizioni (nome, anno, descrizione, data_inizio, data_fine, attiva, creata_da)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $dati['nome'],
        $dati['anno'],
        $dati['descrizione'] ?: null,
        $dati['data_inizio'] ?: null,
        $dati['data_fine'] ?: null,
        !empty($dati['attiva']) ? 1 : 0,
        $adminId ?: null,
    ]);
    return (int) db()->lastInsertId();
}

function media_edizione_aggiorna(int $id, array $dati): void
{
    $stmt = db()->prepare('
        UPDATE media_edizioni
        SET nome = ?, anno = ?, descrizione = ?, data_inizio = ?, data_fine = ?, attiva = ?
        WHERE id = ?
    ');
    $stmt->execute([
        $dati['nome'],
        $dati['anno'],
        $dati['descrizione'] ?: null,
        $dati['data_inizio'] ?: null,
        $dati['data_fine'] ?: null,
        !empty($dati['attiva']) ? 1 : 0,
        $id,
    ]);
}

function media_edizione_elimina(int $id): void
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM media_giornate WHERE id_edizione = ?');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException('L\'edizione contiene giornate: eliminale prima.');
    }
    db()->prepare('DELETE FROM media_edizioni WHERE id = ?')->execute([$id]);
}

// ------------------------------------------------------------
//  Accessi per edizione
// ------------------------------------------------------------

/** @return list<int> */
function media_accesso_edizioni_admin(int $adminId): array
{
    $stmt = db()->prepare('SELECT id_edizione FROM media_accesso_edizione WHERE id_admin = ? ORDER BY id_edizione');
    $stmt->execute([$adminId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Sostituisce le edizioni consentite a un uploader.
 *
 * @param list<int> $idEdizioni
 */
function media_accesso_imposta(int $adminId, array $idEdizioni, ?int $creatoDa = null): void
{
    $idEdizioni = array_values(array_filter(array_map('intval', $idEdizioni), static fn (int $id): bool => $id > 0));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM media_accesso_edizione WHERE id_admin = ?')->execute([$adminId]);
        if ($idEdizioni !== []) {
            $valide = [];
            $segnaposto = implode(',', array_fill(0, count($idEdizioni), '?'));
            $stmt = $pdo->prepare('SELECT id FROM media_edizioni WHERE id IN (' . $segnaposto . ')');
            $stmt->execute($idEdizioni);
            $valide = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $ins = $pdo->prepare('
                INSERT INTO media_accesso_edizione (id_admin, id_edizione, creato_da)
                VALUES (?, ?, ?)
            ');
            foreach ($valide as $idEdizione) {
                $ins->execute([$adminId, $idEdizione, $creatoDa ?: null]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// ------------------------------------------------------------
//  Giornate
// ------------------------------------------------------------

function media_giornate(int $idEdizione): array
{
    $stmt = db()->prepare('
        SELECT g.*,
               (SELECT COUNT(*) FROM media_album a WHERE a.id_giornata = g.id) AS numero_album,
               (SELECT COUNT(*) FROM media_file f WHERE f.id_giornata = g.id AND f.eliminato_il IS NULL) AS numero_file
        FROM media_giornate g
        WHERE g.id_edizione = ?
        ORDER BY g.ordine ASC, g.data_evento ASC, g.id ASC
    ');
    $stmt->execute([$idEdizione]);
    return $stmt->fetchAll();
}

function media_giornata(int $id): ?array
{
    $stmt = db()->prepare('
        SELECT g.*, e.nome AS nome_edizione, e.anno AS anno_edizione
        FROM media_giornate g
        JOIN media_edizioni e ON e.id = g.id_edizione
        WHERE g.id = ? LIMIT 1
    ');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function media_giornata_crea(array $dati): int
{
    $stmt = db()->prepare('
        INSERT INTO media_giornate (id_edizione, nome, data_evento, ordine, note)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $dati['id_edizione'],
        $dati['nome'],
        $dati['data_evento'] ?: null,
        (int) ($dati['ordine'] ?? 0),
        $dati['note'] ?: null,
    ]);
    return (int) db()->lastInsertId();
}

function media_giornata_aggiorna(int $id, array $dati): void
{
    $stmt = db()->prepare('
        UPDATE media_giornate
        SET nome = ?, data_evento = ?, ordine = ?, note = ?
        WHERE id = ?
    ');
    $stmt->execute([
        $dati['nome'],
        $dati['data_evento'] ?: null,
        (int) ($dati['ordine'] ?? 0),
        $dati['note'] ?: null,
        $id,
    ]);
}

function media_giornata_elimina(int $id): void
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM media_album WHERE id_giornata = ?');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException('La giornata contiene album: eliminali prima.');
    }
    db()->prepare('DELETE FROM media_giornate WHERE id = ?')->execute([$id]);
}

// ------------------------------------------------------------
//  Album
// ------------------------------------------------------------

function media_album_per_giornata(int $idGiornata): array
{
    $stmt = db()->prepare('
        SELECT a.*,
               (SELECT COUNT(*) FROM media_file f WHERE f.id_album = a.id AND f.eliminato_il IS NULL) AS numero_file,
               (SELECT f2.id FROM media_file f2
                 WHERE f2.id_album = a.id AND f2.eliminato_il IS NULL AND f2.miniatura IS NOT NULL
                 ORDER BY f2.id ASC LIMIT 1) AS prima_foto
        FROM media_album a
        WHERE a.id_giornata = ?
        ORDER BY a.ordine ASC, a.nome ASC
    ');
    $stmt->execute([$idGiornata]);
    return $stmt->fetchAll();
}

function media_album(int $id): ?array
{
    $stmt = db()->prepare('
        SELECT a.*, g.nome AS nome_giornata, g.id_edizione,
               e.nome AS nome_edizione, e.anno AS anno_edizione
        FROM media_album a
        JOIN media_giornate g ON g.id = a.id_giornata
        JOIN media_edizioni e ON e.id = g.id_edizione
        WHERE a.id = ? LIMIT 1
    ');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Elenco piatto per le tendine di selezione. */
function media_album_tutti(?array $idEdizioniConsentite = null): array
{
    $sql = '
        SELECT a.id, a.nome, a.id_giornata, g.nome AS nome_giornata, e.id AS id_edizione,
               e.nome AS nome_edizione, e.anno
        FROM media_album a
        JOIN media_giornate g ON g.id = a.id_giornata
        JOIN media_edizioni e ON e.id = g.id_edizione';
    $parametri = [];
    if ($idEdizioniConsentite !== null) {
        if ($idEdizioniConsentite === []) {
            return [];
        }
        $segnaposto = implode(',', array_fill(0, count($idEdizioniConsentite), '?'));
        $sql .= ' WHERE e.id IN (' . $segnaposto . ')';
        $parametri = array_map('intval', $idEdizioniConsentite);
    }
    $sql .= ' ORDER BY e.anno DESC, g.ordine ASC, a.ordine ASC, a.nome ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($parametri);
    return $stmt->fetchAll();
}

function media_album_crea(array $dati): int
{
    $stmt = db()->prepare('
        INSERT INTO media_album (id_giornata, nome, descrizione, ordine)
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([
        $dati['id_giornata'],
        $dati['nome'],
        $dati['descrizione'] ?: null,
        (int) ($dati['ordine'] ?? 0),
    ]);
    return (int) db()->lastInsertId();
}

function media_album_aggiorna(int $id, array $dati): void
{
    $stmt = db()->prepare('
        UPDATE media_album SET nome = ?, descrizione = ?, ordine = ? WHERE id = ?
    ');
    $stmt->execute([
        $dati['nome'],
        $dati['descrizione'] ?: null,
        (int) ($dati['ordine'] ?? 0),
        $id,
    ]);
}

function media_album_elimina(int $id): void
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM media_file WHERE id_album = ?');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException('L\'album contiene file: spostali o eliminali prima.');
    }
    db()->prepare('DELETE FROM media_album WHERE id = ?')->execute([$id]);
}

function media_album_numero_file(int $id): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM media_file WHERE id_album = ? AND eliminato_il IS NULL');
    $stmt->execute([$id]);
    return (int) $stmt->fetchColumn();
}

/** Album di una edizione, con giornata e conteggio file. */
function media_album_per_edizione(int $idEdizione): array
{
    $stmt = db()->prepare('
        SELECT a.*, g.nome AS nome_giornata, g.data_evento,
               (SELECT COUNT(*) FROM media_file f WHERE f.id_album = a.id AND f.eliminato_il IS NULL) AS numero_file
        FROM media_album a
        JOIN media_giornate g ON g.id = a.id_giornata
        WHERE g.id_edizione = ?
        ORDER BY g.ordine ASC, g.data_evento ASC, g.id ASC, a.ordine ASC, a.nome ASC
    ');
    $stmt->execute([$idEdizione]);
    return $stmt->fetchAll();
}

// ------------------------------------------------------------
//  File
// ------------------------------------------------------------

function media_file(int $id): ?array
{
    $stmt = db()->prepare('
        SELECT f.*, a.nome AS nome_album, g.nome AS nome_giornata, e.nome AS nome_edizione,
               u.username AS username_uploader
        FROM media_file f
        JOIN media_album a ON a.id = f.id_album
        JOIN media_giornate g ON g.id = f.id_giornata
        JOIN media_edizioni e ON e.id = f.id_edizione
        LEFT JOIN admin u ON u.id = f.id_uploader
        WHERE f.id = ? LIMIT 1
    ');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function media_file_per_album(int $idAlbum, int $limite = 60, int $offset = 0, bool $inclusiEliminati = false): array
{
    $limite = max(1, min(200, $limite));
    $offset = max(0, $offset);
    $where  = $inclusiEliminati ? '' : ' AND f.eliminato_il IS NULL';

    $stmt = db()->prepare('
        SELECT f.*, u.username AS username_uploader
        FROM media_file f
        LEFT JOIN admin u ON u.id = f.id_uploader
        WHERE f.id_album = ?' . $where . '
        ORDER BY f.created_at DESC, f.id DESC
        LIMIT ' . $limite . ' OFFSET ' . $offset
    );
    $stmt->execute([$idAlbum]);
    return $stmt->fetchAll();
}

/**
 * File per un elenco di ID (usato per gli ZIP di una selezione).
 *
 * @param list<int> $ids
 */
function media_file_per_ids(array $ids, bool $inclusiEliminati = false): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    if ($ids === []) {
        return [];
    }
    $segnaposto = implode(',', array_fill(0, count($ids), '?'));
    $sql = '
        SELECT f.*, a.nome AS nome_album, g.nome AS nome_giornata, e.anno AS anno_edizione,
               u.username AS username_uploader
        FROM media_file f
        JOIN media_album a ON a.id = f.id_album
        JOIN media_giornate g ON g.id = f.id_giornata
        JOIN media_edizioni e ON e.id = f.id_edizione
        LEFT JOIN admin u ON u.id = f.id_uploader
        WHERE f.id IN (' . $segnaposto . ')';
    if (!$inclusiEliminati) {
        $sql .= ' AND f.eliminato_il IS NULL';
    }
    $sql .= ' ORDER BY e.anno ASC, g.ordine ASC, a.ordine ASC, f.created_at ASC, f.id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($ids);
    return $stmt->fetchAll();
}

function media_file_conteggio_edizione(int $idEdizione): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM media_file WHERE id_edizione = ? AND eliminato_il IS NULL');
    $stmt->execute([$idEdizione]);
    return (int) $stmt->fetchColumn();
}

function media_file_conteggio_giornata(int $idGiornata): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM media_file WHERE id_giornata = ? AND eliminato_il IS NULL');
    $stmt->execute([$idGiornata]);
    return (int) $stmt->fetchColumn();
}

function media_file_conteggio_album(int $idAlbum, bool $inclusiEliminati = false): int
{
    $sql = 'SELECT COUNT(*) FROM media_file WHERE id_album = ?';
    if (!$inclusiEliminati) {
        $sql .= ' AND eliminato_il IS NULL';
    }
    $stmt = db()->prepare($sql);
    $stmt->execute([$idAlbum]);
    return (int) $stmt->fetchColumn();
}

function media_file_duplicati(string $sha256, ?int $escludiId = null): array
{
    $sql = 'SELECT f.id, f.nome_originale, f.dimensione, f.created_at, a.nome AS nome_album
            FROM media_file f
            JOIN media_album a ON a.id = f.id_album
            WHERE f.sha256 = ? AND f.eliminato_il IS NULL';
    $params = [$sha256];
    if ($escludiId !== null) {
        $sql .= ' AND f.id <> ?';
        $params[] = $escludiId;
    }
    $sql .= ' ORDER BY f.id ASC LIMIT 5';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function media_file_crea(array $dati): int
{
    $stmt = db()->prepare('
        INSERT INTO media_file
            (uuid, id_edizione, id_giornata, id_album, tipo, nome_originale, nome_file, percorso,
             miniatura, mime_type, estensione, dimensione, sha256, larghezza, altezza, durata_sec,
             stato, id_uploader)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $dati['uuid'],
        $dati['id_edizione'],
        $dati['id_giornata'],
        $dati['id_album'],
        $dati['tipo'],
        $dati['nome_originale'],
        $dati['nome_file'],
        $dati['percorso'],
        $dati['miniatura'] ?? null,
        $dati['mime_type'],
        $dati['estensione'],
        $dati['dimensione'],
        $dati['sha256'],
        $dati['larghezza'] ?? null,
        $dati['altezza'] ?? null,
        $dati['durata_sec'] ?? null,
        $dati['stato'] ?? 'pronto',
        $dati['id_uploader'] ?? null,
    ]);
    return (int) db()->lastInsertId();
}

function media_file_aggiorna_miniatura(int $id, ?string $miniatura, string $stato): void
{
    $stmt = db()->prepare('UPDATE media_file SET miniatura = ?, stato = ? WHERE id = ?');
    $stmt->execute([$miniatura, $stato, $id]);
}

function media_file_soft_delete(int $id, int $adminId): bool
{
    $stmt = db()->prepare('
        UPDATE media_file SET eliminato_il = NOW(), eliminato_da = ?
        WHERE id = ? AND eliminato_il IS NULL
    ');
    $stmt->execute([$adminId ?: null, $id]);
    return $stmt->rowCount() > 0;
}

function media_file_ripristina(int $id): bool
{
    $stmt = db()->prepare('UPDATE media_file SET eliminato_il = NULL, eliminato_da = NULL WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

function media_file_eliminati(int $limite = 100, int $offset = 0): array
{
    $limite = max(1, min(500, $limite));
    $offset = max(0, $offset);
    return db()->query('
        SELECT f.*, a.nome AS nome_album, g.nome AS nome_giornata, e.nome AS nome_edizione,
               u.username AS username_uploader
        FROM media_file f
        JOIN media_album a ON a.id = f.id_album
        JOIN media_giornate g ON g.id = f.id_giornata
        JOIN media_edizioni e ON e.id = f.id_edizione
        LEFT JOIN admin u ON u.id = f.id_uploader
        WHERE f.eliminato_il IS NOT NULL
        ORDER BY f.eliminato_il DESC
        LIMIT ' . $limite . ' OFFSET ' . $offset . '
    ')->fetchAll();
}

function media_file_eliminati_scaduti(int $giorni): array
{
    $limite = date('Y-m-d H:i:s', time() - max(1, $giorni) * 86400);
    $stmt = db()->prepare('
        SELECT * FROM media_file
        WHERE eliminato_il IS NOT NULL
          AND eliminato_il < ?
        ORDER BY eliminato_il ASC
        LIMIT 500
    ');
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

function media_file_elimina_record(int $id): ?array
{
    $file = media_file($id);
    if (!$file) {
        return null;
    }
    db()->prepare('DELETE FROM media_file WHERE id = ?')->execute([$id]);
    return $file;
}

function media_file_per_ambito(string $ambito, int $id): array
{
    $colonne = [
        'edizione' => 'f.id_edizione',
        'giornata' => 'f.id_giornata',
        'album'    => 'f.id_album',
    ];
    if (!isset($colonne[$ambito])) {
        throw new RuntimeException('Ambito non valido.');
    }
    $stmt = db()->prepare('
        SELECT f.*, a.nome AS nome_album, g.nome AS nome_giornata, e.anno AS anno_edizione
        FROM media_file f
        JOIN media_album a ON a.id = f.id_album
        JOIN media_giornate g ON g.id = f.id_giornata
        JOIN media_edizioni e ON e.id = f.id_edizione
        WHERE ' . $colonne[$ambito] . ' = ? AND f.eliminato_il IS NULL
        ORDER BY g.ordine ASC, a.ordine ASC, f.created_at ASC, f.id ASC
    ');
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

// ------------------------------------------------------------
//  Upload
// ------------------------------------------------------------

function media_upload(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM media_upload WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function media_upload_per_uuid(string $uuid): ?array
{
    $stmt = db()->prepare('SELECT * FROM media_upload WHERE uuid = ? LIMIT 1');
    $stmt->execute([$uuid]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function media_upload_crea(array $dati): int
{
    $stmt = db()->prepare('
        INSERT INTO media_upload
            (uuid, id_admin, id_album, nome_originale, dimensione_totale, chunk_size, numero_chunk, percorso_tmp)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $dati['uuid'],
        $dati['id_admin'],
        $dati['id_album'],
        $dati['nome_originale'],
        $dati['dimensione_totale'],
        $dati['chunk_size'],
        $dati['numero_chunk'],
        $dati['percorso_tmp'],
    ]);
    return (int) db()->lastInsertId();
}

function media_upload_aggiorna_progresso(int $id, int $chunkRicevuti, int $dimensioneRicevuta): void
{
    $stmt = db()->prepare('
        UPDATE media_upload SET chunk_ricevuti = ?, dimensione_ricevuta = ? WHERE id = ?
    ');
    $stmt->execute([$chunkRicevuti, $dimensioneRicevuta, $id]);
}

function media_upload_completa(int $id, string $sha256): void
{
    $stmt = db()->prepare('
        UPDATE media_upload
        SET stato = \'completato\', sha256_finale = ?, completato_il = NOW()
        WHERE id = ?
    ');
    $stmt->execute([$sha256, $id]);
}

function media_upload_annulla(int $id, string $stato = 'annullato', ?string $errore = null): void
{
    $stmt = db()->prepare('UPDATE media_upload SET stato = ?, errore = ? WHERE id = ?');
    $stmt->execute([$stato, $errore, $id]);
}

function media_upload_vecchi(int $ore = 48): array
{
    $limite = date('Y-m-d H:i:s', time() - max(1, $ore) * 3600);
    $stmt = db()->prepare('
        SELECT * FROM media_upload
        WHERE stato = \'in_corso\' AND updated_at < ?
        LIMIT 200
    ');
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

/** Upload ancora in corso dell'utente, per la ripresa lato interfaccia. */
function media_upload_attivi(int $adminId, int $limite = 20): array
{
    $limite = max(1, min(50, $limite));
    $stmt = db()->prepare('
        SELECT u.*, a.nome AS nome_album, g.nome AS nome_giornata, e.nome AS nome_edizione, e.anno AS anno_edizione
        FROM media_upload u
        JOIN media_album a ON a.id = u.id_album
        JOIN media_giornate g ON g.id = a.id_giornata
        JOIN media_edizioni e ON e.id = g.id_edizione
        WHERE u.id_admin = ? AND u.stato = \'in_corso\'
        ORDER BY u.updated_at DESC
        LIMIT ' . $limite . '
    ');
    $stmt->execute([$adminId]);
    return $stmt->fetchAll();
}

// ------------------------------------------------------------
//  ZIP
// ------------------------------------------------------------

function media_zip_job(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM media_zip_job WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function media_zip_job_per_uuid(string $uuid): ?array
{
    $stmt = db()->prepare('SELECT * FROM media_zip_job WHERE uuid = ? LIMIT 1');
    $stmt->execute([$uuid]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function media_zip_job_crea(array $dati): int
{
    $stmt = db()->prepare('
        INSERT INTO media_zip_job (uuid, id_admin, ambito, id_ambito, file_ids)
        VALUES (?, ?, ?, ?, ?)
    ');
    $fileIds = null;
    if (!empty($dati['file_ids'])) {
        $fileIds = json_encode(array_values(array_map('intval', $dati['file_ids'])), JSON_UNESCAPED_SLASHES);
    }
    $stmt->execute([
        $dati['uuid'],
        $dati['id_admin'],
        $dati['ambito'],
        (int) $dati['id_ambito'],
        $fileIds,
    ]);
    return (int) db()->lastInsertId();
}

function media_zip_job_attivo_per_admin(int $adminId): bool
{
    $stmt = db()->prepare('
        SELECT COUNT(*) FROM media_zip_job
        WHERE id_admin = ? AND stato IN (\'in_attesa\', \'in_corso\')
    ');
    $stmt->execute([$adminId]);
    return (int) $stmt->fetchColumn() > 0;
}

function media_zip_job_in_corso_bloccati(int $ore = 2): array
{
    $limite = date('Y-m-d H:i:s', time() - max(1, $ore) * 3600);
    $stmt = db()->prepare('
        SELECT * FROM media_zip_job
        WHERE stato = \'in_corso\' AND started_at IS NOT NULL AND started_at < ?
        LIMIT 100
    ');
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

function media_zip_job_prendi_in_carico(int $id): bool
{
    $stmt = db()->prepare('
        UPDATE media_zip_job
        SET stato = \'in_corso\', started_at = NOW()
        WHERE id = ? AND stato = \'in_attesa\'
    ');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

function media_zip_job_completa(int $id, array $parti, int $numeroFile, int $dimensione, int $oreScadenza): void
{
    $scadenza = date('Y-m-d H:i:s', time() + max(1, $oreScadenza) * 3600);
    $stmt = db()->prepare('
        UPDATE media_zip_job
        SET stato = \'completato\', parti = ?, numero_file = ?, dimensione = ?,
            completato_il = NOW(), scade_il = ?
        WHERE id = ?
    ');
    $stmt->execute([
        json_encode($parti, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $numeroFile,
        $dimensione,
        $scadenza,
        $id,
    ]);
}

function media_zip_job_errore(int $id, string $errore): void
{
    $stmt = db()->prepare('UPDATE media_zip_job SET stato = \'errore\', errore = ? WHERE id = ?');
    $stmt->execute([substr($errore, 0, 2000), $id]);
}

function media_zip_job_in_attesa(int $limite = 3): array
{
    $limite = max(1, min(20, $limite));
    return db()->query('
        SELECT * FROM media_zip_job
        WHERE stato = \'in_attesa\'
        ORDER BY created_at ASC
        LIMIT ' . $limite . '
    ')->fetchAll();
}

function media_zip_job_scaduti(): array
{
    return db()->query('
        SELECT * FROM media_zip_job
        WHERE stato = \'completato\' AND scade_il IS NOT NULL AND scade_il < NOW()
        LIMIT 200
    ')->fetchAll();
}

function media_zip_job_di_admin(int $adminId, int $limite = 20): array
{
    $limite = max(1, min(100, $limite));
    $stmt = db()->prepare('
        SELECT * FROM media_zip_job
        WHERE id_admin = ?
        ORDER BY created_at DESC
        LIMIT ' . $limite . '
    ');
    $stmt->execute([$adminId]);
    return $stmt->fetchAll();
}

// ------------------------------------------------------------
//  Statistiche
// ------------------------------------------------------------

function media_statistiche(?array $idEdizioniConsentite = null): array
{
    $sql = '
        SELECT
            COALESCE(SUM(eliminato_il IS NULL), 0) AS file_totali,
            COALESCE(SUM(CASE WHEN eliminato_il IS NULL THEN dimensione ELSE 0 END), 0) AS byte_totali,
            COALESCE(SUM(eliminato_il IS NULL AND tipo = \'foto\'), 0) AS foto,
            COALESCE(SUM(eliminato_il IS NULL AND tipo = \'video\'), 0) AS video,
            COALESCE(SUM(eliminato_il IS NOT NULL), 0) AS nel_cestino
        FROM media_file';
    $parametri = [];
    if ($idEdizioniConsentite !== null) {
        if ($idEdizioniConsentite === []) {
            return ['file_totali' => 0, 'byte_totali' => 0, 'foto' => 0, 'video' => 0, 'nel_cestino' => 0];
        }
        $segnaposto = implode(',', array_fill(0, count($idEdizioniConsentite), '?'));
        $sql .= ' WHERE id_edizione IN (' . $segnaposto . ')';
        $parametri = array_map('intval', $idEdizioniConsentite);
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($parametri);
    $row = $stmt->fetch();

    return [
        'file_totali' => (int) ($row['file_totali'] ?? 0),
        'byte_totali' => (int) ($row['byte_totali'] ?? 0),
        'foto'        => (int) ($row['foto'] ?? 0),
        'video'       => (int) ($row['video'] ?? 0),
        'nel_cestino' => (int) ($row['nel_cestino'] ?? 0),
    ];
}

// ------------------------------------------------------------
//  Utenti dell'archivio (tabella admin condivisa)
// ------------------------------------------------------------

function media_utenti_archivio(): array
{
    return db()->query('
        SELECT u.id, u.username, u.role, u.created_at,
               (SELECT COUNT(*) FROM media_file f WHERE f.id_uploader = u.id AND f.eliminato_il IS NULL) AS numero_file,
               (SELECT COUNT(*) FROM media_accesso_edizione a WHERE a.id_admin = u.id) AS numero_accessi
        FROM admin u
        WHERE u.role IN (\'uploader\', \'editor\', \'admin\')
        ORDER BY FIELD(u.role, \'admin\', \'editor\', \'uploader\'), u.username
    ')->fetchAll();
}

function media_utente(int $id): ?array
{
    $stmt = db()->prepare('SELECT id, username, role, created_at FROM admin WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function media_conta_admin_archivio(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM admin WHERE role = \'admin\'')->fetchColumn();
}

function media_utente_crea(string $username, string $password, string $ruolo): int
{
    if (!media_ruolo_valido($ruolo)) {
        throw new RuntimeException('Ruolo non valido.');
    }
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = db()->prepare('INSERT INTO admin (username, password_hash, role) VALUES (?, ?, ?)');
    $stmt->execute([$username, $hash, $ruolo]);
    return (int) db()->lastInsertId();
}

function media_utente_aggiorna_ruolo(int $id, string $ruolo): void
{
    if (!media_ruolo_valido($ruolo)) {
        throw new RuntimeException('Ruolo non valido.');
    }
    $stmt = db()->prepare('UPDATE admin SET role = ? WHERE id = ?');
    $stmt->execute([$ruolo, $id]);
}

function media_utente_password(int $id, string $password): void
{
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = db()->prepare('UPDATE admin SET password_hash = ? WHERE id = ?');
    $stmt->execute([$hash, $id]);
}

function media_utente_elimina(int $id): void
{
    $utente = media_utente($id);
    if (!$utente) {
        throw new RuntimeException('Utente non trovato.');
    }
    if ((int) $utente['id'] === media_admin_id()) {
        throw new RuntimeException('Non puoi eliminare il tuo account.');
    }
    if ($utente['role'] === 'admin') {
        $stmt = db()->prepare('SELECT COUNT(*) FROM admin WHERE role = \'admin\' AND id <> ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() < 1) {
            throw new RuntimeException('Deve restare almeno un amministratore.');
        }
    }
    db()->prepare('DELETE FROM admin WHERE id = ? AND role IN (\'uploader\', \'editor\', \'admin\')')->execute([$id]);
}

// ------------------------------------------------------------
//  Impostazioni
// ------------------------------------------------------------

/** @return array<string, string> */
function media_impostazioni_salvate(): array
{
    $chiavi = array_keys(media_impostazioni_default());
    if ($chiavi === []) {
        return [];
    }
    $segnaposto = implode(',', array_fill(0, count($chiavi), '?'));
    $stmt = db()->prepare('SELECT chiave, valore FROM impostazioni WHERE chiave IN (' . $segnaposto . ')');
    $stmt->execute($chiavi);
    $risultato = [];
    foreach ($stmt->fetchAll() as $row) {
        $risultato[(string) $row['chiave']] = (string) $row['valore'];
    }
    return $risultato;
}

/** @param array<string, string> $valori */
function media_impostazioni_salva(array $valori): void
{
    $ammesse = array_keys(media_impostazioni_default());
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            INSERT INTO impostazioni (chiave, valore)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE valore = VALUES(valore)
        ');
        foreach ($valori as $chiave => $valore) {
            if (!in_array($chiave, $ammesse, true)) {
                continue;
            }
            $stmt->execute([$chiave, mb_substr((string) $valore, 0, 255, 'UTF-8')]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
