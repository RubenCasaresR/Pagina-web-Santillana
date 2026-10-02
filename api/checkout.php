<?php
// POST /api/checkout.php {items, name, apellidos?, email, phone, address, depto?, city, estado?, pais?, zip, billing_same?, billing_address?, subscribe_news?, acepta_terminos}
// Crea orden PENDING en DB + orden PayPal. Devuelve {folio, total, paypal_order_id}.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/paypal.php';
require_once __DIR__ . '/auth-lib.php';
sess_start();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jexit(405, ['error' => 'metodo']);
try {
  $b = body_json();
  rate_limit('checkout', 10, 600);
  if (!recaptcha_ok($b['recaptcha'] ?? null)) jexit(422, ['error' => 'captcha']);
  $pdo = db();
  [$lineas, $sub] = validar_items($pdo, $b['items'] ?? []);
  [$nombre, $email, $tel, $dir, $cd, $cp] = validar_datos($b);
  $apellidos = mb_substr(trim((string)($b['apellidos'] ?? '')), 0, 160);
  $estado = mb_substr(trim((string)($b['estado'] ?? '')), 0, 80);
  $pais = mb_substr(trim((string)($b['pais'] ?? 'México')), 0, 60) ?: 'México';
  $depto = mb_substr(trim((string)($b['depto'] ?? '')), 0, 120);
  $bs = $b['billing_same'] ?? '1';
  $billing_same = ($bs === '1' || $bs === 1 || $bs === true) ? 1 : 0;
  $billing_address = mb_substr(trim((string)($b['billing_address'] ?? '')), 0, 300);
  if (!$billing_same && mb_strlen($billing_address) < 8) jexit(422, ['error' => 'facturacion']);
  $subscribe = !empty($b['subscribe_news']) ? 1 : 0;
  $env = shipping_for($sub);
  $total = $sub + $env;
  if ($total <= 0) jexit(422, ['error' => 'items']);
  [$http, $pp] = pp_call('POST', '/v2/checkout/orders', [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
      'reference_id' => 'SANT-' . time(),
      'amount' => ['currency_code' => 'MXN', 'value' => money_fmt($total)],
    ]],
  ]);
  if ($http !== 201 || empty($pp['id'])) jexit(502, ['error' => 'paypal-crear']);
  $folio = folio_nuevo($pdo);
  $idem = uuid4();
  $cid = !empty($_SESSION['cid']) ? (int)$_SESSION['cid'] : null;
  $pdo->beginTransaction();
  $pdo->prepare('INSERT INTO orders(folio,idempotency_key,email,nombre,apellidos,telefono,direccion,depto,ciudad,estado,pais,cp,billing_same,billing_address,subscribe_news,payment_method,subtotal,envio,total,paypal_order_id,customer_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
    ->execute([$folio, $idem, $email, $nombre, $apellidos, $tel, $dir, $depto, $cd, $estado, $pais, $cp, $billing_same, $billing_address, $subscribe, 'paypal', $sub, $env, $total, $pp['id'], $cid]);
  if ($subscribe) $pdo->prepare('INSERT IGNORE INTO newsletter(email) VALUES(?)')->execute([$email]);
  $oid = (int)$pdo->lastInsertId();
  $ins = $pdo->prepare('INSERT INTO order_items(order_id,product_id,qty,unit_price) VALUES(?,?,?,?)');
  foreach ($lineas as $l) $ins->execute([$oid, $l['id'], $l['qty'], $l['unit_price']]);
  // reserva blanda de stock
  $res = $pdo->prepare('UPDATE products SET reservado=reservado+? WHERE id=?');
  foreach ($lineas as $l) $res->execute([$l['qty'], $l['id']]);
  $pdo->commit();
  jexit(200, ['folio' => $folio, 'total' => $total / 100, 'total_c' => $total,
    'paypal_order_id' => $pp['id'], 'idempotency_key' => $idem]);
} catch (RuntimeException $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  $m = $e->getMessage();
  if (str_starts_with($m, 'sin-stock') || str_starts_with($m, 'no-disponible') || $m === 'qty' || $m === 'items' || $m === 'producto-inexistente') jexit(422, ['error' => $m]);
  jexit(502, ['error' => $m]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  jexit(500, ['error' => 'checkout']);
}
