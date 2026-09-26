-- --------------------------------------------------------
-- Nuovo schema per il sistema di iscrizione online
-- Torneo Calcio a 5 Baone 2026
-- --------------------------------------------------------

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS tornei (
  id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome        VARCHAR(100) NOT NULL,
  stagione    VARCHAR(20) NULL,
  data_inizio DATE NULL,
  data_fine   DATE NULL,
  attivo      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capitani (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome                VARCHAR(100) NOT NULL,
  cognome             VARCHAR(100) NOT NULL,
  email               VARCHAR(190) NOT NULL UNIQUE,
  telefono            VARCHAR(30) NULL,
  data_nascita        DATE NULL,
  indirizzo           VARCHAR(255) NULL,
  password_hash       VARCHAR(255) NOT NULL,
  email_verificata_at DATETIME NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS genitori (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_giocatore INT UNSIGNED NOT NULL UNIQUE,
  nome         VARCHAR(100) NOT NULL,
  cognome      VARCHAR(100) NOT NULL,
  email        VARCHAR(190) NOT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_genitori_giocatore
    FOREIGN KEY (id_giocatore) REFERENCES giocatori(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tipi_documento (
  id         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codice     VARCHAR(40) NOT NULL UNIQUE,
  nome       VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documenti (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_tipo            TINYINT UNSIGNED NOT NULL,
  versione           SMALLINT UNSIGNED NOT NULL,
  titolo             VARCHAR(150) NOT NULL,
  url_file           VARCHAR(255) NULL,
  data_pubblicazione DATE NOT NULL,
  note               VARCHAR(255) NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_documenti_tipo_versione (id_tipo, versione),
  CONSTRAINT fk_documenti_tipo
    FOREIGN KEY (id_tipo) REFERENCES tipi_documento(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consensi (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_documento INT UNSIGNED NOT NULL,
  id_capitano  INT UNSIGNED NULL,
  id_giocatore INT UNSIGNED NULL,
  id_genitore  INT UNSIGNED NULL,
  concesso     TINYINT(1) NOT NULL DEFAULT 1,
  data_ora     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address   VARCHAR(45) NULL,
  user_agent   VARCHAR(255) NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_consensi_documento
    FOREIGN KEY (id_documento) REFERENCES documenti(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_consensi_capitano
    FOREIGN KEY (id_capitano) REFERENCES capitani(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_consensi_giocatore
    FOREIGN KEY (id_giocatore) REFERENCES giocatori(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_consensi_genitore
    FOREIGN KEY (id_genitore) REFERENCES genitori(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS token_verifica (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo          ENUM('email_verifica','reset_password','invito_giocatore','consenso_genitore') NOT NULL,
  token         VARCHAR(100) NOT NULL UNIQUE,
  id_capitano   INT UNSIGNED NULL,
  id_giocatore  INT UNSIGNED NULL,
  id_genitore   INT UNSIGNED NULL,
  scadenza      DATETIME NOT NULL,
  usato_at      DATETIME NULL,
  invalidato_at DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_token_capitano
    FOREIGN KEY (id_capitano) REFERENCES capitani(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_token_giocatore
    FOREIGN KEY (id_giocatore) REFERENCES giocatori(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_token_genitore
    FOREIGN KEY (id_genitore) REFERENCES genitori(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS metodi_pagamento (
  id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome        VARCHAR(100) NOT NULL,
  descrizione VARCHAR(255) NULL,
  istruzioni  TEXT NULL,
  iban        VARCHAR(34) NULL,
  ordine      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  attivo      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transazioni (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_squadra          SMALLINT UNSIGNED NOT NULL,
  importo             DECIMAL(8,2) NOT NULL,
  id_metodo_pagamento TINYINT UNSIGNED NOT NULL,
  data_pagamento      DATE NOT NULL,
  stato               ENUM('confermata','in_attesa','annullata') NOT NULL DEFAULT 'confermata',
  note                TEXT NULL,
  id_organizzatore    INT UNSIGNED NOT NULL,
  registrato_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificato_at       TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_transazioni_squadra
    FOREIGN KEY (id_squadra) REFERENCES squadre(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_transazioni_metodo
    FOREIGN KEY (id_metodo_pagamento) REFERENCES metodi_pagamento(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_transazioni_organizzatore
    FOREIGN KEY (id_organizzatore) REFERENCES admin(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS impostazioni (
  chiave      VARCHAR(60) NOT NULL,
  valore      VARCHAR(255) NOT NULL DEFAULT '',
  descrizione VARCHAR(150) NULL,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (chiave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dati iniziali minimi
-- --------------------------------------------------------
INSERT INTO impostazioni (chiave, valore, descrizione)
VALUES ('quota_iscrizione_default', '180.00', 'Quota standard di iscrizione per squadra')
ON DUPLICATE KEY UPDATE valore = VALUES(valore), descrizione = VALUES(descrizione);

INSERT INTO tipi_documento (codice, nome)
VALUES
  ('regolamento', 'Regolamento'),
  ('privacy', 'Informativa privacy'),
  ('liberatoria_foto', 'Liberatoria foto')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- --------------------------------------------------------
-- Note:
-- - Il database esistente ha già le tabelle squadre, giocatori, admin.
-- - Le nuove colonne richieste vanno aggiunte in fase di migrazione
--   a quelle tabelle esistenti, come indicato nel documento di progetto.
-- - Non sono create VIEW, TRIGGER, PROCEDURE o EVENT.
-- --------------------------------------------------------
