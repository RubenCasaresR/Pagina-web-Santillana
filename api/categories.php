<?php
// GET /api/categories.php — categorías desde MySQL (reemplaza data/categorias.json).
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try {
  $pdo = db();
  $rows = $pdo->query('SELECT c.cat AS name, COUNT(*) AS total FROM product_cats c JOIN products p ON p.id=c.product_id WHERE p.disponible=1 GROUP BY c.cat')->fetchAll();
  $counts = [];
  foreach ($rows as $r) $counts[$r['name']] = (int)$r['total'];
  $order = ['Celulares','Computación','Televisiones','Videojuegos','Electrónica','Tablets','Hogar','Herramientas','Ofertas','Novedades','Maquillaje','Cuidado Personal','Deportes','Moda','Juguetes','Navidad','Iluminación','Papelería','Alimentos'];
  $cats = array_keys($counts);
  usort($cats, function ($a, $b) use ($order) {
    $ia = array_search($a, $order, true); $ib = array_search($b, $order, true);
    $ia = $ia === false ? 999 : $ia; $ib = $ib === false ? 999 : $ib;
    return $ia === $ib ? strcmp($a, $b) : $ia - $ib;
  });
  echo json_encode($cats, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  jexit(500, ['error' => 'categorias']);
}
