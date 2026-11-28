ALTER TABLE pos_orders
    ADD COLUMN tracking_token_hash CHAR(64) NULL,
    ADD COLUMN was_delivered TINYINT(1) NOT NULL DEFAULT 0,
    ADD UNIQUE KEY uq_pos_orders_tracking_token (tracking_token_hash);

UPDATE pos_orders SET was_delivered = 1 WHERE status = 'delivered';
