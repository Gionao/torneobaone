-- Eseguire una sola volta prima di distribuire il codice POS aggiornato.
-- Gli ordini esistenti rimangono ordini normali.
ALTER TABLE pos_orders
    ADD COLUMN is_staff TINYINT(1) NOT NULL DEFAULT 0 AFTER is_takeaway;
