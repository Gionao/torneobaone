-- ============================================================================
-- RISULTATI DEL CALENDARIO DI SVILUPPO - TORNEO BAONE 2027
-- Eseguire dopo assets/sql/database_insert.sql.
--
-- Aggiorna esclusivamente le 30 partite sintetiche dei tre gironi e ricalcola
-- la classifica delle squadre del torneo di sviluppo. I punteggi sono inventati
-- per popolare l'interfaccia locale; non sono risultati ufficiali.
-- Nessuna tabella temporanea, cancellazione o modifica dello schema.
-- ============================================================================

START TRANSACTION;

UPDATE `partite` p
JOIN (
  SELECT 'A' AS `gruppo`, 1 AS `giornata`, 2 AS `casa`, 5 AS `ospite`, 2 AS `gol_casa`, 1 AS `gol_osp` UNION ALL
  SELECT 'A',1,3,4,3,3 UNION ALL
  SELECT 'A',2,1,5,1,0 UNION ALL
  SELECT 'A',2,2,3,0,2 UNION ALL
  SELECT 'A',3,1,4,2,2 UNION ALL
  SELECT 'A',3,3,5,4,1 UNION ALL
  SELECT 'A',4,1,3,1,3 UNION ALL
  SELECT 'A',4,2,4,2,0 UNION ALL
  SELECT 'A',5,1,2,2,1 UNION ALL
  SELECT 'A',5,4,5,2,2 UNION ALL

  SELECT 'B',1,2,5,1,0 UNION ALL
  SELECT 'B',1,3,4,2,2 UNION ALL
  SELECT 'B',2,1,5,3,1 UNION ALL
  SELECT 'B',2,2,3,1,2 UNION ALL
  SELECT 'B',3,1,4,0,0 UNION ALL
  SELECT 'B',3,3,5,2,0 UNION ALL
  SELECT 'B',4,1,3,1,3 UNION ALL
  SELECT 'B',4,2,4,4,2 UNION ALL
  SELECT 'B',5,1,2,2,2 UNION ALL
  SELECT 'B',5,4,5,1,0 UNION ALL

  SELECT 'C',1,2,5,3,2 UNION ALL
  SELECT 'C',1,3,4,0,1 UNION ALL
  SELECT 'C',2,1,5,0,0 UNION ALL
  SELECT 'C',2,2,3,3,3 UNION ALL
  SELECT 'C',3,1,4,2,1 UNION ALL
  SELECT 'C',3,3,5,0,2 UNION ALL
  SELECT 'C',4,1,3,1,0 UNION ALL
  SELECT 'C',4,2,4,2,1 UNION ALL
  SELECT 'C',5,1,2,2,4 UNION ALL
  SELECT 'C',5,4,5,2,2
) risultati
JOIN `capitani` cap_casa
  ON cap_casa.`email` = CONCAT(
    'capitano',
    CASE risultati.`gruppo` WHEN 'A' THEN 0 WHEN 'B' THEN 5 ELSE 10 END + risultati.`casa`,
    '@torneobaone.it'
  )
JOIN `squadre` casa
  ON casa.`id_capitano` = cap_casa.`id`
 AND casa.`id_torneo` = (SELECT `id` FROM `tornei` WHERE `nome` = 'Torneo Baone Sviluppo 2027' ORDER BY `id` LIMIT 1)
JOIN `capitani` cap_ospite
  ON cap_ospite.`email` = CONCAT(
    'capitano',
    CASE risultati.`gruppo` WHEN 'A' THEN 0 WHEN 'B' THEN 5 ELSE 10 END + risultati.`ospite`,
    '@torneobaone.it'
  )
JOIN `squadre` ospite
  ON ospite.`id_capitano` = cap_ospite.`id`
 AND ospite.`id_torneo` = casa.`id_torneo`
SET p.`gol_casa` = risultati.`gol_casa`,
    p.`gol_osp` = risultati.`gol_osp`,
    p.`giocata` = 1
WHERE p.`id_squadra_casa` = casa.`id`
  AND p.`id_squadra_osp` = ospite.`id`
  AND p.`note` = CONCAT('SINTETICO SVILUPPO - Girone ', risultati.`gruppo`, ' - Giornata ', risultati.`giornata`);

-- Ricalcola la classifica (regolamento applicativo: vittoria 3, pareggio 1).
INSERT INTO `classifica`
  (`id_squadra`, `punti`, `giocate`, `vinte`, `pareggiate`, `perse`, `gol_fatti`, `gol_subiti`, `diff_reti`, `ammonizioni`, `espulsioni`)
SELECT
  s.`id`,
  COALESCE(SUM(CASE
    WHEN p.`id` IS NULL THEN 0
    WHEN p.`gol_casa` = p.`gol_osp` THEN 1
    WHEN (p.`id_squadra_casa` = s.`id` AND p.`gol_casa` > p.`gol_osp`)
      OR (p.`id_squadra_osp` = s.`id` AND p.`gol_osp` > p.`gol_casa`) THEN 3
    ELSE 0 END), 0),
  COUNT(p.`id`),
  COALESCE(SUM(CASE WHEN (p.`id_squadra_casa` = s.`id` AND p.`gol_casa` > p.`gol_osp`)
                      OR (p.`id_squadra_osp` = s.`id` AND p.`gol_osp` > p.`gol_casa`) THEN 1 ELSE 0 END), 0),
  COALESCE(SUM(CASE WHEN p.`id` IS NOT NULL AND p.`gol_casa` = p.`gol_osp` THEN 1 ELSE 0 END), 0),
  COALESCE(SUM(CASE WHEN (p.`id_squadra_casa` = s.`id` AND p.`gol_casa` < p.`gol_osp`)
                      OR (p.`id_squadra_osp` = s.`id` AND p.`gol_osp` < p.`gol_casa`) THEN 1 ELSE 0 END), 0),
  COALESCE(SUM(CASE WHEN p.`id_squadra_casa` = s.`id` THEN p.`gol_casa`
                    WHEN p.`id_squadra_osp` = s.`id` THEN p.`gol_osp` ELSE 0 END), 0),
  COALESCE(SUM(CASE WHEN p.`id_squadra_casa` = s.`id` THEN p.`gol_osp`
                    WHEN p.`id_squadra_osp` = s.`id` THEN p.`gol_casa` ELSE 0 END), 0),
  COALESCE(SUM(CASE WHEN p.`id_squadra_casa` = s.`id` THEN p.`gol_casa` - p.`gol_osp`
                    WHEN p.`id_squadra_osp` = s.`id` THEN p.`gol_osp` - p.`gol_casa` ELSE 0 END), 0),
  COALESCE((
    SELECT SUM(CASE WHEN e.`tipo` IN ('ammonizione', 'doppia_ammonizione') THEN 1 ELSE 0 END)
    FROM `eventi_partita` e WHERE e.`id_squadra` = s.`id`
  ), 0),
  COALESCE((
    SELECT SUM(CASE WHEN e.`tipo` = 'espulsione' THEN 1 ELSE 0 END)
    FROM `eventi_partita` e WHERE e.`id_squadra` = s.`id`
  ), 0)
FROM `squadre` s
LEFT JOIN `partite` p
  ON p.`giocata` = 1
 AND p.`gol_casa` IS NOT NULL
 AND p.`gol_osp` IS NOT NULL
 AND p.`id_fase` IN (SELECT f.`id` FROM `fasi` f WHERE f.`nome` = 'Girone')
 AND (p.`id_squadra_casa` = s.`id` OR p.`id_squadra_osp` = s.`id`)
WHERE s.`id_torneo` = (
  SELECT `id` FROM `tornei` WHERE `nome` = 'Torneo Baone Sviluppo 2027' ORDER BY `id` LIMIT 1
)
GROUP BY s.`id`
ON DUPLICATE KEY UPDATE
  `punti` = VALUES(`punti`),
  `giocate` = VALUES(`giocate`),
  `vinte` = VALUES(`vinte`),
  `pareggiate` = VALUES(`pareggiate`),
  `perse` = VALUES(`perse`),
  `gol_fatti` = VALUES(`gol_fatti`),
  `gol_subiti` = VALUES(`gol_subiti`),
  `diff_reti` = VALUES(`diff_reti`),
  `ammonizioni` = VALUES(`ammonizioni`),
  `espulsioni` = VALUES(`espulsioni`);

COMMIT;
