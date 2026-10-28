-- ============================================================
--  Archivio multimediale — Migrazione 002
--  Estende i ruoli della tabella condivisa `admin` con i ruoli
--  dell'archivio: `uploader` ed `editor`.
--
--  I ruoli esistenti restano invariati. Migrazione additiva:
--  nessun utente viene creato, modificato o eliminato.
-- ============================================================

ALTER TABLE `admin`
  MODIFY COLUMN `role`
    ENUM('admin','operatore','bar','cucina','telecronista','uploader','editor')
    NOT NULL DEFAULT 'admin';
