-- Abilita account dedicati alla gestione delle partite fuori dall'area admin.
ALTER TABLE `admin`
  MODIFY COLUMN `role` ENUM('admin','operatore','bar','cucina','telecronista') NOT NULL DEFAULT 'admin';

-- Crea un account assegnando username e password hash reali:
-- INSERT INTO `admin` (`username`, `password_hash`, `role`)
-- VALUES ('telecronista', '$2y$12$INSERIRE_HASH_BCRYPT', 'telecronista');
