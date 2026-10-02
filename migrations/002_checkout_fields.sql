-- migrations/002_checkout_fields.sql — campos del checkout 2 columnas + SPEI + newsletter/contacto.
-- Aplicar en DBs existentes (post-001). api/db.sql ya los incluye para instalaciones nuevas.

ALTER TABLE orders
  ADD COLUMN apellidos VARCHAR(160) NOT NULL DEFAULT '' AFTER nombre,
  ADD COLUMN estado VARCHAR(80) NOT NULL DEFAULT '' AFTER ciudad,
  ADD COLUMN pais VARCHAR(60) NOT NULL DEFAULT 'México' AFTER estado,
  ADD COLUMN depto VARCHAR(120) NOT NULL DEFAULT '' AFTER direccion,
  ADD COLUMN billing_same TINYINT(1) NOT NULL DEFAULT 1 AFTER depto,
  ADD COLUMN billing_address VARCHAR(300) NOT NULL DEFAULT '' AFTER billing_same,
  ADD COLUMN subscribe_news TINYINT(1) NOT NULL DEFAULT 0 AFTER billing_address,
  ADD COLUMN payment_method VARCHAR(10) NOT NULL DEFAULT 'paypal' AFTER subscribe_news,
  ADD COLUMN spei_ref VARCHAR(64) DEFAULT NULL AFTER paypal_capture_id,
  MODIFY COLUMN status ENUM('PENDING','PENDING_TRANSFER','PAID','CANCELLED','REFUNDED','EXPIRED') NOT NULL DEFAULT 'PENDING';

CREATE TABLE IF NOT EXISTS newsletter (
  id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(160) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensajes (
  id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(160) NOT NULL, email VARCHAR(160) NOT NULL,
  mensaje VARCHAR(2000) NOT NULL, leido TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
