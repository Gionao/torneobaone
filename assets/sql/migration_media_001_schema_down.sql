-- ============================================================
--  Archivio multimediale — Migrazione 001 (DOWN)
--  ATTENZIONE: operazione distruttiva. Elimina tutte le tabelle
--  e tutti i metadati dell'archivio. Non eseguire senza
--  autorizzazione esplicita e senza backup verificato.
--  I file fisici su disco NON vengono rimossi da questo script.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `media_log`;
DROP TABLE IF EXISTS `media_zip_job`;
DROP TABLE IF EXISTS `media_upload`;
DROP TABLE IF EXISTS `media_file`;
DROP TABLE IF EXISTS `media_album`;
DROP TABLE IF EXISTS `media_giornate`;
DROP TABLE IF EXISTS `media_edizioni`;

SET FOREIGN_KEY_CHECKS = 1;
