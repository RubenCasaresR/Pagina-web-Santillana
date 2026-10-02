<?php
// tools/fix-slugs.php — Uso: php tools/fix-slugs.php [--apply]
// Repara slugs heredados del scraper (ej. ce-hrus23ontrol-para-*, *paraobia-*).
// Estrategia: regenera slug desde el NOMBRE (fuente limpia) con slugify()
// y garantiza unicidad. Sin --apply solo muestra el plan (dry-run).
// Las URLs viejas siguen funcionando: producto.php hace 301 al canónico nuevo.
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); echo "Solo CLI.\n"; exit(1); }
require_once __DIR__ . '/../api/config.php';

function slugify(string $s): string {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  // Degradar acentos/ñ sin depender de iconv en Windows
  $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u','â'=>'a','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u','ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ß'=>'ss','ç'=>'c'];
  $s = strtr($s, $map);
  if (function_exists('iconv')) {
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if (is_string($t) && $t !== '') $s = $t;
  }
  $s = strtolower($s);
  $s = preg_replace('/[^a-z0-9]+/', '-', $s);
  $s = trim(preg_replace('/-{2,}/', '-', $s ?? ''), '-');
  $s = mb_substr($s ?? '', 0, 200);
  $s = trim($s, '-');
  return $s !== '' ? $s : 'producto';
}

$apply = in_array('--apply', $argv ?? [], true);
$pdo = new PDO('mysql:host='.cfg('db_host').';dbname='.cfg('db_name').';charset=utf8mb4',
  (string)cfg('db_user'), (string)cfg('db_pass'),
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$rows = $pdo->query('SELECT id, slug, nombre FROM products ORDER BY id')->fetchAll();
$total = count($rows);
echo "Productos: $total\n";

// Slugs objetivo ocupados (para unicidad)
$taken = [];
foreach ($rows as $r) $taken[$r['slug']] = $r['id'];

$plan = [];
$used = [];
foreach ($rows as $r) {
  $id = (string)$r['id'];
  $old = (string)($r['slug'] ?? '');
  $base = slugify((string)($r['nombre'] ?? ''));
  if ($base === '') $base = $id;
  $new = $base;
  $k = 2;
  // Reservar: si el slug base lo tiene otro producto (viejo o ya reasignado), sufijar
  while ((isset($taken[$new]) && $taken[$new] !== $id) || isset($used[$new])) {
    $new = mb_substr($base, 0, 190) . '-' . $k++;
  }
  $used[$new] = $id;
  if ($new !== $old) $plan[] = ['id' => $id, 'old' => $old, 'new' => $new, 'nombre' => $r['nombre']];
}

$rotos = array_values(array_filter($plan, fn($p) => str_contains($p['old'], 'ontrol-') || str_contains($p['old'], 'paraobia') || str_starts_with($p['old'], 'ce-')));
echo 'A cambiar: ' . count($plan) . ' (' . count($rotos) . " con patrón roto 'ontrol/obia/ce-')\n";
foreach (array_slice($plan, 0, 15) as $p) {
  echo "  {$p['id']}\n    - {$p['old']}\n    + {$p['new']}\n";
}
if (count($plan) > 15) echo '  ... y ' . (count($plan) - 15) . " más\n";

if (!$apply) { echo "Dry-run. Ejecuta con --apply para guardar (crea respaldo antes).\n"; exit(0); }

$tag = date('Ymd_Hi');
$csv = __DIR__ . "/slugfix_{$tag}.csv";
$fh = fopen($csv, 'w');
fputcsv($fh, ['id', 'old_slug', 'new_slug']);
foreach ($plan as $p) fputcsv($fh, [$p['id'], $p['old'], $p['new']]);
fclose($fh);
echo "Respaldo CSV: tools/slugfix_{$tag}.csv (para revertir: UPDATE products SET slug=<old> WHERE id=<id>)\n";
$up = $pdo->prepare('UPDATE products SET slug=? WHERE id=?');
$n = 0;
foreach (array_chunk($plan, 100) as $chunk) {
  $pdo->beginTransaction();
  foreach ($chunk as $p) { $up->execute([$p['new'], $p['id']]); $n++; }
  $pdo->commit();
  echo "  ...$n/" . count($plan) . "\n";
}
echo "OK: $n slugs actualizados. Regenera el sitemap: php tools/generate-sitemap.php\n";
