ALTER TABLE `squadre`
  ADD COLUMN `autorizzata` TINYINT(1) NOT NULL DEFAULT 0 AFTER `attiva`;
