<?php
// DIAGNÓSTICO TEMPORAL — borrar tras usar. No crea pedidos ni cargos.
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/paypal.php';
require_once __DIR__ . '/auth-lib.php';
$out = ['php' => PHP_VERSION];
try { sess_start(); $out['sesion'] = 'OK'; }
catch (Throwable $e) { $out['sesion'] = 'FALLO: ' . $e->getMessage(); echo json_encode($out); exit; }
try {
  $pdo = db();
  $id = (string)$pdo->query("SELECT id FROM products WHERE disponible=1 AND stock>reservado LIMIT 1")->fetchColumn();
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
