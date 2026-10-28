-- ============================================================
--  Archivio multimediale — Migrazione 004
--  Accessi degli uploader per edizione e ZIP di una selezione.
--
--  Migrazione additiva. Non elimina dati esistenti.
--  Eseguire dopo le migrazioni 001, 002 e 003.
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Edizioni autorizzate per ogni uploader.
-- Editor e amministratori vedono sempre tutto e non hanno righe.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media_accesso_edizione` (
  `id_admin`     INT UNSIGNED NOT NULL,
  `id_edizione`  INT UNSIGNED NOT NULL,
  `creato_da`    INT UNSIGNED NULL DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_admin`, `id_edizione`),
  KEY `idx_media_accesso_edizione` (`id_edizione`),
  CONSTRAINT `fk_media_accesso_admin`
    FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_media_accesso_edizione`
    FOREIGN KEY (`id_edizione`) REFERENCES `media_edizioni` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_media_accesso_creato_da`
    FOREIGN KEY (`creato_da`) REFERENCES `admin` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ZIP di file selezionati manualmente.
-- ------------------------------------------------------------
ALTER TABLE `media_zip_job`
  MODIFY COLUMN `ambito`
    ENUM('edizione','giornata','album','selezione')
    NOT NULL;

ALTER TABLE `media_zip_job`
  ADD COLUMN `file_ids` TEXT NULL DEFAULT NULL AFTER `id_ambito`;
