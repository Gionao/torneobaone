-- ============================================================
-- Allineamento architettura database — migrazione 001
--
-- Eseguire UNA SOLA VOLTA su un database esistente dopo aver:
-- 1) verificato il database selezionato e il backup completo;
-- 2) applicato gli schemi del torneo e del drive se le tabelle media
--    non esistono ancora;
-- 3) controllato i risultati delle query di preflight qui sotto.
--
-- La migrazione non elimina record. Le ALTER TABLE possono fallire se
-- esistono dati incoerenti o vincoli già creati manualmente.
-- Non eseguire alla cieca in produzione.
-- ============================================================

SET NAMES utf8mb4;

-- Preflight: deve restituire 0 per poter aggiungere la FK POS -> admin.
SELECT COUNT(*) AS pos_orders_without_admin
FROM `pos_orders` o
LEFT JOIN `admin` a ON a.`id` = o.`admin_id`
WHERE o.`admin_id` IS NOT NULL AND a.`id` IS NULL;

-- Preflight: entrambe le query devono restituire 0 prima dei vincoli media.
SELECT COUNT(*) AS media_files_with_mismatched_edizione_giornata
FROM `media_file` f
JOIN `media_giornate` g ON g.`id` = f.`id_giornata`
WHERE f.`id_edizione` <> g.`id_edizione`;

SELECT COUNT(*) AS media_files_with_mismatched_giornata_album
FROM `media_file` f
JOIN `media_album` a ON a.`id` = f.`id_album`
WHERE f.`id_giornata` <> a.`id_giornata`;

-- Ruoli interni condivisi dai moduli.
ALTER TABLE `admin`
  MODIFY COLUMN `role`
    ENUM('admin','operatore','bar','cucina','telecronista','uploader','editor')
    NOT NULL DEFAULT 'admin';

-- Conserva gli ordini storici anche quando viene eliminato un operatore.
ALTER TABLE `pos_orders`
  MODIFY COLUMN `admin_id` INT UNSIGNED NULL DEFAULT NULL,
  ADD KEY `idx_pos_orders_admin` (`admin_id`),
  ADD CONSTRAINT `fk_pos_orders_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- Impedisce che un file associ a caso un album, una giornata e un'edizione
-- esistenti ma appartenenti a rami diversi della gerarchia.
ALTER TABLE `media_giornate`
  ADD UNIQUE KEY `uq_media_giornate_edizione_id` (`id_edizione`, `id`);

ALTER TABLE `media_album`
  ADD UNIQUE KEY `uq_media_album_giornata_id` (`id_giornata`, `id`);

ALTER TABLE `media_file`
  ADD CONSTRAINT `fk_media_file_edizione_giornata`
    FOREIGN KEY (`id_edizione`, `id_giornata`)
    REFERENCES `media_giornate` (`id_edizione`, `id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_media_file_giornata_album`
    FOREIGN KEY (`id_giornata`, `id_album`)
    REFERENCES `media_album` (`id_giornata`, `id`)
    ON DELETE RESTRICT ON UPDATE CASCADE;

-- Impostazioni predefinite, senza sovrascrivere valori già configurati.
INSERT IGNORE INTO `impostazioni` (`chiave`, `valore`, `descrizione`) VALUES
('media_storage_path',      '',      'Percorso archivio media (vuoto = storage/media nel progetto)'),
('media_max_upload_mb',     '1024',  'Dimensione massima di un singolo file caricabile (MB)'),
('media_chunk_mb',          '4',     'Dimensione dei blocchi di upload (MB)'),
('media_thumb_max_px',      '480',   'Lato massimo delle miniature (pixel)'),
('media_retention_giorni',  '30',    'Giorni di conservazione nel cestino prima dell''eliminazione definitiva'),
('media_zip_max_file',      '2000',  'Numero massimo di file per archivio ZIP'),
('media_zip_max_mb',        '5000',  'Dimensione massima totale di un archivio ZIP (MB)'),
('media_zip_parte_mb',      '1500',  'Dimensione massima di ogni parte ZIP (MB)'),
('media_zip_scadenza_ore',  '24',    'Ore di disponibilità di un archivio ZIP generato');
