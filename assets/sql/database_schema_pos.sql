USE `my_torneobaone`;

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
  `admin_id`      INT UNSIGNED NULL DEFAULT NULL,
  `station_name`  VARCHAR(80) NULL DEFAULT NULL,
  `status`        ENUM('pending','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `total_amount`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pos_orders_date` (`created_at`),
  KEY `idx_pos_orders_status` (`status`),
  KEY `idx_pos_orders_admin` (`admin_id`),
  CONSTRAINT `fk_pos_orders_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
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