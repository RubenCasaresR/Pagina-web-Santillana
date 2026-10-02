<?php
// api/auth.php — registro, login, logout, sesión y perfil de clientes.
// POST {action: register|login|logout|update, ...} · GET ?action=me
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth-lib.php';
sess_start();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
  $pdo = db();
  if ($method === 'GET' && ($_GET['action'] ?? '') === 'me') {
    if (empty($_SESSION['cid'])) jexit(200, ['logged' => false]);
    $st = $pdo->prepare('SELECT id,nombre,email,telefono,created_at FROM customers WHERE id=?');
    $st->execute([$_SESSION['cid']]);
    $c = $st->fetch();
    if (!$c) { unset($_SESSION['cid']); jexit(200, ['logged' => false]); }
    jexit(200, ['logged' => true, 'customer' => $c]);
  }
  if ($method !== 'POST') jexit(405, ['error' => 'metodo']);
  $b = body_json();
  $action = (string)($b['action'] ?? '');

  if ($action === 'logout') {
    audit_logout($pdo ?? db());
    unset($_SESSION['cid']);
    jexit(200, ['ok' => true]);
  }

  if ($action === 'register') {
    rate_limit('reg', 5, 600);
    $nombre = trim((string)($b['nombre'] ?? ''));
    $email = strtolower(trim((string)($b['email'] ?? '')));
    $pass = (string)($b['pass'] ?? '');
    $tel = preg_replace('/\D+/', '', (string)($b['telefono'] ?? ''));
    if (mb_strlen($nombre) < 3) jexit(422, ['error' => 'nombre']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jexit(422, ['error' => 'email']);
    if (mb_strlen($pass) < 8) jexit(422, ['error' => 'pass-min-8']);
    if ($tel !== '' && !preg_match('/^\d{10}$/', $tel)) jexit(422, ['error' => 'telefono-10-digitos']);
    try {
      $pdo->prepare('INSERT INTO customers(nombre,email,pass_hash,telefono) VALUES(?,?,?,?)')
        ->execute([$nombre, $email, password_hash($pass, PASSWORD_DEFAULT), $tel]);
    } catch (PDOException $e) {
      if (($e->errorInfo[1] ?? 0) === 1062) jexit(422, ['error' => 'email-registrado']);
      throw $e;
    }
    $cid = (int)$pdo->lastInsertId();
    $_SESSION['cid'] = $cid;
    if (!empty($b['items']) && is_array($b['items'])) guardar_carrito($pdo, $cid, $b['items']);
    // vincular pedidos previos hechos con este correo
    $pdo->prepare('UPDATE orders SET customer_id=? WHERE email=? AND customer_id IS NULL')->execute([$cid, $email]);
    $_SESSION['audit_id'] = audit_login($pdo, 'cliente', $email);
    jexit(200, ['ok' => true, 'nombre' => $nombre, 'email' => $email]);
  }

  if ($action === 'login') {
    rate_limit('login', 8, 600);
    $email = strtolower(trim((string)($b['email'] ?? '')));
    $pass = (string)($b['pass'] ?? '');
    // 1) ¿es el administrador? (mismo formulario, sin revelar que existe)
    $ahash = (string)cfg('admin_pass_hash');
    if ($ahash !== '' && hash_equals((string)cfg('admin_email'), $email) && password_verify($pass, $ahash)) {
      $_SESSION['sant_admin'] = 1;
      $_SESSION['audit_id'] = audit_login($pdo, 'admin', $email);
      jexit(200, ['ok' => true, 'nombre' => 'Administrador', 'email' => $email, 'is_admin' => true, 'cart' => []]);
    }
    $st = $pdo->prepare('SELECT * FROM customers WHERE email=?');
    $st->execute([$email]);
    $c = $st->fetch();
    if (!$c || !password_verify($pass, $c['pass_hash'])) {
      // delay anti-enumeración
      usleep(300000);
      jexit(422, ['error' => 'credenciales']);
    }
    if (password_needs_rehash($c['pass_hash'], PASSWORD_DEFAULT)) {
      $pdo->prepare('UPDATE customers SET pass_hash=? WHERE id=?')->execute([password_hash($pass, PASSWORD_DEFAULT), $c['id']]);
    }
    $_SESSION['cid'] = (int)$c['id'];
    $merged = [];
    if (!empty($b['items']) && is_array($b['items'])) $merged = guardar_carrito($pdo, (int)$c['id'], $b['items'], true);
    $pdo->prepare('UPDATE orders SET customer_id=? WHERE email=? AND customer_id IS NULL')->execute([(int)$c['id'], $c['email']]);
    $_SESSION['audit_id'] = audit_login($pdo, 'cliente', $c['email']);
    jexit(200, ['ok' => true, 'nombre' => $c['nombre'], 'email' => $c['email'], 'cart' => $merged]);
  }

  if ($action === 'update') {
    if (empty($_SESSION['cid'])) jexit(401, ['error' => 'no-login']);
    $nombre = trim((string)($b['nombre'] ?? ''));
    $tel = preg_replace('/\D+/', '', (string)($b['telefono'] ?? ''));
    $pass = (string)($b['pass_new'] ?? '');
    if ($nombre !== '' && mb_strlen($nombre) < 3) jexit(422, ['error' => 'nombre']);
    if ($tel !== '' && !preg_match('/^\d{10}$/', $tel)) jexit(422, ['error' => 'telefono-10-digitos']);
    if ($pass !== '' && mb_strlen($pass) < 8) jexit(422, ['error' => 'pass-min-8']);
    if ($nombre !== '') $pdo->prepare('UPDATE customers SET nombre=? WHERE id=?')->execute([$nombre, $_SESSION['cid']]);
    if ($tel !== '') $pdo->prepare('UPDATE customers SET telefono=? WHERE id=?')->execute([$tel, $_SESSION['cid']]);
    if ($pass !== '') $pdo->prepare('UPDATE customers SET pass_hash=? WHERE id=?')->execute([password_hash($pass, PASSWORD_DEFAULT), $_SESSION['cid']]);
    jexit(200, ['ok' => true]);
  }

  jexit(422, ['error' => 'accion']);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  jexit(500, ['error' => 'auth']);
}
