<?php
// api/cron-expire.php — libera stock reservado de pedidos abandonados.
// PENDING (PayPal sin capturar) >30 min  → EXPIRED
// PENDING_TRANSFER (SPEI sin acreditar) >72 h → EXPIRED
// Uso CLI (recomendado):  php api/cron-expire.php
// Cron Linux: */15 * * * * php /ruta/bazar-e.mx/api/cron-expire.php
// Windows Task Scheduler: php.exe "C:\ruta\api\cron-expire.php" cada 15 min
// Vía HTTP: solo con sesión admin (admin/index.php). Sin admin → 403.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
  require_once __DIR__ . '/auth-lib.php';
  sess_start();
  if (empty($_SESSION['sant_admin'])) jexit(403, ['error' => 'solo-admin']);
}

try {
  $pdo = db();
  $out = ['paypal_expirados' => 0, 'spei_expirados' => 0];

  // 1) PayPal PENDING viejos (>30 min, nunca pagados)
  $st = $pdo->query(
    "SELECT id FROM orders WHERE status='PENDING' AND created_at < (NOW() - INTERVAL 30 MINUTE) LIMIT 500"
  );
  $ids = $st->fetchAll(PDO::FETCH_COLUMN);
  foreach ($ids as $oid) {
    $pdo->beginTransaction();
    // Bloquea la orden; si ya cambió de estado otro proceso la salta
    $o = $pdo->prepare("SELECT id FROM orders WHERE id=? AND status='PENDING' FOR UPDATE");
    $o->execute([$oid]);
    if (!$o->fetch()) { $pdo->rollBack(); continue; }
    $it = $pdo->prepare('SELECT product_id, qty FROM order_items WHERE order_id=?');
    $it->execute([$oid]);
    $up = $pdo->prepare('UPDATE products SET reservado=GREATEST(reservado-?,0) WHERE id=?');
    foreach ($it->fetchAll() as $l) $up->execute([$l['qty'], $l['product_id']]);
    $pdo->prepare("UPDATE orders SET status='EXPIRED' WHERE id=?")->execute([$oid]);
    $pdo->commit();
    $out['paypal_expirados']++;
  }

  // 2) SPEI PENDING_TRANSFER viejos (>72 h sin acreditar)
  $st = $pdo->query(
    "SELECT id FROM orders WHERE status='PENDING_TRANSFER' AND created_at < (NOW() - INTERVAL 72 HOUR) LIMIT 500"
  );
  $ids = $st->fetchAll(PDO::FETCH_COLUMN);
  foreach ($ids as $oid) {
    $pdo->beginTransaction();
    $o = $pdo->prepare("SELECT id FROM orders WHERE id=? AND status='PENDING_TRANSFER' FOR UPDATE");
    $o->execute([$oid]);
    if (!$o->fetch()) { $pdo->rollBack(); continue; }
    $it = $pdo->prepare('SELECT product_id, qty FROM order_items WHERE order_id=?');
    $it->execute([$oid]);
    $up = $pdo->prepare('UPDATE products SET reservado=GREATEST(reservado-?,0) WHERE id=?');
    foreach ($it->fetchAll() as $l) $up->execute([$l['qty'], $l['product_id']]);
    $pdo->prepare("UPDATE orders SET status='EXPIRED' WHERE id=?")->execute([$oid]);
    $pdo->commit();
    $out['spei_expirados']++;
  }

  $out['ok'] = true;
  if ($isCli) {
    echo json_encode($out, JSON_UNESCAPED_UNICODE) . PHP_EOL;
  } else {
    jexit(200, $out);
  }
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  if ($isCli) { fwrite(STDERR, 'expire-error' . PHP_EOL); exit(1); }
  jexit(500, ['error' => 'expire']);
}
