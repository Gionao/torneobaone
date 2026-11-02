-- ============================================================
-- Dati demo per il POS
-- Richiede database_schema_pos.sql e database_schema_torneo.sql.
-- Solo per sviluppo/test: gli ordini hanno date storiche.
-- Script ripetibile: evita di reinserire categorie, prodotti,
-- ordini e righe demo gia' presenti.
-- ============================================================

USE `my_torneobaone`;
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Categorie e prodotti demo
-- ------------------------------------------------------------
INSERT INTO `pos_categories` (`name`, `department`, `sort_order`)
SELECT 'Demo bar', 'bar', 90
WHERE NOT EXISTS (
  SELECT 1 FROM `pos_categories` WHERE `name` = 'Demo bar' AND `department` = 'bar'
);

INSERT INTO `pos_categories` (`name`, `department`, `sort_order`)
SELECT 'Demo cucina', 'kitchen', 90
WHERE NOT EXISTS (
  SELECT 1 FROM `pos_categories` WHERE `name` = 'Demo cucina' AND `department` = 'kitchen'
);

INSERT INTO `pos_products` (`category_id`, `name`, `description`, `price`, `product_kind`)
SELECT c.`id`, 'Demo - Acqua 0,5 L', 'Prodotto dimostrativo', 1.00, 'drink'
FROM `pos_categories` c
WHERE c.`name` = 'Demo bar' AND c.`department` = 'bar'
  AND NOT EXISTS (
    SELECT 1 FROM `pos_products` p
    WHERE p.`category_id` = c.`id` AND p.`name` = 'Demo - Acqua 0,5 L'
  );

INSERT INTO `pos_products` (`category_id`, `name`, `description`, `price`, `product_kind`)
SELECT c.`id`, 'Demo - Bibita lattina', 'Prodotto dimostrativo', 2.50, 'drink'
FROM `pos_categories` c
WHERE c.`name` = 'Demo bar' AND c.`department` = 'bar'
  AND NOT EXISTS (
    SELECT 1 FROM `pos_products` p
    WHERE p.`category_id` = c.`id` AND p.`name` = 'Demo - Bibita lattina'
  );

INSERT INTO `pos_products` (`category_id`, `name`, `description`, `price`, `product_kind`)
SELECT c.`id`, 'Demo - Panino', 'Prodotto dimostrativo', 5.50, 'food'
FROM `pos_categories` c
WHERE c.`name` = 'Demo cucina' AND c.`department` = 'kitchen'
  AND NOT EXISTS (
    SELECT 1 FROM `pos_products` p
    WHERE p.`category_id` = c.`id` AND p.`name` = 'Demo - Panino'
  );

INSERT INTO `pos_products` (`category_id`, `name`, `description`, `price`, `product_kind`)
SELECT c.`id`, 'Demo - Patatine fritte', 'Prodotto dimostrativo', 3.00, 'food'
FROM `pos_categories` c
WHERE c.`name` = 'Demo cucina' AND c.`department` = 'kitchen'
  AND NOT EXISTS (
    SELECT 1 FROM `pos_products` p
    WHERE p.`category_id` = c.`id` AND p.`name` = 'Demo - Patatine fritte'
  );

-- ------------------------------------------------------------
-- Ordini conclusi in giornate passate
-- L'admin demo viene usato se presente; altrimenti si usa il
-- primo account admin gia' creato dallo schema del torneo.
-- ------------------------------------------------------------
INSERT INTO `pos_orders` (
  `daily_number`, `customer_name`, `table_number`, `is_takeaway`, `covers`,
  `cover_fee`, `takeaway_fee`, `admin_id`, `station_name`, `status`,
  `total_amount`, `created_at`
)
SELECT 1, 'Cliente demo 1', '4', 0, 2, 3.00, 0.00,
       COALESCE(
         (SELECT `id` FROM `admin` WHERE `username` = 'demo_torneo' LIMIT 1),
         (SELECT MIN(`id`) FROM `admin`)
       ),
       'Banco demo', 'delivered', 9.00, '2025-06-14 18:20:00'
WHERE NOT EXISTS (
  SELECT 1 FROM `pos_orders`
  WHERE `daily_number` = 1 AND `customer_name` = 'Cliente demo 1'
    AND `created_at` = '2025-06-14 18:20:00'
);

INSERT INTO `pos_orders` (
  `daily_number`, `customer_name`, `table_number`, `is_takeaway`, `covers`,
  `cover_fee`, `takeaway_fee`, `admin_id`, `station_name`, `status`,
  `total_amount`, `created_at`
)
SELECT 2, 'Cliente demo 2', NULL, 1, 1, 0.00, 1.00,
       COALESCE(
         (SELECT `id` FROM `admin` WHERE `username` = 'demo_torneo' LIMIT 1),
         (SELECT MIN(`id`) FROM `admin`)
       ),
       'Banco demo', 'delivered', 12.00, '2025-06-14 19:05:00'
WHERE NOT EXISTS (
  SELECT 1 FROM `pos_orders`
  WHERE `daily_number` = 2 AND `customer_name` = 'Cliente demo 2'
    AND `created_at` = '2025-06-14 19:05:00'
);

INSERT INTO `pos_orders` (
  `daily_number`, `customer_name`, `table_number`, `is_takeaway`, `covers`,
  `cover_fee`, `takeaway_fee`, `admin_id`, `station_name`, `status`,
  `total_amount`, `created_at`
)
SELECT 1, 'Cliente demo 3', '2', 0, 3, 4.50, 0.00,
       COALESCE(
         (SELECT `id` FROM `admin` WHERE `username` = 'demo_torneo' LIMIT 1),
         (SELECT MIN(`id`) FROM `admin`)
       ),
       'Banco demo', 'delivered', 13.00, '2025-06-15 20:10:00'
WHERE NOT EXISTS (
  SELECT 1 FROM `pos_orders`
  WHERE `daily_number` = 1 AND `customer_name` = 'Cliente demo 3'
    AND `created_at` = '2025-06-15 20:10:00'
);

-- Righe degli ordini: valori e prodotti corrispondono agli ordini sopra.
INSERT INTO `pos_order_items` (
  `order_id`, `product_id`, `quantity`, `unit_price`, `status`
)
SELECT o.`id`, p.`id`, d.`quantity`, d.`unit_price`, 'delivered'
FROM (
  SELECT 1 AS `daily_number`, 'Cliente demo 1' AS `customer_name`,
         '2025-06-14 18:20:00' AS `created_at`, 'Demo - Bibita lattina' AS `product_name`,
         2 AS `quantity`, 2.50 AS `unit_price`
  UNION ALL
  SELECT 1, 'Cliente demo 1', '2025-06-14 18:20:00', 'Demo - Acqua 0,5 L', 1, 1.00
  UNION ALL
  SELECT 2, 'Cliente demo 2', '2025-06-14 19:05:00', 'Demo - Panino', 2, 5.50
  UNION ALL
  SELECT 1, 'Cliente demo 3', '2025-06-15 20:10:00', 'Demo - Patatine fritte', 2, 3.00
  UNION ALL
  SELECT 1, 'Cliente demo 3', '2025-06-15 20:10:00', 'Demo - Bibita lattina', 1, 2.50
) d
JOIN `pos_orders` o
  ON o.`daily_number` = d.`daily_number`
 AND o.`customer_name` = d.`customer_name`
 AND o.`created_at` = d.`created_at`
JOIN `pos_products` p ON p.`name` = d.`product_name`
WHERE NOT EXISTS (
  SELECT 1 FROM `pos_order_items` i
  WHERE i.`order_id` = o.`id` AND i.`product_id` = p.`id`
);
