-- ============================================================
-- Archivio fotografico — schema completo
-- Crea le tabelle per il drive foto/video e le relative relazioni.
--
-- Prerequisito: deve esistere la tabella condivisa `admin`
-- con `id` di tipo INT UNSIGNED.
-- Gli schemi torneo includono i ruoli `uploader` ed `editor`.
-- Questo script presuppone che esista la tabella `impostazioni`.
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Edizioni dell'archivio
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
  UNIQUE KEY `uq_media_giornate_edizione_id` (`id_edizione`, `id`),
  CONSTRAINT `fk_media_giornate_edizione`
    FOREIGN KEY (`id_edizione`) REFERENCES `media_edizioni` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Album della giornata
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
  UNIQUE KEY `uq_media_album_giornata_id` (`id_giornata`, `id`),
  CONSTRAINT `fk_media_album_giornata`
    FOREIGN KEY (`id_giornata`) REFERENCES `media_giornate` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- File multimediali
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
  CONSTRAINT `fk_media_file_edizione_giornata`
    FOREIGN KEY (`id_edizione`, `id_giornata`) REFERENCES `media_giornate` (`id_edizione`, `id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_media_file_album`
    FOREIGN KEY (`id_album`) REFERENCES `media_album` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_media_file_giornata_album`
    FOREIGN KEY (`id_giornata`, `id_album`) REFERENCES `media_album` (`id_giornata`, `id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_media_file_uploader`
    FOREIGN KEY (`id_uploader`) REFERENCES `admin` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_media_file_eliminato_da`
    FOREIGN KEY (`eliminato_da`) REFERENCES `admin` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Upload a blocchi riprendibili
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
-- Job per la generazione di archivi ZIP
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media_zip_job` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`           CHAR(36) NOT NULL,
  `id_admin`       INT UNSIGNED NULL DEFAULT NULL,
  `ambito`         ENUM('edizione','giornata','album','selezione') NOT NULL,
  `id_ambito`      INT UNSIGNED NOT NULL,
  `file_ids`       TEXT NULL DEFAULT NULL,
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

-- ------------------------------------------------------------
-- Edizioni autorizzate per ogni uploader
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

INSERT IGNORE INTO `impostazioni` (`chiave`, `valore`, `descrizione`) VALUES
('media_storage_path',      '',      'Percorso archivio media (vuoto = storage/media nel progetto)'),
('media_max_upload_mb',     '1024',  'Dimensione massima di un singolo file caricabile (MB)'),
('media_chunk_mb',          '4',     'Dimensione dei blocchi di upload (MB)'),
('media_thumb_max_px',      '480',  'Lato massimo delle miniature (pixel)'),
('media_retention_giorni',  '30',   'Giorni di conservazione nel cestino prima dell''eliminazione definitiva'),
('media_zip_max_file',      '2000', 'Numero massimo di file per archivio ZIP'),
('media_zip_max_mb',        '5000', 'Dimensione massima totale di un archivio ZIP (MB)'),
('media_zip_parte_mb',      '1500', 'Dimensione massima di ogni parte ZIP (MB)'),
('media_zip_scadenza_ore',  '24',   'Ore di disponibilità di un archivio ZIP generato');
