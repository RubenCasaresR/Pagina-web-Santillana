-- migrations/001_baseline.sql — snapshot del schema al 2026-10-01 (pre-fase checkout).
-- En instalaciones NUEVAS basta api/db.sql (schema canónico actual).
-- En DBs existentes: 001 ya aplicado -> aplicar 002 en adelante.

CREATE DATABASE IF NOT EXISTS santillana CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE santillana;

CREATE TABLE IF NOT EXISTS `customer_carts` (
  `customer_id` int(11) NOT NULL,
  `product_id` varchar(32) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`customer_id`,`product_id`),
  CONSTRAINT `fk_cc_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(160) NOT NULL,
  `email` varchar(160) NOT NULL,
  `pass_hash` varchar(255) NOT NULL,
  `telefono` varchar(20) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_cemail` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mensajes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(160) NOT NULL,
  `email` varchar(160) NOT NULL,
  `mensaje` varchar(2000) NOT NULL,
  `leido` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `newsletter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(160) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
  `order_id` int(11) NOT NULL,
  `product_id` varchar(32) NOT NULL,
  `qty` int(11) NOT NULL,
  `unit_price` int(11) NOT NULL,
  PRIMARY KEY (`order_id`,`product_id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `folio` varchar(16) NOT NULL,
  `idempotency_key` char(36) NOT NULL,
  `email` varchar(160) NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `direccion` varchar(220) NOT NULL,
  `ciudad` varchar(120) NOT NULL,
  `cp` char(5) NOT NULL,
  `subtotal` int(11) NOT NULL,
  `envio` int(11) NOT NULL,
  `total` int(11) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'MXN',
  `status` enum('PENDING','PAID','CANCELLED','REFUNDED','EXPIRED') NOT NULL DEFAULT 'PENDING',
  `paypal_order_id` varchar(64) DEFAULT NULL,
  `paypal_capture_id` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` timestamp NULL DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `folio` (`folio`),
  UNIQUE KEY `idempotency_key` (`idempotency_key`),
  UNIQUE KEY `paypal_order_id` (`paypal_order_id`),
  KEY `idx_status` (`status`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_cats` (
  `product_id` varchar(32) NOT NULL,
  `cat` varchar(40) NOT NULL,
  PRIMARY KEY (`product_id`,`cat`),
  CONSTRAINT `fk_pc_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id` varchar(32) NOT NULL,
  `sku` varchar(32) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `marca` varchar(80) NOT NULL DEFAULT '',
  `cat_primary` varchar(40) NOT NULL DEFAULT '',
  `precio` int(11) NOT NULL DEFAULT 0,
  `precio_lista` int(11) NOT NULL DEFAULT 0,
  `moneda` char(3) NOT NULL DEFAULT 'MXN',
  `stock` int(11) NOT NULL DEFAULT 0,
  `reservado` int(11) NOT NULL DEFAULT 0,
  `disponible` tinyint(1) NOT NULL DEFAULT 1,
  `destacado` tinyint(1) NOT NULL DEFAULT 0,
  `en_oferta` tinyint(1) NOT NULL DEFAULT 0,
  `img` varchar(255) NOT NULL DEFAULT '',
  `img_large` varchar(255) NOT NULL DEFAULT '',
  `img_webp` varchar(255) NOT NULL DEFAULT '',
  `img_large_webp` varchar(255) NOT NULL DEFAULT '',
  `descripcion_corta` varchar(500) NOT NULL DEFAULT '',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_cat` (`cat_primary`),
  KEY `idx_disp` (`disponible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhook_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source` varchar(20) NOT NULL DEFAULT 'paypal',
  `event_type` varchar(80) NOT NULL DEFAULT '',
  `payload` mediumtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
