-- ============================================================
--  MIGRAZIONE RUOLI, CASSE E ORDINI — POS Torneo Baone 2026
--  Database: torneobaone
-- ============================================================

USE torneobaone;

-- Ruoli e nome cassa su tabella admin
ALTER TABLE `admin`
  ADD COLUMN `role` ENUM('admin','operatore','bar','cucina')
      NOT NULL DEFAULT 'operatore'
      AFTER `username`,
  ADD COLUMN `station_name` VARCHAR(80)
      NULL DEFAULT NULL
      AFTER `role`;

UPDATE `admin` SET `role` = 'admin' WHERE `username` = 'admin';

-- Dati cliente e cassa su ordini POS
ALTER TABLE `pos_orders`
  ADD COLUMN `customer_name` VARCHAR(100)
      NOT NULL DEFAULT ''
      AFTER `daily_number`,
  ADD COLUMN `covers` TINYINT UNSIGNED
      NOT NULL DEFAULT 1
      AFTER `customer_name`,
  ADD COLUMN `admin_id` INT UNSIGNED
      NULL DEFAULT NULL
      AFTER `covers`,
  ADD COLUMN `station_name` VARCHAR(80)
      NULL DEFAULT NULL
      AFTER `admin_id`,
  ADD CONSTRAINT `fk_order_admin`
      FOREIGN KEY (`admin_id`) REFERENCES `admin`(`id`)
      ON DELETE SET NULL;
