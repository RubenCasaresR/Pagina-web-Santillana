<?php
// tools/import.php — Uso: php tools/import.php  (lee ../data/productos.json → MySQL). Re-ejecutable.
declare(strict_types=1);
require_once __DIR__ . '/../api/bootstrap.php';
if (php_sapi_name() !== 'cli' && empty($_GET['confirm'])) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "Abrir con ?confirm=1 para importar.\n";
  exit;
}
header('Content-Type: text/plain; charset=utf-8');
$prods = json_decode(file_get_contents(__DIR__ . '/../data/productos.json'), true);
if (!is_array($prods)) { echo "JSON inválido\n"; exit(1); }
$pdo = db();
$n = 0; $c = 0;
$pdo->beginTransaction();
$up = $pdo->prepare('INSERT INTO products(id,sku,nombre,slug,marca,cat_primary,precio,precio_lista,moneda,stock,disponible,destacado,en_oferta,img,img_large,img_webp,img_large_webp,descripcion_corta)
  VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),slug=VALUES(slug),marca=VALUES(marca),cat_primary=VALUES(cat_primary),
  precio=VALUES(precio),precio_lista=VALUES(precio_lista),disponible=VALUES(disponible),destacado=VALUES(destacado),
  en_oferta=VALUES(en_oferta),img=VALUES(img),img_large=VALUES(img_large),img_webp=VALUES(img_webp),img_large_webp=VALUES(img_large_webp),descripcion_corta=VALUES(descripcion_corta)');
$del = $pdo->prepare('DELETE FROM product_cats WHERE product_id=?');
$ins = $pdo->prepare('INSERT IGNORE INTO product_cats(product_id,cat) VALUES(?,?)');
foreach ($prods as $p) {
  $precio_c = (int)round((float)$p['precio'] * 100);
  $lista_c = (int)round((float)($p['precio_lista'] ?? $p['precio']) * 100);
  $up->execute([$p['id'], $p['sku'], $p['nombre'], $p['slug'], $p['marca'] ?? '', $p['cat_primary'],
    $precio_c, $lista_c, 'MXN', (int)($p['stock'] ?? 10), !empty($p['disponible']) ? 1 : 0,
    !empty($p['destacado']) ? 1 : 0, !empty($p['en_oferta']) ? 1 : 0,
    explode('?', $p['img'])[0], explode('?', $p['img_large'])[0],
    explode('?', $p['img_webp'] ?? '')[0], explode('?', $p['img_large_webp'] ?? '')[0],
    $p['descripcion_corta'] ?? '']);
  $del->execute([$p['id']]);
  foreach ((array)($p['categorias'] ?? []) as $cat) { $ins->execute([$p['id'], $cat]); $c++; }
  $n++;
}
$pdo->commit();
echo "OK: $n productos, $c relaciones categoria.\n";
