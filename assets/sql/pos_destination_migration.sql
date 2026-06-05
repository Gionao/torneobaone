-- Destinazione ordine e numero tavolo (POS)
USE `torneobaone`;

ALTER TABLE `pos_orders`
  ADD COLUMN `destination_type` ENUM('tavolo','asporto','campetto')
      NOT NULL DEFAULT 'tavolo'
      AFTER `covers`,
  ADD COLUMN `table_number` SMALLINT UNSIGNED
      NULL DEFAULT NULL
      AFTER `destination_type`;
