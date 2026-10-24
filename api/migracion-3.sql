-- Migración 3 (comprar sin iniciar sesión).
-- Para una base que YA tiene las tablas users y orders. Importar UNA sola vez en phpMyAdmin.

ALTER TABLE orders MODIFY user_id INT UNSIGNED NULL;
ALTER TABLE orders ADD COLUMN guest_email VARCHAR(190) NULL AFTER user_id;
ALTER TABLE orders ADD COLUMN mailed_at DATETIME NULL AFTER paid_at;
ALTER TABLE orders ADD KEY idx_orders_guest (guest_email);
