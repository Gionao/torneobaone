-- Aggiunge i ruoli usati dall'archivio foto alla tabella admin esistente.
-- Da eseguire una sola volta sul database del sito (es. da phpMyAdmin).
-- Mantiene tutti i ruoli e i record esistenti.
ALTER TABLE `admin`
  MODIFY COLUMN `role`
    ENUM('admin','operatore','bar','cucina','telecronista','uploader','editor')
    NOT NULL DEFAULT 'admin';
