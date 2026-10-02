<?php
// POST /api/track.php {folio, email} — estado del pedido (solo datos propios).
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jexit(405, ['error' => 'metodo']);
try {
  $b = body_json();
  $folio = strtoupper(trim((string)($b['folio'] ?? '')));
  $email = strtolower(trim((string)($b['email'] ?? '')));
  if (!preg_match('/^ST-\d{5}$/', $folio)) jexit(422, ['error' => 'folio']);
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jexit(422, ['error' => 'email']);
  $pdo = db();
  $st = $pdo->prepare('SELECT folio,status,total,ciudad,cp,created_at,paid_at FROM orders WHERE folio=? AND email=?');
  $st->execute([$folio, $email]);
  $o = $st->fetch();
  if (!$o) jexit(404, ['error' => 'no-encontrado']);
  $it = $pdo->prepare('SELECT oi.qty, oi.unit_price, p.nombre FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id=(SELECT id FROM orders WHERE folio=?)');
  $it->execute([$folio]);
  $items = [];
  foreach ($it->fetchAll() as $l) {
    $items[] = ['nombre' => $l['nombre'], 'qty' => (int)$l['qty'], 'unit_price' => (int)$l['unit_price'] / 100];
  }
  $o['total'] = (int)$o['total'] / 100;
  $o['items'] = $items;
  $estado_txt = ['PENDING' => 'Pago pendiente', 'PENDING_TRANSFER' => 'Pendiente de transferencia', 'PAID' => 'Pagado — en preparación', 'CANCELLED' => 'Cancelado', 'REFUNDED' => 'Reembolsado', 'EXPIRED' => 'Expirado'];
  $o['estado_txt'] = $estado_txt[$o['status']] ?? $o['status'];
  echo json_encode($o, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  jexit(500, ['error' => 'rastreo']);
}
