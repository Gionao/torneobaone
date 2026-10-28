-- ============================================================
--  Archivio multimediale — Migrazione 001
--  Crea le tabelle dell'archivio foto/video.
--
--  Migrazione additiva e idempotente (CREATE TABLE IF NOT EXISTS).
--  Non elimina e non modifica dati esistenti.
--  Eseguire solo su database di sviluppo/staging, mai in produzione
--  senza approvazione esplicita.
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Edizioni dell'archivio (entità indipendente da `tornei`)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media_edizioni` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`         VARCHAR(120) NOT NULL,
  `anno`         SMALLINT UNSIGNED NOT NULL,
  `descrizione`  TEXT NULL DEFAULT NULL,
  `data_inizio`  DATE NULL DEFAULT NULL,
  `data_fine`    DATE NULL DEFAULT NULL,
  `attiva`       TINYINT(1) NOT NULL DEFAULT 1,
  `creata_da`    INT UNSIGNED NULL DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_media_edizioni_anno` (`anno`),
  KEY `idx_media_edizioni_attiva` (`attiva`),
  CONSTRAINT `fk_media_edizioni_admin`
    FOREIGN KEY (`creata_da`) REFERENCES `admin` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Giornate dell'edizione
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media_giornate` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_edizione`  INT UNSIGNED NOT NULL,
  `nome`         VARCHAR(120) NOT NULL,
  `data_evento`  DATE NULL DEFAULT NULL,
  `ordine`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `note`         TEXT NULL DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_media_giornate_edizione` (`id_edizione`, `ordine`),
  CONSTRAINT `fk_media_giornate_edizione`
    FOREIGN KEY (`id_edizione`) REFERENCES `media_edizioni` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Album (categoria) della giornata
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media_album` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_giornata`   INT UNSIGNED NOT NULL,
  `nome`          VARCHAR(120) NOT NULL,
  `descrizione`   TEXT NULL DEFAULT NULL,
  `ordine`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `copertina_id`  BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_media_album_giornata` (`id_giornata`, `ordine`),
  CONSTRAINT `fk_media_album_giornata`
    FOREIGN KEY (`id_giornata`) REFERENCES `media_giornate` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- File multimediali (originali preservati)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media_file` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`            CHAR(36) NOT NULL,
  `id_edizione`     INT UNSIGNED NOT NULL,
  `id_giornata`     INT UNSIGNED NOT NULL,
  `id_album`        INT UNSIGNED NOT NULL,
  `tipo`            ENUM('foto','video') NOT NULL,
  `nome_originale`  VARCHAR(255) NOT NULL,
  `nome_file`       VARCHAR(160) NOT NULL,
  `percorso`        VARCHAR(255) NOT NULL,
  `miniatura`       VARCHAR(255) NULL DEFAULT NULL,
  `mime_type`       VARCHAR(100) NOT NULL,
  `estensione`      VARCHAR(10) NOT NULL,
  `dimensione`      BIGINT UNSIGNED NOT NULL,
  `sha256`          CHAR(64) NOT NULL,
  `larghezza`       INT UNSIGNED NULL DEFAULT NULL,
  `altezza`         INT UNSIGNED NULL DEFAULT NULL,
  `durata_sec`      INT UNSIGNED NULL DEFAULT NULL,
  `stato`           ENUM('pronto','senza_miniatura','errore') NOT NULL DEFAULT 'pronto',
  `id_uploader`     INT UNSIGNED NULL DEFAULT NULL,
  `eliminato_il`    DATETIME NULL DEFAULT NULL,
  `eliminato_da`    INT UNSIGNED NULL DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_media_file_uuid` (`uuid`),
  KEY `idx_media_file_album` (`id_album`, `eliminato_il`),
  KEY `idx_media_file_giornata` (`id_giornata`),
  KEY `idx_media_file_edizione` (`id_edizione`),
  KEY `idx_media_file_sha256` (`sha256`),
  KEY `idx_media_file_uploader` (`id_uploader`),
  KEY `idx_media_file_created` (`created_at`),
  CONSTRAINT `fk_media_file_edizione`
    FOREIGN KEY (`id_edizione`) REFERENCES `media_edizioni` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_media_file_giornata`
    FOREIGN KEY (`id_giornata`) REFERENCES `media_giornate` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_media_file_album`
    FOREIGN KEY (`id_album`) REFERENCES `media_album` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_media_file_uploader`
    FOREIGN KEY (`id_uploader`) REFERENCES `admin` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_media_file_eliminato_da`
    FOREIGN KEY (`eliminato_da`) REFERENCES `admin` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Upload a blocchi (stato persistente, riprendibile)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media_upload` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`                 CHAR(36) NOT NULL,
  `id_admin`             INT UNSIGNED NULL DEFAULT NULL,
  `id_album`             INT UNSIGNED NOT NULL,
  `nome_originale`       VARCHAR(255) NOT NULL,
  `dimensione_totale`    BIGINT UNSIGNED NOT NULL,
  `dimensione_ricevuta`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `chunk_size`           INT UNSIGNED NOT NULL,
  `numero_chunk`         INT UNSIGNED NOT NULL,
  `chunk_ricevuti`       INT UNSIGNED NOT NULL DEFAULT 0,
  `sha256_finale`        CHAR(64) NULL DEFAULT NULL,
  `stato`                ENUM('in_corso','completato','annullato','errore') NOT NULL DEFAULT 'in_corso',
  `percorso_tmp`         VARCHAR(255) NOT NULL,
  `errore`               VARCHAR(255) NULL DEFAULT NULL,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completato_il`        DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_media_upload_uuid` (`uuid`),
  KEY `idx_media_upload_admin` (`id_admin`, `stato`),
  KEY `idx_media_upload_stato` (`stato`, `updated_at`),
  KEY `idx_media_upload_album` (`id_album`),
  CONSTRAINT `fk_media_upload_admin`
    FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_media_upload_album`
    FOREIGN KEY (`id_album`) REFERENCES `media_album` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Job ZIP (generazione in background, scadenza automatica)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media_zip_job` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`           CHAR(36) NOT NULL,
  `id_admin`       INT UNSIGNED NULL DEFAULT NULL,
  `ambito`         ENUM('edizione','giornata','album') NOT NULL,
  `id_ambito`      INT UNSIGNED NOT NULL,
  `stato`          ENUM('in_attesa','in_corso','completato','errore','scaduto') NOT NULL DEFAULT 'in_attesa',
  `parti`          TEXT NULL DEFAULT NULL,
  `numero_file`    INT UNSIGNED NULL DEFAULT NULL,
  `dimensione`     BIGINT UNSIGNED NULL DEFAULT NULL,
  `errore`         TEXT NULL DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at`     DATETIME NULL DEFAULT NULL,
  `completato_il`  DATETIME NULL DEFAULT NULL,
  `scade_il`       DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_media_zip_job_uuid` (`uuid`),
  KEY `idx_media_zip_job_stato` (`stato`, `created_at`),
  KEY `idx_media_zip_job_admin` (`id_admin`),
  KEY `idx_media_zip_job_scadenza` (`scade_il`),
  CONSTRAINT `fk_media_zip_job_admin`
    FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Registro operazioni (audit)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media_log` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_admin`    INT UNSIGNED NULL DEFAULT NULL,
  `azione`      VARCHAR(60) NOT NULL,
  `entita`      VARCHAR(40) NOT NULL,
  `id_entita`   BIGINT UNSIGNED NULL DEFAULT NULL,
  `dettagli`    TEXT NULL DEFAULT NULL,
  `ip`          VARCHAR(45) NULL DEFAULT NULL,
  `user_agent`  VARCHAR(255) NULL DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_media_log_admin` (`id_admin`),
  KEY `idx_media_log_entita` (`entita`, `id_entita`),
  KEY `idx_media_log_created` (`created_at`),
  CONSTRAINT `fk_media_log_admin`
    FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
