-- ============================================================
--  Archivio multimediale — Migrazione 003
--  Impostazioni predefinite nella tabella `impostazioni`.
--
--  INSERT IGNORE: non sovrascrive valori già presenti.
--  Migrazione additiva e ripetibile.
-- ============================================================

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
