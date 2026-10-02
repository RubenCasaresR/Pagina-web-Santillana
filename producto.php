<?php
// producto.php — plantilla ÚNICA de ficha. Fuente única: MySQL (sin fallback a JSON).
// Rutas: /producto.php?id=X · /producto.php?key=slug-id · pretty /producto/slug-id (vía .htaccess).
declare(strict_types=1);
require_once __DIR__ . '/api/config.php';
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
// base absoluta del sitio (funciona en / o cualquier subcarpeta)
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
if ($base === '//') $base = '/';
$base = str_replace(' ', '%20', $base);

function db_or_null() {
  try {
    return new PDO('mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4',
      (string)cfg('db_user'), (string)cfg('db_pass'),
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  } catch (Throwable $e) { return null; }
}
// Resuelve ruta de imagen: absoluta (http/https///data:) se usa tal cual, relativa se antepone $base.
// El preview (app.js resolveImg/resolveImgLarge) ya lo hace así; el detalle no lo hacía y rompía imgs del CDN.
function img_url(string $raw, string $base): string {
  $raw = trim($raw);
  if ($raw === '') return $base . 'assets/logo-santillana.png';
  if (preg_match('#^(https?:)?//#i', $raw) || str_starts_with($raw, 'data:')) return $raw;
  return $base . ltrim($raw, '/');
}
function img_url_or_empty(string $raw, string $base): string {
  $raw = trim($raw);
  if ($raw === '') return '';
  if (preg_match('#^(https?:)?//#i', $raw) || str_starts_with($raw, 'data:')) return $raw;
  return $base . ltrim($raw, '/');
}
function row_from_db(PDO $pdo, string $id): ?array {
  $st = $pdo->prepare('SELECT p.*, GROUP_CONCAT(c.cat) cats FROM products p LEFT JOIN product_cats c ON c.product_id=p.id WHERE p.id=? GROUP BY p.id');
  $st->execute([$id]);
  $r = $st->fetch();
  if (!$r) return null;
  $r['categorias'] = $r['cats'] ? explode(',', $r['cats']) : [];
  $r['precio'] = (int)$r['precio'] / 100;
  $r['precio_lista'] = (int)$r['precio_lista'] / 100;
  $r['stock'] = max(0, (int)$r['stock'] - (int)$r['reservado']);
  foreach (['disponible', 'destacado', 'en_oferta'] as $k) $r[$k] = (bool)$r[$k];
  return $r;
}
function related(PDO $pdo, array $p): array {
  $cat = $p['cat_primary'] ?? '';
  try {
    $st = $pdo->prepare("SELECT id,nombre,sku,slug,cat_primary,precio,img,img_large,img_webp,img_large_webp FROM products WHERE cat_primary=? AND id<>? AND disponible=1 ORDER BY (img<>'' AND img IS NOT NULL) DESC, destacado DESC, id DESC LIMIT 4");
    $st->execute([$cat, $p['id']]);
    $rows = $st->fetchAll();
    foreach ($rows as &$rw) $rw['precio'] = (int)$rw['precio'] / 100;
    return $rows;
  } catch (Throwable $e) { return []; }
}

// ---- resolver id ----
$id = '';
$key = trim((string)($_GET['key'] ?? ''));
$qid = trim((string)($_GET['id'] ?? ''));
if ($qid !== '') $id = preg_replace('/\D+/', '', $qid);
elseif (preg_match('/^p-(\d+)$/', $key, $m)) $id = $m[1];
elseif (preg_match('/-(ce-[0-9a-f]{12})$/', $key, $m)) $id = $m[1];
elseif (preg_match('/-(\d{10,})$/', $key, $m)) $id = $m[1];

$pdo = db_or_null();
$p = ($id !== '' && $pdo) ? row_from_db($pdo, $id) : null;
if (!$p || empty($p['disponible'])) {
  http_response_code(404);
  $p = null;
}
if ($p) {
  $canon = $base . 'producto/' . ($p['slug'] ? $p['slug'] . '-' . $p['id'] : 'p-' . $p['id']);
  // slug incorrecto, ruta no-canónica o ficha vieja -> 301 al canónico
  $want = $p['slug'] ? $p['slug'] . '-' . $p['id'] : 'p-' . $p['id'];
  if ($key !== '' && ($key !== $want || isset($_GET['legacy']))) {
    header('Location: ' . str_replace(' ', '%20', $canon), true, 301);
    exit;
  }
  $rel = $pdo ? related($pdo, $p) : [];
  $img = img_url((string)($p['img_large'] ?: $p['img']), $base);
  $imgW = img_url_or_empty((string)(($p['img_large_webp'] ?? '') ?: ($p['img_webp'] ?? '')), $base);
  $fmt = fn($n) => '$' . number_format((float)$n, 2);
  $isOffer = !empty($p['en_oferta']) || in_array('Ofertas', $p['categorias'] ?? [], true);
}
?><!DOCTYPE html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if ($p): ?>
<title><?= $h($p['nombre']) ?> — Santillana</title>
<?php
  // Descripción SEO enriquecida: evita thin content cuando descripcion_corta está vacía o es = nombre.
  $descBase = trim((string)($p['descripcion_corta'] ?? ''));
  if ($descBase === '' || $descBase === $p['nombre']) {
    $descBase = $p['nombre']
      . ($p['marca'] ? ' — ' . $p['marca'] : '')
      . ($p['cat_primary'] ? ' | ' . $p['cat_primary'] : '')
      . '.';
  }
?>
<meta name="description" content="<?= $h(mb_substr($descBase . ' Precio ' . $fmt($p['precio']) . '. SKU ' . $p['sku'] . '.', 0, 160)) ?>">
<meta property="og:type" content="product">
<meta property="og:title" content="<?= $h($p['nombre']) ?> — Santillana">
<meta property="og:description" content="<?= $h(mb_substr($descBase, 0, 160)) ?>">
<meta property="og:url" content="<?= $h(cfg('site_url') . '/producto/' . ($p['slug'] ? $p['slug'] . '-' . $p['id'] : 'p-' . $p['id'])) ?>">
<meta property="og:image" content="<?= $h($img) ?>">
<meta property="og:image:alt" content="<?= $h($p['nombre']) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $h($p['nombre']) ?> — Santillana">
<meta name="twitter:description" content="<?= $h(mb_substr($descBase, 0, 160)) ?>">
<link rel="canonical" href="<?= $h(cfg('site_url') . '/producto/' . ($p['slug'] ? $p['slug'] . '-' . $p['id'] : 'p-' . $p['id'])) ?>">
<script type="application/ld+json">{"@context":"https://schema.org","@type":"Product","name":<?= json_encode($p['nombre'], JSON_UNESCAPED_UNICODE) ?>,"image":[<?= json_encode($img, JSON_UNESCAPED_SLASHES) ?>],"description":<?= json_encode(mb_substr($descBase, 0, 300), JSON_UNESCAPED_UNICODE) ?>,"sku":<?= json_encode($p['sku']) ?>,"brand":{"@type":"Brand","name":<?= json_encode($p['marca'] ?? '', JSON_UNESCAPED_UNICODE) ?>},"offers":{"@type":"Offer","priceCurrency":"MXN","price":<?= json_encode((string)$p['precio']) ?>,"availability":"<?= (!empty($p['disponible']) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock') ?>","url":<?= json_encode(cfg('site_url') . '/producto/' . ($p['slug'] ? $p['slug'] . '-' . $p['id'] : 'p-' . $p['id'])) ?>}}</script>
<script type="application/ld+json">{"@context":"https://schema.org","@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"name":"Inicio","item":<?= json_encode(cfg('site_url') . '/index.html') ?>},{"@type":"ListItem","position":2,"name":<?= json_encode($p['cat_primary'] ?? '', JSON_UNESCAPED_UNICODE) ?>},{"@type":"ListItem","position":3,"name":<?= json_encode($p['nombre'], JSON_UNESCAPED_UNICODE) ?>}]}</script>
<?php else: ?>
<title>Producto no encontrado — Santillana</title><meta name="robots" content="noindex">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="preconnect" href="https://cdn.shopify.com" crossorigin><link rel="dns-prefetch" href="https://cdn.shopify.com"><link rel="preload" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'"><noscript><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript><link rel="preload" href="<?= $h($base) ?>estilos.css?v=20261014" as="style"><link rel="stylesheet" href="<?= $h($base) ?>estilos.css?v=20261014">
<link rel="icon" href="<?= $h($base) ?>assets/logo-santillana.png" type="image/png"><link rel="apple-touch-icon" href="<?= $h($base) ?>assets/logo-santillana.png"></head><body>
<div class="announce">Pago 100% seguro · Entrega a todo México</div>
<header class="topbar" id="topbar"><div class="wrap topbar-in">
<a class="logo" href="<?= $h($base) ?>index.html"><img class="logo-img" src="<?= $h($base) ?>assets/logo-santillana.png" alt="Santillana – tienda en México" width="220" height="73" fetchpriority="high" decoding="async"></a>
<form class="search" action="<?= $h($base) ?>index.html" method="get" role="search"><svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg><input id="q" name="q" type="search" placeholder="Buscar control, Roku, pantalla, laptop…" autocomplete="off" aria-label="Buscar productos"><button type="submit" aria-label="Buscar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg><span>Buscar</span></button></form>
<nav class="top-actions"><a class="cart-btn acct-btn" data-account-btn data-drawer-bound="1" href="<?= $h($base) ?>cuenta.html" aria-label="Entrar o crear cuenta"><svg class="ic ic-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <span data-account-label>Entrar</span></a><a class="cart-btn" href="<?= $h($base) ?>carrito.html" aria-label="Abrir carrito"><svg class="cart-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg> Carrito <span class="cart-count" data-cart-count>0</span></a></nav>
</div></header>
<main class="wrap">
<?php if (!$p): ?>
<div class="empty" style="margin:30px 0"><b>Producto no encontrado</b><span>Es posible que se haya agotado o la dirección cambió.</span><br><a class="btn add" href="<?= $h($base) ?>index.html" style="max-width:240px">Ver catálogo</a></div>
<?php else: ?>
<p class="crumb"><a href="<?= $h($base) ?>index.html">Inicio</a> / <a href="<?= $h($base) ?>index.html?cat=<?= urlencode($p['cat_primary']) ?>#catalogo"><?= $h($p['cat_primary']) ?></a> / <?= $h($p['nombre']) ?></p>
<div class="detail"><div><picture><?php
  $imgC = img_url((string)($p['img'] ?: $p['img_large']), $base); $imgCW = img_url_or_empty((string)($p['img_webp'] ?? ''), $base);
  $wbset = trim((($imgCW ? $imgCW . ' 160w' : '') . ($imgW ? ', ' . $imgW . ' 480w' : '')), ', ');
  $imgset = ($imgC === $img) ? $img : $imgC . ' 160w, ' . $img . ' 480w';
?><?php if ($wbset): ?><source srcset="<?= $h($wbset) ?>" sizes="(max-width:860px) 90vw, 460px" type="image/webp"><?php endif; ?><img fetchpriority="high" decoding="async" width="460" height="440" src="<?= $h($img) ?>" srcset="<?= $h($imgset) ?>" sizes="(max-width:860px) 90vw, 460px" alt="<?= $h($p['nombre']) ?>" data-fb="0" data-compact="<?= $h($imgC) ?>" data-logo="<?= $h($base) ?>assets/logo-santillana.png" onerror="var f=this.dataset.fb||'0';if(f==='0'){this.dataset.fb='1';this.src=this.dataset.compact;}else{this.onerror=null;this.src=this.dataset.logo;}"></picture></div>
<div><span class="badge"><?= $h($p['cat_primary']) ?></span><?php if ($isOffer): ?> <span class="badge badge-offer">Oferta</span><?php endif; ?><h1 style="font-size:24px"><?= $h($p['nombre']) ?></h1>
<p class="meta">SKU <?= $h($p['sku']) ?> · ID <?= $h($p['id']) ?></p>
<p class="precio" style="font-size:28px"><?= $h($fmt($p['precio'])) ?></p>
<p class="nota" id="ficha-disp">Consultando disponibilidad...</p>
<p><?= $h($descBase ?? ($p['descripcion_corta'] ?: $p['nombre'])) ?></p>
<p class="meta">Marca: <?= $h($p['marca'] ?? '') ?></p>
<div class="qty"><button onclick="c(-1)" aria-label="Disminuir cantidad">−</button><input id="qty" value="1" inputmode="numeric"><button onclick="c(1)" aria-label="Aumentar cantidad">+</button></div>
<div class="card-f"><button class="btn add" data-add="<?= $h($p['id']) ?>" id="addbtn">Agregar al carrito</button>
<a class="btn ver" href="<?= $h($base) ?>carrito.html">Ver carrito</a></div>
<p class="meta">Categorías: <?= $h(implode(', ', $p['categorias'] ?? [])) ?></p></div></div>
<h2>Relacionados</h2><div class="grid"><?php foreach ($rel as $r):
  $ru = $base . 'producto/' . (($r['slug'] ?? '') ? $r['slug'] . '-' . $r['id'] : 'p-' . $r['id']);
  $rOrig = img_url((string)(($r['img_large'] ?? '') ?: ($r['img'] ?? '')), $base);
  // Thumb 480px vía CDN (~10KB en vez de ~200KB). La original queda de respaldo.
  $isShop = (strpos($rOrig, 'cdn.shopify.com') !== false || strpos($rOrig, '/cdn/shop/') !== false || strpos($rOrig, 'myshopify.com') !== false);
  $rThumb = $isShop ? $rOrig . (strpos($rOrig, '?') === false ? '?width=480' : '&width=480') : $rOrig;
  $rW = img_url_or_empty((string)(($r['img_large_webp'] ?? '') ?: ($r['img_webp'] ?? '')), $base);
  if ($rW !== '' && (strpos($rW, 'cdn.shopify.com') !== false || strpos($rW, '/cdn/shop/') !== false)) $rW .= (strpos($rW, '?') === false ? '?width=480' : '&width=480');
  $rFb = ($rOrig !== '' && $rOrig !== $rThumb) ? ' data-large="' . $h($rOrig) . '" data-fb="0"' : '';
?><article class="card"><div class="card-img"><a href="<?= $h($ru) ?>" aria-label="Ver <?= $h($r['nombre']) ?>" tabindex="-1"><picture><?php if ($rW !== ''): ?><source srcset="<?= $h($rW) ?>" type="image/webp"><?php endif; ?><img loading="lazy" decoding="async" width="480" height="210" src="<?= $h($rThumb !== '' ? $rThumb : $base . 'assets/logo-santillana.png') ?>"<?= $rFb ?> alt="<?= $h($r['nombre']) ?>" onerror="var f=this.dataset.fb||'0';var l=this.dataset.large;if(f==='0'&&l&&l!==this.src){this.dataset.fb='1';this.src=l;}else{this.onerror=null;this.src='<?= $h($base) ?>assets/logo-santillana.png';}"></picture></a><button class="card-add" data-add="<?= $h($r['id']) ?>" aria-label="Agregar <?= $h($r['nombre']) ?> al carrito"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg></button></div><div class="card-b"><span class="badge"><?= $h($r['cat_primary']) ?></span><h3><a href="<?= $h($ru) ?>"><?= $h($r['nombre']) ?></a></h3><span class="precio"><?= $h($fmt($r['precio'])) ?></span></div></article><?php endforeach; ?></div>
<?php endif; ?>
</main>
<footer class="footer"><div class="wrap foot-min">
<a class="f-mini-brand" href="<?= $h($base) ?>index.html"><img src="<?= $h($base) ?>assets/logo-santillana.png" alt="Santillana – tienda en México" width="100" height="33" loading="lazy" decoding="async"><span>© 2026 Santillana</span></a>
<nav class="f-mini-nav" aria-label="Enlaces"><a href="<?= $h($base) ?>index.html#catalogo">Catálogo</a><a href="<?= $h($base) ?>carrito.html">Carrito</a><a href="<?= $h($base) ?>rastreo.html">Rastrear</a><a href="<?= $h($base) ?>contacto.html">Contacto</a></nav>
<div class="f-mini-contact"><a href="mailto:ventas@santillana-mx.com">ventas@santillana-mx.com</a><a href="tel:+523310642354">33 1064 2354</a><span class="pay pay-mini"><span class="pay-logo" title="PayPal"><img src="<?= $h($base) ?>assets/paypal.png" alt="PayPal" width="72" height="20" loading="lazy" decoding="async"></span><span class="pay-logo" title="Transferencia SPEI"><img src="<?= $h($base) ?>assets/spei.png" alt="SPEI" width="60" height="20" loading="lazy" decoding="async"></span></span></div>
</div><div class="foot-base"><div class="wrap base-in"><span>Precios en MXN</span><nav><a href="<?= $h($base) ?>privacidad.html">Privacidad</a><a href="<?= $h($base) ?>terminos.html">Términos</a></nav></div></div></footer>
<a class="wa-float" href="https://wa.me/523310642354?text=Hola%20Santillana%2C%20necesito%20ayuda" target="_blank" rel="noopener" aria-label="WhatsApp Santillana"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2z"/></svg></a>
  <script src="<?= $h($base) ?>app.js?v=20261014" defer></script>
<script>function c(d){const i=document.getElementById('qty');i.value=Math.max(1,parseInt(i.value||'1')+d)}
document.getElementById('addbtn').dataset.qty='1';
document.getElementById('qty').addEventListener('input',e=>document.getElementById('addbtn').dataset.qty=e.target.value);</script>
</body></html>
