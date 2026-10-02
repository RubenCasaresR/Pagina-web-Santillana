<?php
// POST /api/capture.php {paypal_order_id} — captura, verifica importe, descuenta stock, marca PAID.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/paypal.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jexit(405, ['error' => 'metodo']);
try {
  $b = body_json();
  rate_limit('capture', 20, 600);
  $ppid = trim((string)($b['paypal_order_id'] ?? ''));
  if ($ppid === '') jexit(422, ['error' => 'orden']);
  $pdo = db();
  $st = $pdo->prepare('SELECT * FROM orders WHERE paypal_order_id=?');
  $st->execute([$ppid]);
  $o = $st->fetch();
  if (!$o) jexit(404, ['error' => 'no-existe']);
  if ($o['status'] === 'PAID') jexit(200, ['folio' => $o['folio'], 'total' => $o['total'] / 100, 'ya_pagada' => true]);
  if ($o['status'] !== 'PENDING') jexit(422, ['error' => 'estado-' . $o['status']]);
  [$http, $pp] = pp_call('POST', "/v2/checkout/orders/$ppid/capture", new stdClass());
  // pp_call espera ?array; stdClass se codifica como {} (captura sin cuerpo)
  $cap = $pp['purchase_units'][0]['payments']['captures'][0] ?? null;
  if ($http !== 201 || !$cap || ($cap['status'] ?? '') !== 'COMPLETED') jexit(502, ['error' => 'captura-rechazada']);
  $pagado = (int)round((float)($cap['amount']['value'] ?? 0) * 100);
  if ($pagado !== (int)$o['total']) jexit(502, ['error' => 'importe-distinto']);
  $pdo->beginTransaction();
  $pdo->prepare("UPDATE orders SET status='PAID', paypal_capture_id=?, paid_at=NOW() WHERE id=?")
    ->execute([$cap['id'] ?? null, $o['id']]);
  $it = $pdo->prepare('SELECT product_id, qty FROM order_items WHERE order_id=?');
  $it->execute([$o['id']]);
  $up = $pdo->prepare('UPDATE products SET stock=stock-?, reservado=GREATEST(reservado-?,0) WHERE id=?');
  foreach ($it->fetchAll() as $l) $up->execute([$l['qty'], $l['qty'], $l['product_id']]);
  $pdo->commit();
  @mail($o['email'], 'Santillana — pedido ' . $o['folio'] . ' confirmado',
    'Gracias por tu compra. Folio ' . $o['folio'] . '. Total $' . money_fmt((int)$o['total']) . ' MXN. Te avisaremos del envío.');
  jexit(200, ['folio' => $o['folio'], 'total' => $o['total'] / 100, 'capture_id' => $cap['id'] ?? null]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  jexit(500, ['error' => 'capture']);
}
