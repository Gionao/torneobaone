-- ============================================================
--  TORNEO CALCIO A 5 - BAONE 2026 + POS ORDINI
--  Schema completo database (installazione da zero)
--  Database: my_torneobaone
--  Charset: utf8mb4  |  Collation: utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS `my_torneobaone`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `my_torneobaone`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+02:00";


-- ============================================================
--  TORNEO — Partite, squadre, classifica
-- ============================================================

-- ------------------------------------------------------------
-- 1. ADMIN — Credenziali pannello (torneo + POS)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(60)     NOT NULL,
  `role`          ENUM('admin','operatore','bar','cucina')
                  NOT NULL DEFAULT 'operatore',
  `station_name`  VARCHAR(80)     NULL DEFAULT NULL,
  `password_hash` VARCHAR(255)    NOT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Utente admin di default (password: Admin2026! — DA CAMBIARE subito)
INSERT INTO `admin` (`username`, `role`, `password_hash`) VALUES
('admin', 'admin', '$2y$12$9z8Q2k1F0pL7mX3vN5tRLeY4bJ6wD8sC0hU2iA1gK3nP7qW5oE9rO')
ON DUPLICATE KEY UPDATE `role` = 'admin';
-- Rigenera il hash con password_hash() prima del deploy in produzione.


-- ------------------------------------------------------------
-- 2. FASI — Girone / Playoff / Semifinali / Finali
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fasi` (
  `id`     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`   VARCHAR(50)      NOT NULL,
  `ordine` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `fasi` (`nome`, `ordine`) VALUES
('Girone',           1),
('Playoff',          2),
('Semifinale',       3),
('Finale 3°/4°',     4),
('Finale 1°/2°',     5)
ON DUPLICATE KEY UPDATE `nome` = VALUES(`nome`), `ordine` = VALUES(`ordine`);


-- ------------------------------------------------------------
-- 3. SQUADRE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `squadre` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`        VARCHAR(80)       NOT NULL,
  `colore`      VARCHAR(7)        NOT NULL DEFAULT '#1a3a5c',
  `logo_url`    VARCHAR(255)      NULL DEFAULT NULL,
  `capitano`    VARCHAR(100)      NULL DEFAULT NULL,
  `created_at`  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 4. GIOCATORI — Rose squadre
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `giocatori` (
  `id`            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `id_squadra`    SMALLINT UNSIGNED NOT NULL,
  `nome`          VARCHAR(100)      NOT NULL,
  `cognome`       VARCHAR(100)      NOT NULL,
  `numero_maglia` TINYINT UNSIGNED  NULL DEFAULT NULL,
  `e_portiere`    TINYINT(1)        NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_gioc_squadra` (`id_squadra`),
  CONSTRAINT `fk_gioc_squadra`
    FOREIGN KEY (`id_squadra`) REFERENCES `squadre` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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
  `gol_casa`        TINYINT UNSIGNED  NULL DEFAULT NULL,
  `gol_osp`         TINYINT UNSIGNED  NULL DEFAULT NULL,
  `shootout_casa`   TINYINT UNSIGNED  NULL DEFAULT NULL,
  `shootout_osp`    TINYINT UNSIGNED  NULL DEFAULT NULL,
  `giocata`         TINYINT(1)        NOT NULL DEFAULT 0,
  `note`            TEXT              NULL DEFAULT NULL,
  `created_at`      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_partita_fase` (`id_fase`),
  KEY `fk_partita_casa` (`id_squadra_casa`),
  KEY `fk_partita_osp`  (`id_squadra_osp`),
  CONSTRAINT `fk_partita_fase` FOREIGN KEY (`id_fase`)         REFERENCES `fasi` (`id`),
  CONSTRAINT `fk_partita_casa` FOREIGN KEY (`id_squadra_casa`) REFERENCES `squadre` (`id`),
  CONSTRAINT `fk_partita_osp`  FOREIGN KEY (`id_squadra_osp`)  REFERENCES `squadre` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 6. EVENTI_PARTITA — Gol, cartellini, MVP
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eventi_partita` (
  `id`           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `id_partita`   INT UNSIGNED      NOT NULL,
  `id_giocatore` INT UNSIGNED      NULL DEFAULT NULL,
  `nome_libero`  VARCHAR(100)      NULL DEFAULT NULL,
  `id_squadra`   SMALLINT UNSIGNED NOT NULL,
  `tipo`         ENUM(
                   'gol',
                   'gol_autorete',
                   'ammonizione',
                   'espulsione',
                   'doppia_ammonizione',
                   'mvp'
                 ) NOT NULL,
  `minuto`       TINYINT UNSIGNED  NULL DEFAULT NULL,
  `note`         VARCHAR(200)      NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_evento_partita`   (`id_partita`),
  KEY `fk_evento_giocatore` (`id_giocatore`),
  KEY `fk_evento_squadra`   (`id_squadra`),
  CONSTRAINT `fk_evento_partita`   FOREIGN KEY (`id_partita`)   REFERENCES `partite` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evento_giocatore` FOREIGN KEY (`id_giocatore`) REFERENCES `giocatori` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_evento_squadra`   FOREIGN KEY (`id_squadra`)   REFERENCES `squadre` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 7. CLASSIFICA — Ricalcolata da PHP
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `classifica` (
  `id`          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `id_squadra`  SMALLINT UNSIGNED NOT NULL,
  `punti`       SMALLINT          NOT NULL DEFAULT 0,
  `giocate`     TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `vinte`       TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `pareggiate`  TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `perse`       TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `gol_fatti`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `gol_subiti`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `diff_reti`   SMALLINT          NOT NULL DEFAULT 0,
  `ammonizioni` TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `espulsioni`  TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `updated_at`  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_class_squadra` (`id_squadra`),
  CONSTRAINT `fk_class_squadra` FOREIGN KEY (`id_squadra`) REFERENCES `squadre` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  POS — Menu, coupon e ordini
-- ============================================================

-- ------------------------------------------------------------
-- 8. Categorie / reparti (cucina, bar)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pos_categories` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(50) NOT NULL,
  `department` ENUM('kitchen','bar') NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 9. Prodotti
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pos_products` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT           NOT NULL,
  `name`        VARCHAR(100)  NOT NULL,
  `description` TEXT          NULL,
  `price`       DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pos_product_category`
    FOREIGN KEY (`category_id`) REFERENCES `pos_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 10. Ingredienti
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pos_ingredients` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 11. Prodotto ↔ ingredienti
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pos_product_ingredients` (
  `product_id`    INT          NOT NULL,
  `ingredient_id` INT          NOT NULL,
  `is_removable`  TINYINT(1)   NOT NULL DEFAULT 1,
  `is_extra`      TINYINT(1)   NOT NULL DEFAULT 0,
  `extra_price`   DECIMAL(4,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`product_id`, `ingredient_id`),
  CONSTRAINT `fk_ppi_product`
    FOREIGN KEY (`product_id`)    REFERENCES `pos_products` (`id`),
  CONSTRAINT `fk_ppi_ingredient`
    FOREIGN KEY (`ingredient_id`) REFERENCES `pos_ingredients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 12. Coupon monouso
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pos_coupons` (
  `id`                       INT AUTO_INCREMENT PRIMARY KEY,
  `code`                     VARCHAR(20) NOT NULL,
  `product_type_restriction` ENUM('any','sandwich','drink','snack') NOT NULL DEFAULT 'any',
  `is_used`                  TINYINT(1)  NOT NULL DEFAULT 0,
  `used_at`                  TIMESTAMP NULL DEFAULT NULL,
  `created_at`               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_pos_coupon_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 13. Ordini (testata)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pos_orders` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `daily_number`  INT          NOT NULL,
  `customer_name` VARCHAR(100) NOT NULL DEFAULT '',
  `covers`            TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `destination_type`  ENUM('tavolo','asporto','campetto') NOT NULL DEFAULT 'tavolo',
  `table_number`      SMALLINT UNSIGNED NULL DEFAULT NULL,
  `admin_id`          INT UNSIGNED NULL DEFAULT NULL,
  `station_name`  VARCHAR(80)  NULL DEFAULT NULL,
  `status`        ENUM('pending','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `total_amount`  DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `fk_order_admin` (`admin_id`),
  CONSTRAINT `fk_order_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 14. Righe ordine
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pos_order_items` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `order_id`         INT          NOT NULL,
  `product_id`       INT          NOT NULL,
  `coupon_id`        INT          NULL DEFAULT NULL,
  `quantity`         INT          NOT NULL DEFAULT 1,
  `unit_price`       DECIMAL(6,2) NOT NULL,
  `discount_applied` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `status`           ENUM('pending','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_pos_order_item_coupon` (`coupon_id`),
  CONSTRAINT `fk_poi_order`
    FOREIGN KEY (`order_id`)   REFERENCES `pos_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_poi_product`
    FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`),
  CONSTRAINT `fk_poi_coupon`
    FOREIGN KEY (`coupon_id`)  REFERENCES `pos_coupons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 15. Personalizzazioni riga ordine
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pos_order_item_modifications` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `order_item_id` INT          NOT NULL,
  `ingredient_id` INT          NOT NULL,
  `action`        ENUM('removed','added') NOT NULL,
  `extra_price`   DECIMAL(4,2) NOT NULL DEFAULT 0.00,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_poim_item`
    FOREIGN KEY (`order_item_id`) REFERENCES `pos_order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_poim_ingredient`
    FOREIGN KEY (`ingredient_id`) REFERENCES `pos_ingredients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  VISTE — Torneo (classifica e capocannonieri)
-- ============================================================

CREATE OR REPLACE VIEW `v_classifica` AS
SELECT
  c.*,
  s.nome   AS nome_squadra,
  s.colore AS colore_squadra,
  s.logo_url
FROM `classifica` c
JOIN `squadre`    s ON s.id = c.id_squadra
ORDER BY
  c.punti       DESC,
  c.diff_reti   DESC,
  c.gol_fatti   DESC,
  c.gol_subiti  ASC,
  c.ammonizioni ASC,
  c.espulsioni  ASC,
  s.nome        ASC;

CREATE OR REPLACE VIEW `v_capocannonieri` AS
SELECT
  COALESCE(CONCAT(g.nome, ' ', g.cognome), e.nome_libero) AS giocatore,
  s.nome   AS squadra,
  s.colore AS colore_squadra,
  COUNT(*) AS gol
FROM `eventi_partita` e
LEFT JOIN `giocatori` g ON g.id = e.id_giocatore
JOIN  `squadre`       s ON s.id = e.id_squadra
WHERE e.tipo = 'gol'
GROUP BY e.id_giocatore, e.nome_libero, e.id_squadra
ORDER BY gol DESC;
