<?php
// tools/export-csv.php — descarga el catálogo en CSV (requiere sesión admin). Para revisión masiva en Excel.
declare(strict_types=1);
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/auth-lib.php';
sess_start();
if (empty($_SESSION['sant_admin'])) { http_response_code(403); echo 'Acceso denegado. Entra a /admin/ primero.'; exit; }
$pdo = new PDO('mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4',
  (string)cfg('db_user'), (string)cfg('db_pass'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="productos-' . date('Ymd') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel abra los acentos
fputcsv($out, ['id', 'sku', 'nombre', 'slug', 'marca', 'cat_primary', 'categorias', 'precio_mxn', 'precio_lista_mxn', 'stock', 'disponible', 'destacado', 'en_oferta', 'img', 'descripcion']);
foreach ($pdo->query("SELECT p.*, GROUP_CONCAT(c.cat SEPARATOR ' | ') cats FROM products p LEFT JOIN product_cats c ON c.product_id=p.id GROUP BY p.id ORDER BY p.id") as $r) {
  fputcsv($out, [$r['id'], $r['sku'], $r['nombre'], $r['slug'], $r['marca'], $r['cat_primary'], $r['cats'] ?? '',
    number_format($r['precio'] / 100, 2, '.', ''), number_format($r['precio_lista'] / 100, 2, '.', ''),
    $r['stock'], $r['disponible'], $r['destacado'], $r['en_oferta'], $r['img'], $r['descripcion_corta']]);
}
