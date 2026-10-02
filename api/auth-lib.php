<?php
// api/auth-lib.php — sesión + guardado de carrito compartidos.
declare(strict_types=1);

function sess_start(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30, 'path' => '/',
    'secure' => $https, 'httponly' => true, 'samesite' => 'Lax',
  ]);
  session_start();
}

function audit_login(PDO $pdo, string $tipo, string $email): int {
  try {
    $pdo->prepare('INSERT INTO accesos(tipo,email,ip,agente) VALUES(?,?,?,?)')
      ->execute([$tipo, $email, mb_substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45), mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    return (int)$pdo->lastInsertId();
  } catch (Throwable $e) { return 0; }
}
function audit_logout(PDO $pdo): void {
  $id = (int)($_SESSION['audit_id'] ?? 0);
  unset($_SESSION['audit_id']);
  if ($id <= 0) return;
  try {
    $pdo->prepare('UPDATE accesos SET salida=NOW(), segundos=TIMESTAMPDIFF(SECOND,entrada,NOW()) WHERE id=? AND salida IS NULL')
      ->execute([$id]);
  } catch (Throwable $e) { /* noop */ }
}
/** Guarda carrito del cliente. Con $merge=true une cantidades (máx). Devuelve carrito resultante. */
function guardar_carrito(PDO $pdo, int $cid, array $items, bool $merge = false): array {
  $clean = [];
  foreach ($items as $it) {
    $id = (string)($it['id'] ?? '');
    $qty = max(1, min(99, (int)($it['qty'] ?? 0)));
    if ($id !== '' && $qty > 0) $clean[$id] = $merge ? max($qty, $clean[$id] ?? 0) : $qty;
  }
  if (!$clean) {
    if (!$merge) {
      $pdo->prepare('DELETE FROM customer_carts WHERE customer_id=?')->execute([$cid]);
    }
    return [];
  }
  $ph = implode(',', array_fill(0, count($clean), '?'));
  $st = $pdo->prepare("SELECT id FROM products WHERE id IN ($ph) AND disponible=1");
  $st->execute(array_keys($clean));
  $valid = array_column($st->fetchAll(), 'id');
  if ($merge) {
    $cur = [];
    $s2 = $pdo->prepare('SELECT product_id,qty FROM customer_carts WHERE customer_id=?');
    $s2->execute([$cid]);
    foreach ($s2->fetchAll() as $r) $cur[$r['product_id']] = (int)$r['qty'];
    foreach ($clean as $id => $q) $cur[$id] = min(99, max($q, $cur[$id] ?? 0));
    $clean = array_intersect_key($cur, array_flip($valid));
  } else {
    $clean = array_intersect_key($clean, array_flip($valid));
  }
  $pdo->beginTransaction();
  $pdo->prepare('DELETE FROM customer_carts WHERE customer_id=?')->execute([$cid]);
  $ins = $pdo->prepare('INSERT INTO customer_carts(customer_id,product_id,qty) VALUES(?,?,?)');
  foreach ($clean as $id => $q) $ins->execute([$cid, $id, $q]);
  $pdo->commit();
  return array_map(fn($id, $q) => ['id' => $id, 'qty' => $q], array_keys($clean), $clean);
}
