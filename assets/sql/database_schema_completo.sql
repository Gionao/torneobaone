-- ===========================================================================
-- TORNEO BAONE - SCHEMA COMPLETO UNIFICATO
-- Versione bozza 2026-11-04 | Nuova installazione su database VUOTO
-- Fonte: ZIP progetto + riprogettazione ruoli fornita in PDF.
-- MySQL / MariaDB InnoDB, utf8mb4. Selezionare il database in phpMyAdmin.
-- NON ESEGUIRE direttamente su un database con dati reali: questo NON e'
-- uno script di migrazione e CREATE TABLE IF NOT EXISTS non aggiorna tabelle.
-- Nessun utente/password di default per sicurezza: creare il primo superadmin
-- tramite script applicativo protetto che utilizzi password_hash().
-- I controlli dei permessi, login e reindirizzamento devono essere nel PHP.
-- I capitani rimangono separati dal sistema degli utenti staff.
-- ===========================================================================

SET NAMES utf8mb4;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = '+00:00';

-- ------------------------------------------------------------
-- 1. ADMIN - Credenziali pannello amministratore
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_username` (`username`),
  KEY `idx_admin_active` (`attivo`, `is_super_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ruoli predefiniti: modificare in modo coordinato SQL e controlli PHP.
CREATE TABLE IF NOT EXISTS `staff_ruoli_area` (
  `area` VARCHAR(32) NOT NULL,
  `ruolo` VARCHAR(32) NOT NULL,
  `descrizione` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`area`, `ruolo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `staff_ruoli_area` (`area`,`ruolo`,`descrizione`) VALUES
('torneo','amministratore','Gestione generale torneo, squadre, iscrizioni e pagamenti'),
('torneo','segreteria','Iscrizioni e pagamenti, senza amministrazione globale'),
('partite','gestore','Gestione calendario, risultati ed eventi'),
('partite','telecronista','Inserimento eventi e risultati con funzioni limitate'),
('pos','amministratore','Gestione completa del POS, non degli utenti globali'),
('pos','operatore','Cassa e ordini dalla postazione assegnata'),
('pos','bar','Preparazione e gestione ordini bar'),
('pos','cucina','Preparazione e gestione ordini cucina'),
('archivio','amministratore','Gestione completa archivio multimediale'),
('archivio','editor','Organizzazione e modifica media autorizzati'),
('archivio','uploader','Caricamento file nelle edizioni autorizzate')
ON DUPLICATE KEY UPDATE `descrizione` = VALUES(`descrizione`);

CREATE TABLE IF NOT EXISTS `staff_assegnazioni` (
  `id_admin` INT UNSIGNED NOT NULL,
  `area` VARCHAR(32) NOT NULL,
  `ruolo` VARCHAR(32) NOT NULL,
  `station_name` VARCHAR(80) NULL DEFAULT NULL COMMENT 'Postazione specifica del POS',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_admin`,`area`),
  KEY `idx_staff_assegnazioni_ruolo` (`area`,`ruolo`),
  CONSTRAINT `fk_staff_assegnazioni_utente` FOREIGN KEY (`id_admin`)
    REFERENCES `admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_staff_assegnazioni_ruolo` FOREIGN KEY (`area`,`ruolo`)
    REFERENCES `staff_ruoli_area` (`area`,`ruolo`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log di sicurezza. Non sostituisce la verifica dei permessi nel codice PHP.
CREATE TABLE IF NOT EXISTS `staff_audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id` INT UNSIGNED NULL DEFAULT NULL,
  `target_id` INT UNSIGNED NULL DEFAULT NULL,
  `azione` VARCHAR(60) NOT NULL,
  `area` VARCHAR(32) NULL DEFAULT NULL,
  `dettagli` TEXT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_audit_actor` (`actor_id`,`created_at`),
  KEY `idx_staff_audit_target` (`target_id`,`created_at`),
  CONSTRAINT `fk_staff_audit_actor` FOREIGN KEY (`actor_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_staff_audit_target` FOREIGN KEY (`target_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `quota_iscrizione`          DECIMAL(8,2)      NOT NULL DEFAULT 200.00,
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
CREATE TRIGGER `trg_giocatori_max_dieci_insert`
BEFORE INSERT ON `giocatori`
FOR EACH ROW
BEGIN
  DECLARE player_count INT UNSIGNED DEFAULT 0;
  SELECT COUNT(*) INTO player_count FROM `giocatori` WHERE `id_squadra` = NEW.`id_squadra`;
  IF player_count >= 10 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una squadra può avere al massimo 10 giocatori';
  END IF;
END//

CREATE TRIGGER `trg_giocatori_max_dieci_update`
BEFORE UPDATE ON `giocatori`
FOR EACH ROW
BEGIN
  DECLARE player_count INT UNSIGNED DEFAULT 0;
  IF NEW.`id_squadra` <> OLD.`id_squadra` THEN
    SELECT COUNT(*) INTO player_count FROM `giocatori` WHERE `id_squadra` = NEW.`id_squadra`;
    IF player_count >= 10 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una squadra può avere al massimo 10 giocatori';
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
('quota_iscrizione_default', '200.00', 'Quota standard di iscrizione per squadra')
ON DUPLICATE KEY UPDATE `valore` = VALUES(`valore`), `descrizione` = VALUES(`descrizione`);

-- ------------------------------------------------------------
-- TABELLE POS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pos_settings` (
  `key`        VARCHAR(60) NOT NULL,
  `value`      VARCHAR(255) NOT NULL DEFAULT '',
  `label`      VARCHAR(120) NOT NULL DEFAULT '',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pos_settings` (`key`, `value`, `label`) VALUES
('cover_fee', '1.50', 'Prezzo coperto (€)'),
('takeaway_fee', '1.00', 'Costo asporto (€)')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

CREATE TABLE IF NOT EXISTS `pos_categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(80) NOT NULL,
  `department` ENUM('kitchen','bar') NOT NULL DEFAULT 'kitchen',
  `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_ingredients` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(80) NOT NULL,
  `is_removable` TINYINT(1) NOT NULL DEFAULT 1,
  `is_extra`     TINYINT(1) NOT NULL DEFAULT 0,
  `extra_price`  DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_products` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`  INT UNSIGNED NOT NULL,
  `name`         VARCHAR(120) NOT NULL,
  `description`  TEXT NULL,
  `price`        DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `product_kind` VARCHAR(30) NULL DEFAULT NULL,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_pos_products_category` (`category_id`),
  CONSTRAINT `fk_pos_products_category`
    FOREIGN KEY (`category_id`) REFERENCES `pos_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_product_ingredients` (
  `product_id`    INT UNSIGNED NOT NULL,
  `ingredient_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`product_id`, `ingredient_id`),
  CONSTRAINT `fk_pos_pi_product`
    FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pos_pi_ingredient`
    FOREIGN KEY (`ingredient_id`) REFERENCES `pos_ingredients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_coupons` (
  `id`                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`                     VARCHAR(20) NOT NULL,
  `product_type_restriction` VARCHAR(30) NOT NULL DEFAULT 'any',
  `discount_type`            ENUM('fixed','percent') NOT NULL DEFAULT 'fixed',
  `discount_value`           DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `is_used`                  TINYINT(1) NOT NULL DEFAULT 0,
  `used_at`                  DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pos_coupon_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_orders` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `daily_number`  SMALLINT UNSIGNED NOT NULL,
  `customer_name` VARCHAR(100) NOT NULL DEFAULT '',
  `table_number`  VARCHAR(20) NULL DEFAULT NULL,
  `is_takeaway`   TINYINT(1) NOT NULL DEFAULT 0,
  `covers`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `cover_fee`     DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `takeaway_fee`  DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `admin_id`      INT UNSIGNED NOT NULL,
  `station_name`  VARCHAR(80) NULL DEFAULT NULL,
  `status`        ENUM('pending','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `total_amount`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pos_orders_date` (`created_at`),
  KEY `idx_pos_orders_status` (`status`),
  KEY `idx_pos_orders_admin` (`admin_id`),
  CONSTRAINT `fk_pos_orders_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_order_items` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`         INT UNSIGNED NOT NULL,
  `product_id`       INT UNSIGNED NOT NULL,
  `quantity`         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `item_note`        VARCHAR(255) NULL DEFAULT NULL,
  `unit_price`       DECIMAL(8,2) NOT NULL,
  `discount_applied` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `coupon_id`        INT UNSIGNED NULL DEFAULT NULL,
  `status`           ENUM('pending','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `idx_pos_items_order` (`order_id`),
  KEY `idx_pos_items_product` (`product_id`),
  KEY `idx_pos_items_coupon` (`coupon_id`),
  CONSTRAINT `fk_pos_items_order`
    FOREIGN KEY (`order_id`) REFERENCES `pos_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pos_items_product`
    FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`),
  CONSTRAINT `fk_pos_items_coupon`
    FOREIGN KEY (`coupon_id`) REFERENCES `pos_coupons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_order_item_modifications` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_item_id` INT UNSIGNED NOT NULL,
  `ingredient_id` INT UNSIGNED NOT NULL,
  `action`        ENUM('removed','added') NOT NULL,
  `extra_price`   DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_pos_modifications_item` (`order_item_id`),
  KEY `idx_pos_modifications_ingredient` (`ingredient_id`),
  CONSTRAINT `fk_pos_modifications_item`
    FOREIGN KEY (`order_item_id`) REFERENCES `pos_order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pos_modifications_ingredient`
    FOREIGN KEY (`ingredient_id`) REFERENCES `pos_ingredients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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


-- ===========================================================================
-- ARCHIVIO MULTIMEDIALE (schema originale unificato)
-- ===========================================================================

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
