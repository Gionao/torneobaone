-- =============================================================
-- MIGRAZIONE FINALE: unificazione capitano legacy / account capitano
-- Obiettivo: introdurre la fonte di verità canonica senza perdere i dati storici
-- =============================================================
-- Regola di business finale:
--   * fonte di verità: squadre.id_capitano -> capitani.id
--   * dati legacy (squadre.capitano, squadre.capitano_giocatore_id, giocatori.ruolo='Capitano')
--     vengono mantenuti solo per compatibilità e non come autorità del nuovo flusso
-- =============================================================

-- 1) Tabella capitani: crea solo se mancante
CREATE TABLE IF NOT EXISTS `capitani` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `cognome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `telefono` VARCHAR(30) NULL,
  `data_nascita` DATE NULL,
  `indirizzo` VARCHAR(255) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `email_verificata_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_capitani_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Aggiunta della colonna canonica in squadre, se mancante
ALTER TABLE `squadre`
  ADD COLUMN IF NOT EXISTS `id_capitano` INT UNSIGNED NULL DEFAULT NULL AFTER `logo_url`;

-- 3) Aggiunta dell'indice di supporto, se mancante
CREATE INDEX IF NOT EXISTS `idx_squadre_id_capitano`
  ON `squadre` (`id_capitano`);

-- 4) Aggiunta della foreign key canonica, se mancante
SELECT COUNT(*) INTO @has_fk
FROM `information_schema`.`REFERENTIAL_CONSTRAINTS`
WHERE `CONSTRAINT_SCHEMA` = DATABASE()
  AND `TABLE_NAME` = 'squadre'
  AND `CONSTRAINT_NAME` = 'fk_squadre_capitano_account';

SET @sql = IF(@has_fk = 0,
  'ALTER TABLE `squadre` ADD CONSTRAINT `fk_squadre_capitano_account` FOREIGN KEY (`id_capitano`) REFERENCES `capitani` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5) Migrazione conservativa dei dati esistenti
--    Non sovrascrive dati già presenti. Allinea solo se c'è una corrispondenza univoca
--    tra il nome del capitano legacy e il nome dell'account capitano.
UPDATE `squadre` s
JOIN (
  SELECT
    c.id AS capitano_id,
    TRIM(LOWER(CONCAT(c.nome, ' ', c.cognome))) AS full_name
  FROM `capitani` c
) c
  ON TRIM(LOWER(s.capitano)) = c.full_name
SET s.id_capitano = c.capitano_id
WHERE s.id_capitano IS NULL
  AND s.capitano IS NOT NULL
  AND TRIM(s.capitano) <> ''
  AND NOT EXISTS (
    SELECT 1
    FROM `capitani` c2
    WHERE TRIM(LOWER(CONCAT(c2.nome, ' ', c2.cognome))) = TRIM(LOWER(s.capitano))
      AND c2.id <> c.capitano_id
  );

-- 6) Nessun cleanup dei campi legacy: sono mantenuti per compatibilità con codice vecchio,
--    liste, report e migrazioni legacy non ancora rimosse.
--    In questo modo il database resta compatibile senza perdere storicità.

-- 7) Nota pratica:
--    - `squadre.capitano` e `squadre.capitano_giocatore_id` restano INVARIANTI e non sono la fonte di verità.
--    - `giocatori.ruolo = 'Capitano'` resta un dato legacy di rosa e non un account di autenticazione.
--    - Il nuovo flusso usa `squadre.id_capitano -> capitani.id` come unica chiave di riferimento.

-- 8) Evento manuale consigliato: dopo l'applicazione della migrazione,
--    verificare i record con `squadre.id_capitano IS NULL` e decidere se creare
--    un account capitano o lasciarli a completamento manuale.
