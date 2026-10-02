-- Garantisce la compatibilità anche su DB già esistenti senza id_capitano
ALTER TABLE `squadre`
  ADD COLUMN IF NOT EXISTS `id_capitano` INT UNSIGNED NULL DEFAULT NULL AFTER `logo_url`;

CREATE INDEX IF NOT EXISTS `idx_squadre_id_capitano`
  ON `squadre` (`id_capitano`);

ALTER TABLE `squadre`
  ADD COLUMN IF NOT EXISTS `attiva` TINYINT(1) NOT NULL DEFAULT 0 AFTER `id_capitano`;

UPDATE `squadre` s
JOIN `capitani` c ON c.id = s.id_capitano
SET s.attiva = 1
WHERE c.email_verificata_at IS NOT NULL
  AND s.attiva = 0;

CREATE INDEX IF NOT EXISTS `idx_squadre_attiva`
  ON `squadre` (`attiva`);
