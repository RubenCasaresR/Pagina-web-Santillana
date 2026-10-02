<?php
// DIAGNÓSTICO TEMPORAL — borrar tras usar. No crea pedidos ni cargos.
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/paypal.php';
$out = ['php' => PHP_VERSION];
try {
  $pdo = db();
  $out['conexion'] = 'OK';
  $cols = [];
  foreach ($pdo->query('SHOW COLUMNS FROM orders') as $c) $cols[] = $c['Field'];
  $out['orders_cols'] = count($cols);
  foreach (['folio','email','nombre','paypal_order_id','customer_id','subtotal','total'] as $need) {
    if (!in_array($need, $cols, true)) $out['falta_columna'] = $need;
  }
  $id = (string)$pdo->query("SELECT id FROM products WHERE disponible=1 AND stock>reservado LIMIT 1")->fetchColumn();
  $out['producto_prueba'] = $id;
  [$lineas, $sub] = validar_items($pdo, [['id' => $id, 'qty' => 1]]);
  $out['items'] = 'OK';
  $out['subtotal'] = $sub;
} catch (Throwable $e) { $out['items'] = 'FALLO: ' . get_class($e) . ': ' . $e->getMessage(); echo json_encode($out); exit; }
try {
  $t = pp_token();
  $out['paypal'] = 'OK (' . strlen($t) . ')';
} catch (Throwable $e) { $out['paypal'] = 'FALLO: ' . $e->getMessage(); echo json_encode($out); exit; }
$out['todo'] = 'OK';
echo json_encode($out, JSON_UNESCAPED_UNICODE);
