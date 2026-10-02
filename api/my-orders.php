<?php
// GET /api/my-orders.php — pedidos del cliente logueado (por customer_id o email).
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth-lib.php';
sess_start();
try {
  if (empty($_SESSION['cid'])) jexit(401, ['error' => 'no-login']);
  $pdo = db();
  $st = $pdo->prepare('SELECT email FROM customers WHERE id=?');
  $st->execute([$_SESSION['cid']]);
  $c = $st->fetch();
  if (!$c) jexit(401, ['error' => 'no-login']);
  $st = $pdo->prepare('SELECT folio,status,total,created_at,paid_at FROM orders WHERE customer_id=? OR email=? ORDER BY id DESC LIMIT 50');
  $st->execute([$_SESSION['cid'], $c['email']]);
  $orders = [];
  $estado = ['PENDING' => 'Pago pendiente', 'PENDING_TRANSFER' => 'Pendiente de transferencia', 'PAID' => 'Pagado — en preparación', 'CANCELLED' => 'Cancelado', 'REFUNDED' => 'Reembolsado', 'EXPIRED' => 'Expirado'];
  foreach ($st->fetchAll() as $o) {
    $it = $pdo->prepare('SELECT oi.qty, p.nombre FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id=(SELECT id FROM orders WHERE folio=?)');
    $it->execute([$o['folio']]);
    $o['total'] = (int)$o['total'] / 100;
    $o['estado_txt'] = $estado[$o['status']] ?? $o['status'];
    $o['items'] = $it->fetchAll();
    $orders[] = $o;
  }
  jexit(200, ['orders' => $orders]);
} catch (Throwable $e) {
  jexit(500, ['error' => 'pedidos']);
}
