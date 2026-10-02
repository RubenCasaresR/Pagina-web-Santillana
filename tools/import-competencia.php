<?php
// tools/import-competencia.php — Uso: php tools/import-competencia.php  (SOLO CLI).
// Vuelca los datos del bot (1337 productos) a MySQL.
// - IDs deterministas ce-<md5:12> => re-ejecutable / actualizable.
// - Respalda products y product_cats antes (sufijo fecha).
// - Sin imágenes locales: usa la URL del CDN (http pasa directo en resolveImg/picTag).
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); echo "Solo CLI.\n"; exit(1); }
require_once __DIR__ . '/../api/bootstrap.php';

function slugify_ce(string $s): string {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
  if (function_exists('iconv')) { $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s); if (is_string($t) && $t !== '') $s = $t; }
  $s = strtolower($s);
  $s = preg_replace('/[^a-z0-9]+/', '-', $s);
  $s = trim(preg_replace('/-{2,}/', '-', $s ?? ''), '-');
  $s = mb_substr($s ?? '', 0, 200);
  return trim($s, '-') !== '' ? trim($s, '-') : 'producto';
}
function cat_ce(array $p): string {
  $t = mb_strtolower(($p['titulo'] ?? '') . ' ' . ($p['tipo'] ?? ''), 'UTF-8');
  $has = fn(...$ws) => array_reduce($ws, fn($a, $w) => $a || str_contains($t, $w), false);
  if ($has('consola', 'nintendo', 'playstation', 'videojuego') || ($has('xbox') && !$has('remoto', 'universal'))) return 'Videojuegos';
  if (str_contains($t, 'control')) return 'Electrónica'; // controles/remotos, aunque mencionen marcas de TV
  if ($has('smart tv', 'pantalla', 'televisor', 'televisión', 'roku', 'fire tv')) return 'Televisiones';
  if ($has('tablet', 'ipad', 'galaxy tab')) return 'Tablets';
  if ($has('laptop', 'notebook', 'teclado', 'mouse', 'impresora', 'computadora', 'monitores')) return 'Computación';
  if ($has('celular', 'smartphone', 'iphone', 'motorola', 'xiaomi', 'redmi', 'oppo', 'realme', 'poco f', 'poco x')) return 'Celulares';
  $tipo = trim((string)($p['tipo'] ?? ''));
  if (in_array($tipo, ['Hogar', 'Basculas', 'Termo', 'ventilador'], true)) return 'Hogar';
  if ($tipo === 'Computo') return 'Computación';
  return 'Electrónica';
}

$raw = file_get_contents(__DIR__ . '/../controlexpert-monitor-/datos.json');
$json = json_decode($raw, true);
if (!is_array($json['productos'] ?? null)) { echo "JSON inválido\n"; exit(1); }
$items = $json['productos'];
printf("Fuente: %d productos (%s)\n", count($items), $json['actualizadoEl'] ?? '?');

$pdo = db();
$tag = date('Ymd_Hi');
$pdo->exec("CREATE TABLE products_backup_{$tag} LIKE products");
$pdo->exec("INSERT INTO products_backup_{$tag} SELECT * FROM products");
$pdo->exec("CREATE TABLE product_cats_backup_{$tag} LIKE product_cats");
$pdo->exec("INSERT INTO product_cats_backup_{$tag} SELECT * FROM product_cats");
echo "Respaldo: products_backup_{$tag} + product_cats_backup_{$tag}\n";

$up = $pdo->prepare('INSERT INTO products(id,sku,nombre,slug,marca,cat_primary,precio,precio_lista,moneda,stock,disponible,destacado,en_oferta,img,img_large,img_webp,img_large_webp,descripcion_corta)
  VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),slug=VALUES(slug),marca=VALUES(marca),cat_primary=VALUES(cat_primary),
  precio=VALUES(precio),precio_lista=VALUES(precio_lista),disponible=VALUES(disponible),
  img=VALUES(img),img_large=VALUES(img_large),descripcion_corta=VALUES(descripcion_corta)');
$del = $pdo->prepare('DELETE FROM product_cats WHERE product_id=?');
$ins = $pdo->prepare('INSERT IGNORE INTO product_cats(product_id,cat) VALUES(?,?)');
$slugTaken = $pdo->prepare('SELECT id FROM products WHERE slug=? LIMIT 1');

// slugs existentes (de otros imports) para evitar choques
$existingSlugs = [];
foreach ($pdo->query('SELECT slug FROM products')->fetchAll() as $r) $existingSlugs[$r['slug']] = true;

$n = 0; $upd = 0; $cats = [];
$pdo->beginTransaction();
foreach ($items as $p) {
  $h = md5($p['handle'] ?? $p['titulo']);
  $id = 'ce-' . substr($h, 0, 12);
  $sku = 'CE-' . strtoupper(substr($h, 0, 8));
  $nombre = mb_substr(trim((string)$p['titulo']), 0, 255);
  if ($nombre === '') continue;
  // Higiene de marca: sin menciones al proveedor externo en textos visibles.
  $nombre = trim(preg_replace('/\s+/', ' ', str_ireplace('control expert', '', $nombre)), " \t\n\r\0\x0B-–—");
  $vendor = trim((string)($p['vendor'] ?? ''));
  if (mb_stripos($vendor, 'control expert') !== false) $vendor = 'Genérico';
  // Slug SIEMPRE desde el nombre limpio (los handles del scraper venían rotos: 'ontrol', 'obia').
  $slug = slugify_ce($nombre);
  if ($slug === '' || $slug === 'producto') $slug = slugify_ce(($p['handle'] ?? '') !== '' ? (string)$p['handle'] : $id);
  // unicidad de slug frente a otros productos
  $base = $slug; $k = 2;
  while (isset($existingSlugs[$slug])) {
    $slugTaken->execute([$slug]);
    $owner = $slugTaken->fetchColumn();
    $slugTaken->closeCursor();
    if ($owner === $id || $owner === false) break;
    $slug = mb_substr($base, 0, 240) . '-' . $k++;
  }
  $existingSlugs[$slug] = true;
  $cat = cat_ce($p);
  $cats[$cat] = ($cats[$cat] ?? 0) + 1;
  $precio = (int)round((float)($p['precio'] ?? 0) * 100);
  $disp = !empty($p['disponible']) ? 1 : 0;
  $img = mb_substr(trim((string)($p['imagen'] ?? '')), 0, 255);
  try {
    $up->execute([$id, $sku, $nombre, $slug, mb_substr($vendor, 0, 80),
      $cat, $precio, $precio, 'MXN', $disp ? 10 : 0, $disp, 0, 0, $img, $img, '', '', '']);
    $n++;
  } catch (PDOException $e) {
    // choque de SKU (improbable): reintenta con sufijo
    if (str_contains($e->getMessage(), 'sku')) {
      $sku .= '-' . substr($h, 8, 4);
      $up->execute([$id, $sku, $nombre, $slug, mb_substr($vendor, 0, 80),
        $cat, $precio, $precio, 'MXN', $disp ? 10 : 0, $disp, 0, 0, $img, $img, '', '', '']);
      $n++;
    } else { throw $e; }
  }
  $del->execute([$id]);
  $ins->execute([$id, $cat]);
}
$pdo->commit();
echo "Importados/actualizados: $n\n";
foreach ($cats as $c => $q) echo "  $c: $q\n";
echo 'Total en BD: ' . $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() . PHP_EOL;
