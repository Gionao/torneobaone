-- ============================================================
--  MIGRAZIONE: Ruoli giocatori + Capitano come membro della rosa
--  Da eseguire UNA SOLA VOLTA su database già esistenti
--  (per nuove installazioni, usare direttamente database_schema.sql)
-- ============================================================

-- 1. Aggiunge la colonna ruolo ai giocatori (se non esiste già)
ALTER TABLE `giocatori`
  ADD COLUMN `ruolo` ENUM('Giocatore','Portiere','Capitano') NOT NULL DEFAULT 'Giocatore' AFTER `numero_maglia`;

-- Sincronizza il ruolo con il vecchio flag e_portiere
UPDATE `giocatori` SET `ruolo` = 'Portiere' WHERE `e_portiere` = 1;

-- 2. Aggiunge la colonna di collegamento capitano sulla squadra
ALTER TABLE `squadre`
  ADD COLUMN `capitano_giocatore_id` INT UNSIGNED NULL DEFAULT NULL AFTER `capitano`,
  ADD KEY `fk_squadra_capitano` (`capitano_giocatore_id`);

ALTER TABLE `squadre`
  ADD CONSTRAINT `fk_squadra_capitano`
    FOREIGN KEY (`capitano_giocatore_id`) REFERENCES `giocatori` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- 3. Per le squadre già esistenti che hanno un nome di capitano testuale
--    ma nessun giocatore in rosa con quel ruolo, crea il record giocatore
--    e collega la squadra. Il nome viene splittato in modo grossolano:
--    prima parola = nome, resto = cognome. Verifica/correggi manualmente
--    dopo l'esecuzione se necessario.

-- Imposta ruolo Capitano su giocatori già in rosa che coincidono col nome
UPDATE `giocatori` g
JOIN `squadre` s ON s.id = g.id_squadra
SET g.ruolo = 'Capitano'
WHERE s.capitano IS NOT NULL
  AND s.capitano <> ''
  AND TRIM(CONCAT(g.nome, ' ', g.cognome)) = TRIM(s.capitano)
  AND g.ruolo <> 'Portiere';

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

-- Se il cognome risulta vuoto (capitano con un solo nome), usa '-' come placeholder
UPDATE `giocatori` SET `cognome` = '-' WHERE `ruolo` = 'Capitano' AND TRIM(`cognome`) = '';

-- 4. Collega ogni squadra al record giocatore-capitano appena creato/esistente
UPDATE `squadre` s
JOIN `giocatori` g ON g.id_squadra = s.id AND g.ruolo = 'Capitano'
SET s.capitano_giocatore_id = g.id
WHERE s.capitano_giocatore_id IS NULL;

-- 5. Rimuove il flag obsoleto e_portiere (se presente)
ALTER TABLE `giocatori` DROP COLUMN `e_portiere`;
