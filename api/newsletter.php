<?php
// POST /api/newsletter.php {email} — suscripción real (tabla newsletter, doble registro de duplicados).
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jexit(405, ['error' => 'metodo']);
try {
  $b = body_json();
  rate_limit('news', 5, 3600);
  $email = trim((string)($b['email'] ?? ''));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jexit(422, ['error' => 'email']);
  $pdo = db();
  $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter (
    id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(160) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  try {
    $pdo->prepare('INSERT INTO newsletter(email) VALUES(?)')->execute([$email]);
    jexit(200, ['ok' => true]);
  } catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) === 1062) jexit(200, ['ok' => true, 'dup' => true]);
    throw $e;
  }
} catch (Throwable $e) {
  jexit(500, ['error' => 'db']);
}
