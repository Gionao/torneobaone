-- ============================================================
--  TORNEO CALCIO A 5 - BAONE 2026
--  Schema Database per phpMyAdmin / Altervista MySQL
--  Charset: utf8mb4  |  Collation: utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS 'torneobaone'
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+02:00";

-- ------------------------------------------------------------
-- 1. ADMIN - Credenziali pannello amministratore
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(60)     NOT NULL UNIQUE,
  `password_hash` VARCHAR(255)    NOT NULL,          -- bcrypt via password_hash()
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Utente admin di default  (password: Admin2026! — DA CAMBIARE subito)
INSERT INTO `admin` (`username`, `password_hash`) VALUES
('admin', '$2y$12$9z8Q2k1F0pL7mX3vN5tRLeY4bJ6wD8sC0hU2iA1gK3nP7qW5oE9rO');
-- ^ hash generato con: password_hash('Admin2026!', PASSWORD_BCRYPT, ['cost'=>12])
-- IMPORTANTE: rigenera questo hash con la tua password reale prima del deploy!


-- ------------------------------------------------------------
-- 2. FASI - Girone / Playoff / Semifinali / Finali
-- ------------------------------------------------------------
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
-- 3. SQUADRE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `squadre` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`        VARCHAR(80)       NOT NULL,
  `colore`      VARCHAR(7)        NOT NULL DEFAULT '#1a3a5c',  -- hex per badge UI
  `logo_url`    VARCHAR(255)      NULL DEFAULT NULL,
  `capitano`    VARCHAR(100)      NULL DEFAULT NULL,           -- nome completo capitano (cache per liste/visualizzazioni)
  `capitano_giocatore_id` INT UNSIGNED NULL DEFAULT NULL,       -- FK al record giocatore (ruolo='Capitano') in rosa
  `created_at`  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_squadra_capitano` (`capitano_giocatore_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 4. GIOCATORI  (lista rose)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `giocatori` (
  `id`           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `id_squadra`   SMALLINT UNSIGNED NOT NULL,
  `nome`         VARCHAR(100)      NOT NULL,
  `cognome`      VARCHAR(100)      NOT NULL,
  `numero_maglia` TINYINT UNSIGNED NULL DEFAULT NULL,
  `ruolo`        ENUM('Giocatore','Portiere','Capitano') NOT NULL DEFAULT 'Giocatore',
  PRIMARY KEY (`id`),
  KEY `fk_gioc_squadra` (`id_squadra`),
  CONSTRAINT `fk_gioc_squadra`
    FOREIGN KEY (`id_squadra`) REFERENCES `squadre` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `squadre`
  ADD CONSTRAINT `fk_squadra_capitano`
    FOREIGN KEY (`capitano_giocatore_id`) REFERENCES `giocatori` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;


-- ------------------------------------------------------------
-- 5. PARTITE
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
-- 6. EVENTI_PARTITA  (gol, ammonizioni, espulsioni, MVP)
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
-- 7. CLASSIFICA  (ricalcolata via stored procedure / PHP)
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
