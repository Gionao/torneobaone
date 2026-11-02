-- ============================================================
-- Dati demo per il torneo
-- Richiede database_schema_torneo.sql.
-- Solo per sviluppo/test: account e dati sono fittizi.
-- Password degli account demo: DemoBaone2026!
-- Rimuovere o cambiare gli account demo prima della produzione.
-- Script ripetibile: non sovrascrive i record esistenti.
-- ============================================================

USE `my_torneobaone`;
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Utente amministratore per i dati demo
-- ------------------------------------------------------------
INSERT IGNORE INTO `admin` (`username`, `password_hash`, `role`, `station_name`)
VALUES (
  'demo_torneo',
  '$2y$10$tCOZke9nWkRksMQ9DuMU2.awUEnfWDkJLo.hszC3maWdRs3oDzZrW',
  'admin',
  'Demo'
);

-- ------------------------------------------------------------
-- Torneo demo (disattivato per non interferire con quello reale)
-- ------------------------------------------------------------
INSERT INTO `tornei` (`nome`, `stagione`, `data_inizio`, `data_fine`, `attivo`)
SELECT 'Torneo Demo Baone', 'DEMO-2026', '2026-06-10', '2026-06-20', 0
WHERE NOT EXISTS (
  SELECT 1 FROM `tornei`
  WHERE `nome` = 'Torneo Demo Baone' AND `stagione` = 'DEMO-2026'
);

SET @demo_torneo_id = (
  SELECT `id` FROM `tornei`
  WHERE `nome` = 'Torneo Demo Baone' AND `stagione` = 'DEMO-2026'
  ORDER BY `id` LIMIT 1
);

-- ------------------------------------------------------------
-- Utenti capitani fittizi (password: DemoBaone2026!)
-- ------------------------------------------------------------
INSERT INTO `capitani` (
  `nome`, `cognome`, `email`, `telefono`, `data_nascita`, `indirizzo`,
  `password_hash`, `email_verificata_at`
)
SELECT 'Luca', 'Rossi', 'luca.rossi.demo@example.test', '3330000001',
       '1990-04-12', 'Via Demo 1, Baone',
       '$2y$10$tCOZke9nWkRksMQ9DuMU2.awUEnfWDkJLo.hszC3maWdRs3oDzZrW',
       '2026-01-10 10:00:00'
WHERE NOT EXISTS (
  SELECT 1 FROM `capitani` WHERE `email` = 'luca.rossi.demo@example.test'
);

INSERT INTO `capitani` (
  `nome`, `cognome`, `email`, `telefono`, `data_nascita`, `indirizzo`,
  `password_hash`, `email_verificata_at`
)
SELECT 'Marco', 'Bianchi', 'marco.bianchi.demo@example.test', '3330000002',
       '1988-09-20', 'Via Demo 2, Baone',
       '$2y$10$tCOZke9nWkRksMQ9DuMU2.awUEnfWDkJLo.hszC3maWdRs3oDzZrW',
       '2026-01-10 10:00:00'
WHERE NOT EXISTS (
  SELECT 1 FROM `capitani` WHERE `email` = 'marco.bianchi.demo@example.test'
);

INSERT INTO `capitani` (
  `nome`, `cognome`, `email`, `telefono`, `data_nascita`, `indirizzo`,
  `password_hash`, `email_verificata_at`
)
SELECT 'Andrea', 'Verdi', 'andrea.verdi.demo@example.test', '3330000003',
       '1992-02-08', 'Via Demo 3, Baone',
       '$2y$10$tCOZke9nWkRksMQ9DuMU2.awUEnfWDkJLo.hszC3maWdRs3oDzZrW',
       '2026-01-10 10:00:00'
WHERE NOT EXISTS (
  SELECT 1 FROM `capitani` WHERE `email` = 'andrea.verdi.demo@example.test'
);

INSERT INTO `capitani` (
  `nome`, `cognome`, `email`, `telefono`, `data_nascita`, `indirizzo`,
  `password_hash`, `email_verificata_at`
)
SELECT 'Paolo', 'Neri', 'paolo.neri.demo@example.test', '3330000004',
       '1989-12-03', 'Via Demo 4, Baone',
       '$2y$10$tCOZke9nWkRksMQ9DuMU2.awUEnfWDkJLo.hszC3maWdRs3oDzZrW',
       '2026-01-10 10:00:00'
WHERE NOT EXISTS (
  SELECT 1 FROM `capitani` WHERE `email` = 'paolo.neri.demo@example.test'
);

-- ------------------------------------------------------------
-- Metodi di pagamento demo
-- ------------------------------------------------------------
INSERT INTO `metodi_pagamento` (`nome`, `descrizione`, `ordine`, `attivo`)
SELECT 'Demo bonifico', 'Metodo dimostrativo', 90, 1
WHERE NOT EXISTS (
  SELECT 1 FROM `metodi_pagamento` WHERE `nome` = 'Demo bonifico'
);

INSERT INTO `metodi_pagamento` (`nome`, `descrizione`, `ordine`, `attivo`)
SELECT 'Demo contanti', 'Metodo dimostrativo', 91, 1
WHERE NOT EXISTS (
  SELECT 1 FROM `metodi_pagamento` WHERE `nome` = 'Demo contanti'
);

-- ------------------------------------------------------------
-- Squadre demo: le iscrizioni sono rappresentate dalle squadre
-- e dai giocatori; nello schema non esiste una tabella separata
-- denominata `iscrizioni`.
-- ------------------------------------------------------------
INSERT INTO `squadre` (
  `nome`, `colore`, `id_capitano`, `id_torneo`, `attiva`, `autorizzata`,
  `numero_giocatori_previsto`, `stato_iscrizione`, `stato_pagamento`,
  `quota_iscrizione`
)
SELECT d.`nome`, d.`colore`, c.`id`, @demo_torneo_id, 0, 0,
       d.`numero_giocatori`, d.`stato_iscrizione`, d.`stato_pagamento`, 180.00
FROM (
  SELECT 'Demo - I Falchi' AS `nome`, '#2455A4' AS `colore`,
         'luca.rossi.demo@example.test' AS `email`, 3 AS `numero_giocatori`,
         'completa' AS `stato_iscrizione`, 'pagato' AS `stato_pagamento`
  UNION ALL
  SELECT 'Demo - Le Volpi', '#D35400', 'marco.bianchi.demo@example.test', 3,
         'completa', 'parziale'
  UNION ALL
  SELECT 'Demo - Raptors', '#228B22', 'andrea.verdi.demo@example.test', 3,
         'completa', 'non_pagato'
  UNION ALL
  SELECT 'Demo - Pirati', '#6C3483', 'paolo.neri.demo@example.test', 3,
         'in_corso', 'non_pagato'
) d
JOIN `capitani` c ON c.`email` = d.`email`
WHERE @demo_torneo_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `squadre` s
    WHERE s.`nome` = d.`nome` OR s.`id_capitano` = c.`id`
  );

-- ------------------------------------------------------------
-- Giocatori demo collegati alle iscrizioni delle squadre
-- ------------------------------------------------------------
INSERT INTO `giocatori` (
  `id_squadra`, `nome`, `cognome`, `numero_maglia`, `ruolo`,
  `email`, `telefono`, `data_nascita`, `indirizzo`, `stato_iscrizione`
)
SELECT s.`id`, d.`nome`, d.`cognome`, d.`numero_maglia`, d.`ruolo`,
       d.`email`, d.`telefono`, d.`data_nascita`, 'Via Demo, Baone', 'completo'
FROM (
  SELECT 'Demo - I Falchi' AS `squadra`, 'Matteo' AS `nome`, 'Ferrari' AS `cognome`,
         1 AS `numero_maglia`, 'Portiere' AS `ruolo`, 'matteo.ferrari.demo@example.test' AS `email`,
         '3330000101' AS `telefono`, '1995-03-11' AS `data_nascita`
  UNION ALL SELECT 'Demo - I Falchi', 'Enrico', 'Moretti', 7, 'Capitano', 'enrico.moretti.demo@example.test', '3330000102', '1993-07-14'
  UNION ALL SELECT 'Demo - I Falchi', 'Davide', 'Gallo', 10, 'Giocatore', 'davide.gallo.demo@example.test', '3330000103', '1997-01-25'
  UNION ALL SELECT 'Demo - Le Volpi', 'Simone', 'Conti', 1, 'Portiere', 'simone.conti.demo@example.test', '3330000201', '1994-05-09'
  UNION ALL SELECT 'Demo - Le Volpi', 'Nicola', 'Marino', 8, 'Capitano', 'nicola.marino.demo@example.test', '3330000202', '1991-11-18'
  UNION ALL SELECT 'Demo - Le Volpi', 'Fabio', 'Greco', 11, 'Giocatore', 'fabio.greco.demo@example.test', '3330000203', '1996-06-02'
  UNION ALL SELECT 'Demo - Raptors', 'Stefano', 'Rizzo', 1, 'Portiere', 'stefano.rizzo.demo@example.test', '3330000301', '1992-08-21'
  UNION ALL SELECT 'Demo - Raptors', 'Alessio', 'Lombardi', 9, 'Capitano', 'alessio.lombardi.demo@example.test', '3330000302', '1990-10-30'
  UNION ALL SELECT 'Demo - Raptors', 'Giorgio', 'Barbieri', 14, 'Giocatore', 'giorgio.barbieri.demo@example.test', '3330000303', '1998-04-17'
  UNION ALL SELECT 'Demo - Pirati', 'Tommaso', 'Fontana', 1, 'Portiere', 'tommaso.fontana.demo@example.test', '3330000401', '1994-02-13'
  UNION ALL SELECT 'Demo - Pirati', 'Edoardo', 'Santoro', 6, 'Capitano', 'edoardo.santoro.demo@example.test', '3330000402', '1992-12-05'
  UNION ALL SELECT 'Demo - Pirati', 'Federico', 'Caruso', 12, 'Giocatore', 'federico.caruso.demo@example.test', '3330000403', '1997-09-23'
) d
JOIN `squadre` s ON s.`nome` = d.`squadra`
WHERE NOT EXISTS (
  SELECT 1 FROM `giocatori` g
  WHERE g.`id_squadra` = s.`id` AND g.`email` = d.`email`
);

-- ------------------------------------------------------------
-- Transazioni demo collegate alle squadre
-- ------------------------------------------------------------
INSERT INTO `transazioni` (
  `id_squadra`, `importo`, `id_metodo_pagamento`, `data_pagamento`,
  `stato`, `note`, `id_organizzatore`
)
SELECT s.`id`, d.`importo`,
       (SELECT mp.`id` FROM `metodi_pagamento` mp
        WHERE mp.`nome` = d.`metodo` ORDER BY mp.`id` LIMIT 1),
       d.`data_pagamento`, d.`stato`, d.`note`,
       COALESCE(
         (SELECT a.`id` FROM `admin` a WHERE a.`username` = 'demo_torneo' LIMIT 1),
         (SELECT MIN(a.`id`) FROM `admin` a)
       )
FROM (
  SELECT 'Demo - I Falchi' AS `squadra`, 180.00 AS `importo`,
         'Demo bonifico' AS `metodo`, '2026-02-12' AS `data_pagamento`,
         'confermata' AS `stato`, 'DEMO: saldo quota squadra' AS `note`
  UNION ALL
  SELECT 'Demo - Le Volpi', 90.00, 'Demo contanti', '2026-02-14',
         'confermata', 'DEMO: acconto quota squadra'
  UNION ALL
  SELECT 'Demo - Raptors', 50.00, 'Demo bonifico', '2026-02-16',
         'in_attesa', 'DEMO: pagamento in attesa di conferma'
) d
JOIN `squadre` s ON s.`nome` = d.`squadra`
WHERE NOT EXISTS (
  SELECT 1 FROM `transazioni` t
  WHERE t.`id_squadra` = s.`id` AND t.`note` = d.`note`
);
