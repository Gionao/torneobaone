-- ============================================================
--  Archivio multimediale — Migrazione 004 (DOWN)
--  ATTENZIONE: rimuove gli accessi per edizione e il supporto
--  agli ZIP di una selezione. Non elimina file o altri metadati.
--  Eseguire solo con autorizzazione esplicita e backup verificato.
-- ============================================================

ALTER TABLE `media_zip_job`
  DROP COLUMN `file_ids`;

ALTER TABLE `media_zip_job`
  MODIFY COLUMN `ambito`
    ENUM('edizione','giornata','album')
    NOT NULL;

DROP TABLE IF EXISTS `media_accesso_edizione`;
