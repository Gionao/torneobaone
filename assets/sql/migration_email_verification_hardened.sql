-- ============================================================
-- Migrazione di supporto per verifica email e token temporanei
-- Garantisce la compatibilità con il sistema già presente.
-- ============================================================

ALTER TABLE `capitani`
  ADD COLUMN IF NOT EXISTS `email_verificata_at` DATETIME NULL AFTER `password_hash`;

CREATE TABLE IF NOT EXISTS `token_verifica` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo`          ENUM('email_verifica','reset_password','invito_giocatore','consenso_genitore') NOT NULL,
  `token`         VARCHAR(255) NOT NULL,
  `id_capitano`   INT UNSIGNED NULL,
  `id_giocatore`  INT UNSIGNED NULL,
  `id_genitore`   INT UNSIGNED NULL,
  `scadenza`      DATETIME NOT NULL,
  `usato_at`      DATETIME NULL,
  `invalidato_at` DATETIME NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_verifica_token` (`token`),
  KEY `idx_token_verifica_tipo` (`tipo`),
  KEY `idx_token_verifica_capitano` (`id_capitano`),
  KEY `idx_token_verifica_giocatore` (`id_giocatore`),
  KEY `idx_token_verifica_scadenza` (`scadenza`),
  CONSTRAINT `fk_token_verifica_capitano`
    FOREIGN KEY (`id_capitano`) REFERENCES `capitani` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_token_verifica_giocatore`
    FOREIGN KEY (`id_giocatore`) REFERENCES `giocatori` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_token_verifica_genitore`
    FOREIGN KEY (`id_genitore`) REFERENCES `genitori` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nota: le applicazioni salvano il valore hash del token invece di
-- memorizzare il token in chiaro, per ridurre il rischio di esposizione.
