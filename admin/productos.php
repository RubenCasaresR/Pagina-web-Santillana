<?php
// admin/productos.php — CRUD de productos (crear / editar / desactivar / eliminar + subida de imagen).
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/layout.php';
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
if (empty($_SESSION['sant_admin'])) { header('Location: index.php'); exit; }
$pdo = new PDO('mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4', (string)cfg('db_user'), (string)cfg('db_pass'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$CATS = ['Celulares', 'Computación', 'Televisiones', 'Videojuegos', 'Electrónica', 'Tablets', 'Hogar', 'Herramientas', 'Ofertas', 'Novedades'];
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok(): bool { return hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? '')); }
function slugify(string $s): string {
  $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($s)) ?: $s;
  $s = preg_replace('/[^a-z0-9]+/', '-', strtolower($s));
  return trim($s ?? '', '-') ?: 'producto';
}
function unique_slug(PDO $pdo, string $slug, string $exclude = ''): string {
  $base = $slug; $i = 2;
  while (true) {
    $st = $pdo->prepare('SELECT 1 FROM products WHERE slug=? AND id<>?');
    $st->execute([$slug, $exclude]);
    if (!$st->fetch()) return $slug;
    $slug = $base . '-' . ($i++);
  }
}
function subir_img(string $field, string $fallback, string &$err): string {
  if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) return $fallback;
  $f = $_FILES[$field];
  if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) { $err = 'Imagen inválida o mayor a 5MB.'; return $fallback; }
  $info = @getimagesize($f['tmp_name']);
  $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
  if (!$info || !isset($map[$info['mime']])) { $err = 'Solo JPG, PNG o WebP.'; return $fallback; }
  $dir = __DIR__ . '/../assets/productos';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $name = 'p-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $map[$info['mime']];
  if (!@move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) { $err = 'No se pudo guardar.'; return $fallback; }
  $rel = 'assets/productos/' . $name;
  if (function_exists('imagewebp') && $map[$info['mime']] !== 'webp') {
    $im = @imagecreatefromstring(file_get_contents($dir . '/' . $name));
    if ($im) { @imagewebp($im, $dir . '/' . pathinfo($name, PATHINFO_FILENAME) . '.webp', 82); @imagedestroy($im); }
  }
  return $rel;
}
$msg = $err = '';
if (($_POST['acc'] ?? '') === 'toggle' && csrf_ok()) {
  $pdo->prepare('UPDATE products SET disponible=1-disponible WHERE id=?')->execute([(string)$_POST['id']]);
  header('Location: productos.php'); exit;
}
if (($_POST['acc'] ?? '') === 'del' && csrf_ok()) {
  $id = (string)$_POST['id'];
  $pdo->prepare('DELETE FROM customer_carts WHERE product_id=?')->execute([$id]);
  $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
  header('Location: productos.php'); exit;
}
if (($_POST['acc'] ?? '') === 'save' && csrf_ok()) {
  $id = trim((string)($_POST['id'] ?? ''));
  $isNew = ($id === '');
  if ($isNew) {
    $mx = $pdo->query('SELECT MAX(CAST(id AS UNSIGNED)) m FROM products')->fetch()['m'];
    $id = $mx ? (string)((int)$mx + 1) : (string)(9000000000000 + time() % 999999999);
  }
  $nombre = trim((string)$_POST['nombre']);
  $sku = trim((string)$_POST['sku']);
  $slug = unique_slug($pdo, trim((string)$_POST['slug']) !== '' ? trim((string)$_POST['slug']) : slugify($nombre), $isNew ? '' : $id);
  $marca = trim((string)$_POST['marca']);
  $catp = in_array($_POST['cat_primary'] ?? '', $CATS, true) ? $_POST['cat_primary'] : 'Novedades';
  $precio = (int)round((float)$_POST['precio'] * 100);
  $lista = (int)round((float)($_POST['precio_lista'] ?? $_POST['precio']) * 100);
  $stock = max(0, (int)$_POST['stock']);
  $img = subir_img('f_img', trim((string)$_POST['img']), $err);
  $imgL = subir_img('f_img_large', trim((string)$_POST['img_large']), $err);
  $imgW = $img !== '' && !str_ends_with(strtolower($img), '.webp') ? preg_replace('/\.(png|jpe?g)$/i', '.webp', $img) : $img;
  if (!is_file(__DIR__ . '/../' . $imgW)) $imgW = '';
  $imgLW = $imgL !== '' && !str_ends_with(strtolower($imgL), '.webp') ? preg_replace('/\.(png|jpe?g)$/i', '.webp', $imgL) : $imgL;
  if (!is_file(__DIR__ . '/../' . $imgLW)) $imgLW = '';
  if ($nombre === '' || $sku === '') $err = 'Nombre y SKU son obligatorios.';
  elseif ($precio <= 0) $err = 'Precio inválido.';
  else {
    $pdo->prepare('INSERT INTO products(id,sku,nombre,slug,marca,cat_primary,precio,precio_lista,moneda,stock,disponible,destacado,en_oferta,img,img_large,img_webp,img_large_webp,descripcion_corta) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE sku=VALUES(sku),nombre=VALUES(nombre),slug=VALUES(slug),marca=VALUES(marca),cat_primary=VALUES(cat_primary),precio=VALUES(precio),precio_lista=VALUES(precio_lista),stock=VALUES(stock),disponible=VALUES(disponible),destacado=VALUES(destacado),en_oferta=VALUES(en_oferta),img=VALUES(img),img_large=VALUES(img_large),img_webp=VALUES(img_webp),img_large_webp=VALUES(img_large_webp),descripcion_corta=VALUES(descripcion_corta)')
      ->execute([$id, $sku, $nombre, $slug, $marca, $catp, $precio, $lista, 'MXN', $stock, !empty($_POST['disponible']) ? 1 : 0, !empty($_POST['destacado']) ? 1 : 0, !empty($_POST['en_oferta']) ? 1 : 0, $img, $imgL, $imgW, $imgLW, trim((string)$_POST['descripcion'])]);
    $pdo->prepare('DELETE FROM product_cats WHERE product_id=?')->execute([$id]);
    $ins = $pdo->prepare('INSERT IGNORE INTO product_cats(product_id,cat) VALUES(?,?)');
    foreach ((array)($_POST['cats'] ?? []) as $cx) if (in_array($cx, $CATS, true)) $ins->execute([$id, $cx]);
    header('Location: productos.php?ok=1'); exit;
  }
}
$q = trim((string)($_GET['q'] ?? ''));
$edit = null; $editCats = [];
if (!empty($_GET['edit'])) {
  $st = $pdo->prepare('SELECT * FROM products WHERE id=?');
  $st->execute([(string)$_GET['edit']]);
  $edit = $st->fetch() ?: null;
  if ($edit) {
    $s2 = $pdo->prepare('SELECT cat FROM product_cats WHERE product_id=?');
    $s2->execute([$edit['id']]);
    $editCats = array_column($s2->fetchAll(), 'cat');
    $edit['precio'] = $edit['precio'] / 100;
    $edit['precio_lista'] = $edit['precio_lista'] / 100;
  }
}
if ($q !== '') {
  $st = $pdo->prepare('SELECT id,sku,nombre,cat_primary,precio,stock,disponible FROM products WHERE nombre LIKE ? OR sku LIKE ? OR id LIKE ? ORDER BY id DESC LIMIT 100');
  $st->execute(["%$q%", "%$q%", "%$q%"]);
  $rows = $st->fetchAll();
} else {
  $rows = $pdo->query('SELECT id,sku,nombre,cat_primary,precio,stock,disponible FROM products ORDER BY id DESC LIMIT 60')->fetchAll();
}
$e = $edit ?? ['id' => '', 'sku' => '', 'nombre' => '', 'slug' => '', 'marca' => '', 'cat_primary' => 'Novedades', 'precio' => '', 'precio_lista' => '', 'stock' => 10, 'disponible' => 1, 'destacado' => 0, 'en_oferta' => 0, 'img' => '', 'img_large' => '', 'descripcion_corta' => ''];
echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Productos — Admin</title><link rel="stylesheet" href="admin.css?v=2"></head><body>';
adm_side('productos', 'productos');
echo '<h1>Productos</h1><p class="adm-sub">Crear, editar, activar y eliminar del catálogo</p>';
if (!empty($_GET['ok'])) echo '<div class="card" style="padding:12px 18px;border-color:#bbf7d0;background:#f0fdf4"><b>Guardado.</b></div>';
if ($err) echo '<div class="card" style="padding:12px 18px;border-color:#f5c2c2;background:#fef2f2"><b>' . h($err) . '</b></div>';
echo '<section class="card"><div class="card-h"><h2>Catálogo</h2><form method="get" class="toolbar"><input name="q" value="' . h($q) . '" placeholder="Buscar nombre, SKU o ID" style="padding:8px 12px;border:1px solid var(--borde);border-radius:10px"><button class="btn btn-ghost btn-sm">Buscar</button> <a class="btn btn-primary btn-sm" href="productos.php">+ Nuevo</a></form></div>'
  . '<div class="tblwrap"><table class="tbl"><tr><th>ID</th><th>SKU</th><th>Nombre</th><th>Cat</th><th class="num">Precio</th><th class="num">Stock</th><th>Estado</th><th></th></tr>';
foreach ($rows as $r) {
  echo '<tr><td>' . h($r['id']) . '</td><td>' . h($r['sku']) . '</td><td>' . h(mb_substr($r['nombre'], 0, 55)) . '</td><td>' . h($r['cat_primary']) . '</td><td class="num">$' . number_format($r['precio'] / 100, 2) . '</td><td class="num">' . (int)$r['stock'] . '</td>'
    . '<td>' . ($r['disponible'] ? '<span class="pill pill-paid">Visible</span>' : '<span class="pill pill-gray">Oculto</span>') . '</td>'
    . '<td style="white-space:nowrap"><a class="btn btn-ghost btn-sm" href="?edit=' . h($r['id']) . '">Editar</a> '
    . '<form method="post" class="inline"><input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '"><input type="hidden" name="acc" value="toggle"><input type="hidden" name="id" value="' . h($r['id']) . '"><button class="btn btn-ghost btn-sm">' . ($r['disponible'] ? 'Ocultar' : 'Mostrar') . '</button></form> '
    . '<form method="post" class="inline" onsubmit="return confirm(\'¿Eliminar? Se quitará del catálogo y de carritos.\')"><input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '"><input type="hidden" name="acc" value="del"><input type="hidden" name="id" value="' . h($r['id']) . '"><button class="btn btn-danger btn-sm">Eliminar</button></form></td></tr>';
}
echo '</table></div></section>';
echo '<section class="card"><div class="card-h"><h2>' . ($edit ? 'Editar ' . h($edit['id']) : 'Nuevo producto') . '</h2></div>'
  . '<form method="post" enctype="multipart/form-data" class="frm" style="padding:18px">'
  . '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '"><input type="hidden" name="acc" value="save"><input type="hidden" name="id" value="' . h($e['id']) . '">'
  . '<label>Nombre*<input name="nombre" required value="' . h($e['nombre']) . '"></label>'
  . '<div class="frm-row"><label>SKU*<input name="sku" required value="' . h($e['sku']) . '"></label>'
  . '<label>Slug (vacío = auto)<input name="slug" value="' . h($e['slug']) . '"></label></div>'
  . '<div class="frm-row"><label>Marca<input name="marca" value="' . h($e['marca']) . '"></label>'
  . '<label>Categoría principal<select name="cat_primary">' . implode('', array_map(fn($c) => '<option' . ($c === $e['cat_primary'] ? ' selected' : '') . '>' . h($c) . '</option>', $CATS)) . '</select></label></div>'
  . '<fieldset style="border:1px solid var(--borde);border-radius:10px"><legend style="font-size:13px;font-weight:700">Categorías</legend>' . implode(' ', array_map(fn($c) => '<label class="mut" style="margin-right:12px"><input type="checkbox" name="cats[]" value="' . h($c) . '"' . (in_array($c, $editCats, true) ? ' checked' : '') . '> ' . h($c) . '</label>', $CATS)) . '</fieldset>'
  . '<div class="frm-row"><label>Precio MXN*<input name="precio" type="number" step="0.01" min="1" required value="' . h($e['precio']) . '"></label>'
  . '<label>Precio lista (ofertas)<input name="precio_lista" type="number" step="0.01" min="0" value="' . h($e['precio_lista']) . '"></label></div>'
  . '<label>Stock<input name="stock" type="number" min="0" value="' . (int)$e['stock'] . '"></label>'
  . '<label>Imagen (ruta o subida)<input name="img" value="' . h($e['img']) . '" placeholder="santillana/..._compact.png"><input type="file" name="f_img" accept=".jpg,.jpeg,.png,.webp"></label>'
  . '<label>Imagen grande (ruta o subida)<input name="img_large" value="' . h($e['img_large']) . '" placeholder="santillana/..._large.png"><input type="file" name="f_img_large" accept=".jpg,.jpeg,.png,.webp"></label>'
  . '<label>Descripción<textarea name="descripcion" rows="3">' . h($e['descripcion_corta']) . '</textarea></label>'
  . '<label class="mut"><input type="checkbox" name="disponible"' . (!empty($e['disponible']) ? ' checked' : '') . '> Visible en tienda</label>'
  . '<label class="mut"><input type="checkbox" name="destacado"' . (!empty($e['destacado']) ? ' checked' : '') . '> Destacado</label>'
  . '<label class="mut"><input type="checkbox" name="en_oferta"' . (!empty($e['en_oferta']) ? ' checked' : '') . '> En oferta</label>'
  . '<p><button class="btn btn-primary">Guardar producto</button></p></form></section>';
adm_foot();
