-- ============================================================
--  Archivio multimediale — Migrazione 002 (DOWN)
--  ATTENZIONE: operazione distruttiva. Non eseguire senza
--  autorizzazione esplicita.
--
--  Rimuove i ruoli `uploader` ed `editor` dall'ENUM.
--  Fallisce (o converte i valori) se esistono account con quei
--  ruoli: prima occorre riassegnarli o eliminarli.
-- ============================================================

ALTER TABLE `admin`
  MODIFY COLUMN `role`
    ENUM('admin','operatore','bar','cucina','telecronista')
    NOT NULL DEFAULT 'admin';
