-- migrations/004_audit.sql — auditoría de accesos (quién entró, cuándo, cuánto duró).
CREATE TABLE IF NOT EXISTS accesos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('admin','cliente') NOT NULL,
  email VARCHAR(160) NOT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  agente VARCHAR(255) NOT NULL DEFAULT '',
  entrada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  salida TIMESTAMP NULL DEFAULT NULL,
  segundos INT DEFAULT NULL,
  KEY idx_tipo (tipo), KEY idx_email (email), KEY idx_entrada (entrada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
