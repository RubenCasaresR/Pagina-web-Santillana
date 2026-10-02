<?php
// GET /api/catalog.php?q=&cat=&orden=&page=&per= — lee de MySQL (no del JSON público).
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try {
  $pdo = db();
  $q = trim((string)($_GET['q'] ?? ''));
  $cat = trim((string)($_GET['cat'] ?? 'Todo'));
  $orden = (string)($_GET['orden'] ?? 'rel');
  $page = max(1, (int)($_GET['page'] ?? 1));
  $per = (int)($_GET['per'] ?? 20);
  if (!in_array($per, [12, 20, 32, 48, 200], true)) $per = 20;
  $where = ['p.disponible=1']; $args = [];
  if ($cat !== '' && $cat !== 'Todo') {
    $where[] = 'EXISTS(SELECT 1 FROM product_cats c WHERE c.product_id=p.id AND c.cat=?)';
    $args[] = $cat;
  }
  if ($q !== '') { $where[] = '(p.nombre LIKE ? OR p.sku LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
  $w = 'WHERE ' . implode(' AND ', $where);
  $st = $pdo->prepare("SELECT COUNT(*) AS c FROM products p $w");
  $st->execute($args);
  $total = (int)$st->fetch()['c'];
  $order = match ($orden) {
    'asc' => 'p.precio ASC', 'desc' => 'p.precio DESC', 'az' => 'p.nombre ASC',
    default => 'p.destacado DESC, p.id DESC',
  };
  $pages = max(1, (int)ceil($total / $per));
  $page = min($page, $pages);
  $off = ($page - 1) * $per;
  $st = $pdo->prepare("SELECT p.*, GROUP_CONCAT(c.cat) cats FROM products p LEFT JOIN product_cats c ON c.product_id=p.id $w GROUP BY p.id ORDER BY $order LIMIT $per OFFSET $off");
  $st->execute($args);
  $items = [];
  foreach ($st->fetchAll() as $r) {
    $items[] = [
      'id' => $r['id'], 'nombre' => $r['nombre'], 'sku' => $r['sku'], 'slug' => $r['slug'],
      'marca' => $r['marca'], 'cat_primary' => $r['cat_primary'],
      'categorias' => $r['cats'] ? explode(',', $r['cats']) : [],
      'precio' => (int)$r['precio'] / 100, 'precio_lista' => (int)$r['precio_lista'] / 100,
      'moneda' => $r['moneda'], 'stock' => (int)$r['stock'] - (int)$r['reservado'],
      'disponible' => (bool)$r['disponible'], 'en_oferta' => (bool)$r['en_oferta'],
      'img' => $r['img'], 'img_large' => $r['img_large'],
      'img_webp' => $r['img_webp'] ?? '', 'img_large_webp' => $r['img_large_webp'] ?? '',
      'descripcion_corta' => $r['descripcion_corta'],
    ];
  }
  echo json_encode(['total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per, 'items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  jexit(500, ['error' => 'catalogo']);
}
