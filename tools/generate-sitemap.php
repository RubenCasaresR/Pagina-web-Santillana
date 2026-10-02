<?php
// tools/generate-sitemap.php — Uso: php tools/generate-sitemap.php (regen ../sitemap.xml solo con disponible=1).
// Fuente única: MySQL (products.disponible=1). Sin fallback a JSON.
declare(strict_types=1);
if (php_sapi_name() !== 'cli') {
  session_start();
  if (empty($_SESSION['sant_admin'])) { http_response_code(403); echo 'Acceso denegado. Entra a /admin/ primero.'; exit; }
}
require_once __DIR__ . '/../api/config.php';

$site = rtrim((string)cfg('site_url'), '/');
$today = date('Y-m-d');

function prods_from_db(): array {
  $pdo = new PDO('mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4',
    (string)cfg('db_user'), (string)cfg('db_pass'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  return $pdo->query("SELECT id, slug FROM products WHERE disponible=1 ORDER BY id")->fetchAll();
}

$prods = prods_from_db();
$src = 'db';

$statics = [
  ['index.html', $today, 'daily', '1.0'],
  ['rastreo.html', $today, 'monthly', '0.6'],
  ['contacto.html', $today, 'monthly', '0.6'],
  ['privacidad.html', $today, 'yearly', '0.3'],
  ['terminos.html', $today, 'yearly', '0.3'],
  // Nota: carrito.html (Disallow en robots) y cuenta.html (noindex) no van al sitemap.
  // baja.html es transaccional: tampoco se indexa.
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
if (php_sapi_name() === 'cli') {
  echo "OK ($src): " . count($statics) . " estáticas + $n productos -> sitemap.xml\n";
} else {
  header('Content-Type: text/html; charset=utf-8');
  echo '<p>Sitemap regenerado: ' . count($statics) . ' páginas + ' . $n . ' productos.</p><p><a href="../admin/index.php#tools">Volver al panel</a></p>';
}
