-- ============================================================================
-- POPOLAMENTO GENERATO PER SVILUPPO - TORNEO BAONE 2027
-- Eseguire solo in un database locale/di sviluppo dopo database_schema.sql.
--
-- Tutti i nomi, account, consensi, pagamenti e partite sono sintetici.
-- Le righe di consenso e pagamento simulano i flussi applicativi e NON sono
-- accettazioni legali né incassi reali. Non usare o copiare questo seed in
-- produzione. Gli account sintetici non hanno una password utilizzabile.
--
-- Nessuna tabella temporanea viene creata: i dati sono inseriti direttamente
-- nelle tabelle applicative con INSERT / INSERT ... SELECT.
--
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+02:00';
START TRANSACTION;

-- Account amministratore di sviluppo, senza sovrascrivere un account esistente.
INSERT IGNORE INTO `admin` (`username`, `password_hash`, `role`, `station_name`)
VALUES (
  'sviluppo_baone',
  '!disabled-fixture',
  'admin',
  NULL
);
SET @sviluppo_admin_id := (SELECT `id` FROM `admin` WHERE `username` = 'sviluppo_baone' LIMIT 1);

-- Torneo dedicato al popolamento locale.
INSERT INTO `tornei` (`nome`, `stagione`, `data_inizio`, `data_fine`, `attivo`)
SELECT 'Torneo Baone Sviluppo 2027', '2027', '2027-06-01', '2027-07-01', 1
WHERE NOT EXISTS (SELECT 1 FROM `tornei` WHERE `nome` = 'Torneo Baone Sviluppo 2027');
SET @sviluppo_torneo_id := (
  SELECT `id` FROM `tornei` WHERE `nome` = 'Torneo Baone Sviluppo 2027' ORDER BY `id` LIMIT 1
);

INSERT INTO `fasi` (`nome`, `ordine`)
SELECT 'Girone', 1 WHERE NOT EXISTS (SELECT 1 FROM `fasi` WHERE `nome` = 'Girone');

INSERT INTO `tipi_documento` (`codice`, `nome`) VALUES
  ('privacy', 'Informativa privacy'),
  ('regolamento', 'Regolamento del torneo'),
  ('liberatoria_foto', 'Liberatoria foto e video')
ON DUPLICATE KEY UPDATE `nome` = VALUES(`nome`);

-- Se il database non contiene ancora documenti per uno dei tipi, crea un
-- documento locale chiaramente marcato come sintetico.
INSERT INTO `documenti` (`id_tipo`, `versione`, `titolo`, `url_file`, `data_pubblicazione`, `note`)
SELECT t.`id`, 'SVIL1', CONCAT('Sviluppo - ', t.`nome`), NULL, CURRENT_DATE,
       'Documento sintetico per test locali; non valido per uso ufficiale.'
FROM `tipi_documento` t
WHERE t.`codice` IN ('privacy', 'regolamento', 'liberatoria_foto')
  AND NOT EXISTS (SELECT 1 FROM `documenti` d WHERE d.`id_tipo` = t.`id`);

INSERT INTO `metodi_pagamento` (`nome`, `descrizione`, `istruzioni`, `iban`, `ordine`, `attivo`)
SELECT 'Pagamento sviluppo', 'Metodo sintetico per test locali',
       'Transazione simulata; non costituisce un incasso.', NULL, 250, 1
WHERE NOT EXISTS (SELECT 1 FROM `metodi_pagamento` WHERE `nome` = 'Pagamento sviluppo');
SET @sviluppo_metodo_id := (
  SELECT `id` FROM `metodi_pagamento` WHERE `nome` = 'Pagamento sviluppo' ORDER BY `id` LIMIT 1
);

-- Account capitani: email univoche usate come credenziali di accesso.
INSERT IGNORE INTO `capitani`
  (`nome`, `cognome`, `email`, `telefono`, `data_nascita`, `indirizzo`, `password_hash`, `email_verificata_at`)
VALUES
  ('Luca','Rinaldi','capitano1@torneobaone.it','+390000000001','1992-04-12','Via Sviluppo 1, Baone','!disabled-fixture',CURRENT_TIMESTAMP),
  ('Marco','Bellini','capitano2@torneobaone.it','+390000000002','1990-09-23','Via Sviluppo 2, Este','!disabled-fixture',CURRENT_TIMESTAMP),
  ('Andrea','Moretti','capitano3@torneobaone.it','+390000000003','1994-01-08','Via Sviluppo 3, Baone','!disabled-fixture',CURRENT_TIMESTAMP),
  ('Davide','Conti','capitano4@torneobaone.it','+390000000004','1991-11-17','Via Sviluppo 4, Rivadolmo','!disabled-fixture',CURRENT_TIMESTAMP),
  ('Matteo','Galli','capitano5@torneobaone.it','+390000000005','1993-06-30','Via Sviluppo 5, Calaone','!disabled-fixture',CURRENT_TIMESTAMP),
  ('Stefano','Marin','capitano6@torneobaone.it','+390000000006','1989-02-14','Via Sviluppo 6, Arquà','!disabled-fixture',CURRENT_TIMESTAMP),
  ('Nicola','Fabbri','capitano7@torneobaone.it','+390000000007','1995-07-21','Via Sviluppo 7, Baone','!disabled-fixture',CURRENT_TIMESTAMP),
  ('Paolo','Caruso','capitano8@torneobaone.it','+390000000008','1992-12-05','Via Sviluppo 8, San Giorgio','!disabled-fixture',CURRENT_TIMESTAMP),
  ('Simone','Ferrari','capitano9@torneobaone.it','+390000000009','1990-03-19','Via Sviluppo 9, Este','!disabled-fixture',CURRENT_TIMESTAMP),
  ('Alessandro','Costa','capitano10@torneobaone.it','+390000000010','1996-10-27','Via Sviluppo 10, Montemerlo','!disabled-fixture',CURRENT_TIMESTAMP),
  ('Gabriele','Fontana','capitano11@torneobaone.it','+390000000011','1991-05-03','Via Sviluppo 11, Baone','!disabled-fixture',CURRENT_TIMESTAMP),
  ('Federico','Rossi','capitano12@torneobaone.it','+390000000012','1993-08-16','Via Sviluppo 12, Calaone','!disabled-fixture',CURRENT_TIMESTAMP),
  ('Enrico','Basso','capitano13@torneobaone.it','+390000000013','1988-12-29','Via Sviluppo 13, Baone','!disabled-fixture',CURRENT_TIMESTAMP),
  ('Tommaso','Longo','capitano14@torneobaone.it','+390000000014','1994-02-24','Via Sviluppo 14, Rivadolmo','!disabled-fixture',CURRENT_TIMESTAMP),
  ('Riccardo','Greco','capitano15@torneobaone.it','+390000000015','1992-07-11','Via Sviluppo 15, Este','!disabled-fixture',CURRENT_TIMESTAMP);

-- 15 squadre distribuite in tre gironi da cinque.
INSERT IGNORE INTO `squadre`
  (`nome`, `colore`, `id_capitano`, `id_torneo`, `attiva`, `autorizzata`, `numero_giocatori_previsto`, `stato_iscrizione`, `stato_pagamento`, `quota_iscrizione`)
SELECT t.`nome`, t.`colore`, c.`id`, @sviluppo_torneo_id, 1, 1, 9, 'completa', 'pagato', 180.00
FROM (
  SELECT 'capitano1@torneobaone.it' AS `email`, 'Aquile di Baone' AS `nome`, '#174A7E' AS `colore`
  UNION ALL SELECT 'capitano2@torneobaone.it','Leoni di Este','#A94442'
  UNION ALL SELECT 'capitano3@torneobaone.it','Falchi Euganei','#3C8D5A'
  UNION ALL SELECT 'capitano4@torneobaone.it','Lupi di Rivadolmo','#7251A2'
  UNION ALL SELECT 'capitano5@torneobaone.it','Tigri di Calaone','#D27A24'
  UNION ALL SELECT 'capitano6@torneobaone.it','Volpi di Arquà','#287A78'
  UNION ALL SELECT 'capitano7@torneobaone.it','Draghi di Baone','#B14968'
  UNION ALL SELECT 'capitano8@torneobaone.it','Cinghiali San Giorgio','#78633B'
  UNION ALL SELECT 'capitano9@torneobaone.it','Pantere di Este','#495C9A'
  UNION ALL SELECT 'capitano10@torneobaone.it','Grifoni di Montemerlo','#9A633A'
  UNION ALL SELECT 'capitano11@torneobaone.it','Saette di Baone','#3F7EBD'
  UNION ALL SELECT 'capitano12@torneobaone.it','Orsi di Calaone','#658044'
  UNION ALL SELECT 'capitano13@torneobaone.it','Gabbiani Euganei','#397F9C'
  UNION ALL SELECT 'capitano14@torneobaone.it','Cerberi di Rivadolmo','#955A35'
  UNION ALL SELECT 'capitano15@torneobaone.it','Fenici di Este','#A63F3F'
) t
JOIN `capitani` c ON c.`email` = t.`email`
WHERE NOT EXISTS (SELECT 1 FROM `squadre` s WHERE s.`id_capitano` = c.`id`);

-- Nove giocatori per squadra (il capitano è l'account separato in `capitani`).
-- Nomi sintetici distinti, adulti, con dati di contatto non recapitabili.
INSERT INTO `giocatori`
  (`id_squadra`, `nome`, `cognome`, `numero_maglia`, `ruolo`, `email`, `telefono`, `data_nascita`, `indirizzo`, `stato_iscrizione`)
SELECT s.`id`, n.`nome`, co.`cognome`, slot.`numero_maglia`,
       IF(slot.`numero_maglia` = 1, 'Portiere', 'Giocatore'),
       CONCAT('sviluppo.giocatore.', LPAD(team.`team_no`, 2, '0'), '.', LPAD(slot.`numero_maglia`, 2, '0'), '@sviluppo.invalid'),
       CONCAT('+390000', LPAD(team.`team_no`, 2, '0'), LPAD(slot.`numero_maglia`, 4, '0')),
       DATE_SUB('2027-01-01', INTERVAL (20 + MOD(team.`team_no` * 9 + slot.`numero_maglia`, 18)) YEAR),
       CONCAT('Via Sviluppo ', team.`team_no`, ', Baone'),
       'completo'
FROM (
  SELECT 1 AS `team_no` UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
  UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10
  UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
) team
JOIN `capitani` c ON c.`email` = CONCAT('capitano', team.`team_no`, '@torneobaone.it')
JOIN `squadre` s ON s.`id_capitano` = c.`id`
CROSS JOIN (
  SELECT 1 AS `numero_maglia` UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
  UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
) slot
JOIN (
  SELECT 1 AS `idx`, 'Alessio' AS `nome` UNION ALL SELECT 2,'Bruno' UNION ALL SELECT 3,'Carlo'
  UNION ALL SELECT 4,'Dario' UNION ALL SELECT 5,'Edoardo' UNION ALL SELECT 6,'Fabio'
  UNION ALL SELECT 7,'Giorgio' UNION ALL SELECT 8,'Ivan' UNION ALL SELECT 9,'Lorenzo'
  UNION ALL SELECT 10,'Michele' UNION ALL SELECT 11,'Nicolò' UNION ALL SELECT 12,'Oscar'
  UNION ALL SELECT 13,'Pietro' UNION ALL SELECT 14,'Samuele' UNION ALL SELECT 15,'Vittorio'
) n ON n.`idx` = MOD(((team.`team_no` - 1) * 9 + slot.`numero_maglia` - 1), 15) + 1
JOIN (
  SELECT 1 AS `idx`, 'Abriani' AS `cognome` UNION ALL SELECT 2,'Bertin' UNION ALL SELECT 3,'Cecchinato'
  UNION ALL SELECT 4,'Dalla Valle' UNION ALL SELECT 5,'Fabris' UNION ALL SELECT 6,'Gambaro'
  UNION ALL SELECT 7,'Lazzari' UNION ALL SELECT 8,'Milan' UNION ALL SELECT 9,'Zanetti'
) co ON co.`idx` = FLOOR(((team.`team_no` - 1) * 9 + slot.`numero_maglia` - 1) / 15) + 1
WHERE NOT EXISTS (
  SELECT 1 FROM `giocatori` g
  WHERE g.`id_squadra` = s.`id`
    AND g.`email` = CONCAT('sviluppo.giocatore.', LPAD(team.`team_no`, 2, '0'), '.', LPAD(slot.`numero_maglia`, 2, '0'), '@sviluppo.invalid')
);

-- Consensi sintetici per simulare i flussi applicativi locali.
INSERT INTO `consensi` (`id_documento`, `id_capitano`, `concesso`, `data_ora`, `ip_address`, `user_agent`)
SELECT doc.`id`, c.`id`, 1, CURRENT_TIMESTAMP, '192.0.2.42', 'SYNTHETIC DEVELOPMENT SEED - NOT LEGAL CONSENT'
FROM `capitani` c
JOIN `squadre` s ON s.`id_capitano` = c.`id` AND s.`id_torneo` = @sviluppo_torneo_id
CROSS JOIN (
  SELECT 'privacy' AS `codice` UNION ALL SELECT 'regolamento' UNION ALL SELECT 'liberatoria_foto'
) codici
JOIN `tipi_documento` t ON t.`codice` = codici.`codice`
JOIN `documenti` doc ON doc.`id` = (
  SELECT dx.`id` FROM `documenti` dx
  WHERE dx.`id_tipo` = t.`id`
  ORDER BY dx.`versione` DESC, dx.`id` DESC LIMIT 1
)
WHERE c.`email` LIKE 'capitano%@torneobaone.it'
  AND NOT EXISTS (
    SELECT 1 FROM `consensi` x
    WHERE x.`id_documento` = doc.`id` AND x.`id_capitano` = c.`id` AND x.`concesso` = 1
  );

INSERT INTO `consensi` (`id_documento`, `id_giocatore`, `concesso`, `data_ora`, `ip_address`, `user_agent`)
SELECT doc.`id`, g.`id`, 1, CURRENT_TIMESTAMP, '192.0.2.42', 'SYNTHETIC DEVELOPMENT SEED - NOT LEGAL CONSENT'
FROM `giocatori` g
JOIN `squadre` s ON s.`id` = g.`id_squadra` AND s.`id_torneo` = @sviluppo_torneo_id
CROSS JOIN (
  SELECT 'privacy' AS `codice` UNION ALL SELECT 'regolamento' UNION ALL SELECT 'liberatoria_foto'
) codici
JOIN `tipi_documento` t ON t.`codice` = codici.`codice`
JOIN `documenti` doc ON doc.`id` = (
  SELECT dx.`id` FROM `documenti` dx
  WHERE dx.`id_tipo` = t.`id`
  ORDER BY dx.`versione` DESC, dx.`id` DESC LIMIT 1
)
WHERE g.`email` LIKE 'sviluppo.giocatore.%@sviluppo.invalid'
  AND NOT EXISTS (
    SELECT 1 FROM `consensi` x
    WHERE x.`id_documento` = doc.`id` AND x.`id_giocatore` = g.`id` AND x.`concesso` = 1
  );

-- Una transazione sintetica confermata per ciascuna squadra.
INSERT INTO `transazioni`
  (`id_squadra`, `importo`, `id_metodo_pagamento`, `data_pagamento`, `stato`, `note`, `id_organizzatore`)
SELECT s.`id`, s.`quota_iscrizione`, @sviluppo_metodo_id, CURRENT_DATE, 'confermata',
       'SINTETICO SVILUPPO - non e un incasso reale', @sviluppo_admin_id
FROM `squadre` s
WHERE s.`id_torneo` = @sviluppo_torneo_id
  AND NOT EXISTS (
    SELECT 1 FROM `transazioni` x
    WHERE x.`id_squadra` = s.`id`
      AND x.`id_metodo_pagamento` = @sviluppo_metodo_id
      AND x.`note` = 'SINTETICO SVILUPPO - non e un incasso reale'
  );

INSERT INTO `classifica`
  (`id_squadra`, `punti`, `giocate`, `vinte`, `pareggiate`, `perse`, `gol_fatti`, `gol_subiti`, `diff_reti`, `ammonizioni`, `espulsioni`)
SELECT s.`id`, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0
FROM `squadre` s
WHERE s.`id_torneo` = @sviluppo_torneo_id
  AND NOT EXISTS (SELECT 1 FROM `classifica` c WHERE c.`id_squadra` = s.`id`);

-- Girone all'italiana: 5 giornate x 2 partite x 3 gironi = 30 partite.
INSERT INTO `partite`
  (`id_fase`, `id_squadra_casa`, `id_squadra_osp`, `data_ora`, `campo`, `gol_casa`, `gol_osp`, `giocata`, `note`)
SELECT
  (SELECT f.`id` FROM `fasi` f WHERE f.`nome` = 'Girone' ORDER BY f.`ordine`, f.`id` LIMIT 1),
  casa.`id`, ospite.`id`,
  DATE_ADD('2027-06-01 20:30:00', INTERVAL (((gare.`giornata` - 1) * 7 + gruppi.`giorni_offset`) * 24 + gare.`slot` - 1) HOUR),
  'Campo Principale Baone', NULL, NULL, 0,
  CONCAT('SINTETICO SVILUPPO - Girone ', gruppi.`gruppo`, ' - Giornata ', gare.`giornata`)
FROM (
  SELECT 1 AS `giornata`, 2 AS `casa`, 5 AS `ospite`, 1 AS `slot` UNION ALL SELECT 1,3,4,2
  UNION ALL SELECT 2,1,5,1 UNION ALL SELECT 2,2,3,2
  UNION ALL SELECT 3,1,4,1 UNION ALL SELECT 3,3,5,2
  UNION ALL SELECT 4,1,3,1 UNION ALL SELECT 4,2,4,2
  UNION ALL SELECT 5,1,2,1 UNION ALL SELECT 5,4,5,2
) gare
CROSS JOIN (
  SELECT 'A' AS `gruppo`, 0 AS `base_team`, 0 AS `giorni_offset`
  UNION ALL SELECT 'B',5,1 UNION ALL SELECT 'C',10,2
) gruppi
JOIN `capitani` cap_casa
  ON cap_casa.`email` = CONCAT('capitano', gruppi.`base_team` + gare.`casa`, '@torneobaone.it')
JOIN `squadre` casa ON casa.`id_capitano` = cap_casa.`id` AND casa.`id_torneo` = @sviluppo_torneo_id
JOIN `capitani` cap_ospite
  ON cap_ospite.`email` = CONCAT('capitano', gruppi.`base_team` + gare.`ospite`, '@torneobaone.it')
JOIN `squadre` ospite ON ospite.`id_capitano` = cap_ospite.`id` AND ospite.`id_torneo` = @sviluppo_torneo_id
WHERE NOT EXISTS (
  SELECT 1 FROM `partite` p
  WHERE p.`id_squadra_casa` = casa.`id`
    AND p.`id_squadra_osp` = ospite.`id`
    AND p.`note` = CONCAT('SINTETICO SVILUPPO - Girone ', gruppi.`gruppo`, ' - Giornata ', gare.`giornata`)
);

COMMIT;
