-- ============================================================
--  TORNEO CALCIO A 5 - BAONE 2026
--  Schema Database per phpMyAdmin / Altervista MySQL
--  Charset: utf8mb4  |  Collation: utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS `my_torneobaone`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `my_torneobaone`;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+02:00";

-- ------------------------------------------------------------
-- 1. ADMIN - Credenziali pannello amministratore
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(60)     NOT NULL UNIQUE,
  `password_hash` VARCHAR(255)    NOT NULL,          -- bcrypt via password_hash()
  `role`          ENUM('admin','operatore','bar','cucina','telecronista','uploader','editor') NOT NULL DEFAULT 'admin',
  `station_name`  VARCHAR(80)     NULL DEFAULT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Utente admin di default  (password: admin — DA CAMBIARE subito)
INSERT INTO `admin` (`username`, `password_hash`) VALUES
('admin', '$2y$10$ZVqAQkZ.6NVKkBofUpIj6uideKqYape41nHFimOaUtjl2qHtKvQjK');
-- ^ hash generato con: password_hash('Admin2026!', PASSWORD_BCRYPT, ['cost'=>12])
-- IMPORTANTE: rigenera questo hash con la tua password reale prima del deploy!


-- ------------------------------------------------------------
-- 2. FASI - Girone / Playoff / Semifinali / Finali
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tornei` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`        VARCHAR(100) NOT NULL,
  `stagione`    VARCHAR(20) NULL DEFAULT NULL,
  `data_inizio` DATE NULL DEFAULT NULL,
  `data_fine`   DATE NULL DEFAULT NULL,
  `attivo`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fasi` (
  `id`    TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`  VARCHAR(50)      NOT NULL,   -- es. 'Girone', 'Playoff', 'Semifinale', 'Finale'
  `ordine` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `fasi` (`nome`, `ordine`) VALUES
('Girone',           1),
('Playoff',          2),
('Semifinale',       3),
('Finale 3°/4°',     4),
('Finale 1°/2°',     5);


-- ------------------------------------------------------------
-- 3. CAPITANI
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `capitani` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`                VARCHAR(100) NOT NULL,
  `cognome`             VARCHAR(100) NOT NULL,
  `email`               VARCHAR(190) NOT NULL,
  `telefono`            VARCHAR(30) NULL DEFAULT NULL,
  `data_nascita`        DATE NULL DEFAULT NULL,
  `indirizzo`           VARCHAR(255) NULL DEFAULT NULL,
  `password_hash`       VARCHAR(255) NOT NULL,
  `email_verificata_at` DATETIME NULL DEFAULT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_capitani_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 4. SQUADRE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `squadre` (
  `id`                        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`                      VARCHAR(80)       NOT NULL,
  `colore`                    VARCHAR(7)        NOT NULL DEFAULT '#1a3a5c',
  `logo_url`                  VARCHAR(255)      NULL DEFAULT NULL,
  `id_capitano`               INT UNSIGNED      NULL DEFAULT NULL,
  `id_torneo`                 TINYINT UNSIGNED  NULL DEFAULT NULL,
  `attiva`                    TINYINT(1)        NOT NULL DEFAULT 0,
  `autorizzata`               TINYINT(1)        NOT NULL DEFAULT 0,
  `numero_giocatori_previsto` TINYINT UNSIGNED  NULL DEFAULT NULL,
  `stato_iscrizione`          ENUM('bozza','in_corso','completa') NOT NULL DEFAULT 'bozza',
  `stato_pagamento`           ENUM('non_pagato','parziale','pagato') NOT NULL DEFAULT 'non_pagato',
  `quota_iscrizione`          DECIMAL(8,2)      NOT NULL DEFAULT 180.00,
  `created_at`                TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_squadre_id_capitano` (`id_capitano`),
  KEY `idx_squadre_torneo` (`id_torneo`),
  CONSTRAINT `fk_squadre_capitano_account`
    FOREIGN KEY (`id_capitano`) REFERENCES `capitani` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_squadre_torneo`
    FOREIGN KEY (`id_torneo`) REFERENCES `tornei` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 5. GIOCATORI  (lista rose)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `giocatori` (
  `id`           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `id_squadra`   SMALLINT UNSIGNED NOT NULL,
  `nome`         VARCHAR(100)      NOT NULL,
  `cognome`      VARCHAR(100)      NOT NULL,
  `numero_maglia` TINYINT UNSIGNED NULL DEFAULT NULL,
  `ruolo`        ENUM('Giocatore','Portiere','Capitano') NOT NULL DEFAULT 'Giocatore',
  `email`        VARCHAR(190)      NULL DEFAULT NULL,
  `telefono`     VARCHAR(30)       NULL DEFAULT NULL,
  `data_nascita` DATE              NULL DEFAULT NULL,
  `indirizzo`    VARCHAR(255)      NULL DEFAULT NULL,
  `stato_iscrizione` VARCHAR(40)   NOT NULL DEFAULT 'non_registrato',
  PRIMARY KEY (`id`),
  KEY `fk_gioc_squadra` (`id_squadra`),
  CONSTRAINT `fk_gioc_squadra`
    FOREIGN KEY (`id_squadra`) REFERENCES `squadre` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER //
CREATE TRIGGER `trg_giocatori_max_nove_insert`
BEFORE INSERT ON `giocatori`
FOR EACH ROW
BEGIN
  DECLARE player_count INT UNSIGNED DEFAULT 0;
  SELECT COUNT(*) INTO player_count FROM `giocatori` WHERE `id_squadra` = NEW.`id_squadra`;
  IF player_count >= 9 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una squadra può avere al massimo 9 giocatori';
  END IF;
END//

CREATE TRIGGER `trg_giocatori_max_nove_update`
BEFORE UPDATE ON `giocatori`
FOR EACH ROW
BEGIN
  DECLARE player_count INT UNSIGNED DEFAULT 0;
  IF NEW.`id_squadra` <> OLD.`id_squadra` THEN
    SELECT COUNT(*) INTO player_count FROM `giocatori` WHERE `id_squadra` = NEW.`id_squadra`;
    IF player_count >= 9 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una squadra può avere al massimo 9 giocatori';
    END IF;
  END IF;
END//
DELIMITER ;


-- ------------------------------------------------------------
-- 6. PARTITE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `partite` (
  `id`              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `id_fase`         TINYINT UNSIGNED  NOT NULL,
  `id_squadra_casa` SMALLINT UNSIGNED NOT NULL,
  `id_squadra_osp`  SMALLINT UNSIGNED NOT NULL,
  `data_ora`        DATETIME          NULL DEFAULT NULL,
  `campo`           VARCHAR(80)       NULL DEFAULT NULL,
  -- Risultato (NULL = non ancora giocata)
  `gol_casa`        TINYINT UNSIGNED  NULL DEFAULT NULL,
  `gol_osp`         TINYINT UNSIGNED  NULL DEFAULT NULL,
  -- Shootout (tiebreaker opzionale in fase finale)
  `shootout_casa`   TINYINT UNSIGNED  NULL DEFAULT NULL,
  `shootout_osp`    TINYINT UNSIGNED  NULL DEFAULT NULL,
  `giocata`         TINYINT(1)        NOT NULL DEFAULT 0,
  `note`            TEXT              NULL DEFAULT NULL,
  `created_at`      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_partita_fase`       (`id_fase`),
  KEY `fk_partita_casa`       (`id_squadra_casa`),
  KEY `fk_partita_osp`        (`id_squadra_osp`),
  CONSTRAINT `fk_partita_fase`  FOREIGN KEY (`id_fase`)         REFERENCES `fasi`    (`id`),
  CONSTRAINT `fk_partita_casa`  FOREIGN KEY (`id_squadra_casa`) REFERENCES `squadre` (`id`),
  CONSTRAINT `fk_partita_osp`   FOREIGN KEY (`id_squadra_osp`)  REFERENCES `squadre` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 7. EVENTI_PARTITA  (gol, ammonizioni, espulsioni, MVP)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eventi_partita` (
  `id`            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `id_partita`    INT UNSIGNED      NOT NULL,
  `id_giocatore`  INT UNSIGNED      NULL DEFAULT NULL,   -- NULL = giocatore non censito
  `nome_libero`   VARCHAR(100)      NULL DEFAULT NULL,   -- fallback testo libero
  `id_squadra`    SMALLINT UNSIGNED NOT NULL,
  `tipo`          ENUM(
                    'gol',
                    'gol_autorete',
                    'ammonizione',
                    'espulsione',
                    'doppia_ammonizione',
                    'mvp'
                  )                 NOT NULL,
  `minuto`        TINYINT UNSIGNED  NULL DEFAULT NULL,
  `note`          VARCHAR(200)      NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_evento_partita`   (`id_partita`),
  KEY `fk_evento_giocatore` (`id_giocatore`),
  KEY `fk_evento_squadra`   (`id_squadra`),
  CONSTRAINT `fk_evento_partita`   FOREIGN KEY (`id_partita`)   REFERENCES `partite`   (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evento_giocatore` FOREIGN KEY (`id_giocatore`) REFERENCES `giocatori` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_evento_squadra`   FOREIGN KEY (`id_squadra`)   REFERENCES `squadre`   (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 8. CLASSIFICA  (ricalcolata via stored procedure / PHP)
--    Questa tabella viene SVUOTATA e RISCRITTA ad ogni aggiornamento
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `classifica` (
  `id`            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `id_squadra`    SMALLINT UNSIGNED NOT NULL UNIQUE,
  `punti`         SMALLINT          NOT NULL DEFAULT 0,
  `giocate`       TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `vinte`         TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `pareggiate`    TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `perse`         TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `gol_fatti`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `gol_subiti`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `diff_reti`     SMALLINT          NOT NULL DEFAULT 0,
  `ammonizioni`   TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `espulsioni`    TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `updated_at`    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_class_squadra` (`id_squadra`),
  CONSTRAINT `fk_class_squadra` FOREIGN KEY (`id_squadra`) REFERENCES `squadre` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELLE ISCRIZIONI, DOCUMENTI, CONSENSI E PAGAMENTI
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `genitori` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_giocatore` INT UNSIGNED NOT NULL,
  `nome`         VARCHAR(100) NOT NULL,
  `cognome`      VARCHAR(100) NOT NULL,
  `email`        VARCHAR(190) NOT NULL,
  `telefono`     VARCHAR(30) NULL DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_genitori_giocatore` (`id_giocatore`),
  CONSTRAINT `fk_genitori_giocatore`
    FOREIGN KEY (`id_giocatore`) REFERENCES `giocatori` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tipi_documento` (
  `id`         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codice`     VARCHAR(40) NOT NULL,
  `nome`       VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tipi_documento_codice` (`codice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `documenti` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_tipo`            TINYINT UNSIGNED NOT NULL,
  `versione`           VARCHAR(10) NOT NULL,
  `titolo`             VARCHAR(150) NOT NULL,
  `url_file`           VARCHAR(255) NULL DEFAULT NULL,
  `data_pubblicazione` DATE NOT NULL,
  `note`               VARCHAR(255) NULL DEFAULT NULL,
  `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documenti_tipo_versione` (`id_tipo`, `versione`),
  CONSTRAINT `fk_documenti_tipo`
    FOREIGN KEY (`id_tipo`) REFERENCES `tipi_documento` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tipi_documento` (`codice`, `nome`) VALUES
  ('privacy', 'Informativa privacy'),
  ('regolamento', 'Regolamento del torneo'),
  ('liberatoria_foto', 'Liberatoria foto e video')
ON DUPLICATE KEY UPDATE `nome` = VALUES(`nome`);

INSERT INTO `documenti` (`id_tipo`, `versione`, `titolo`, `url_file`, `data_pubblicazione`)
SELECT t.`id`, 1, t.`nome`, NULL, CURRENT_DATE
FROM `tipi_documento` t
WHERE t.`codice` IN ('privacy', 'regolamento', 'liberatoria_foto')
  AND NOT EXISTS (SELECT 1 FROM `documenti` d WHERE d.`id_tipo` = t.`id`);

CREATE TABLE IF NOT EXISTS `consensi` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_documento` INT UNSIGNED NOT NULL,
  `id_capitano`  INT UNSIGNED NULL DEFAULT NULL,
  `id_giocatore` INT UNSIGNED NULL DEFAULT NULL,
  `id_genitore`  INT UNSIGNED NULL DEFAULT NULL,
  `concesso`     TINYINT(1) NOT NULL DEFAULT 1,
  `data_ora`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address`   VARCHAR(45) NULL DEFAULT NULL,
  `user_agent`   VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_consensi_documento`
    FOREIGN KEY (`id_documento`) REFERENCES `documenti` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_consensi_capitano`
    FOREIGN KEY (`id_capitano`) REFERENCES `capitani` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_consensi_giocatore`
    FOREIGN KEY (`id_giocatore`) REFERENCES `giocatori` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_consensi_genitore`
    FOREIGN KEY (`id_genitore`) REFERENCES `genitori` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `token_verifica` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo`          ENUM('email_verifica','reset_password','invito_giocatore','consenso_genitore') NOT NULL,
  `token`         VARCHAR(255) NOT NULL,
  `id_capitano`   INT UNSIGNED NULL DEFAULT NULL,
  `id_giocatore`  INT UNSIGNED NULL DEFAULT NULL,
  `id_genitore`   INT UNSIGNED NULL DEFAULT NULL,
  `scadenza`      DATETIME NOT NULL,
  `usato_at`      DATETIME NULL DEFAULT NULL,
  `invalidato_at` DATETIME NULL DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_verifica_token` (`token`),
  KEY `idx_token_verifica_tipo` (`tipo`),
  KEY `idx_token_verifica_capitano` (`id_capitano`),
  KEY `idx_token_verifica_giocatore` (`id_giocatore`),
  KEY `idx_token_verifica_scadenza` (`scadenza`),
  CONSTRAINT `fk_token_verifica_capitano`
    FOREIGN KEY (`id_capitano`) REFERENCES `capitani` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_token_verifica_giocatore`
    FOREIGN KEY (`id_giocatore`) REFERENCES `giocatori` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_token_verifica_genitore`
    FOREIGN KEY (`id_genitore`) REFERENCES `genitori` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `metodi_pagamento` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`        VARCHAR(100) NOT NULL,
  `descrizione` VARCHAR(255) NULL DEFAULT NULL,
  `istruzioni`  TEXT NULL,
  `iban`        VARCHAR(34) NULL DEFAULT NULL,
  `ordine`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `attivo`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transazioni` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_squadra`          SMALLINT UNSIGNED NOT NULL,
  `importo`             DECIMAL(8,2) NOT NULL,
  `id_metodo_pagamento` TINYINT UNSIGNED NOT NULL,
  `data_pagamento`      DATE NOT NULL,
  `stato`               ENUM('confermata','in_attesa','annullata') NOT NULL DEFAULT 'confermata',
  `note`                TEXT NULL,
  `id_organizzatore`    INT UNSIGNED NOT NULL,
  `registrato_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modificato_at`       TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_transazioni_squadra`
    FOREIGN KEY (`id_squadra`) REFERENCES `squadre` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_transazioni_metodo`
    FOREIGN KEY (`id_metodo_pagamento`) REFERENCES `metodi_pagamento` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_transazioni_organizzatore`
    FOREIGN KEY (`id_organizzatore`) REFERENCES `admin` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `impostazioni` (
  `chiave`      VARCHAR(60) NOT NULL,
  `valore`      VARCHAR(255) NOT NULL DEFAULT '',
  `descrizione` VARCHAR(150) NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`chiave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `impostazioni` (`chiave`, `valore`, `descrizione`) VALUES
('quota_iscrizione_default', '180.00', 'Quota standard di iscrizione per squadra')
ON DUPLICATE KEY UPDATE `valore` = VALUES(`valore`), `descrizione` = VALUES(`descrizione`);


-- ============================================================
--  VISTE UTILI  (opzionali ma comode per le query della UI)
-- ============================================================

-- Vista classifica completa ordinata secondo regolamento
CREATE OR REPLACE VIEW `v_classifica` AS
SELECT
  c.*,
  s.nome      AS nome_squadra,
  s.colore    AS colore_squadra,
  s.logo_url
FROM `classifica` c
JOIN `squadre`    s ON s.id = c.id_squadra
ORDER BY
  c.punti         DESC,
  -- scontri diretti: gestiti in PHP (troppo complessi per una view)
  c.diff_reti     DESC,
  c.gol_fatti     DESC,
  c.gol_subiti    ASC,
  c.ammonizioni   ASC,
  c.espulsioni    ASC,
  s.nome          ASC;


-- Vista capocannoniere
CREATE OR REPLACE VIEW `v_capocannonieri` AS
SELECT
  COALESCE(CONCAT(g.nome, ' ', g.cognome), e.nome_libero) AS giocatore,
  s.nome       AS squadra,
  s.colore     AS colore_squadra,
  COUNT(*)     AS gol
FROM `eventi_partita` e
LEFT JOIN `giocatori` g ON g.id = e.id_giocatore
JOIN  `squadre`       s ON s.id = e.id_squadra
WHERE e.tipo = 'gol'
GROUP BY e.id_giocatore, e.nome_libero, e.id_squadra
ORDER BY gol DESC;
