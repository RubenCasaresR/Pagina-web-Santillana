<?php
// Bootstrap API JSON + PDO. Incluir primero en cada endpoint.
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

function jexit(int $code, array $body): never {
  http_response_code($code);
  echo json_encode($body, JSON_UNESCAPED_UNICODE);
  exit;
}
function body_json(): array {
  $raw = file_get_contents('php://input') ?: '';
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}
function db(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $pdo = new PDO(
      'mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4',
      (string)cfg('db_user'), (string)cfg('db_pass'),
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
       PDO::ATTR_EMULATE_PREPARES => false]
    );
  }
  return $pdo;
}
function money_fmt(int $centavos): string {
  return number_format($centavos / 100, 2, '.', ',');
}
function folio_nuevo(PDO $pdo): string {
  for ($i = 0; $i < 8; $i++) {
    $f = 'ST-' . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $q = $pdo->prepare('SELECT 1 FROM orders WHERE folio=?');
    $q->execute([$f]);
    if (!$q->fetch()) return $f;
  }
  throw new RuntimeException('folio');
}
function shipping_for(int $sub): int {
  if ($sub <= 0) return 0;
  return $sub >= (int)cfg('ship_free_from') ? 0 : (int)cfg('ship_cost');
}
function uuid4(): string {
  $b = random_bytes(16);
  $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
  $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}
/** Rate-limit simple por IP (archivos en tmp). $key distinto por endpoint. */
function rate_limit(string $key, int $max, int $window): void {
  $dir = sys_get_temp_dir() . '/sant_rl';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  $f = $dir . '/' . $key . '_' . md5($_SERVER['REMOTE_ADDR'] ?? 'x');
  $now = time();
  $d = ['t' => $now, 'h' => 0];
  if (is_file($f)) {
    $j = json_decode((string)@file_get_contents($f), true);
    if (is_array($j) && $now - (int)($j['t'] ?? 0) < $window) $d = $j;
  }
  if ((int)$d['h'] >= $max) jexit(429, ['error' => 'limite']);
  $d['h']++;
  @file_put_contents($f, json_encode($d));
}
/** reCAPTCHA v3 opcional: si no hay secreto configurado, pasa. Si lo hay, exige score>=0.5. */
function recaptcha_ok(?string $token): bool {
  $sec = (string)cfg('recaptcha_secret');
  if ($sec === '') return true;
  if (!$token) return false;
  $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['secret' => $sec, 'response' => $token]), CURLOPT_TIMEOUT => 10]);
  $res = curl_exec($ch);
  curl_close($ch);
  $d = json_decode((string)$res, true);
  return !empty($d['success']) && ((float)($d['score'] ?? 0)) >= 0.5;
}
/** Valida items contra DB. Devuelve [lineas, subtotal]. Lanza RuntimeException si algo falla. */
function validar_items(PDO $pdo, array $items): array {
  if (!$items || count($items) > 50) throw new RuntimeException('items');
  $ids = [];
  foreach ($items as $it) {
    $id = (string)($it['id'] ?? '');
    $qty = (int)($it['qty'] ?? 0);
    if ($id === '' || $qty < 1 || $qty > 99) throw new RuntimeException('qty');
    $ids[$id] = $qty;
  }
  $ph = implode(',', array_fill(0, count($ids), '?'));
  $st = $pdo->prepare("SELECT id, precio, stock, reservado, disponible FROM products WHERE id IN ($ph)");
  $st->execute(array_keys($ids));
  $rows = $st->fetchAll();
  if (count($rows) !== count($ids)) throw new RuntimeException('producto-inexistente');
  $lineas = []; $sub = 0;
  foreach ($rows as $r) {
    $q = $ids[$r['id']];
    if (!(int)$r['disponible']) throw new RuntimeException('no-disponible:' . $r['id']);
    if ($q > ((int)$r['stock'] - (int)$r['reservado'])) throw new RuntimeException('sin-stock:' . $r['id']);
    $lineas[] = ['id' => $r['id'], 'qty' => $q, 'unit_price' => (int)$r['precio']];
    $sub += (int)$r['precio'] * $q;
  }
  return [$lineas, $sub];
}
function validar_datos(array $d): array {
  $g = fn($k) => trim((string)($d[$k] ?? ''));
  $nombre = $g('name'); $email = $g('email'); $tel = preg_replace('/\D+/', '', $g('phone'));
  $dir = $g('address'); $cd = $g('city'); $cp = $g('zip');
  if (mb_strlen($nombre) < 5) jexit(422, ['error' => 'nombre']);
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jexit(422, ['error' => 'email']);
  if (!preg_match('/^\d{10}$/', $tel)) jexit(422, ['error' => 'telefono-10-digitos']);
  if (mb_strlen($dir) < 8) jexit(422, ['error' => 'direccion']);
  if (mb_strlen($cd) < 3) jexit(422, ['error' => 'ciudad']);
  if (!preg_match('/^\d{5}$/', $cp)) jexit(422, ['error' => 'cp']);
  if (empty($d['acepta_terminos'])) jexit(422, ['error' => 'terminos']);
  return [$nombre, $email, $tel, $dir, $cd, $cp];
}
