<?php
// tools/import-fotos-shopify.php — Uso: php tools/import-fotos-shopify.php (SOLO CLI).
// Rellena img/img_large vacíos desde el og:image de la página mishop.mx (vía productos_shopify.json).
// - Solo toca products con img='' o NULL. Re-ejecutable: lo ya rellenado se omite.
// - Respalda products antes (sufijo fecha). Hotlink al CDN (patrón existente en la tienda).
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); echo "Solo CLI.\n"; exit(1); }
require_once __DIR__ . '/../api/bootstrap.php';

function og_image(string $url, string &$dbg = ''): string {
  // Endpoint .js de Shopify: JSON liviano con featured_image (evita el 429 del HTML).
  $js = rtrim($url, '/') . '.js';
  $ch = curl_init($js);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: es-MX,es;q=0.9'],
    CURLOPT_ENCODING => '', CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]);
  $body = curl_exec($ch);
  $dbg = 'code=' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . ' len=' . strlen((string)$body) . ' err=' . curl_error($ch);
  curl_close($ch);
  if (is_string($body) && $body !== '') {
    $j = json_decode($body, true);
    $img = trim((string)($j['featured_image'] ?? $j['images'][0] ?? ''));
    if ($img !== '') {
      if (str_starts_with($img, '//')) $img = 'https:' . $img;
      elseif (str_starts_with($img, 'http://')) $img = 'https://' . substr($img, 7);
      if (str_starts_with($img, 'https://')) return mb_substr($img, 0, 255);
    }
  }
  // Fallback: og:image del HTML (puede dar 429; se reintenta una vez).
  sleep(2);
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
    CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: es-MX,es;q=0.9'],
    CURLOPT_ENCODING => '', CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]);
  $html = curl_exec($ch);
  $dbg .= ' | html_code=' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . ' len=' . strlen((string)$html);
  curl_close($ch);
  foreach (['og:image:secure_url', 'og:image'] as $prop) {
    if (preg_match('/<meta[^>]+property="' . preg_quote($prop, '/') . '"[^>]+content="([^"]+)"/i', $html, $m)
      || preg_match('/<meta[^>]+content="([^"]+)"[^>]+property="' . preg_quote($prop, '/') . '"/i', $html, $m)) {
      $u = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
      if ($u === '') continue;
      if (str_starts_with($u, 'http://')) $u = 'https://' . substr($u, 7);
      if (!str_starts_with($u, 'https://') && !str_starts_with($u, '//')) continue;
      return mb_substr($u, 0, 255);
    }
  }
  return '';
}

$shop = json_decode(file_get_contents(__DIR__ . '/../productos_shopify.json'), true);
if (!is_array($shop)) { echo "productos_shopify.json inválido\n"; exit(1); }
$byId = [];
foreach ($shop as $s) {
  $u = trim((string)($s['URL'] ?? ''));
  if ($u !== '') $byId[(string)$s['ID_Producto']] = $u;
}
printf("Fuente shopify: %d con URL\n", count($byId));

$pdo = db();
$tag = date('Ymd_Hi');
$pdo->exec("CREATE TABLE products_backup_{$tag} LIKE products");
$pdo->exec("INSERT INTO products_backup_{$tag} SELECT * FROM products");
echo "Respaldo: products_backup_{$tag}\n";

$rows = $pdo->query("SELECT id FROM products WHERE img='' OR img IS NULL")->fetchAll(PDO::FETCH_COLUMN);
printf("Sin foto en DB: %d\n", count($rows));
$up = $pdo->prepare('UPDATE products SET img=?, img_large=? WHERE id=?');
$ok = 0; $fail = []; $skip = 0;
foreach ($rows as $i => $id) {
  $url = $byId[(string)$id] ?? '';
  if ($url === '') { $skip++; continue; }
  $dbg = '';
  $img = og_image($url, $dbg);
  if ($i < 3) echo "  debug $id: $dbg url=$url\n";
  if ($img === '') { $fail[] = "$id ($dbg)"; continue; }
  $up->execute([$img, $img, $id]);
  $ok++;
  if (($i + 1) % 25 === 0) printf("  %d/%d ok=%d...\n", $i + 1, count($rows), $ok);
  usleep(800000);
}
echo "OK: $ok fotos importadas, omitidos sin URL: $skip\n";
echo 'Fallos: ' . count($fail) . "\n";
foreach (array_slice($fail, 0, 20) as $f) echo "  $f\n";
echo 'Restan sin foto: ' . $pdo->query("SELECT COUNT(*) FROM products WHERE img='' OR img IS NULL")->fetchColumn() . PHP_EOL;
