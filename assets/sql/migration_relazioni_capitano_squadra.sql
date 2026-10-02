-- Migrazione una tantum per database esistenti.
-- Prima dell'esecuzione verificare che non ci siano id_capitano duplicati/orfani
-- e che nessuna squadra abbia piu' di 9 giocatori.

ALTER TABLE `squadre`
  DROP FOREIGN KEY `fk_squadra_capitano`,
  DROP INDEX `idx_squadre_id_capitano`,
  DROP INDEX `uq_squadre_token_capitano`,
  DROP COLUMN `capitano_giocatore_id`,
  DROP COLUMN `capitano`,
  DROP COLUMN `token_capitano`,
  ADD UNIQUE KEY `uq_squadre_id_capitano` (`id_capitano`),
  ADD CONSTRAINT `fk_squadre_capitano_account`
    FOREIGN KEY (`id_capitano`) REFERENCES `capitani` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

DELIMITER //
DROP TRIGGER IF EXISTS `trg_giocatori_max_nove_insert`//
CREATE TRIGGER `trg_giocatori_max_nove_insert`
BEFORE INSERT ON `giocatori`
FOR EACH ROW
BEGIN
  DECLARE player_count INT UNSIGNED DEFAULT 0;
  SELECT COUNT(*) INTO player_count FROM `giocatori` WHERE `id_squadra` = NEW.`id_squadra`;
  IF player_count >= 9 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una squadra puo\' avere al massimo 9 giocatori';
  END IF;
END//

DROP TRIGGER IF EXISTS `trg_giocatori_max_nove_update`//
CREATE TRIGGER `trg_giocatori_max_nove_update`
BEFORE UPDATE ON `giocatori`
FOR EACH ROW
BEGIN
  DECLARE player_count INT UNSIGNED DEFAULT 0;
  IF NEW.`id_squadra` <> OLD.`id_squadra` THEN
    SELECT COUNT(*) INTO player_count FROM `giocatori` WHERE `id_squadra` = NEW.`id_squadra`;
    IF player_count >= 9 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una squadra puo\' avere al massimo 9 giocatori';
    END IF;
  END IF;
END//
DELIMITER ;