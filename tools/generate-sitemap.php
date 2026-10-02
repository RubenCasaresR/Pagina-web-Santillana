<?php
// tools/generate-sitemap.php — Uso: php tools/generate-sitemap.php (regen ../sitemap.xml solo con disponible=1).
// Fuente: MySQL (products.disponible=1) con fallback a ../data/productos.json (disponible truthy).
declare(strict_types=1);
require_once __DIR__ . '/../api/config.php';

$site = rtrim((string)cfg('site_url'), '/');
$today = date('Y-m-d');

function prods_from_db(): ?array {
  try {
    $pdo = new PDO('mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4',
      (string)cfg('db_user'), (string)cfg('db_pass'),
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $rows = $pdo->query("SELECT id, slug FROM products WHERE disponible=1 ORDER BY id")->fetchAll();
    return $rows;
  } catch (Throwable $e) { return null; }
}

function prods_from_json(): array {
  $f = __DIR__ . '/../data/productos.json';
  $all = json_decode(@file_get_contents($f), true) ?: [];
  $out = [];
  foreach ($all as $p) {
    if (empty($p['disponible'])) continue;
    $out[] = ['id' => (string)$p['id'], 'slug' => (string)($p['slug'] ?? '')];
  }
  return $out;
}

$prods = prods_from_db();
$src = 'db';
if ($prods === null) { $prods = prods_from_json(); $src = 'json'; }

$statics = [
  ['index.html', $today, 'daily', '1.0'],
  ['carrito.html', $today, 'monthly', '0.5'],
  ['rastreo.html', $today, 'monthly', '0.6'],
  ['contacto.html', $today, 'monthly', '0.6'],
  ['cuenta.html', $today, 'monthly', '0.4'],
  ['privacidad.html', $today, 'yearly', '0.3'],
  ['terminos.html', $today, 'yearly', '0.3'],
  ['baja.html', $today, 'yearly', '0.2'],
];

$e = fn($s) => htmlspecialchars((string)$s, ENT_XML1, 'UTF-8');
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
  . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($statics as [$loc, $mod, $freq, $prio]) {
  $xml .= "  <url><loc>{$e($site . '/' . $loc)}</loc><lastmod>{$mod}</lastmod><changefreq>{$freq}</changefreq><priority>{$prio}</priority></url>\n";
}
$n = 0;
foreach ($prods as $p) {
  $id = (string)($p['id'] ?? '');
  if ($id === '') continue;
  $slug = trim((string)($p['slug'] ?? ''));
  $key = $slug !== '' ? $slug . '-' . $id : 'p-' . $id;
  $xml .= "  <url><loc>{$e($site . '/producto/' . $key)}</loc><lastmod>{$today}</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>\n";
  $n++;
}
$xml .= '</urlset>' . "\n";

file_put_contents(__DIR__ . '/../sitemap.xml', $xml);
if (php_sapi_name() === 'cli') echo "OK ($src): " . count($statics) . " estáticas + $n productos -> sitemap.xml\n";
