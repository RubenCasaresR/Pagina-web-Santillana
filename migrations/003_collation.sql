-- migrations/003_collation.sql — unificar todo a utf8mb4_unicode_ci (order_items y
-- webhook_log heredados quedaron en utf8mb4_general_ci y rompían JOINs).
ALTER TABLE order_items CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE webhook_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
