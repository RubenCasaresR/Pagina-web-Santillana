<?php
// POST /api/unsubscribe.php {email} — baja de novedades (derecho de cancelación).
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jexit(405, ['error' => 'metodo']);
try {
  $b = body_json();
  $email = trim((string)($b['email'] ?? ''));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jexit(422, ['error' => 'email']);
  db()->prepare('DELETE FROM newsletter WHERE email=?')->execute([$email]);
  jexit(200, ['ok' => true]);
} catch (Throwable $e) {
  jexit(500, ['error' => 'baja']);
}
