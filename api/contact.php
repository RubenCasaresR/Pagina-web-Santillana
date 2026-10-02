<?php
// POST /api/contact.php {nombre, email, mensaje} — guarda mensaje + anti-spam básico (honeypot + rate-limit).
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jexit(405, ['error' => 'metodo']);
try {
  $b = body_json();
  if (!empty($b['empresa'])) jexit(200, ['ok' => true]); // honeypot
  if (!recaptcha_ok($b['recaptcha'] ?? null)) jexit(422, ['error' => 'captcha']);
  $nombre = trim((string)($b['nombre'] ?? ''));
  $email = trim((string)($b['email'] ?? ''));
  $msg = trim((string)($b['mensaje'] ?? ''));
  if (mb_strlen($nombre) < 3) jexit(422, ['error' => 'nombre']);
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jexit(422, ['error' => 'email']);
  if (mb_strlen($msg) < 10 || mb_strlen($msg) > 2000) jexit(422, ['error' => 'mensaje']);
  // rate-limit: max 3/hora por IP
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'x';
  $dir = sys_get_temp_dir() . '/sant_rl';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  $f = $dir . '/c_' . md5($ip);
  $h = (int)@file_get_contents($f);
  $t = @filemtime($f) ?: 0;
  if (time() - $t < 3600 && $h >= 3) jexit(429, ['error' => 'limite']);
  @file_put_contents($f, (string)(time() - $t < 3600 ? $h + 1 : 1));
  $pdo = db();
  $pdo->exec("CREATE TABLE IF NOT EXISTS mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(160) NOT NULL, email VARCHAR(160) NOT NULL,
    mensaje VARCHAR(2000) NOT NULL, leido TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->prepare('INSERT INTO mensajes(nombre,email,mensaje) VALUES(?,?,?)')->execute([$nombre, $email, $msg]);
  jexit(200, ['ok' => true]);
} catch (Throwable $e) {
  jexit(500, ['error' => 'contacto']);
}
