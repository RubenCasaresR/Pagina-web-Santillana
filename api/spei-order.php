<?php
// POST /api/spei-order.php — mismos datos que checkout.php + payment_method=spei.
// Crea orden PENDING_TRANSFER con referencia. Devuelve {folio, total, referencia, banco, clabe, titular}.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth-lib.php';
sess_start();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jexit(405, ['error' => 'metodo']);
try {
  $b = body_json();
  rate_limit('spei', 10, 600);
  $__cl = preg_replace('/\D+/', '', (string)cfg('spei_clabe'));
  if (!preg_match('/^[1-9]\d{17}$/', $__cl) || trim((string)cfg('spei_banco')) === '' || trim((string)cfg('spei_banco')) === 'Banco Ejemplo') jexit(503, ['error' => 'spei-no-configurado']);
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
  $folio = folio_nuevo($pdo);
  $idem = uuid4();
  $cid = !empty($_SESSION['cid']) ? (int)$_SESSION['cid'] : null;
  $ref = str_pad((string)random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
  $pdo->beginTransaction();
  $pdo->prepare('INSERT INTO orders(folio,idempotency_key,email,nombre,apellidos,telefono,direccion,depto,ciudad,estado,pais,cp,billing_same,billing_address,subscribe_news,payment_method,status,spei_ref,subtotal,envio,total,customer_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
    ->execute([$folio, $idem, $email, $nombre, $apellidos, $tel, $dir, $depto, $cd, $estado, $pais, $cp, $billing_same, $billing_address, $subscribe, 'spei', 'PENDING_TRANSFER', $ref, $sub, $env, $total, $cid]);
  $oid = (int)$pdo->lastInsertId();
  $ins = $pdo->prepare('INSERT INTO order_items(order_id,product_id,qty,unit_price) VALUES(?,?,?,?)');
  foreach ($lineas as $l) $ins->execute([$oid, $l['id'], $l['qty'], $l['unit_price']]);
  $res = $pdo->prepare('UPDATE products SET reservado=reservado+? WHERE id=?');
  foreach ($lineas as $l) $res->execute([$l['qty'], $l['id']]);
  if ($subscribe) $pdo->prepare('INSERT IGNORE INTO newsletter(email) VALUES(?)')->execute([$email]);
  $pdo->commit();
  @mail($email, 'Santillana — pedido ' . $folio . ' pendiente de transferencia',
    'Folio ' . $folio . '. Transfiere $' . money_fmt($total) . ' MXN a ' . cfg('spei_banco') . ' CLABE ' . cfg('spei_clabe') . ' a nombre de ' . cfg('spei_titular') . ' con referencia ' . $ref . '.');
  jexit(200, ['folio' => $folio, 'total' => $total / 100, 'total_c' => $total,
    'referencia' => $ref, 'banco' => (string)cfg('spei_banco'), 'clabe' => (string)cfg('spei_clabe'),
    'titular' => (string)cfg('spei_titular'), 'idempotency_key' => $idem]);
} catch (RuntimeException $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  $m = $e->getMessage();
  if (str_starts_with($m, 'sin-stock') || str_starts_with($m, 'no-disponible') || $m === 'qty' || $m === 'items' || $m === 'producto-inexistente') jexit(422, ['error' => $m]);
  jexit(502, ['error' => $m]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  jexit(500, ['error' => 'spei']);
}
