-- ============================================================
--  MIGRAZIONE: Ruolo unico su giocatori, rimozione e_portiere
--  Da eseguire UNA SOLA VOLTA su database già esistenti
-- ============================================================

-- 1. Colonna ruolo (se assente)
ALTER TABLE `giocatori`
  ADD COLUMN `ruolo` ENUM('Giocatore','Portiere','Capitano') NOT NULL DEFAULT 'Giocatore' AFTER `numero_maglia`;

-- 2. Portieri dal vecchio flag
UPDATE `giocatori` SET `ruolo` = 'Portiere' WHERE `e_portiere` = 1;

-- 3. Capitano collegato alla squadra (se assente)
ALTER TABLE `squadre`
  ADD COLUMN `capitano_giocatore_id` INT UNSIGNED NULL DEFAULT NULL AFTER `capitano`,
  ADD KEY `fk_squadra_capitano` (`capitano_giocatore_id`);

ALTER TABLE `squadre`
  ADD CONSTRAINT `fk_squadra_capitano`
    FOREIGN KEY (`capitano_giocatore_id`) REFERENCES `giocatori` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- 4. Imposta ruolo Capitano su giocatori già in rosa che coincidono col nome
UPDATE `giocatori` g
JOIN `squadre` s ON s.id = g.id_squadra
SET g.ruolo = 'Capitano'
WHERE s.capitano IS NOT NULL
  AND s.capitano <> ''
  AND TRIM(CONCAT(g.nome, ' ', g.cognome)) = TRIM(s.capitano)
  AND g.ruolo <> 'Portiere';

-- 5. Crea record capitano in rosa solo se non esiste già un match per nome
INSERT INTO `giocatori` (`id_squadra`, `nome`, `cognome`, `ruolo`)
SELECT
  s.id,
  SUBSTRING_INDEX(s.capitano, ' ', 1) AS nome,
  TRIM(SUBSTRING(s.capitano, LENGTH(SUBSTRING_INDEX(s.capitano, ' ', 1)) + 1)) AS cognome,
  'Capitano'
FROM `squadre` s
WHERE s.capitano IS NOT NULL
  AND s.capitano <> ''
  AND s.capitano_giocatore_id IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM `giocatori` g WHERE g.id_squadra = s.id AND g.ruolo = 'Capitano'
  )
  AND NOT EXISTS (
    SELECT 1 FROM `giocatori` g
    WHERE g.id_squadra = s.id
      AND TRIM(CONCAT(g.nome, ' ', g.cognome)) = TRIM(s.capitano)
  );

UPDATE `giocatori` SET `cognome` = '-' WHERE `ruolo` = 'Capitano' AND TRIM(`cognome`) = '';

-- 6. Collega ogni squadra al record giocatore-capitano
UPDATE `squadre` s
JOIN `giocatori` g ON g.id_squadra = s.id AND g.ruolo = 'Capitano'
SET s.capitano_giocatore_id = g.id
WHERE s.capitano_giocatore_id IS NULL;

-- 7. Rimuove il flag obsoleto
ALTER TABLE `giocatori` DROP COLUMN `e_portiere`;
