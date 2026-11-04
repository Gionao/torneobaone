-- Torneo Baone - additive unified staff identity/assignment migration.
-- MySQL 8.0 tested target. This file is NOT an automatic production script.
-- Review database/migrations/README.md, take a verified backup, and run only
-- against a restored disposable copy first.
-- No DROP, TRUNCATE, DELETE, or business-row rewrite is performed.

SET NAMES utf8mb4;

DELIMITER //
CREATE PROCEDURE IF NOT EXISTS `tb_add_column_if_missing`(
  IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_definition TEXT
)
BEGIN
  DECLARE v_count INT DEFAULT 0;
  SELECT COUNT(*) INTO v_count
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column;
  IF v_count = 0 THEN
    SET @tb_ddl = CONCAT('ALTER TABLE `', REPLACE(p_table, '`', '``'), '` ADD COLUMN `',
      REPLACE(p_column, '`', '``'), '` ', p_definition);
    PREPARE tb_stmt FROM @tb_ddl;
    EXECUTE tb_stmt;
    DEALLOCATE PREPARE tb_stmt;
  END IF;
END//
DELIMITER ;

-- Existing staff table and primary key are prerequisites; do not recreate or
-- replace it because business tables reference admin.id.
CALL `tb_add_column_if_missing`('admin', 'attivo', 'TINYINT(1) NOT NULL DEFAULT 1');
CALL `tb_add_column_if_missing`('admin', 'is_super_admin', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL `tb_add_column_if_missing`('admin', 'must_change_password', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL `tb_add_column_if_missing`('admin', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL `tb_add_column_if_missing`('admin', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

CREATE TABLE IF NOT EXISTS `staff_ruoli_area` (
  `area` VARCHAR(32) NOT NULL,
  `ruolo` VARCHAR(32) NOT NULL,
  `descrizione` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`area`, `ruolo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `staff_ruoli_area` (`area`, `ruolo`, `descrizione`) VALUES
('torneo','amministratore','Gestione generale torneo, squadre, iscrizioni e pagamenti'),
('torneo','segreteria','Iscrizioni e pagamenti, senza amministrazione globale'),
('partite','gestore','Gestione calendario, risultati ed eventi'),
('partite','telecronista','Inserimento eventi e risultati con funzioni limitate'),
('pos','amministratore','Gestione completa del POS, non degli utenti globali'),
('pos','operatore','Cassa e ordini dalla postazione assegnata'),
('pos','bar','Preparazione e gestione ordini bar'),
('pos','cucina','Preparazione e gestione ordini cucina'),
('archivio','amministratore','Gestione completa archivio multimediale'),
('archivio','editor','Organizzazione e modifica media autorizzati'),
('archivio','uploader','Caricamento file nelle edizioni autorizzate')
ON DUPLICATE KEY UPDATE `descrizione` = VALUES(`descrizione`);

CREATE TABLE IF NOT EXISTS `staff_assegnazioni` (
  `id_admin` INT UNSIGNED NOT NULL,
  `area` VARCHAR(32) NOT NULL,
  `ruolo` VARCHAR(32) NOT NULL,
  `station_name` VARCHAR(80) NULL DEFAULT NULL,
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_admin`, `area`),
  KEY `idx_staff_assegnazioni_ruolo` (`area`, `ruolo`),
  CONSTRAINT `fk_staff_assegnazioni_utente` FOREIGN KEY (`id_admin`)
    REFERENCES `admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_staff_assegnazioni_ruolo` FOREIGN KEY (`area`, `ruolo`)
    REFERENCES `staff_ruoli_area` (`area`, `ruolo`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CALL `tb_add_column_if_missing`('staff_assegnazioni', 'attivo', 'TINYINT(1) NOT NULL DEFAULT 1');

CREATE TABLE IF NOT EXISTS `staff_security_guard` (
  `id` TINYINT UNSIGNED NOT NULL,
  `active_super_admin_count` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_staff_security_guard_singleton` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CALL `tb_add_column_if_missing`('staff_security_guard', 'active_super_admin_count', 'INT UNSIGNED NOT NULL DEFAULT 0');
INSERT INTO `staff_security_guard` (`id`, `active_super_admin_count`)
SELECT 1, COUNT(*) FROM `admin` WHERE `attivo` = 1 AND `is_super_admin` = 1
ON DUPLICATE KEY UPDATE `active_super_admin_count` = VALUES(`active_super_admin_count`);

-- Read-only migration review result: print full legacy value frequencies and
-- accounts with the ambiguous generic value. Resolve before granting roles.
SET @tb_has_role = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin' AND COLUMN_NAME = 'role'
);
SET @tb_review_sql = IF(@tb_has_role = 1,
  'SELECT `role` AS legacy_role, COUNT(*) AS account_count FROM `admin` GROUP BY `role` ORDER BY `role`',
  'SELECT ''No legacy admin.role column exists; no role mapping was inferred.'' AS migration_review');
PREPARE tb_review_stmt FROM @tb_review_sql;
EXECUTE tb_review_stmt;
DEALLOCATE PREPARE tb_review_stmt;

-- Only unambiguous operational role values are migrated automatically.
-- Generic `admin` is intentionally excluded: legacy data cannot distinguish
-- tournament, POS, or archive administrators, and it is never a Super Admin.
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS `tb_migrate_unambiguous_staff_roles`()
BEGIN
  DECLARE v_has_role INT DEFAULT 0;
  DECLARE v_has_station INT DEFAULT 0;
  SELECT COUNT(*) INTO v_has_role FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin' AND COLUMN_NAME = 'role';
  SELECT COUNT(*) INTO v_has_station FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin' AND COLUMN_NAME = 'station_name';

  IF v_has_role = 1 THEN
    SET @tb_sql = 'INSERT INTO `staff_assegnazioni` (`id_admin`,`area`,`ruolo`) SELECT a.`id`, m.`area`, m.`ruolo` FROM `admin` a JOIN (SELECT ''telecronista'' AS old_role, ''partite'' AS area, ''telecronista'' AS ruolo UNION ALL SELECT ''operatore'',''pos'',''operatore'' UNION ALL SELECT ''bar'',''pos'',''bar'' UNION ALL SELECT ''cucina'',''pos'',''cucina'' UNION ALL SELECT ''uploader'',''archivio'',''uploader'' UNION ALL SELECT ''editor'',''archivio'',''editor'') m ON a.`role` = m.`old_role` WHERE NOT EXISTS (SELECT 1 FROM `staff_assegnazioni` s WHERE s.`id_admin` = a.`id` AND s.`area` = m.`area`)';
    PREPARE tb_map_stmt FROM @tb_sql;
    EXECUTE tb_map_stmt;
    DEALLOCATE PREPARE tb_map_stmt;

    IF v_has_station = 1 THEN
      SET @tb_sql = 'UPDATE `staff_assegnazioni` s JOIN `admin` a ON a.`id` = s.`id_admin` SET s.`station_name` = a.`station_name` WHERE s.`area` = ''pos'' AND s.`ruolo` = ''operatore'' AND (s.`station_name` IS NULL OR s.`station_name` = '''') AND a.`station_name` IS NOT NULL AND a.`station_name` <> ''''';
      PREPARE tb_station_stmt FROM @tb_sql;
      EXECUTE tb_station_stmt;
      DEALLOCATE PREPARE tb_station_stmt;
    END IF;
  END IF;
END//
DELIMITER ;
CALL `tb_migrate_unambiguous_staff_roles`();

-- Install last-active-Super-Admin protections. MySQL has no portable
-- CREATE TRIGGER IF NOT EXISTS. On a repeated run these two statements will
-- report that their trigger names already exist; earlier DDL/data operations
-- are guarded/idempotent and no assignment is duplicated or overwritten.
DELIMITER //
CREATE TRIGGER `trg_admin_keep_active_super_admin_update`
BEFORE UPDATE ON `admin`
FOR EACH ROW
BEGIN
  DECLARE v_guard_count INT UNSIGNED DEFAULT 0;
  IF OLD.`attivo` = 1 AND OLD.`is_super_admin` = 1
     AND (NEW.`attivo` <> 1 OR NEW.`is_super_admin` <> 1) THEN
    SELECT `active_super_admin_count` INTO v_guard_count FROM `staff_security_guard` WHERE `id` = 1 FOR UPDATE;
    IF v_guard_count <= 1 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Impossibile disattivare o declassare l ultimo Super Admin attivo';
    END IF;
  END IF;
END//

CREATE TRIGGER `trg_admin_count_super_admin_insert`
AFTER INSERT ON `admin`
FOR EACH ROW
BEGIN
  IF NEW.`attivo` = 1 AND NEW.`is_super_admin` = 1 THEN
    UPDATE `staff_security_guard` SET `active_super_admin_count` = `active_super_admin_count` + 1 WHERE `id` = 1;
  END IF;
END//

CREATE TRIGGER `trg_admin_count_super_admin_update`
AFTER UPDATE ON `admin`
FOR EACH ROW
BEGIN
  IF OLD.`attivo` = 1 AND OLD.`is_super_admin` = 1
     AND (NEW.`attivo` <> 1 OR NEW.`is_super_admin` <> 1) THEN
    UPDATE `staff_security_guard` SET `active_super_admin_count` = `active_super_admin_count` - 1 WHERE `id` = 1;
  ELSEIF (OLD.`attivo` <> 1 OR OLD.`is_super_admin` <> 1)
     AND NEW.`attivo` = 1 AND NEW.`is_super_admin` = 1 THEN
    UPDATE `staff_security_guard` SET `active_super_admin_count` = `active_super_admin_count` + 1 WHERE `id` = 1;
  END IF;
END//

CREATE TRIGGER `trg_admin_keep_active_super_admin_delete`
BEFORE DELETE ON `admin`
FOR EACH ROW
BEGIN
  DECLARE v_guard_count INT UNSIGNED DEFAULT 0;
  IF OLD.`attivo` = 1 AND OLD.`is_super_admin` = 1 THEN
    SELECT `active_super_admin_count` INTO v_guard_count FROM `staff_security_guard` WHERE `id` = 1 FOR UPDATE;
    IF v_guard_count <= 1 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Impossibile eliminare l ultimo Super Admin attivo';
    END IF;
  END IF;
END//

CREATE TRIGGER `trg_admin_count_super_admin_delete`
AFTER DELETE ON `admin`
FOR EACH ROW
BEGIN
  IF OLD.`attivo` = 1 AND OLD.`is_super_admin` = 1 THEN
    UPDATE `staff_security_guard` SET `active_super_admin_count` = `active_super_admin_count` - 1 WHERE `id` = 1;
  END IF;
END//
DELIMITER ;

-- Report every value not automatically mapped, including generic `admin`,
-- unknown enum values, and legacy roles that need contextual human review.
SET @tb_unmapped_sql = IF(@tb_has_role = 1,
  'SELECT a.`id`, a.`username`, a.`role` AS legacy_role, IF(a.`role` = ''admin'', ''AMBIGUOUS: select exactly the intended sector(s); never infer Super Admin'', ''UNMAPPED: resolve manually before cutover'') AS action_required FROM `admin` a WHERE a.`role` NOT IN (''telecronista'',''operatore'',''bar'',''cucina'',''uploader'',''editor'') ORDER BY a.`id`',
  'SELECT ''No legacy role column: assignments were not inferred; create/reconcile them from a reviewed account source.'' AS action_required');
PREPARE tb_unmapped_stmt FROM @tb_unmapped_sql;
EXECUTE tb_unmapped_stmt;
DEALLOCATE PREPARE tb_unmapped_stmt;

-- Legacy columns (`role`, `station_name`) are intentionally retained. Do not
-- grant a generic legacy admin value; resolve its target area explicitly and
-- use a separately controlled bootstrap for the first Super Admin.
