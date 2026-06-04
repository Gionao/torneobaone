-- ============================================================
--  SOLO POPOLAMENTO DATI DEMO - TORNEO BAONE 2026
-- ============================================================

-- 1. SQUADRE
INSERT INTO `squadre` (`id`, `nome`, `colore`, `logo_url`, `capitano`) VALUES
(1, 'Stella Rossa Baone', '#FF0000', 'assets/logos/stella_rossa.png', 'Marco Rossi'),
(2, 'FC Real Calaone', '#0000FF', 'assets/logos/real_calaone.png', 'Luca Bianchi'),
(3, 'Atletico Rivadolmo', '#FFCC00', 'assets/logos/atletico_riva.png', 'Alessandro Verdi'),
(4, 'Valle San Giorgio C5', '#008000', 'assets/logos/valle_sg.png', 'Francesco Neri')
ON DUPLICATE KEY UPDATE `nome`=VALUES(`nome`), `colore`=VALUES(`colore`), `capitano`=VALUES(`capitano`);


-- 2. GIOCATORI
INSERT INTO `giocatori` (`id`, `id_squadra`, `nome`, `cognome`, `numero_maglia`, `e_portiere`) VALUES
-- Stella Rossa Baone
(1, 1, 'Gianluigi', 'Donnaroma', 1, 1),
(2, 1, 'Marco', 'Rossi', 10, 0),
(3, 1, 'Giovanni', 'Ferrari', 7, 0),
(4, 1, 'Antonio', 'Russo', 9, 0),
(5, 1, 'Matteo', 'Romano', 4, 0),
-- FC Real Calaone
(6, 2, 'Angelo', 'Peruzzi', 12, 1),
(7, 2, 'Luca', 'Bianchi', 8, 0),
(8, 2, 'Davide', 'Gallo', 11, 0),
(9, 2, 'Simone', 'Costa', 5, 0),
(10, 2, 'Federico', 'Leo', 23, 0),
-- Atletico Rivadolmo
(11, 3, 'Francesco', 'Toldo', 99, 1),
(12, 3, 'Alessandro', 'Verdi', 20, 0),
(13, 3, 'Roberto', 'Baggio', 10, 0),
(14, 3, 'Andrea', 'Pirlo', 21, 0),
(15, 3, 'Filippo', 'Inzaghi', 9, 0),
-- Valle San Giorgio C5
(16, 4, 'Sebastiano', 'Rossi', 22, 1),
(17, 4, 'Francesco', 'Neri', 6, 0),
(18, 4, 'Christian', 'Vieri', 32, 0),
(19, 4, 'Fabio', 'Cannavaro', 5, 0),
(20, 4, 'Gennaro', 'Gattuso', 8, 0)
ON DUPLICATE KEY UPDATE `nome`=VALUES(`nome`), `cognome`=VALUES(`cognome`), `numero_maglia`=VALUES(`numero_maglia`), `e_portiere`=VALUES(`e_portiere`);


-- 3. PARTITE
INSERT INTO `partite` (`id`, `id_fase`, `id_squadra_casa`, `id_squadra_osp`, `data_ora`, `campo`, `gol_casa`, `gol_osp`, `giocata`, `note`) VALUES
-- Giornata 1
(1, 1, 1, 2, '2026-05-10 20:30:00', 'Campo Principale Baone', 4, 2, 1, 'Partita inaugurale combattuta.'),
(2, 1, 3, 4, '2026-05-10 21:30:00', 'Campo Principale Baone', 1, 3, 1, 'Sorpresa della squadra ospite.'),
-- Giornata 2
(3, 1, 1, 3, '2026-05-17 20:30:00', 'Campo Principale Baone', 2, 2, 1, 'Pareggio spettacolare allo scadere.'),
(4, 1, 2, 4, '2026-05-17 21:30:00', 'Campo Principale Baone', 0, 1, 1, 'Partita molto tattica.'),
-- Giornata 3 (Da giocare)
(5, 1, 4, 1, '2026-05-24 20:30:00', 'Campo Principale Baone', NULL, NULL, 0, 'Scontro diretto per il primo posto.'),
(6, 1, 2, 3, '2026-05-24 21:30:00', 'Campo Principale Baone', NULL, NULL, 0, 'In palio l\'accesso ai playoff.'),
-- Fasi Finali (Programmate)
(7, 2, 2, 3, '2026-05-31 20:30:00', 'Campo Principale Baone', NULL, NULL, 0, 'Playoff 3° vs 4°'),
(8, 3, 1, 4, '2026-06-07 21:00:00', 'Campo Principale Baone', NULL, NULL, 0, 'Semifinale')
ON DUPLICATE KEY UPDATE `gol_casa`=VALUES(`gol_casa`), `gol_osp`=VALUES(`gol_osp`), `giocata`=VALUES(`giocata`);


-- 4. EVENTI PARTITA (Svuota prima per evitare doppioni di chiavi non primarie)
TRUNCATE TABLE `eventi_partita`;

INSERT INTO `eventi_partita` (`id_partita`, `id_giocatore`, `nome_libero`, `id_squadra`, `tipo`, `minuto`, `note`) VALUES
-- Partita 1 (Stella Rossa 4 - 2 Real Calaone)
(1, 2, NULL, 1, 'gol', 12, 'Tiro da fuori'),
(1, 4, NULL, 1, 'gol', 18, 'Azione solitaria'),
(1, 7, NULL, 2, 'gol', 24, 'Calcio di rigore'),
(1, 2, NULL, 1, 'gol', 35, 'In contropiede'),
(1, 8, NULL, 2, 'gol', 42, 'Colpo di testa'),
(1, 3, NULL, 1, 'gol', 49, 'Assist di Rossi'),
(1, 9, NULL, 2, 'ammonizione', 15, 'Fallo tattico'),
(1, 2, NULL, 1, 'mvp', NULL, 'Migliore in campo assoluto'),
-- Partita 2 (Atletico Rivadolmo 1 - 3 Valle San Giorgio)
(2, 15, NULL, 3, 'gol', 5, 'Gol di rapina'),
(2, 18, NULL, 4, 'gol', 22, 'Potente diagonale'),
(2, 18, NULL, 4, 'gol', 30, 'Punizione magistrale'),
(2, NULL, 'Autorete Difesa Riva', 3, 'gol_autorete', 45, 'Incomprensione col portiere'),
(2, 20, NULL, 4, 'ammonizione', 38, 'Proteste'),
(2, 12, NULL, 3, 'espulsione', 48, 'Fallo da ultimo uomo'),
-- Partita 3 (Stella Rossa 2 - 2 Atletico Rivadolmo)
(3, 4, NULL, 1, 'gol', 8, 'Tap-in vincente'),
(3, 13, NULL, 3, 'gol', 15, 'Pallonetto delizioso'),
(3, 15, NULL, 3, 'gol', 29, 'Sotto la traversa'),
(3, 3, NULL, 1, 'gol', 50, 'Pareggio all\'ultimo respiro'),
(3, 5, NULL, 1, 'ammonizione', 41, 'Gioco scorretto'),
-- Partita 4 (Real Calaone 0 - 1 Valle San Giorgio)
(4, 17, NULL, 4, 'gol', 19, 'Sotto la pioggia'),
(4, 10, NULL, 2, 'ammonizione', 11, 'Fallo di mano'),
(4, 19, NULL, 4, 'ammonizione', 44, 'Trattenuta');


-- 5. CLASSIFICA COERENTE (Svuota e ripopola lo stato attuale delle partite giocate)
TRUNCATE TABLE `classifica`;

INSERT INTO `classifica` (`id_squadra`, `punti`, `giocate`, `vinte`, `pareggiate`, `perse`, `gol_fatti`, `gol_subiti`, `diff_reti`, `ammonizioni`, `espulsioni`) VALUES
(4, 6, 2, 2, 0, 0, 4, 1, 3, 2, 0),   -- Valle San Giorgio
(1, 4, 2, 1, 1, 0, 6, 4, 2, 1, 0),   -- Stella Rossa Baone
(3, 1, 2, 0, 1, 1, 3, 5, -2, 0, 1),  -- Atletico Rivadolmo
(2, 0, 2, 0, 0, 2, 2, 5, -3, 2, 0);  -- FC Real Calaone