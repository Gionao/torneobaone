-- ============================================================
--  Archivio multimediale — Migrazione 003 (DOWN)
--  ATTENZIONE: operazione distruttiva. Non eseguire senza
--  autorizzazione esplicita e senza backup verificato.
--  Rimuove solo le chiavi aggiunte dalla migrazione 003.
-- ============================================================

DELETE FROM `impostazioni`
WHERE `chiave` IN (
  'media_storage_path',
  'media_max_upload_mb',
  'media_chunk_mb',
  'media_thumb_max_px',
  'media_retention_giorni',
  'media_zip_max_file',
  'media_zip_max_mb',
  'media_zip_parte_mb',
  'media_zip_scadenza_ore'
);
