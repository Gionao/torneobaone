-- Torneo Baone — estensione iscrizioni (migrazione additiva, MySQL 8+).
-- PRE-FLIGHT OBBLIGATORIO: backup ripristinabile; controllare SELECT VERSION();
-- verificare le colonne indicate con SHOW CREATE TABLE prima dell'esecuzione.
-- Non eseguire su produzione senza change window e approvazione separata.
-- Le ALTER TABLE sono intenzionalmente una tantum (MySQL non offre ADD COLUMN
-- IF NOT EXISTS in tutte le versioni supportate); fermarsi se una colonna esiste.

ALTER TABLE tornei
  ADD COLUMN max_squadre SMALLINT UNSIGNED NULL DEFAULT NULL,
  ADD COLUMN scadenza_iscrizioni DATETIME NULL DEFAULT NULL,
  ADD COLUMN iscrizioni_riaperte_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN iscrizioni_riaperte_da INT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE giocatori
  ADD COLUMN id_torneo TINYINT UNSIGNED NULL DEFAULT NULL,
  ADD COLUMN codice_fiscale CHAR(16) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
  ADD COLUMN email_verificata_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN ruolo_roster VARCHAR(20) NOT NULL DEFAULT 'giocatore',
  ADD COLUMN sostituisce_id INT UNSIGNED NULL DEFAULT NULL,
  ADD COLUMN ritirato_at DATETIME NULL DEFAULT NULL;

UPDATE giocatori g
JOIN squadre s ON s.id = g.id_squadra
SET g.id_torneo = s.id_torneo
WHERE g.id_torneo IS NULL;

ALTER TABLE giocatori
  ADD COLUMN codice_fiscale_attivo CHAR(16) CHARACTER SET ascii COLLATE ascii_general_ci
    GENERATED ALWAYS AS (CASE WHEN stato_iscrizione <> 'annullato' THEN codice_fiscale ELSE NULL END) STORED,
  ADD KEY `idx_giocatori_torneo_squadra` (`id_torneo`,`id_squadra`),
  ADD UNIQUE KEY `uq_giocatori_cf_torneo_attivi` (`id_torneo`,`codice_fiscale_attivo`),
  ADD CONSTRAINT `fk_giocatori_torneo` FOREIGN KEY (`id_torneo`) REFERENCES `tornei` (`id`) ON DELETE RESTRICT;

ALTER TABLE genitori
  ADD COLUMN codice_fiscale CHAR(16) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
  ADD COLUMN indirizzo VARCHAR(255) NULL DEFAULT NULL,
  ADD COLUMN dichiara_tutela TINYINT(1) NULL DEFAULT NULL,
  ADD COLUMN email_verificata_at DATETIME NULL DEFAULT NULL;

ALTER TABLE squadre
  ADD COLUMN stato_approvazione ENUM('in_attesa','approvata','respinta','sospesa') NOT NULL DEFAULT 'in_attesa',
  ADD COLUMN approvata_da INT UNSIGNED NULL DEFAULT NULL,
  ADD COLUMN approvata_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN approvazione_note VARCHAR(500) NULL DEFAULT NULL;

ALTER TABLE squadre
  ADD CONSTRAINT `fk_squadre_approvata_da` FOREIGN KEY (`approvata_da`) REFERENCES `admin` (`id`) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `consensi_eventi` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_giocatore` INT UNSIGNED NULL,
  `id_capitano` INT UNSIGNED NULL,
  `id_genitore` INT UNSIGNED NULL,
  `codice_controllo` VARCHAR(40) NOT NULL,
  `id_documento` INT UNSIGNED NOT NULL,
  `valore` ENUM('accettato','rifiutato','revocato') NOT NULL,
  `attore_tipo` ENUM('partecipante','genitore','staff') NOT NULL,
  `id_staff` INT UNSIGNED NULL,
  `canale` VARCHAR(30) NOT NULL,
  `riferimento_verifica` BIGINT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_consensi_eventi_giocatore` (`id_giocatore`,`codice_controllo`,`created_at`),
  KEY `idx_consensi_eventi_genitore` (`id_genitore`,`created_at`),
  CONSTRAINT `fk_cons_evt_gioc` FOREIGN KEY (`id_giocatore`) REFERENCES `giocatori` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cons_evt_cap` FOREIGN KEY (`id_capitano`) REFERENCES `capitani` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cons_evt_gen` FOREIGN KEY (`id_genitore`) REFERENCES `genitori` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cons_evt_doc` FOREIGN KEY (`id_documento`) REFERENCES `documenti` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cons_evt_staff` FOREIGN KEY (`id_staff`) REFERENCES `admin` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_cons_evt_soggetto` CHECK (
    (id_giocatore IS NOT NULL) + (id_capitano IS NOT NULL) + (id_genitore IS NOT NULL) = 1
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `autorizzazioni_genitore` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_genitore` INT UNSIGNED NOT NULL,
  `id_giocatore` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `otp_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `link_scade_at` DATETIME NOT NULL,
  `otp_scade_at` DATETIME NULL,
  `tentativi_otp` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `ultimo_invio_at` DATETIME NOT NULL,
  `verificato_at` DATETIME NULL,
  `consumato_at` DATETIME NULL,
  `invalidato_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aut_gen_token` (`token_hash`),
  KEY `idx_aut_gen_player` (`id_giocatore`,`consumato_at`,`invalidato_at`),
  CONSTRAINT `fk_aut_gen_parent` FOREIGN KEY (`id_genitore`) REFERENCES `genitori` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_aut_gen_player` FOREIGN KEY (`id_giocatore`) REFERENCES `giocatori` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `autorizzazioni_cartacee` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_giocatore` INT UNSIGNED NOT NULL,
  `riferimento` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `stato` ENUM('generata','ricevuta','validata','respinta','sostituita') NOT NULL DEFAULT 'generata',
  `versioni_documenti` JSON NOT NULL,
  `ricevuta_at` DATETIME NULL,
  `validata_at` DATETIME NULL,
  `validata_da` INT UNSIGNED NULL,
  `note_staff` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aut_cart_ref` (`riferimento`),
  KEY `idx_aut_cart_player` (`id_giocatore`,`stato`),
  CONSTRAINT `fk_aut_cart_player` FOREIGN KEY (`id_giocatore`) REFERENCES `giocatori` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_aut_cart_staff` FOREIGN KEY (`validata_da`) REFERENCES `admin` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_iscrizioni` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_admin` INT UNSIGNED NULL,
  `id_squadra` SMALLINT UNSIGNED NULL,
  `id_giocatore` INT UNSIGNED NULL,
  `azione` VARCHAR(50) NOT NULL,
  `dettagli` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_iscr_team` (`id_squadra`,`created_at`),
  KEY `idx_audit_iscr_player` (`id_giocatore`,`created_at`),
  CONSTRAINT `fk_audit_iscr_admin` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PRE-FLIGHT DUPLICATI (risolvere manualmente prima di aggiungere gli indici):
-- SELECT id_squadra, UPPER(REPLACE(TRIM(codice_fiscale),' ','')), COUNT(*)
-- FROM giocatori WHERE codice_fiscale IS NOT NULL GROUP BY 1,2 HAVING COUNT(*)>1;
-- Identità storiche senza CF non vanno fuse automaticamente.
-- L'indice univoco viene aggiunto in una migrazione successiva dopo bonifica.
