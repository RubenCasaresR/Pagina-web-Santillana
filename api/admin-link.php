<?php
// POST /api/admin-link.php {t:"..."} — canjea pase de "Ir a la tienda" por sesión admin.
// Un solo uso, expira en 10 min, con límite de intentos.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth-lib.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jexit(405, ['error' => 'metodo']);
sess_start();
try {
  $t = preg_replace('/[^a-f0-9]/', '', strtolower((string)(body_json()['t'] ?? '')));
  if (strlen($t) !== 32) jexit(422, ['error' => 'pase']);
  if (!throttle_ip('admin_link', 20, 600)) jexit(429, ['error' => 'limite']);
  $pdo = db();
  $st = $pdo->prepare("SELECT token FROM admin_links WHERE token=? AND created_at >= (NOW() - INTERVAL 10 MINUTE)");
  $st->execute([$t]);
  if (!$st->fetch()) jexit(422, ['error' => 'pase']);
  $pdo->prepare('DELETE FROM admin_links WHERE token=?')->execute([$t]);
  session_regenerate_id(true);
  $_SESSION['sant_admin'] = 1;
  $_SESSION['audit_id'] = audit_login($pdo, 'admin', (string)cfg('admin_email'));
  jexit(200, ['ok' => true]);
} catch (Throwable $e) {
  jexit(500, ['error' => 'pase']);
}
