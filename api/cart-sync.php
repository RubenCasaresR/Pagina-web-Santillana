<?php
// api/cart-sync.php — carrito del cliente en servidor.
// GET → {items:[{id,qty}]} · POST {items} → reemplaza y devuelve resultante.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth-lib.php';
sess_start();
try {
  if (empty($_SESSION['cid'])) jexit(401, ['error' => 'no-login']);
  $pdo = db();
  $cid = (int)$_SESSION['cid'];
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $st = $pdo->prepare('SELECT cc.product_id id, cc.qty FROM customer_carts cc JOIN products p ON p.id=cc.product_id WHERE cc.customer_id=? AND p.disponible=1');
    $st->execute([$cid]);
    jexit(200, ['items' => $st->fetchAll()]);
  }
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jexit(405, ['error' => 'metodo']);
  $b = body_json();
  jexit(200, ['items' => guardar_carrito($pdo, $cid, $b['items'] ?? [])]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  jexit(500, ['error' => 'carrito']);
}
