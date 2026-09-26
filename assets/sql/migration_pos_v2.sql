-- ============================================================
--  MIGRAZIONE POS v2 — Festa dei Panini
--  Da eseguire su database esistente (torneobaone)
--  Compatibile con Altervista (nessuna VIEW, nessun TRIGGER)
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+02:00';

-- ------------------------------------------------------------
-- 1. pos_orders — aggiungi campi tavolo, asporto, nome cliente
--    separati + costo coperto/asporto
-- ------------------------------------------------------------

-- Rinomina customer_name → customer_name (già ok), aggiungi table_number e is_takeaway
ALTER TABLE `pos_orders`
    ADD COLUMN IF NOT EXISTS `table_number`   VARCHAR(20) NULL DEFAULT NULL AFTER `customer_name`,
    ADD COLUMN IF NOT EXISTS `is_takeaway`    TINYINT(1)  NOT NULL DEFAULT 0  AFTER `table_number`,
    ADD COLUMN IF NOT EXISTS `cover_fee`      DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER `is_takeaway`,
    ADD COLUMN IF NOT EXISTS `takeaway_fee`   DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER `cover_fee`;

-- ------------------------------------------------------------
-- 2. pos_order_items — aggiungi note di riga
-- ------------------------------------------------------------
ALTER TABLE `pos_order_items`
    ADD COLUMN IF NOT EXISTS `item_note` VARCHAR(255) NULL DEFAULT NULL AFTER `quantity`;

-- ------------------------------------------------------------
-- 3. pos_coupons — nuovo sistema sconto fisso/percentuale
--    Aggiunge discount_type e discount_value; rimuove
--    product_type_restriction se non serve più (ma lo lasciamo
--    per retrocompatibilità, diventa opzionale)
-- ------------------------------------------------------------
ALTER TABLE `pos_coupons`
    ADD COLUMN IF NOT EXISTS `discount_type`  ENUM('fixed','percent') NOT NULL DEFAULT 'fixed' AFTER `product_type_restriction`,
    ADD COLUMN IF NOT EXISTS `discount_value` DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER `discount_type`;

-- ------------------------------------------------------------
-- 4. pos_settings — tabella chiave/valore per config dinamica
--    (prezzo coperto, prezzo asporto)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pos_settings` (
    `key`        VARCHAR(60)   NOT NULL,
    `value`      VARCHAR(255)  NOT NULL DEFAULT '',
    `label`      VARCHAR(120)  NOT NULL DEFAULT '',
    `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valori di default
INSERT INTO `pos_settings` (`key`, `value`, `label`) VALUES
('cover_fee',    '1.50', 'Prezzo coperto (€)'),
('takeaway_fee', '1.00', 'Costo asporto (€)')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

-- ============================================================
--  TABELLE COMPLETE (per installazione da zero)
--  Se stai aggiornando un DB esistente, le ALTER TABLE sopra
--  sono sufficienti. Questa sezione è per reference.
-- ============================================================

-- pos_categories (invariata)
CREATE TABLE IF NOT EXISTS `pos_categories` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(80)   NOT NULL,
    `department` ENUM('kitchen','bar') NOT NULL DEFAULT 'kitchen',
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pos_ingredients (invariata)
CREATE TABLE IF NOT EXISTS `pos_ingredients` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(80)  NOT NULL,
    `is_removable` TINYINT(1)  NOT NULL DEFAULT 1,
    `is_extra`    TINYINT(1)   NOT NULL DEFAULT 0,
    `extra_price` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pos_products (invariata)
CREATE TABLE IF NOT EXISTS `pos_products` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `category_id`  INT UNSIGNED  NOT NULL,
    `name`         VARCHAR(120)  NOT NULL,
    `price`        DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    `product_kind` VARCHAR(30)   NULL DEFAULT NULL,
    `is_active`    TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `fk_prod_cat` (`category_id`),
    CONSTRAINT `fk_prod_cat` FOREIGN KEY (`category_id`) REFERENCES `pos_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pos_product_ingredients (invariata)
CREATE TABLE IF NOT EXISTS `pos_product_ingredients` (
    `product_id`    INT UNSIGNED NOT NULL,
    `ingredient_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`product_id`, `ingredient_id`),
    CONSTRAINT `fk_pi_prod` FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pi_ing`  FOREIGN KEY (`ingredient_id`) REFERENCES `pos_ingredients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pos_coupons (schema completo aggiornato)
CREATE TABLE IF NOT EXISTS `pos_coupons` (
    `id`                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                     VARCHAR(20)  NOT NULL UNIQUE,
    `product_type_restriction` VARCHAR(30)  NOT NULL DEFAULT 'any',
    `discount_type`            ENUM('fixed','percent') NOT NULL DEFAULT 'fixed',
    `discount_value`           DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `is_used`                  TINYINT(1)   NOT NULL DEFAULT 0,
    `used_at`                  DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_coupon_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pos_orders (schema completo aggiornato)
CREATE TABLE IF NOT EXISTS `pos_orders` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `daily_number`  SMALLINT UNSIGNED NOT NULL,
    `customer_name` VARCHAR(100)    NOT NULL DEFAULT '',
    `table_number`  VARCHAR(20)     NULL DEFAULT NULL,
    `is_takeaway`   TINYINT(1)      NOT NULL DEFAULT 0,
    `covers`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `cover_fee`     DECIMAL(8,2)    NOT NULL DEFAULT 0.00,
    `takeaway_fee`  DECIMAL(8,2)    NOT NULL DEFAULT 0.00,
    `admin_id`      INT UNSIGNED    NOT NULL,
    `station_name`  VARCHAR(80)     NULL DEFAULT NULL,
    `status`        ENUM('pending','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'pending',
    `total_amount`  DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_date`   (`created_at`),
    KEY `idx_order_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pos_order_items (schema completo aggiornato)
CREATE TABLE IF NOT EXISTS `pos_order_items` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`         INT UNSIGNED  NOT NULL,
    `product_id`       INT UNSIGNED  NOT NULL,
    `quantity`         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `item_note`        VARCHAR(255)  NULL DEFAULT NULL,
    `unit_price`       DECIMAL(8,2)  NOT NULL,
    `discount_applied` DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    `coupon_id`        INT UNSIGNED  NULL DEFAULT NULL,
    `status`           ENUM('pending','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'pending',
    PRIMARY KEY (`id`),
    KEY `fk_item_order`  (`order_id`),
    KEY `fk_item_prod`   (`product_id`),
    CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`)   REFERENCES `pos_orders`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_item_prod`  FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pos_order_item_modifications (invariata)
CREATE TABLE IF NOT EXISTS `pos_order_item_modifications` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_item_id`  INT UNSIGNED NOT NULL,
    `ingredient_id`  INT UNSIGNED NOT NULL,
    `action`         ENUM('removed','added') NOT NULL,
    `extra_price`    DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    KEY `fk_mod_item` (`order_item_id`),
    CONSTRAINT `fk_mod_item` FOREIGN KEY (`order_item_id`) REFERENCES `pos_order_items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mod_ing`  FOREIGN KEY (`ingredient_id`) REFERENCES `pos_ingredients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
