-- ============================================================
--  MIGRAZIONE POS → database torneobaone (stesso DB del torneo)
--  Eseguire in phpMyAdmin senza modificare le tabelle esistenti
-- ============================================================

USE torneobaone;

-- Categorie / Reparti
CREATE TABLE IF NOT EXISTS pos_categories (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(50)          NOT NULL,
  department ENUM('kitchen','bar') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prodotti (soft delete via is_active)
CREATE TABLE IF NOT EXISTS pos_products (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT           NOT NULL,
  name        VARCHAR(100)  NOT NULL,
  description TEXT,
  price       DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  is_active   TINYINT(1)    NOT NULL DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES pos_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ingredienti
CREATE TABLE IF NOT EXISTS pos_ingredients (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(50) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relazione prodotto-ingredienti
CREATE TABLE IF NOT EXISTS pos_product_ingredients (
  product_id    INT           NOT NULL,
  ingredient_id INT           NOT NULL,
  is_removable  TINYINT(1)    NOT NULL DEFAULT 1,
  is_extra      TINYINT(1)    NOT NULL DEFAULT 0,
  extra_price   DECIMAL(4,2)  NOT NULL DEFAULT 0.00,
  PRIMARY KEY (product_id, ingredient_id),
  FOREIGN KEY (product_id)    REFERENCES pos_products(id),
  FOREIGN KEY (ingredient_id) REFERENCES pos_ingredients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coupon monouso
CREATE TABLE IF NOT EXISTS pos_coupons (
  id                       INT AUTO_INCREMENT PRIMARY KEY,
  code                     VARCHAR(20)                              NOT NULL UNIQUE,
  product_type_restriction ENUM('any','sandwich','drink','snack')   NOT NULL DEFAULT 'any',
  is_used                  TINYINT(1)                               NOT NULL DEFAULT 0,
  used_at                  TIMESTAMP NULL DEFAULT NULL,
  created_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ordini (testata)
CREATE TABLE IF NOT EXISTS pos_orders (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  daily_number  INT NOT NULL,
  status        ENUM('pending','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'pending',
  total_amount  DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Righe ordine
CREATE TABLE IF NOT EXISTS pos_order_items (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  order_id         INT           NOT NULL,
  product_id       INT           NOT NULL,
  coupon_id        INT           NULL UNIQUE,
  quantity         INT           NOT NULL DEFAULT 1,
  unit_price       DECIMAL(6,2)  NOT NULL,
  discount_applied DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  status           ENUM('pending','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'pending',
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id)   REFERENCES pos_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES pos_products(id),
  FOREIGN KEY (coupon_id)  REFERENCES pos_coupons(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Personalizzazioni singola riga
CREATE TABLE IF NOT EXISTS pos_order_item_modifications (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  order_item_id INT          NOT NULL,
  ingredient_id INT          NOT NULL,
  action        ENUM('removed','added') NOT NULL,
  extra_price   DECIMAL(4,2) NOT NULL DEFAULT 0.00,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_item_id) REFERENCES pos_order_items(id) ON DELETE CASCADE,
  FOREIGN KEY (ingredient_id) REFERENCES pos_ingredients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
