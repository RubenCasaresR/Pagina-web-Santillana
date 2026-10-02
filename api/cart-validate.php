<?php
// POST /api/cart-validate.php {items:[{id,qty}]} → {subtotal, envio, total, lineas} recalculado en servidor.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jexit(405, ['error' => 'metodo']);
try {
  $b = body_json();
  [$lineas, $sub] = validar_items(db(), $b['items'] ?? []);
  $env = shipping_for($sub);
  jexit(200, ['subtotal' => $sub / 100, 'envio' => $env / 100, 'total' => ($sub + $env) / 100,
    'subtotal_c' => $sub, 'envio_c' => $env, 'total_c' => $sub + $env, 'lineas' => $lineas]);
} catch (RuntimeException $e) {
  jexit(422, ['error' => $e->getMessage()]);
} catch (Throwable $e) {
  jexit(500, ['error' => 'validacion']);
}
