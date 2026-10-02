<?php
// GET /api/product.php?id= — ficha en vivo: precio y stock reales.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try {
  $id = trim((string)($_GET['id'] ?? ''));
  if ($id === '') jexit(422, ['error' => 'id']);
  $st = db()->prepare('SELECT id,nombre,sku,marca,cat_primary,precio,precio_lista,moneda,stock,reservado,disponible,en_oferta,img,img_large,img_webp,img_large_webp,descripcion_corta FROM products WHERE id=?');
  $st->execute([$id]);
  $r = $st->fetch();
  if (!$r) jexit(404, ['error' => 'no-existe']);
  $r['precio'] = (int)$r['precio'] / 100;
  $r['precio_lista'] = (int)$r['precio_lista'] / 100;
  $r['stock'] = max(0, (int)$r['stock'] - (int)$r['reservado']);
  $r['disponible'] = (bool)$r['disponible'];
  $r['en_oferta'] = (bool)$r['en_oferta'];
  unset($r['reservado']);
  echo json_encode($r, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  jexit(500, ['error' => 'producto']);
}
